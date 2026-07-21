package inspect_test

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"flag"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/inspect"
	"finba.se/geo/internal/model"

	_ "modernc.org/sqlite"
)

const validSHA = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"

func TestValidDatabaseReady(t *testing.T) {
	t.Parallel()
	path := importValidFixture(t)
	rep, err := inspect.Run(context.Background(), inspect.Options{Path: path})
	if err != nil {
		t.Fatal(err)
	}
	if !rep.Ready {
		t.Fatalf("expected READY, checks=%+v warnings=%+v", rep.Checks, rep.Warnings)
	}
	if rep.Counts.Countries < 1 || rep.Counts.Cities < 1 {
		t.Fatalf("counts=%+v", rep.Counts)
	}
	if rep.Metadata.DatasetSHA256 != strings.ToLower(validSHA) {
		t.Fatalf("sha=%q", rep.Metadata.DatasetSHA256)
	}
	if rep.Metadata.SchemaVersion != 2 {
		t.Fatalf("schema=%d", rep.Metadata.SchemaVersion)
	}
}

func TestMissingMetadata(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `DELETE FROM metadata WHERE key = 'provider'`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "required_metadata")
	if rep.Ready {
		t.Fatal("expected NOT READY")
	}
}

func TestInvalidSHA256(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE metadata SET value='not-a-sha' WHERE key='dataset_sha256'`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "required_metadata")
	assertDetailContains(t, rep, "required_metadata", "dataset_sha256")
}

func TestInvalidGeneratedAt(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE metadata SET value='yesterday' WHERE key='generated_at'`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "required_metadata")
	assertDetailContains(t, rep, "required_metadata", "generated_at")
}

func TestInvalidSchemaVersion(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE metadata SET value='0' WHERE key='schema_version'`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "required_metadata")
	assertDetailContains(t, rep, "required_metadata", "schema_version")
}

func TestMissingRequiredTable(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `DROP TABLE cities`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "required_tables")
}

func TestMissingRequiredColumn(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	path := filepath.Join(dir, "geo.db")
	db := openWriteRaw(t, path)
	mustExec(t, db, `
		CREATE TABLE countries (id INTEGER PRIMARY KEY, code TEXT NOT NULL, name TEXT NOT NULL, normalized_name TEXT NOT NULL);
		CREATE TABLE regions (id INTEGER PRIMARY KEY, country_id INTEGER NOT NULL, code TEXT, name TEXT NOT NULL, normalized_name TEXT NOT NULL);
		CREATE TABLE cities (id INTEGER PRIMARY KEY, country_id INTEGER NOT NULL, region_id INTEGER, name TEXT NOT NULL, normalized_name TEXT NOT NULL);
		CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
		CREATE INDEX idx_regions_country_id ON regions (country_id);
		CREATE INDEX idx_cities_region_id ON cities (region_id);
		CREATE INDEX idx_cities_country_id ON cities (country_id);
		CREATE INDEX idx_countries_normalized_name ON countries (normalized_name);
		CREATE INDEX idx_regions_normalized_name ON regions (normalized_name);
		CREATE INDEX idx_cities_normalized_name ON cities (normalized_name);
	`)
	mustExec(t, db, `INSERT INTO countries (id, code, name, normalized_name) VALUES (1, 'BR', 'Brazil', 'brazil')`)
	mustExec(t, db, `INSERT INTO regions (id, country_id, code, name, normalized_name) VALUES (1, 1, 'RS', 'Rio Grande do Sul', 'rio grande do sul')`)
	mustExec(t, db, `INSERT INTO cities (id, country_id, region_id, name, normalized_name) VALUES (1, 1, 1, 'Tramandai', 'tramandai')`)
	seedMetadataOnly(t, db)
	_ = db.Close()

	rep := mustInspect(t, path)
	assertFail(t, rep, "required_columns")
	assertDetailContains(t, rep, "required_columns", "timezone")
}

func TestMissingRequiredIndex(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	path := filepath.Join(dir, "geo.db")
	db := openWriteRaw(t, path)
	mustExec(t, db, `
		CREATE TABLE countries (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, normalized_name TEXT NOT NULL);
		CREATE TABLE regions (id INTEGER PRIMARY KEY, country_id INTEGER NOT NULL, code TEXT, name TEXT NOT NULL, normalized_name TEXT NOT NULL);
		CREATE TABLE cities (id INTEGER PRIMARY KEY, country_id INTEGER NOT NULL, region_id INTEGER, name TEXT NOT NULL, normalized_name TEXT NOT NULL, timezone TEXT);
		CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL);
		CREATE INDEX idx_regions_country_id ON regions (country_id);
		CREATE INDEX idx_cities_region_id ON cities (region_id);
		CREATE INDEX idx_cities_country_id ON cities (country_id);
	`)
	seedMinimal(t, db)
	_ = db.Close()

	rep := mustInspect(t, path)
	assertFail(t, rep, "required_indexes")
	assertDetailContains(t, rep, "required_indexes", "normalized_name")
}

func TestForeignKeyViolation(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `PRAGMA foreign_keys=OFF`)
		mustExec(t, db, `INSERT INTO cities (id, country_id, region_id, name, normalized_name, timezone) VALUES (999, 99999, NULL, 'Ghost', 'ghost', 'UTC')`)
		mustExec(t, db, `PRAGMA foreign_keys=ON`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "foreign_keys")
}

func TestBlankCountryCode(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `PRAGMA foreign_keys=OFF`)
		mustExec(t, db, `UPDATE countries SET code='' WHERE id=31`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "blank_country_code")
}

func TestBlankCityName(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE cities SET name='' WHERE id=1001`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "blank_city_name")
}

