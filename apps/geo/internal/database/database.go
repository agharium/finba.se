// Package database opens and validates the Geo SQLite database.
package database

import (
	"context"
	"database/sql"
	"embed"
	"fmt"
	"net/url"
	"os"
	"path/filepath"
	"strings"

	"finba.se/geo/internal/model"

	_ "modernc.org/sqlite"
)

//go:embed schema.sql
var schemaFS embed.FS

// RequiredTables are the catalog tables every Geo database must contain.
var RequiredTables = []string{"countries", "regions", "cities", "metadata"}

// RequiredMetadataKeys are metadata rows required for API startup and inspection.
var RequiredMetadataKeys = []string{
	model.MetaProvider,
	model.MetaDatasetVersion,
	model.MetaDatasetSHA256,
	model.MetaGeneratedAt,
	model.MetaGeneratorVersion,
	model.MetaSchemaVersion,
}

// OpenReadOnly opens an existing Geo database for read-only use.
// It does not verify schema or metadata; callers that need that should call VerifySchema.
func OpenReadOnly(path string) (*sql.DB, error) {
	if _, err := os.Stat(path); err != nil {
		if os.IsNotExist(err) {
			return nil, fmt.Errorf("geo database not found at %s", path)
		}
		return nil, fmt.Errorf("stat geo database: %w", err)
	}

	db, err := sql.Open("sqlite", sqliteDSN(path, true))
	if err != nil {
		return nil, fmt.Errorf("open sqlite: %w", err)
	}
	db.SetMaxOpenConns(1)

	if err := db.Ping(); err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("ping sqlite: %w", err)
	}

	return db, nil
}

// OpenReadOnlyVerified opens a read-only database and verifies required tables and metadata.
// Used by the API at startup.
func OpenReadOnlyVerified(path string) (*sql.DB, error) {
	db, err := OpenReadOnly(path)
	if err != nil {
		return nil, err
	}
	if err := VerifySchema(context.Background(), db); err != nil {
		_ = db.Close()
		return nil, err
	}
	return db, nil
}

// OpenWrite creates a new empty database file and applies the schema.
// The caller must close the DB. Path should be a temporary file path.
func OpenWrite(path string) (*sql.DB, error) {
	if _, err := os.Stat(path); err == nil {
		return nil, fmt.Errorf("refusing to overwrite existing file %s", path)
	} else if !os.IsNotExist(err) {
		return nil, fmt.Errorf("stat database path: %w", err)
	}

	db, err := sql.Open("sqlite", sqliteDSN(path, false))
	if err != nil {
		return nil, fmt.Errorf("open sqlite: %w", err)
	}
	db.SetMaxOpenConns(1)

	if err := db.Ping(); err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("ping sqlite: %w", err)
	}

	schema, err := schemaFS.ReadFile("schema.sql")
	if err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("read embedded schema: %w", err)
	}

	if _, err := db.Exec(string(schema)); err != nil {
		_ = db.Close()
		return nil, fmt.Errorf("apply schema: %w", err)
	}

	return db, nil
}

// VerifySchema ensures required tables, columns, and metadata keys exist.
func VerifySchema(ctx context.Context, db *sql.DB) error {
	if err := VerifyRequiredTables(ctx, db); err != nil {
		return err
	}
	if err := VerifyRequiredColumns(ctx, db); err != nil {
		return err
	}
	if _, err := ReadRequiredMetadata(ctx, db); err != nil {
		return err
	}
	return nil
}

// VerifyRequiredColumns ensures the current schema columns are present.
func VerifyRequiredColumns(ctx context.Context, db *sql.DB) error {
	required := map[string][]string{
		"countries": {"id", "code", "name", "normalized_name"},
		"regions":   {"id", "country_id", "code", "name", "normalized_name"},
		"cities":    {"id", "country_id", "region_id", "name", "normalized_name", "timezone"},
		"metadata":  {"key", "value"},
	}
	for table, cols := range required {
		info, err := tableColumnSet(ctx, db, table)
		if err != nil {
			return fmt.Errorf("verify columns for %q: %w", table, err)
		}
		for _, col := range cols {
			if _, ok := info[col]; !ok {
				return fmt.Errorf("missing required column %s.%s (database schema is outdated; rebuild with the importer/updater)", table, col)
			}
		}
	}
	return nil
}

