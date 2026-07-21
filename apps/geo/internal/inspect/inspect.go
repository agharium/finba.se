// Package inspect reports whether a Finba Geo SQLite catalog is safe to serve.
package inspect

import (
	"context"
	"database/sql"
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
	"unicode"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/model"
	"finba.se/geo/internal/textutil"

	_ "time/tzdata"
)

const (
	StatusPass    = "pass"
	StatusFail    = "fail"
	StatusWarning = "warning"

	maxExamples = 10
)

// Options configures an inspection run.
type Options struct {
	Path   string
	Strict bool
}

// Report is the structured inspection result.
type Report struct {
	Path          string         `json:"path"`
	FileSizeBytes int64          `json:"fileSizeBytes"`
	Metadata      ReportMetadata `json:"metadata"`
	Counts        Counts         `json:"counts"`
	Checks        []Check        `json:"checks"`
	Warnings      []Check        `json:"warnings"`
	Ready         bool           `json:"ready"`
}

// ReportMetadata holds validated dataset identity fields.
type ReportMetadata struct {
	Provider         string `json:"provider"`
	DatasetVersion   string `json:"datasetVersion"`
	DatasetSHA256    string `json:"datasetSha256"`
	GeneratedAt      string `json:"generatedAt"`
	GeneratorVersion string `json:"generatorVersion"`
	SchemaVersion    int    `json:"schemaVersion"`
}

// Counts holds catalog row totals.
type Counts struct {
	Countries int `json:"countries"`
	Regions   int `json:"regions"`
	Cities    int `json:"cities"`
}

// Check is a single validation outcome.
type Check struct {
	Name    string   `json:"name"`
	Status  string   `json:"status"`
	Message string   `json:"message"`
	Details []string `json:"details,omitempty"`
}

// Run opens path read-only and produces a full inspection report.
func Run(ctx context.Context, opts Options) (Report, error) {
	info, err := os.Stat(opts.Path)
	if err != nil {
		if os.IsNotExist(err) {
			return Report{}, fmt.Errorf("geo database not found at %s", opts.Path)
		}
		return Report{}, fmt.Errorf("stat database: %w", err)
	}

	db, err := database.OpenReadOnly(opts.Path)
	if err != nil {
		return Report{}, err
	}
	defer db.Close()

	return Inspect(ctx, db, opts.Path, info.Size(), opts.Strict)
}

// Inspect validates an already-open read-only database.
func Inspect(ctx context.Context, db *sql.DB, path string, size int64, strict bool) (Report, error) {
	rep := Report{
		Path:          path,
		FileSizeBytes: size,
		Checks:        []Check{},
		Warnings:      []Check{},
	}

	if err := ctx.Err(); err != nil {
		return Report{}, err
	}

	rep.Checks = append(rep.Checks, checkIntegrity(ctx, db))
	rep.Checks = append(rep.Checks, checkForeignKeys(ctx, db))
	tablesCheck, tableOK := checkRequiredTables(ctx, db)
	rep.Checks = append(rep.Checks, tablesCheck)

	colsCheck := checkRequiredColumns(ctx, db, tableOK)
	rep.Checks = append(rep.Checks, colsCheck)

	idxCheck := checkRequiredIndexes(ctx, db, tableOK)
	rep.Checks = append(rep.Checks, idxCheck)

	metaCheck, meta := checkRequiredMetadata(ctx, db, tableOK)
	rep.Checks = append(rep.Checks, metaCheck)
	rep.Metadata = meta

	countsCheck, counts := checkCounts(ctx, db, tableOK)
	rep.Checks = append(rep.Checks, countsCheck)
	rep.Counts = counts

	if tableOK && colsCheck.Status == StatusPass {
		rep.Checks = append(rep.Checks, checkBlankCountryFields(ctx, db)...)
		rep.Checks = append(rep.Checks, checkBlankRegionNames(ctx, db))
		rep.Checks = append(rep.Checks, checkBlankCityNames(ctx, db))
		rep.Checks = append(rep.Checks, checkNormalizedNames(ctx, db)...)
		rep.Checks = append(rep.Checks, checkOrphansAndMismatches(ctx, db)...)
		rep.Checks = append(rep.Checks, checkTimezones(ctx, db))
	}

	rep.Warnings = append(rep.Warnings, collectWarnings(ctx, db, tableOK, meta)...)

	rep.Ready = isReady(rep.Checks, rep.Warnings, strict)
	return rep, nil
}

