// Package importer builds a Finba Geo SQLite catalog from the supplier cities CSV.
package importer

import (
	"context"
	"database/sql"
	"encoding/csv"
	"errors"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
	"unicode/utf8"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/model"
	"finba.se/geo/internal/textutil"

	_ "time/tzdata" // ensure IANA zones resolve in slim environments
)

// Options configures an import run.
type Options struct {
	InputPath        string
	OutputPath       string
	DatasetVersion   string
	DatasetSHA256    string
	GeneratorVersion string
	SchemaVersion    string
	Provider         string
}

// Result summarizes a successful import.
type Result struct {
	Countries int
	Regions   int
	Cities    int
	Output    string
}

type countryRec struct {
	ID             int64
	Code           string
	Name           string
	NormalizedName string
}

type regionRec struct {
	ID             int64
	CountryID      int64
	Code           string
	Name           string
	NormalizedName string
}

// Run imports the CSV into a new SQLite database at Options.OutputPath.
func Run(ctx context.Context, opts Options) (Result, error) {
	if err := validateOptions(opts); err != nil {
		return Result{}, err
	}
	if opts.GeneratorVersion == "" {
		opts.GeneratorVersion = model.GeneratorVersion
	}
	if opts.SchemaVersion == "" {
		opts.SchemaVersion = model.SchemaVersion
	}
	if opts.Provider == "" {
		opts.Provider = model.Provider
	}

	tmpPath := opts.OutputPath + ".tmp"
	_ = os.Remove(tmpPath)

	cleanup := func() {
		_ = os.Remove(tmpPath)
	}

	in, err := os.Open(opts.InputPath)
	if err != nil {
		return Result{}, fmt.Errorf("open input CSV: %w", err)
	}
	defer in.Close()

	db, err := database.OpenWrite(tmpPath)
	if err != nil {
		cleanup()
		return Result{}, err
	}

	result, err := importInto(ctx, db, in, opts)
	if err != nil {
		_ = db.Close()
		cleanup()
		return Result{}, err
	}

	if err := database.CheckIntegrity(ctx, db); err != nil {
		_ = db.Close()
		cleanup()
		return Result{}, fmt.Errorf("post-import validation: %w", err)
	}

	if err := db.Close(); err != nil {
		cleanup()
		return Result{}, fmt.Errorf("close temporary database: %w", err)
	}

	if err := os.MkdirAll(filepath.Dir(opts.OutputPath), 0o755); err != nil {
		cleanup()
		return Result{}, fmt.Errorf("create output directory: %w", err)
	}

	if err := publishAtomically(tmpPath, opts.OutputPath); err != nil {
		cleanup()
		return Result{}, err
	}

	result.Output = opts.OutputPath
	return result, nil
}

func validateOptions(opts Options) error {
	if strings.TrimSpace(opts.InputPath) == "" {
		return errors.New("input path is required")
	}
	if strings.TrimSpace(opts.OutputPath) == "" {
		return errors.New("output path is required")
	}
	if strings.TrimSpace(opts.DatasetVersion) == "" {
		return errors.New("dataset version is required")
	}
	if strings.TrimSpace(opts.DatasetSHA256) == "" {
		return errors.New("dataset SHA-256 is required")
	}
	return nil
}