func tableColumnSet(ctx context.Context, db *sql.DB, table string) (map[string]struct{}, error) {
	rows, err := db.QueryContext(ctx, fmt.Sprintf(`PRAGMA table_info("%s")`, strings.ReplaceAll(table, `"`, `""`)))
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	out := make(map[string]struct{})
	for rows.Next() {
		var cid, notnull, pk int
		var name, ctype string
		var dflt sql.NullString
		if err := rows.Scan(&cid, &name, &ctype, &notnull, &dflt, &pk); err != nil {
			return nil, err
		}
		out[strings.ToLower(name)] = struct{}{}
	}
	return out, rows.Err()
}

// VerifyRequiredTables fails when any required catalog table is missing.
func VerifyRequiredTables(ctx context.Context, db *sql.DB) error {
	for _, table := range RequiredTables {
		ok, err := TableExists(ctx, db, table)
		if err != nil {
			return fmt.Errorf("verify table %q: %w", table, err)
		}
		if !ok {
			return fmt.Errorf("missing required table %q", table)
		}
	}
	return nil
}

// TableExists reports whether a user table exists.
func TableExists(ctx context.Context, db *sql.DB, table string) (bool, error) {
	var name string
	err := db.QueryRowContext(ctx,
		`SELECT name FROM sqlite_master WHERE type='table' AND name=?`, table,
	).Scan(&name)
	if err == sql.ErrNoRows {
		return false, nil
	}
	if err != nil {
		return false, err
	}
	return true, nil
}

// ReadRequiredMetadata returns required metadata values or an error listing problems.
func ReadRequiredMetadata(ctx context.Context, db *sql.DB) (map[string]string, error) {
	values := make(map[string]string, len(RequiredMetadataKeys))
	var problems []string
	for _, key := range RequiredMetadataKeys {
		var value string
		err := db.QueryRowContext(ctx, `SELECT value FROM metadata WHERE key = ?`, key).Scan(&value)
		if err == sql.ErrNoRows {
			problems = append(problems, fmt.Sprintf("missing required metadata key %q", key))
			continue
		}
		if err != nil {
			return nil, fmt.Errorf("verify metadata %q: %w", key, err)
		}
		if strings.TrimSpace(value) == "" {
			problems = append(problems, fmt.Sprintf("metadata key %q is empty", key))
			continue
		}
		values[key] = value
	}
	if len(problems) > 0 {
		return nil, fmt.Errorf("%s", strings.Join(problems, "; "))
	}
	return values, nil
}

// CheckIntegrity runs SQLite integrity and foreign-key checks.
func CheckIntegrity(ctx context.Context, db *sql.DB) error {
	if err := IntegrityCheck(ctx, db); err != nil {
		return err
	}
	return ForeignKeyCheck(ctx, db)
}

// IntegrityCheck runs PRAGMA integrity_check and requires a single "ok" result.
func IntegrityCheck(ctx context.Context, db *sql.DB) error {
	var integrity string
	if err := db.QueryRowContext(ctx, `PRAGMA integrity_check`).Scan(&integrity); err != nil {
		return fmt.Errorf("integrity_check: %w", err)
	}
	if integrity != "ok" {
		return fmt.Errorf("integrity_check failed: %s", integrity)
	}
	return nil
}

// ForeignKeyCheck runs PRAGMA foreign_key_check and requires zero rows.
func ForeignKeyCheck(ctx context.Context, db *sql.DB) error {
	rows, err := db.QueryContext(ctx, `PRAGMA foreign_key_check`)
	if err != nil {
		return fmt.Errorf("foreign_key_check: %w", err)
	}
	defer rows.Close()

	if rows.Next() {
		var table string
		var rowid int64
		var parent string
		var fkid int64
		if err := rows.Scan(&table, &rowid, &parent, &fkid); err != nil {
			return fmt.Errorf("scan foreign_key_check: %w", err)
		}
		return fmt.Errorf("foreign_key_check failed: table=%s rowid=%d parent=%s fkid=%d", table, rowid, parent, fkid)
	}
	if err := rows.Err(); err != nil {
		return fmt.Errorf("foreign_key_check rows: %w", err)
	}
	return nil
}

func sqliteDSN(path string, readOnly bool) string {
	abs, err := filepath.Abs(path)
	if err != nil {
		abs = path
	}
	// Prefer a simple file: path so Windows drive letters are not parsed as hosts.
	q := url.Values{}
	q.Set("_pragma", "foreign_keys(1)")
	if readOnly {
		q.Set("mode", "ro")
	}
	return "file:" + filepath.ToSlash(abs) + "?" + q.Encode()
}