func isReady(checks, warnings []Check, strict bool) bool {
	for _, c := range checks {
		if c.Status == StatusFail {
			return false
		}
	}
	if strict {
		for _, w := range warnings {
			if w.Status == StatusWarning || w.Status == StatusFail {
				return false
			}
		}
	}
	return true
}

func checkIntegrity(ctx context.Context, db *sql.DB) Check {
	c := Check{Name: "sqlite_integrity", Message: "SQLite integrity check passed."}
	if err := database.IntegrityCheck(ctx, db); err != nil {
		c.Status = StatusFail
		c.Message = "SQLite integrity check failed."
		c.Details = []string{err.Error()}
		return c
	}
	c.Status = StatusPass
	return c
}

func checkForeignKeys(ctx context.Context, db *sql.DB) Check {
	c := Check{Name: "foreign_keys", Message: "Foreign key check passed."}
	if err := database.ForeignKeyCheck(ctx, db); err != nil {
		c.Status = StatusFail
		c.Message = "Foreign key check failed."
		c.Details = []string{err.Error()}
		return c
	}
	c.Status = StatusPass
	return c
}

func checkRequiredTables(ctx context.Context, db *sql.DB) (Check, bool) {
	c := Check{Name: "required_tables", Message: "All required tables are present."}
	var missing []string
	for _, table := range database.RequiredTables {
		ok, err := database.TableExists(ctx, db, table)
		if err != nil {
			c.Status = StatusFail
			c.Message = "Failed to inspect tables."
			c.Details = []string{err.Error()}
			return c, false
		}
		if !ok {
			missing = append(missing, fmt.Sprintf("missing table %q", table))
		}
	}
	if len(missing) > 0 {
		c.Status = StatusFail
		c.Message = "One or more required tables are missing."
		c.Details = missing
		return c, false
	}
	c.Status = StatusPass
	return c, true
}

var requiredColumns = map[string]map[string]string{
	"countries": {
		"id":              "integer",
		"code":            "text",
		"name":            "text",
		"normalized_name": "text",
	},
	"regions": {
		"id":              "integer",
		"country_id":      "integer",
		"code":            "text",
		"name":            "text",
		"normalized_name": "text",
	},
	"cities": {
		"id":              "integer",
		"country_id":      "integer",
		"region_id":       "integer",
		"name":            "text",
		"normalized_name": "text",
		"timezone":        "text",
	},
	"metadata": {
		"key":   "text",
		"value": "text",
	},
}

func checkRequiredColumns(ctx context.Context, db *sql.DB, tablesOK bool) Check {
	c := Check{Name: "required_columns", Message: "All required columns are present."}
	if !tablesOK {
		c.Status = StatusFail
		c.Message = "Skipped column checks because required tables are missing."
		return c
	}

	var problems []string
	for table, cols := range requiredColumns {
		info, err := tableColumns(ctx, db, table)
		if err != nil {
			c.Status = StatusFail
			c.Message = "Failed to inspect columns."
			c.Details = []string{err.Error()}
			return c
		}
		for name, wantAff := range cols {
			got, ok := info[name]
			if !ok {
				problems = append(problems, fmt.Sprintf("%s.%s is missing", table, name))
				continue
			}
			if !affinityMatches(got, wantAff) {
				problems = append(problems, fmt.Sprintf("%s.%s has unexpected type %q (want %s affinity)", table, name, got, wantAff))
			}
		}
	}
	if len(problems) > 0 {
		c.Status = StatusFail
		c.Message = "One or more required columns are missing or mistyped."
		c.Details = problems
		return c
	}
	c.Status = StatusPass
	return c
}

func tableColumns(ctx context.Context, db *sql.DB, table string) (map[string]string, error) {
	rows, err := db.QueryContext(ctx, fmt.Sprintf(`PRAGMA table_info(%s)`, quoteIdent(table)))
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	out := make(map[string]string)
	for rows.Next() {
		var cid int
		var name, ctype string
		var notnull, pk int
		var dflt sql.NullString
		if err := rows.Scan(&cid, &name, &ctype, &notnull, &dflt, &pk); err != nil {
			return nil, err
		}
		out[strings.ToLower(name)] = ctype
	}
	return out, rows.Err()
}