func importInto(ctx context.Context, db *sql.DB, r io.Reader, opts Options) (Result, error) {
	reader := csv.NewReader(r)
	reader.ReuseRecord = true
	reader.FieldsPerRecord = -1

	header, err := reader.Read()
	if err != nil {
		return Result{}, fmt.Errorf("read CSV header: %w", err)
	}
	if !utf8.ValidString(strings.Join(header, ",")) {
		return Result{}, errors.New("CSV header contains invalid UTF-8")
	}

	cols, err := resolveColumns(header)
	if err != nil {
		return Result{}, err
	}

	tx, err := db.BeginTx(ctx, nil)
	if err != nil {
		return Result{}, fmt.Errorf("begin transaction: %w", err)
	}
	defer func() { _ = tx.Rollback() }()

	countryStmt, err := tx.PrepareContext(ctx, `
		INSERT INTO countries (id, code, name, normalized_name) VALUES (?, ?, ?, ?)
	`)
	if err != nil {
		return Result{}, fmt.Errorf("prepare countries insert: %w", err)
	}
	defer countryStmt.Close()

	regionStmt, err := tx.PrepareContext(ctx, `
		INSERT INTO regions (id, country_id, code, name, normalized_name) VALUES (?, ?, ?, ?, ?)
	`)
	if err != nil {
		return Result{}, fmt.Errorf("prepare regions insert: %w", err)
	}
	defer regionStmt.Close()

	cityStmt, err := tx.PrepareContext(ctx, `
		INSERT INTO cities (id, country_id, region_id, name, normalized_name, timezone) VALUES (?, ?, ?, ?, ?, ?)
	`)
	if err != nil {
		return Result{}, fmt.Errorf("prepare cities insert: %w", err)
	}
	defer cityStmt.Close()

	metaStmt, err := tx.PrepareContext(ctx, `
		INSERT INTO metadata (key, value) VALUES (?, ?)
	`)
	if err != nil {
		return Result{}, fmt.Errorf("prepare metadata insert: %w", err)
	}
	defer metaStmt.Close()

	countries := make(map[int64]countryRec)
	regions := make(map[int64]regionRec)
	cityIDs := make(map[int64]struct{})

	line := 1 // header
	for {
		if err := ctx.Err(); err != nil {
			return Result{}, err
		}

		record, err := reader.Read()
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return Result{}, fmt.Errorf("read CSV row %d: %w", line+1, err)
		}
		line++

		for _, field := range record {
			if !utf8.ValidString(field) {
				return Result{}, fmt.Errorf("row %d: invalid UTF-8", line)
			}
		}

		city, country, region, err := parseRow(record, cols, line)
		if err != nil {
			return Result{}, err
		}

		if existing, ok := countries[country.ID]; ok {
			if existing != country {
				return Result{}, fmt.Errorf("row %d: conflicting country id %d", line, country.ID)
			}
		} else {
			countries[country.ID] = country
			if _, err := countryStmt.ExecContext(ctx, country.ID, country.Code, country.Name, country.NormalizedName); err != nil {
				return Result{}, fmt.Errorf("row %d: insert country %d: %w", line, country.ID, err)
			}
		}

		if region != nil {
			if existing, ok := regions[region.ID]; ok {
				if existing != *region {
					return Result{}, fmt.Errorf("row %d: conflicting region id %d", line, region.ID)
				}
			} else {
				if _, ok := countries[region.CountryID]; !ok {
					return Result{}, fmt.Errorf("row %d: region %d references unknown country %d", line, region.ID, region.CountryID)
				}
				regions[region.ID] = *region
				var code any
				if region.Code != "" {
					code = region.Code
				}
				if _, err := regionStmt.ExecContext(ctx, region.ID, region.CountryID, code, region.Name, region.NormalizedName); err != nil {
					return Result{}, fmt.Errorf("row %d: insert region %d: %w", line, region.ID, err)
				}
			}
		}

		if _, ok := countries[city.countryID]; !ok {
			return Result{}, fmt.Errorf("row %d: city %d references unknown country %d", line, city.id, city.countryID)
		}
		if city.regionID != nil {
			reg, ok := regions[*city.regionID]
			if !ok {
				return Result{}, fmt.Errorf("row %d: city %d references unknown region %d", line, city.id, *city.regionID)
			}
			if reg.CountryID != city.countryID {
				return Result{}, fmt.Errorf("row %d: city %d region %d belongs to country %d, not %d",
					line, city.id, *city.regionID, reg.CountryID, city.countryID)
			}
		}
		if _, exists := cityIDs[city.id]; exists {
			return Result{}, fmt.Errorf("row %d: duplicate city id %d", line, city.id)
		}
		cityIDs[city.id] = struct{}{}

		var regionID any
		if city.regionID != nil {
			regionID = *city.regionID
		}
		var tz any
		if city.timezone != "" {
			tz = city.timezone
		}
		if _, err := cityStmt.ExecContext(ctx, city.id, city.countryID, regionID, city.name, city.normalizedName, tz); err != nil {
			return Result{}, fmt.Errorf("row %d: insert city %d: %w", line, city.id, err)
		}
	}

	generatedAt := time.Now().UTC().Format(time.RFC3339)
	meta := map[string]string{
		model.MetaProvider:         opts.Provider,
		model.MetaDatasetVersion:   opts.DatasetVersion,
		model.MetaDatasetSHA256:    opts.DatasetSHA256,
		model.MetaGeneratedAt:      generatedAt,
		model.MetaGeneratorVersion: opts.GeneratorVersion,
		model.MetaSchemaVersion:    opts.SchemaVersion,
	}
	for key, value := range meta {
		if _, err := metaStmt.ExecContext(ctx, key, value); err != nil {
			return Result{}, fmt.Errorf("insert metadata %q: %w", key, err)
		}
	}

	if err := tx.Commit(); err != nil {
		return Result{}, fmt.Errorf("commit import: %w", err)
	}

	return Result{
		Countries: len(countries),
		Regions:   len(regions),
		Cities:    len(cityIDs),
	}, nil
}