func TestNormalizedNameMismatch(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE cities SET normalized_name='WRONG' WHERE id=1001`)
		mustExec(t, db, `UPDATE countries SET normalized_name='brasil' WHERE id=31`)
		mustExec(t, db, `UPDATE regions SET normalized_name='rs' WHERE id=2021`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "normalized_cities")
	assertFail(t, rep, "normalized_countries")
	assertFail(t, rep, "normalized_regions")
	if rep.Ready {
		t.Fatal("expected NOT READY")
	}
}

func TestBlankNormalizedName(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE cities SET normalized_name='' WHERE id=1001`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "normalized_cities")
}

func TestCityCountryOrphan(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `PRAGMA foreign_keys=OFF`)
		mustExec(t, db, `UPDATE cities SET country_id=99999 WHERE id=1001`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "city_country_orphan")
}

func TestRegionCountryOrphan(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `PRAGMA foreign_keys=OFF`)
		mustExec(t, db, `UPDATE regions SET country_id=99999 WHERE id=2021`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "region_country_orphan")
}

func TestCityRegionCountryMismatch(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE cities SET country_id=167, region_id=2021 WHERE id=1001`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "city_region_country_mismatch")
}

func TestInvalidTimezone(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE cities SET timezone='America/NotReal' WHERE id=1001`)
	})
	rep := mustInspect(t, path)
	assertFail(t, rep, "timezone_validation")
	assertDetailContains(t, rep, "timezone_validation", "America/NotReal")
}

func TestZeroCountDatabase(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	path := filepath.Join(dir, "geo.db")
	db, err := database.OpenWrite(path)
	if err != nil {
		t.Fatal(err)
	}
	seedMetadataOnly(t, db)
	_ = db.Close()

	rep := mustInspect(t, path)
	assertFail(t, rep, "row_counts")
}

func TestWarningNormalVsStrict(t *testing.T) {
	t.Parallel()
	path := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `CREATE TABLE extra_stuff (id INTEGER PRIMARY KEY)`)
		mustExec(t, db, `UPDATE metadata SET value='99' WHERE key='schema_version'`)
	})

	normal := mustInspectOpts(t, path, false)
	if !hasWarning(normal, "unknown_tables") {
		t.Fatalf("expected unknown_tables warning: %+v", normal.Warnings)
	}
	// schema 99 fails positive integer validation? No, 99 is positive - but it's a future schema warning.
	// Wait - we also need valid SHA etc. Schema 99 is valid positive int, triggers future_schema_version warning.
	// But dataset still has valid metadata. However future schema is warning only.
	// unknown_tables warning - in normal mode Ready should still be true IF no fails.
	// schema_version 99 is valid for required_metadata check, and warns future_schema_version.

	if !normal.Ready {
		// Maybe other fails? schema 99 should pass metadata check
		t.Fatalf("normal mode should be READY with warnings only: checks=%+v warnings=%+v", normal.Checks, normal.Warnings)
	}

	strict := mustInspectOpts(t, path, true)
	if strict.Ready {
		t.Fatal("strict mode should be NOT READY when warnings exist")
	}
}

func TestJSONReportShape(t *testing.T) {
	t.Parallel()
	path := importValidFixture(t)
	rep, err := inspect.Run(context.Background(), inspect.Options{Path: path})
	if err != nil {
		t.Fatal(err)
	}
	var buf bytes.Buffer
	if err := inspect.WriteJSON(&buf, rep); err != nil {
		t.Fatal(err)
	}
	var decoded map[string]any
	if err := json.Unmarshal(buf.Bytes(), &decoded); err != nil {
		t.Fatal(err)
	}
	for _, key := range []string{"path", "fileSizeBytes", "metadata", "counts", "checks", "warnings", "ready"} {
		if _, ok := decoded[key]; !ok {
			t.Fatalf("missing key %q in %s", key, buf.String())
		}
	}
	meta := decoded["metadata"].(map[string]any)
	if _, ok := meta["datasetSha256"]; !ok {
		t.Fatal("missing datasetSha256")
	}
}