func affinityMatches(declared, want string) bool {
	d := strings.ToUpper(strings.TrimSpace(declared))
	switch want {
	case "integer":
		return strings.Contains(d, "INT")
	case "text":
		// SQLite text affinity: TEXT, CHAR, CLOB, or empty/numeric-looking avoided —
		// accept TEXT/CHAR/CLOB or bare empty (SQLite default NUMERIC, but our schema uses TEXT).
		return d == "" || strings.Contains(d, "CHAR") || strings.Contains(d, "CLOB") || strings.Contains(d, "TEXT")
	default:
		return true
	}
}

func quoteIdent(name string) string {
	return `"` + strings.ReplaceAll(name, `"`, `""`) + `"`
}

func checkRequiredIndexes(ctx context.Context, db *sql.DB, tablesOK bool) Check {
	c := Check{Name: "required_indexes", Message: "Required access-path indexes are present."}
	if !tablesOK {
		c.Status = StatusFail
		c.Message = "Skipped index checks because required tables are missing."
		return c
	}

	var problems []string
	if ok, err := hasColumnIndex(ctx, db, "regions", "country_id", false); err != nil {
		return failCheck(c, "Failed to inspect indexes.", err)
	} else if !ok {
		problems = append(problems, "missing index on regions(country_id)")
	}
	if ok, err := hasColumnIndex(ctx, db, "cities", "region_id", false); err != nil {
		return failCheck(c, "Failed to inspect indexes.", err)
	} else if !ok {
		problems = append(problems, "missing index on cities(region_id)")
	}
	if ok, err := hasColumnIndex(ctx, db, "cities", "country_id", false); err != nil {
		return failCheck(c, "Failed to inspect indexes.", err)
	} else if !ok {
		problems = append(problems, "missing index on cities(country_id)")
	}
	if ok, err := hasColumnIndex(ctx, db, "countries", "normalized_name", false); err != nil {
		return failCheck(c, "Failed to inspect indexes.", err)
	} else if !ok {
		problems = append(problems, "missing index on countries(normalized_name)")
	}
	if ok, err := hasColumnIndex(ctx, db, "regions", "normalized_name", false); err != nil {
		return failCheck(c, "Failed to inspect indexes.", err)
	} else if !ok {
		problems = append(problems, "missing index on regions(normalized_name)")
	}
	if ok, err := hasColumnIndex(ctx, db, "cities", "normalized_name", false); err != nil {
		return failCheck(c, "Failed to inspect indexes.", err)
	} else if !ok {
		problems = append(problems, "missing index on cities(normalized_name)")
	}

	if len(problems) > 0 {
		c.Status = StatusFail
		c.Message = "One or more required indexes are missing."
		c.Details = problems
		return c
	}
	c.Status = StatusPass
	return c
}

func failCheck(c Check, msg string, err error) Check {
	c.Status = StatusFail
	c.Message = msg
	c.Details = []string{err.Error()}
	return c
}

func hasColumnIndex(ctx context.Context, db *sql.DB, table, column string, requireNoCase bool) (bool, error) {
	rows, err := db.QueryContext(ctx, fmt.Sprintf(`PRAGMA index_list(%s)`, quoteIdent(table)))
	if err != nil {
		return false, err
	}

	type idxMeta struct {
		name string
	}
	var indexes []idxMeta
	for rows.Next() {
		var seq int
		var name string
		var unique int
		var origin string
		var partial int
		if err := rows.Scan(&seq, &name, &unique, &origin, &partial); err != nil {
			_ = rows.Close()
			return false, err
		}
		indexes = append(indexes, idxMeta{name: name})
	}
	if err := rows.Err(); err != nil {
		_ = rows.Close()
		return false, err
	}
	if err := rows.Close(); err != nil {
		return false, err
	}

	for _, idx := range indexes {
		cols, err := indexColumns(ctx, db, idx.name)
		if err != nil {
			return false, err
		}
		if len(cols) == 0 {
			continue
		}
		// Accept a leading single-column index on the target column.
		if !strings.EqualFold(cols[0].name, column) {
			continue
		}
		if requireNoCase {
			if strings.EqualFold(cols[0].collation, "NOCASE") {
				return true, nil
			}
			continue
		}
		return true, nil
	}
	return false, nil
}

