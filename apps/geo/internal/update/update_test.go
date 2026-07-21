package update_test

import (
	"bytes"
	"compress/gzip"
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/download"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/model"
	"finba.se/geo/internal/release"
	"finba.se/geo/internal/update"

	_ "modernc.org/sqlite"
)

const validSHA = "0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef"

func TestAlreadyUpToDate(t *testing.T) {
	dir := t.TempDir()
	dbPath := filepath.Join(dir, "geo.db")
	seedDB(t, dbPath, "v1.0.0")

	var downloaded bool
	srv := newReleaseServer(t, "v1.0.0", mustGzipFixture(t), func(w http.ResponseWriter, r *http.Request) {
		downloaded = true
		http.NotFound(w, r)
	})
	defer srv.Close()

	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)

	res, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Database:       dbPath,
		Workspace:      filepath.Join(dir, "work"),
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
	})
	if err != nil {
		t.Fatal(err)
	}
	if res.Updated || res.Reason != "already_up_to_date" || res.Version != "v1.0.0" {
		t.Fatalf("%+v", res)
	}
	if downloaded {
		t.Fatal("should not download when already current")
	}
}

func TestForceUpdatePublishes(t *testing.T) {
	dir := t.TempDir()
	dbPath := filepath.Join(dir, "geo.db")
	seedDB(t, dbPath, "v1.0.0")

	gz := mustGzipFixture(t)
	srv := newReleaseServer(t, "v1.0.0", gz, nil)
	defer srv.Close()

	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)

	work := filepath.Join(dir, "work")
	res, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Force:          true,
		Database:       dbPath,
		Workspace:      work,
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
	})
	if err != nil {
		t.Fatal(err)
	}
	if !res.Updated || !res.Published || !res.Validated || !res.Downloaded {
		t.Fatalf("%+v", res)
	}
	if _, err := os.Stat(work); !os.IsNotExist(err) {
		t.Fatalf("workspace should be cleaned: %v", err)
	}
	if readVersion(t, dbPath) != "v1.0.0" {
		t.Fatal("unexpected version")
	}
	db, err := database.OpenReadOnly(dbPath)
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	var schema, norm string
	if err := db.QueryRow(`SELECT value FROM metadata WHERE key='schema_version'`).Scan(&schema); err != nil {
		t.Fatal(err)
	}
	if schema != model.SchemaVersion {
		t.Fatalf("schema=%s want=%s", schema, model.SchemaVersion)
	}
	if err := db.QueryRow(`SELECT normalized_name FROM cities WHERE name='Tramandaí'`).Scan(&norm); err != nil {
		t.Fatal(err)
	}
	if norm != "tramandai" {
		t.Fatalf("normalized_name=%q", norm)
	}
	for _, table := range []string{"countries", "regions", "cities"} {
		var n int
		q := `SELECT COUNT(*) FROM pragma_table_info(?) WHERE name='normalized_name'`
		if err := db.QueryRow(q, table).Scan(&n); err != nil || n != 1 {
			t.Fatalf("table %s missing normalized_name: n=%d err=%v", table, n, err)
		}
	}
}

func TestKeepWorkspace(t *testing.T) {
	dir := t.TempDir()
	dbPath := filepath.Join(dir, "geo.db")
	gz := mustGzipFixture(t)
	srv := newReleaseServer(t, "v2.0.0", gz, nil)
	defer srv.Close()

	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)
	work := filepath.Join(dir, "work")

	res, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Database:       dbPath,
		Workspace:      work,
		KeepWorkspace:  true,
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
	})
	if err != nil || !res.Updated {
		t.Fatalf("res=%+v err=%v", res, err)
	}
	if _, err := os.Stat(filepath.Join(work, "cities.csv")); err != nil {
		t.Fatalf("expected kept workspace csv: %v", err)
	}
}