type columnMap struct {
	id          int
	name        int
	stateID     int
	stateCode   int
	stateName   int
	countryID   int
	countryCode int
	countryName int
	timezone    int
}

func resolveColumns(header []string) (columnMap, error) {
	required := []string{
		"id",
		"name",
		"state_id",
		"state_code",
		"state_name",
		"country_id",
		"country_code",
		"country_name",
		"timezone",
	}
	index := make(map[string]int, len(header))
	for i, h := range header {
		index[strings.TrimSpace(h)] = i
	}

	var missing []string
	for _, name := range required {
		if _, ok := index[name]; !ok {
			missing = append(missing, name)
		}
	}
	if len(missing) > 0 {
		return columnMap{}, fmt.Errorf("missing required CSV headers: %s", strings.Join(missing, ", "))
	}

	return columnMap{
		id:          index["id"],
		name:        index["name"],
		stateID:     index["state_id"],
		stateCode:   index["state_code"],
		stateName:   index["state_name"],
		countryID:   index["country_id"],
		countryCode: index["country_code"],
		countryName: index["country_name"],
		timezone:    index["timezone"],
	}, nil
}

type cityRec struct {
	id             int64
	countryID      int64
	regionID       *int64
	name           string
	normalizedName string
	timezone       string
}

func parseRow(record []string, cols columnMap, line int) (cityRec, countryRec, *regionRec, error) {
	get := func(idx int) string {
		if idx < 0 || idx >= len(record) {
			return ""
		}
		return strings.TrimSpace(record[idx])
	}

	cityID, err := parseInt64(get(cols.id), "city id", line)
	if err != nil {
		return cityRec{}, countryRec{}, nil, err
	}
	countryID, err := parseInt64(get(cols.countryID), "country id", line)
	if err != nil {
		return cityRec{}, countryRec{}, nil, err
	}

	countryCode := get(cols.countryCode)
	if countryCode == "" {
		return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank country code", line)
	}
	countryName := get(cols.countryName)
	if countryName == "" {
		return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank country name", line)
	}
	countryNorm := textutil.NormalizeSearch(countryName)
	if countryNorm == "" {
		return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank normalized country name", line)
	}

	cityName := get(cols.name)
	if cityName == "" {
		return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank city name", line)
	}
	cityNorm := textutil.NormalizeSearch(cityName)
	if cityNorm == "" {
		return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank normalized city name", line)
	}

	tz := get(cols.timezone)
	if tz != "" {
		if _, err := time.LoadLocation(tz); err != nil {
			return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: invalid timezone %q: %w", line, tz, err)
		}
	}

	country := countryRec{
		ID:             countryID,
		Code:           countryCode,
		Name:           countryName,
		NormalizedName: countryNorm,
	}

	var region *regionRec
	stateIDRaw := get(cols.stateID)
	stateName := get(cols.stateName)
	stateCode := get(cols.stateCode)

	// A region is present when state_id is non-empty.
	if stateIDRaw != "" {
		stateID, err := parseInt64(stateIDRaw, "state id", line)
		if err != nil {
			return cityRec{}, countryRec{}, nil, err
		}
		if stateName == "" {
			return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank region name for state_id %d", line, stateID)
		}
		regionNorm := textutil.NormalizeSearch(stateName)
		if regionNorm == "" {
			return cityRec{}, countryRec{}, nil, fmt.Errorf("row %d: blank normalized region name", line)
		}
		region = &regionRec{
			ID:             stateID,
			CountryID:      countryID,
			Code:           stateCode,
			Name:           stateName,
			NormalizedName: regionNorm,
		}
	}

	city := cityRec{
		id:             cityID,
		countryID:      countryID,
		name:           cityName,
		normalizedName: cityNorm,
		timezone:       tz,
	}
	if region != nil {
		id := region.ID
		city.regionID = &id
	}

	return city, country, region, nil
}

func parseInt64(raw, field string, line int) (int64, error) {
	if raw == "" {
		return 0, fmt.Errorf("row %d: blank %s", line, field)
	}
	v, err := strconv.ParseInt(raw, 10, 64)
	if err != nil {
		return 0, fmt.Errorf("row %d: invalid %s %q", line, field, raw)
	}
	return v, nil
}

func publishAtomically(tmpPath, outputPath string) error {
	if err := os.Remove(outputPath); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("remove existing output: %w", err)
	}
	if err := os.Rename(tmpPath, outputPath); err != nil {
		return fmt.Errorf("publish database: %w", err)
	}
	return nil
}