type indexCol struct {
	name      string
	collation string
}

func indexColumns(ctx context.Context, db *sql.DB, indexName string) ([]indexCol, error) {
	rows, err := db.QueryContext(ctx, fmt.Sprintf(`PRAGMA index_xinfo(%s)`, quoteIdent(indexName)))
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []indexCol
	for rows.Next() {
		var seqno, cid, desc, key int
		var name sql.NullString
		var coll string
		if err := rows.Scan(&seqno, &cid, &name, &desc, &coll, &key); err != nil {
			return nil, err
		}
		if key == 0 || !name.Valid {
			continue
		}
		out = append(out, indexCol{name: name.String, collation: coll})
	}
	return out, rows.Err()
}

func checkRequiredMetadata(ctx context.Context, db *sql.DB, tablesOK bool) (Check, ReportMetadata) {
	c := Check{Name: "required_metadata", Message: "Required metadata is present and valid."}
	var meta ReportMetadata
	if !tablesOK {
		c.Status = StatusFail
		c.Message = "Skipped metadata checks because required tables are missing."
		return c, meta
	}

	exists, err := database.TableExists(ctx, db, "metadata")
	if err != nil || !exists {
		c.Status = StatusFail
		c.Message = "Metadata table is unavailable."
		if err != nil {
			c.Details = []string{err.Error()}
		}
		return c, meta
	}

	raw := make(map[string]string, len(database.RequiredMetadataKeys))
	var problems []string
	for _, key := range database.RequiredMetadataKeys {
		var value string
		err := db.QueryRowContext(ctx, `SELECT value FROM metadata WHERE key = ?`, key).Scan(&value)
		if err == sql.ErrNoRows {
			problems = append(problems, fmt.Sprintf("%s is missing", key))
			continue
		}
		if err != nil {
			c.Status = StatusFail
			c.Message = "Failed to read metadata."
			c.Details = []string{err.Error()}
			return c, meta
		}
		if strings.TrimSpace(value) == "" {
			problems = append(problems, fmt.Sprintf("%s is empty", key))
			continue
		}
		raw[key] = strings.TrimSpace(value)
	}

	if v, ok := raw[model.MetaProvider]; ok {
		meta.Provider = v
	}
	if v, ok := raw[model.MetaDatasetVersion]; ok {
		meta.DatasetVersion = v
	}
	if v, ok := raw[model.MetaDatasetSHA256]; ok {
		lower := strings.ToLower(v)
		if !isSHA256Hex(lower) {
			problems = append(problems, "dataset_sha256 is not a 64-character hexadecimal digest")
		} else {
			meta.DatasetSHA256 = lower
		}
	}
	if v, ok := raw[model.MetaGeneratedAt]; ok {
		if _, err := time.Parse(time.RFC3339, v); err != nil {
			problems = append(problems, "generated_at is not valid RFC3339")
		} else {
			meta.GeneratedAt = v
		}
	}
	if v, ok := raw[model.MetaGeneratorVersion]; ok {
		meta.GeneratorVersion = v
	}
	if v, ok := raw[model.MetaSchemaVersion]; ok {
		n, err := strconv.Atoi(v)
		if err != nil || n < 1 {
			problems = append(problems, "schema_version is not a positive integer")
		} else {
			meta.SchemaVersion = n
		}
	}

	if len(problems) > 0 {
		c.Status = StatusFail
		c.Message = "Required metadata is missing or invalid."
		c.Details = problems
		return c, meta
	}
	c.Status = StatusPass
	return c, meta
}

func isSHA256Hex(s string) bool {
	if len(s) != 64 {
		return false
	}
	for _, r := range s {
		if !((r >= '0' && r <= '9') || (r >= 'a' && r <= 'f')) {
			return false
		}
	}
	return true
}