func TestDownloadFailure(t *testing.T) {
	dir := t.TempDir()
	gz := mustGzipFixture(t)
	srv := newReleaseServer(t, "v9", gz, func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "nope", http.StatusBadGateway)
	})
	defer srv.Close()
	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)

	_, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Database:       filepath.Join(dir, "geo.db"),
		Workspace:      filepath.Join(dir, "work"),
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
	})
	if err == nil || !strings.Contains(err.Error(), "download") {
		t.Fatalf("err=%v", err)
	}
	if _, err := os.Stat(filepath.Join(dir, "geo.db")); !os.IsNotExist(err) {
		t.Fatal("production db should remain absent")
	}
}

func TestExtractFailure(t *testing.T) {
	dir := t.TempDir()
	bad := []byte("not-gzip-content")
	srv := newReleaseServer(t, "v9", bad, nil)
	defer srv.Close()
	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)

	_, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Database:       filepath.Join(dir, "missing.db"),
		Workspace:      filepath.Join(dir, "work"),
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
	})
	if err == nil || !strings.Contains(err.Error(), "extract") {
		t.Fatalf("err=%v", err)
	}
}

func TestImportFailure(t *testing.T) {
	dir := t.TempDir()
	badCSV := []byte("id,name\n1,x\n")
	var buf bytes.Buffer
	zw := gzip.NewWriter(&buf)
	_, _ = zw.Write(badCSV)
	_ = zw.Close()

	srv := newReleaseServer(t, "v9", buf.Bytes(), nil)
	defer srv.Close()
	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)

	_, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Database:       filepath.Join(dir, "geo.db"),
		Workspace:      filepath.Join(dir, "work"),
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
	})
	if err == nil || !strings.Contains(err.Error(), "import") {
		t.Fatalf("err=%v", err)
	}
}

func TestInspectFailureLeavesProductionUntouched(t *testing.T) {
	dir := t.TempDir()
	dbPath := filepath.Join(dir, "geo.db")
	seedDB(t, dbPath, "v-old")
	before, _ := os.ReadFile(dbPath)

	gz := mustGzipFixture(t)
	srv := newReleaseServer(t, "v-new", gz, nil)
	defer srv.Close()
	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)

	_, err := update.Run(context.Background(), update.Options{
		Owner:          "acme",
		Repo:           "geo",
		Database:       dbPath,
		Workspace:      filepath.Join(dir, "work"),
		LockPath:       filepath.Join(dir, "update.lock"),
		ReleaseClient:  rel,
		DownloadClient: download.NewWithHTTPClient(srv.Client()),
		Timeout:        time.Minute,
		AfterImport: func(candidate string) error {
			db, err := sql.Open("sqlite", "file:"+filepath.ToSlash(candidate)+"?_pragma=foreign_keys(1)")
			if err != nil {
				return err
			}
			defer db.Close()
			_, err = db.Exec(`UPDATE countries SET code='' WHERE id=31`)
			return err
		},
	})
	if !errors.Is(err, update.ErrNotReady) {
		t.Fatalf("err=%v", err)
	}
	after, _ := os.ReadFile(dbPath)
	if !bytes.Equal(before, after) {
		t.Fatal("production database changed after inspect failure")
	}
}

func TestLockFile(t *testing.T) {
	dir := t.TempDir()
	lockPath := filepath.Join(dir, "update.lock")
	if err := os.WriteFile(lockPath, []byte("held"), 0o644); err != nil {
		t.Fatal(err)
	}
	_, err := update.Run(context.Background(), update.Options{
		Owner:     "acme",
		Repo:      "geo",
		Database:  filepath.Join(dir, "geo.db"),
		Workspace: filepath.Join(dir, "work"),
		LockPath:  lockPath,
		Timeout:   time.Minute,
	})
	if !errors.Is(err, update.ErrAlreadyLocked) {
		t.Fatalf("err=%v", err)
	}
}