func TestHumanReportFields(t *testing.T) {
	t.Parallel()
	path := importValidFixture(t)
	rep, err := inspect.Run(context.Background(), inspect.Options{Path: path})
	if err != nil {
		t.Fatal(err)
	}
	var buf bytes.Buffer
	if err := inspect.WriteHuman(&buf, rep); err != nil {
		t.Fatal(err)
	}
	out := buf.String()
	for _, want := range []string{
		"Finba Geo database",
		"Provider",
		"Dataset version",
		"Dataset SHA-256",
		"Countries",
		"API readiness",
		"READY",
		model.Provider,
	} {
		if !strings.Contains(out, want) {
			t.Fatalf("human report missing %q:\n%s", want, out)
		}
	}
}

func TestIntegrityCorruption(t *testing.T) {
	t.Parallel()
	path := importValidFixture(t)
	// Truncate the file to corrupt the SQLite structure.
	f, err := os.OpenFile(path, os.O_WRONLY, 0)
	if err != nil {
		t.Fatal(err)
	}
	if err := f.Truncate(64); err != nil {
		t.Fatal(err)
	}
	_ = f.Close()

	_, err = inspect.Run(context.Background(), inspect.Options{Path: path})
	// Opening or integrity check should fail operationally or as a failed check.
	if err == nil {
		rep, err2 := inspect.Run(context.Background(), inspect.Options{Path: path})
		if err2 == nil && rep.Ready {
			t.Fatal("expected corruption to prevent READY")
		}
	}
}

func TestCLIArgs(t *testing.T) {
	t.Parallel()

	cfg, err := inspect.ParseArgs(nil)
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Database != "./data/geo.db" || cfg.Timeout != 30*time.Second {
		t.Fatalf("%+v", cfg)
	}

	cfg, err = inspect.ParseArgs([]string{"--database", "./x.db", "--json", "--strict", "--timeout", "5s"})
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Database != "./x.db" || !cfg.JSON || !cfg.Strict || cfg.Timeout != 5*time.Second {
		t.Fatalf("%+v", cfg)
	}

	cfg, err = inspect.ParseArgs([]string{"./pos.db"})
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Database != "./pos.db" {
		t.Fatalf("%+v", cfg)
	}

	_, err = inspect.ParseArgs([]string{"--database", "./a.db", "./b.db"})
	if err == nil || !strings.Contains(err.Error(), "not both") {
		t.Fatalf("expected conflict error, got %v", err)
	}

	_, err = inspect.ParseArgs([]string{"--timeout", "nope"})
	if err == nil {
		t.Fatal("expected invalid timeout")
	}

	_, err = inspect.ParseArgs([]string{"-h"})
	if err == nil || !strings.Contains(err.Error(), flag.ErrHelp.Error()) && err != flag.ErrHelp {
		// flag.ErrHelp
		if err != flag.ErrHelp {
			t.Fatalf("expected help, got %v", err)
		}
	}
}

func TestCLIExecuteExitCodes(t *testing.T) {
	t.Parallel()
	path := importValidFixture(t)

	var stdout, stderr bytes.Buffer
	code := inspect.Execute(context.Background(), inspect.CLIConfig{
		Database: path,
		Timeout:  30 * time.Second,
	}, &stdout, &stderr)
	if code != inspect.ExitOK {
		t.Fatalf("code=%d stderr=%s out=%s", code, stderr.String(), stdout.String())
	}
	if !strings.Contains(stdout.String(), "READY") {
		t.Fatalf("stdout=%s", stdout.String())
	}

	stdout.Reset()
	stderr.Reset()
	code = inspect.Execute(context.Background(), inspect.CLIConfig{
		Database: path,
		JSON:     true,
		Timeout:  30 * time.Second,
	}, &stdout, &stderr)
	if code != inspect.ExitOK {
		t.Fatalf("json code=%d stderr=%s", code, stderr.String())
	}
	if !json.Valid(stdout.Bytes()) {
		t.Fatalf("invalid json: %s", stdout.String())
	}

	bad := buildDB(t, func(db *sql.DB) {
		mustExec(t, db, `UPDATE metadata SET value='bad' WHERE key='dataset_sha256'`)
	})
	stdout.Reset()
	stderr.Reset()
	code = inspect.Execute(context.Background(), inspect.CLIConfig{
		Database: bad,
		Timeout:  30 * time.Second,
	}, &stdout, &stderr)
	if code != inspect.ExitNotReady {
		t.Fatalf("code=%d out=%s", code, stdout.String())
	}

	stdout.Reset()
	stderr.Reset()
	code = inspect.Execute(context.Background(), inspect.CLIConfig{
		Database: filepath.Join(t.TempDir(), "missing.db"),
		Timeout:  30 * time.Second,
	}, &stdout, &stderr)
	if code != inspect.ExitOperational {
		t.Fatalf("code=%d stderr=%s", code, stderr.String())
	}
}