func checkCounts(ctx context.Context, db *sql.DB, tablesOK bool) (Check, Counts) {
	c := Check{Name: "row_counts", Message: "Catalog contains countries, regions, and cities."}
	var counts Counts
	if !tablesOK {
		c.Status = StatusFail
		c.Message = "Skipped count checks because required tables are missing."
		return c, counts
	}

	var problems []string
	var err error
	counts.Countries, err = countRows(ctx, db, "countries")
	if err != nil {
		return failCheck(c, "Failed to count rows.", err), counts
	}
	counts.Regions, err = countRows(ctx, db, "regions")
	if err != nil {
		return failCheck(c, "Failed to count rows.", err), counts
	}
	counts.Cities, err = countRows(ctx, db, "cities")
	if err != nil {
		return failCheck(c, "Failed to count rows.", err), counts
	}

	if counts.Countries == 0 {
		problems = append(problems, "country count is zero")
	}
	if counts.Regions == 0 {
		problems = append(problems, "region count is zero")
	}
	if counts.Cities == 0 {
		problems = append(problems, "city count is zero")
	}
	if len(problems) > 0 {
		c.Status = StatusFail
		c.Message = "Catalog row counts are insufficient."
		c.Details = problems
		return c, counts
	}
	c.Status = StatusPass
	return c, counts
}

func countRows(ctx context.Context, db *sql.DB, table string) (int, error) {
	var n int
	err := db.QueryRowContext(ctx, fmt.Sprintf(`SELECT COUNT(*) FROM %s`, quoteIdent(table))).Scan(&n)
	return n, err
}

func checkBlankCountryFields(ctx context.Context, db *sql.DB) []Check {
	codeCheck := sampleFailQuery(ctx, db, "blank_country_code",
		"No country has a blank code.",
		`SELECT id, code FROM countries WHERE TRIM(code) = '' LIMIT ?`,
		func(id int64, code string) string {
			return fmt.Sprintf("country id %d has blank code", id)
		},
	)
	nameCheck := sampleFailQuery(ctx, db, "blank_country_name",
		"No country has a blank name.",
		`SELECT id, name FROM countries WHERE TRIM(name) = '' LIMIT ?`,
		func(id int64, name string) string {
			return fmt.Sprintf("country id %d has blank name", id)
		},
	)
	return []Check{codeCheck, nameCheck}
}

func checkBlankRegionNames(ctx context.Context, db *sql.DB) Check {
	return sampleFailQuery(ctx, db, "blank_region_name",
		"No region has a blank name.",
		`SELECT id, name FROM regions WHERE TRIM(name) = '' LIMIT ?`,
		func(id int64, name string) string {
			return fmt.Sprintf("region id %d has blank name", id)
		},
	)
}

func checkBlankCityNames(ctx context.Context, db *sql.DB) Check {
	return sampleFailQuery(ctx, db, "blank_city_name",
		"No city has a blank name.",
		`SELECT id, name FROM cities WHERE TRIM(name) = '' LIMIT ?`,
		func(id int64, name string) string {
			return fmt.Sprintf("city id %d has blank name", id)
		},
	)
}

func sampleFailQuery(ctx context.Context, db *sql.DB, name, okMsg, query string, format func(id int64, second string) string) Check {
	c := Check{Name: name, Message: okMsg, Status: StatusPass}
	rows, err := db.QueryContext(ctx, query, maxExamples)
	if err != nil {
		return failCheck(c, "Failed data sanity check.", err)
	}
	defer rows.Close()

	var details []string
	for rows.Next() {
		var id int64
		var second string
		if err := rows.Scan(&id, &second); err != nil {
			return failCheck(c, "Failed data sanity check.", err)
		}
		details = append(details, format(id, second))
	}
	if err := rows.Err(); err != nil {
		return failCheck(c, "Failed data sanity check.", err)
	}
	if len(details) > 0 {
		c.Status = StatusFail
		c.Message = "Data sanity check failed."
		c.Details = details
	}
	return c
}