func TestJSONShapeAlreadyCurrent(t *testing.T) {
	dir := t.TempDir()
	dbPath := filepath.Join(dir, "geo.db")
	seedDB(t, dbPath, "v1.0.0")

	srv := newReleaseServer(t, "v1.0.0", mustGzipFixture(t), func(w http.ResponseWriter, r *http.Request) {
		t.Fatal("should not download")
	})
	defer srv.Close()
	rel := release.NewWithHTTPClient("acme", "geo", srv.Client())
	rel.SetBaseURL(srv.URL)
	res, err := update.Run(context.Background(), update.Options{
		Owner:         "acme",
		Repo:          "geo",
		Database:      dbPath,
		Workspace:     filepath.Join(dir, "work"),
		LockPath:      filepath.Join(dir, "update.lock"),
		ReleaseClient: rel,
		Timeout:       time.Minute,
	})
	if err != nil {
		t.Fatal(err)
	}
	raw, _ := json.Marshal(res)
	var decoded map[string]any
	if err := json.Unmarshal(raw, &decoded); err != nil {
		t.Fatal(err)
	}
	if decoded["updated"] != false || decoded["reason"] != "already_up_to_date" {
		t.Fatalf("%s", raw)
	}
}

func TestParseArgsAndOperationalExit(t *testing.T) {
	_, err := update.ParseArgs([]string{"--timeout", "bad"})
	if err == nil {
		t.Fatal("expected timeout error")
	}
	cfg, err := update.ParseArgs(nil)
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Asset != "csv-cities.csv.gz" || cfg.Timeout != 10*time.Minute {
		t.Fatalf("%+v", cfg)
	}

	dir := t.TempDir()
	var stdout, stderr bytes.Buffer
	code := update.Execute(context.Background(), update.CLIConfig{
		Owner:     "acme",
		Repo:      "missing",
		Database:  filepath.Join(dir, "nope.db"),
		Workspace: filepath.Join(dir, "work2"),
		LockPath:  filepath.Join(dir, "lock2"),
		Timeout:   100 * time.Millisecond,
		JSON:      true,
	}, &stdout, &stderr)
	if code != update.ExitOperational {
		t.Fatalf("code=%d stderr=%s", code, stderr.String())
	}
}

func seedDB(t *testing.T, path, version string) {
	t.Helper()
	_, err := importer.Run(context.Background(), importer.Options{
		InputPath:      filepath.Join("..", "..", "testdata", "cities_ok.csv"),
		OutputPath:     path,
		DatasetVersion: version,
		DatasetSHA256:  validSHA,
	})
	if err != nil {
		t.Fatalf("seed: %v", err)
	}
}

func readVersion(t *testing.T, path string) string {
	t.Helper()
	db, err := database.OpenReadOnly(path)
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	var v string
	if err := db.QueryRow(`SELECT value FROM metadata WHERE key=?`, model.MetaDatasetVersion).Scan(&v); err != nil {
		t.Fatal(err)
	}
	return v
}

func mustGzipFixture(t *testing.T) []byte {
	t.Helper()
	raw, err := os.ReadFile(filepath.Join("..", "..", "testdata", "cities_ok.csv"))
	if err != nil {
		t.Fatal(err)
	}
	var buf bytes.Buffer
	zw := gzip.NewWriter(&buf)
	if _, err := zw.Write(raw); err != nil {
		t.Fatal(err)
	}
	if err := zw.Close(); err != nil {
		t.Fatal(err)
	}
	return buf.Bytes()
}

func newReleaseServer(t *testing.T, tag string, assetBody []byte, assetHandler http.HandlerFunc) *httptest.Server {
	t.Helper()
	return httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch {
		case strings.HasSuffix(r.URL.Path, "/releases/latest"),
			strings.Contains(r.URL.Path, "/releases/tags/"):
			assetURL := "http://" + r.Host + "/asset/" + tag
			_ = json.NewEncoder(w).Encode(map[string]any{
				"tag_name":     tag,
				"name":         tag,
				"draft":        false,
				"prerelease":   false,
				"published_at": "2024-01-02T03:04:05Z",
				"assets": []map[string]any{
					{
						"id":                   1,
						"name":                 "csv-cities.csv.gz",
						"content_type":         "application/gzip",
						"size":                 int64(len(assetBody)),
						"browser_download_url": assetURL,
					},
				},
			})
		case strings.HasPrefix(r.URL.Path, "/asset/"):
			if assetHandler != nil {
				assetHandler(w, r)
				return
			}
			w.Header().Set("Content-Type", "application/gzip")
			_, _ = w.Write(assetBody)
		default:
			http.NotFound(w, r)
		}
	}))
}