func importValidFixture(t *testing.T) string {
	t.Helper()
	out := filepath.Join(t.TempDir(), "geo.db")
	_, err := importer.Run(context.Background(), importer.Options{
		InputPath:      filepath.Join("..", "..", "testdata", "cities_ok.csv"),
		OutputPath:     out,
		DatasetVersion: "fixture-v1",
		DatasetSHA256:  validSHA,
	})
	if err != nil {
		t.Fatalf("import: %v", err)
	}
	return out
}

func buildDB(t *testing.T, mutate func(*sql.DB)) string {
	t.Helper()
	path := importValidFixture(t)
	// Re-open writable to mutate.
	db, err := sql.Open("sqlite", "file:"+filepath.ToSlash(path)+"?_pragma=foreign_keys(1)")
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	mutate(db)
	return path
}

func openWriteRaw(t *testing.T, path string) *sql.DB {
	t.Helper()
	db, err := sql.Open("sqlite", "file:"+filepath.ToSlash(path)+"?_pragma=foreign_keys(1)")
	if err != nil {
		t.Fatal(err)
	}
	return db
}

func seedMinimal(t *testing.T, db *sql.DB) {
	t.Helper()
	mustExec(t, db, `INSERT INTO countries (id, code, name, normalized_name) VALUES (1, 'BR', 'Brazil', 'brazil')`)
	mustExec(t, db, `INSERT INTO regions (id, country_id, code, name, normalized_name) VALUES (1, 1, 'RS', 'Rio Grande do Sul', 'rio grande do sul')`)
	mustExec(t, db, `INSERT INTO cities (id, country_id, region_id, name, normalized_name, timezone) VALUES (1, 1, 1, 'Tramandai', 'tramandai', 'America/Sao_Paulo')`)
	seedMetadataOnly(t, db)
}

func seedMetadataOnly(t *testing.T, db *sql.DB) {
	t.Helper()
	pairs := map[string]string{
		model.MetaProvider:         model.Provider,
		model.MetaDatasetVersion:   "v1",
		model.MetaDatasetSHA256:    validSHA,
		model.MetaGeneratedAt:      time.Now().UTC().Format(time.RFC3339),
		model.MetaGeneratorVersion: model.GeneratorVersion,
		model.MetaSchemaVersion:    model.SchemaVersion,
	}
	for k, v := range pairs {
		mustExec(t, db, `INSERT INTO metadata (key, value) VALUES (?, ?)`, k, v)
	}
}

func mustExec(t *testing.T, db *sql.DB, query string, args ...any) {
	t.Helper()
	if _, err := db.Exec(query, args...); err != nil {
		t.Fatalf("exec %s: %v", query, err)
	}
}

func mustInspect(t *testing.T, path string) inspect.Report {
	t.Helper()
	return mustInspectOpts(t, path, false)
}

func mustInspectOpts(t *testing.T, path string, strict bool) inspect.Report {
	t.Helper()
	rep, err := inspect.Run(context.Background(), inspect.Options{Path: path, Strict: strict})
	if err != nil {
		t.Fatal(err)
	}
	return rep
}

func assertFail(t *testing.T, rep inspect.Report, name string) {
	t.Helper()
	for _, c := range rep.Checks {
		if c.Name == name {
			if c.Status != inspect.StatusFail {
				t.Fatalf("check %s status=%s details=%v", name, c.Status, c.Details)
			}
			return
		}
	}
	t.Fatalf("check %s not found in %+v", name, rep.Checks)
}

func assertDetailContains(t *testing.T, rep inspect.Report, name, substr string) {
	t.Helper()
	for _, c := range rep.Checks {
		if c.Name != name {
			continue
		}
		for _, d := range c.Details {
			if strings.Contains(d, substr) {
				return
			}
		}
		if strings.Contains(c.Message, substr) {
			return
		}
		t.Fatalf("check %s missing detail %q: %+v", name, substr, c)
	}
	t.Fatalf("check %s not found", name)
}

func hasWarning(rep inspect.Report, name string) bool {
	for _, w := range rep.Warnings {
		if w.Name == name {
			return true
		}
	}
	return false
}