func checkNormalizedNames(ctx context.Context, db *sql.DB) []Check {
	return []Check{
		checkNormalizedTable(ctx, db, "normalized_countries", "countries",
			`SELECT id, name, normalized_name FROM countries`),
		checkNormalizedTable(ctx, db, "normalized_regions", "regions",
			`SELECT id, name, normalized_name FROM regions`),
		checkNormalizedTable(ctx, db, "normalized_cities", "cities",
			`SELECT id, name, normalized_name FROM cities`),
	}
}

func checkNormalizedTable(ctx context.Context, db *sql.DB, checkName, label, query string) Check {
	c := Check{
		Name:    checkName,
		Message: fmt.Sprintf("All %s normalized_name values match NormalizeSearch(name).", label),
		Status:  StatusPass,
	}
	rows, err := db.QueryContext(ctx, query)
	if err != nil {
		return failCheck(c, "Failed normalized-name check.", err)
	}
	defer rows.Close()

	var (
		details []string
		total   int
	)
	for rows.Next() {
		var id int64
		var name, normalized string
		if err := rows.Scan(&id, &name, &normalized); err != nil {
			return failCheck(c, "Failed normalized-name check.", err)
		}
		want := textutil.NormalizeSearch(name)
		if strings.TrimSpace(normalized) == "" || normalized != want {
			total++
			if len(details) < maxExamples {
				details = append(details, fmt.Sprintf("id=%d name=%q normalized_name=%q want=%q", id, name, normalized, want))
			}
		}
	}
	if err := rows.Err(); err != nil {
		return failCheck(c, "Failed normalized-name check.", err)
	}
	if total > 0 {
		c.Status = StatusFail
		c.Message = fmt.Sprintf("%d %s normalized_name mismatches.", total, label)
		c.Details = details
		if total > maxExamples {
			c.Details = append(c.Details, fmt.Sprintf("%d total mismatches", total))
		}
	}
	return c
}

func checkOrphansAndMismatches(ctx context.Context, db *sql.DB) []Check {
	cityCountry := Check{Name: "city_country_orphan", Message: "All cities reference an existing country.", Status: StatusPass}
	details, total, err := collectOrphans(ctx, db, `
		SELECT c.id FROM cities c
		LEFT JOIN countries co ON co.id = c.country_id
		WHERE co.id IS NULL
		LIMIT ?`, `
		SELECT COUNT(*) FROM cities c
		LEFT JOIN countries co ON co.id = c.country_id
		WHERE co.id IS NULL`,
		func(id int64) string { return fmt.Sprintf("city id %d references a missing country", id) },
	)
	if err != nil {
		cityCountry = failCheck(cityCountry, "Failed orphan check.", err)
	} else if total > 0 {
		cityCountry.Status = StatusFail
		cityCountry.Message = fmt.Sprintf("%d cities reference a missing country.", total)
		cityCountry.Details = details
	}

	regionCountry := Check{Name: "region_country_orphan", Message: "All regions reference an existing country.", Status: StatusPass}
	details, total, err = collectOrphans(ctx, db, `
		SELECT r.id FROM regions r
		LEFT JOIN countries co ON co.id = r.country_id
		WHERE co.id IS NULL
		LIMIT ?`, `
		SELECT COUNT(*) FROM regions r
		LEFT JOIN countries co ON co.id = r.country_id
		WHERE co.id IS NULL`,
		func(id int64) string { return fmt.Sprintf("region id %d references a missing country", id) },
	)
	if err != nil {
		regionCountry = failCheck(regionCountry, "Failed orphan check.", err)
	} else if total > 0 {
		regionCountry.Status = StatusFail
		regionCountry.Message = fmt.Sprintf("%d regions reference a missing country.", total)
		regionCountry.Details = details
	}

	mismatch := Check{Name: "city_region_country_mismatch", Message: "City regions belong to the same country.", Status: StatusPass}
	details, total, err = collectOrphans(ctx, db, `
		SELECT c.id FROM cities c
		JOIN regions r ON r.id = c.region_id
		WHERE c.region_id IS NOT NULL AND r.country_id != c.country_id
		LIMIT ?`, `
		SELECT COUNT(*) FROM cities c
		JOIN regions r ON r.id = c.region_id
		WHERE c.region_id IS NOT NULL AND r.country_id != c.country_id`,
		func(id int64) string { return fmt.Sprintf("city id %d region belongs to another country", id) },
	)
	if err != nil {
		mismatch = failCheck(mismatch, "Failed mismatch check.", err)
	} else if total > 0 {
		mismatch.Status = StatusFail
		mismatch.Message = fmt.Sprintf("%d cities reference a region belonging to another country.", total)
		mismatch.Details = details
	}

	return []Check{cityCountry, regionCountry, mismatch}
}

func collectOrphans(ctx context.Context, db *sql.DB, sampleSQL, countSQL string, format func(int64) string) ([]string, int, error) {
	var total int
	if err := db.QueryRowContext(ctx, countSQL).Scan(&total); err != nil {
		return nil, 0, err
	}
	if total == 0 {
		return nil, 0, nil
	}
	rows, err := db.QueryContext(ctx, sampleSQL, maxExamples)
	if err != nil {
		return nil, 0, err
	}
	defer rows.Close()
	var details []string
	for rows.Next() {
		var id int64
		if err := rows.Scan(&id); err != nil {
			return nil, 0, err
		}
		details = append(details, format(id))
	}
	if total > maxExamples {
		details = append(details, fmt.Sprintf("%d total affected", total))
	}
	return details, total, rows.Err()
}

func checkTimezones(ctx context.Context, db *sql.DB) Check {
	c := Check{Name: "timezone_validation", Message: "All non-empty city timezones are valid IANA names.", Status: StatusPass}
	rows, err := db.QueryContext(ctx, `
		SELECT id, timezone FROM cities
		WHERE timezone IS NOT NULL AND TRIM(timezone) != ''
	`)
	if err != nil {
		return failCheck(c, "Failed timezone validation.", err)
	}
	defer rows.Close()

	invalidTZ := make(map[string]int)
	var examples []string
	affected := 0
	for rows.Next() {
		var id int64
		var tz string
		if err := rows.Scan(&id, &tz); err != nil {
			return failCheck(c, "Failed timezone validation.", err)
		}
		tz = strings.TrimSpace(tz)
		if _, err := time.LoadLocation(tz); err != nil {
			affected++
			invalidTZ[tz]++
			if len(examples) < maxExamples {
				examples = append(examples, fmt.Sprintf("invalid timezone %q (city id %d)", tz, id))
			}
		}
	}
	if err := rows.Err(); err != nil {
		return failCheck(c, "Failed timezone validation.", err)
	}
	if affected > 0 {
		c.Status = StatusFail
		c.Message = fmt.Sprintf("%d cities have invalid timezones (%d distinct values).", affected, len(invalidTZ))
		c.Details = examples
		if affected > maxExamples {
			c.Details = append(c.Details, fmt.Sprintf("%d cities affected", affected))
		}
	}
	return c
}

func collectWarnings(ctx context.Context, db *sql.DB, tablesOK bool, meta ReportMetadata) []Check {
	var warnings []Check
	if !tablesOK {
		return warnings
	}

	if extras, err := unknownTables(ctx, db); err == nil && len(extras) > 0 {
		warnings = append(warnings, Check{
			Name:    "unknown_tables",
			Status:  StatusWarning,
			Message: "Database contains tables not defined by the current schema.",
			Details: extras,
		})
	}

	if extras, err := unknownColumns(ctx, db); err == nil && len(extras) > 0 {
		warnings = append(warnings, Check{
			Name:    "unknown_columns",
			Status:  StatusWarning,
			Message: "Database contains columns not defined by the current schema.",
			Details: extras,
		})
	}

	if extras, err := unknownIndexes(ctx, db); err == nil && len(extras) > 0 {
		warnings = append(warnings, Check{
			Name:    "unknown_indexes",
			Status:  StatusWarning,
			Message: "Database contains indexes beyond the current schema set.",
			Details: extras,
		})
	}

	knownSchema, _ := strconv.Atoi(model.SchemaVersion)
	if meta.SchemaVersion > 0 && meta.SchemaVersion > knownSchema {
		warnings = append(warnings, Check{
			Name:    "future_schema_version",
			Status:  StatusWarning,
			Message: fmt.Sprintf("schema_version %d is newer than inspector schema %d.", meta.SchemaVersion, knownSchema),
		})
	}

	if meta.GeneratorVersion != "" && isNewerVersion(meta.GeneratorVersion, model.GeneratorVersion) {
		warnings = append(warnings, Check{
			Name:    "newer_generator_version",
			Status:  StatusWarning,
			Message: fmt.Sprintf("generator_version %s is newer than inspector %s.", meta.GeneratorVersion, model.GeneratorVersion),
		})
	}

	return warnings
}

func unknownTables(ctx context.Context, db *sql.DB) ([]string, error) {
	known := map[string]struct{}{}
	for _, t := range database.RequiredTables {
		known[t] = struct{}{}
	}
	rows, err := db.QueryContext(ctx, `
		SELECT name FROM sqlite_master
		WHERE type='table' AND name NOT LIKE 'sqlite_%'
		ORDER BY name
	`)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	var extras []string
	for rows.Next() {
		var name string
		if err := rows.Scan(&name); err != nil {
			return nil, err
		}
		if _, ok := known[name]; !ok {
			extras = append(extras, name)
		}
	}
	return extras, rows.Err()
}

func unknownColumns(ctx context.Context, db *sql.DB) ([]string, error) {
	var extras []string
	for table, cols := range requiredColumns {
		info, err := tableColumns(ctx, db, table)
		if err != nil {
			return nil, err
		}
		for name := range info {
			if _, ok := cols[name]; !ok {
				extras = append(extras, table+"."+name)
			}
		}
	}
	return extras, nil
}

func unknownIndexes(ctx context.Context, db *sql.DB) ([]string, error) {
	var extras []string
	for _, table := range []string{"regions", "cities", "countries"} {
		rows, err := db.QueryContext(ctx, fmt.Sprintf(`PRAGMA index_list(%s)`, quoteIdent(table)))
		if err != nil {
			return nil, err
		}
		type listed struct {
			name   string
			origin string
		}
		var listedIdx []listed
		for rows.Next() {
			var seq int
			var name, origin string
			var unique, partial int
			if err := rows.Scan(&seq, &name, &unique, &origin, &partial); err != nil {
				_ = rows.Close()
				return nil, err
			}
			listedIdx = append(listedIdx, listed{name: name, origin: origin})
		}
		if err := rows.Err(); err != nil {
			_ = rows.Close()
			return nil, err
		}
		if err := rows.Close(); err != nil {
			return nil, err
		}

		for _, idx := range listedIdx {
			if idx.origin == "pk" || idx.origin == "u" {
				continue // primary key / unique constraint indexes
			}
			cols, err := indexColumns(ctx, db, idx.name)
			if err != nil {
				return nil, err
			}
			if !isExpectedIndex(table, cols) {
				extras = append(extras, idx.name)
			}
		}
	}
	return extras, nil
}

func isExpectedIndex(table string, cols []indexCol) bool {
	if len(cols) == 0 {
		return false
	}
	col := strings.ToLower(cols[0].name)
	switch table {
	case "countries":
		return col == "normalized_name"
	case "regions":
		return col == "country_id" || col == "normalized_name"
	case "cities":
		switch col {
		case "region_id", "country_id", "normalized_name":
			return true
		}
	}
	return false
}

func isNewerVersion(have, known string) bool {
	hp, ok1 := parseVersion(have)
	kp, ok2 := parseVersion(known)
	if !ok1 || !ok2 {
		return false
	}
	for i := 0; i < 3; i++ {
		if hp[i] > kp[i] {
			return true
		}
		if hp[i] < kp[i] {
			return false
		}
	}
	return false
}

func parseVersion(v string) ([3]int, bool) {
	v = strings.TrimSpace(strings.TrimPrefix(v, "v"))
	parts := strings.Split(v, ".")
	var out [3]int
	if len(parts) == 0 {
		return out, false
	}
	for i := 0; i < 3 && i < len(parts); i++ {
		n, err := strconv.Atoi(parts[i])
		if err != nil {
			// allow trailing non-numeric pre-release by stripping
			num := ""
			for _, r := range parts[i] {
				if unicode.IsDigit(r) {
					num += string(r)
				} else {
					break
				}
			}
			if num == "" {
				return out, false
			}
			n, _ = strconv.Atoi(num)
		}
		out[i] = n
	}
	return out, true
}
