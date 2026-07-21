package importer_test

import (
	"context"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/model"
	"finba.se/geo/internal/repository"
)

func TestImportSuccessAndMetadata(t *testing.T) {
	t.Parallel()
	out := filepath.Join(t.TempDir(), "geo.db")
	res, err := importer.Run(context.Background(), importer.Options{
		InputPath:      filepath.Join("..", "..", "testdata", "cities_ok.csv"),
		OutputPath:     out,
		DatasetVersion: "test-v1",
		DatasetSHA256:  "deadbeef",
	})
	if err != nil {
		t.Fatalf("import: %v", err)
	}
	if res.Countries != 3 || res.Regions != 5 || res.Cities != 7 {
		t.Fatalf("counts: countries=%d regions=%d cities=%d", res.Countries, res.Regions, res.Cities)
	}

	db, err := database.OpenReadOnly(out)
	if err != nil {
		t.Fatalf("open: %v", err)
	}
	defer db.Close()

	if err := database.CheckIntegrity(context.Background(), db); err != nil {
		t.Fatalf("integrity: %v", err)
	}

	repo := repository.New(db)
	ver, err := repo.Version(context.Background())
	if err != nil {
		t.Fatalf("version: %v", err)
	}
	if ver.Provider != model.Provider {
		t.Fatalf("provider: %q", ver.Provider)
	}
	if ver.DatasetVersion != "test-v1" || ver.DatasetSHA256 != "deadbeef" {
		t.Fatalf("dataset meta: %+v", ver)
	}
	if ver.SchemaVersion != model.SchemaVersion || ver.GeneratorVersion != model.GeneratorVersion {
		t.Fatalf("versions: %+v", ver)
	}
	if _, err := time.Parse(time.RFC3339, ver.GeneratedAt); err != nil {
		t.Fatalf("generated_at: %v", err)
	}
}

func TestMissingHeaders(t *testing.T) {
	t.Parallel()
	path := writeCSV(t, "id,name,country_id\n1,X,1\n")
	_, err := runImport(t, path)
	if err == nil || !strings.Contains(err.Error(), "missing required CSV headers") {
		t.Fatalf("expected missing headers error, got %v", err)
	}
}

func TestImportNormalizedNames(t *testing.T) {
	t.Parallel()
	out := filepath.Join(t.TempDir(), "geo.db")
	_, err := importer.Run(context.Background(), importer.Options{
		InputPath:      filepath.Join("..", "..", "testdata", "cities_ok.csv"),
		OutputPath:     out,
		DatasetVersion: "test-v1",
		DatasetSHA256:  "deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef",
	})
	if err != nil {
		t.Fatal(err)
	}
	db, err := database.OpenReadOnly(out)
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()

	var name, norm string
	if err := db.QueryRow(`SELECT name, normalized_name FROM cities WHERE id=1001`).Scan(&name, &norm); err != nil {
		t.Fatal(err)
	}
	if name != "Tramandaí" || norm != "tramandai" {
		t.Fatalf("city name=%q norm=%q", name, norm)
	}
	if err := db.QueryRow(`SELECT name, normalized_name FROM cities WHERE id=1004`).Scan(&name, &norm); err != nil {
		t.Fatal(err)
	}
	if name != "São Paulo" || norm != "sao paulo" {
		t.Fatalf("city name=%q norm=%q", name, norm)
	}
	if err := db.QueryRow(`SELECT name, normalized_name FROM regions WHERE id=2022`).Scan(&name, &norm); err != nil {
		t.Fatal(err)
	}
	if name != "São Paulo" || norm != "sao paulo" {
		t.Fatalf("region name=%q norm=%q", name, norm)
	}
	var ver string
	if err := db.QueryRow(`SELECT value FROM metadata WHERE key='schema_version'`).Scan(&ver); err != nil {
		t.Fatal(err)
	}
	if ver != model.SchemaVersion {
		t.Fatalf("schema=%s", ver)
	}
}

func TestCountryDedupAndConflict(t *testing.T) {
	t.Parallel()

	ok := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n"+
		"2,CityB,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n")
	res, err := runImport(t, ok)
	if err != nil {
		t.Fatalf("dedup import: %v", err)
	}
	if res.Countries != 1 || res.Regions != 1 {
		t.Fatalf("unexpected counts: %+v", res)
	}

	conflict := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n"+
		"2,CityB,10,AA,RegionA,100,BR,Brasil,,,America/Sao_Paulo,\n")
	_, err = runImport(t, conflict)
	if err == nil || !strings.Contains(err.Error(), "conflicting country") {
		t.Fatalf("expected country conflict, got %v", err)
	}
}

func TestRegionDedupAndConflict(t *testing.T) {
	t.Parallel()

	ok := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n"+
		"2,CityB,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n")
	res, err := runImport(t, ok)
	if err != nil {
		t.Fatalf("region dedup: %v", err)
	}
	if res.Regions != 1 {
		t.Fatalf("regions=%d", res.Regions)
	}

	conflict := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n"+
		"2,CityB,10,AA,RegionB,100,BR,Brazil,,,America/Sao_Paulo,\n")
	_, err = runImport(t, conflict)
	if err == nil || !strings.Contains(err.Error(), "conflicting region") {
		t.Fatalf("expected region conflict, got %v", err)
	}
}

func TestInvalidTimezone(t *testing.T) {
	t.Parallel()
	path := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,Not/A_Zone,\n")
	_, err := runImport(t, path)
	if err == nil || !strings.Contains(err.Error(), "invalid timezone") {
		t.Fatalf("expected timezone error, got %v", err)
	}
}

func TestBlankTimezoneAllowed(t *testing.T) {
	t.Parallel()
	path := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,,\n")
	res, err := runImport(t, path)
	if err != nil {
		t.Fatalf("blank tz: %v", err)
	}
	if res.Cities != 1 {
		t.Fatalf("cities=%d", res.Cities)
	}
}

func TestInvalidUTF8(t *testing.T) {
	t.Parallel()
	// Invalid UTF-8 byte in city name.
	raw := header() + "1,City\xffA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n"
	path := filepath.Join(t.TempDir(), "bad.csv")
	if err := os.WriteFile(path, []byte(raw), 0o644); err != nil {
		t.Fatal(err)
	}
	_, err := runImport(t, path)
	if err == nil || !strings.Contains(err.Error(), "invalid UTF-8") {
		t.Fatalf("expected utf-8 error, got %v", err)
	}
}

func TestBlankCountryCodeAndCityName(t *testing.T) {
	t.Parallel()

	blankCode := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,,Brazil,,,America/Sao_Paulo,\n")
	_, err := runImport(t, blankCode)
	if err == nil || !strings.Contains(err.Error(), "blank country code") {
		t.Fatalf("expected blank country code, got %v", err)
	}

	blankCountryName := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,,,,America/Sao_Paulo,\n")
	_, err = runImport(t, blankCountryName)
	if err == nil || !strings.Contains(err.Error(), "blank country name") {
		t.Fatalf("expected blank country name, got %v", err)
	}

	blankCity := writeCSV(t, header()+
		"1,,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n")
	_, err = runImport(t, blankCity)
	if err == nil || !strings.Contains(err.Error(), "blank city name") {
		t.Fatalf("expected blank city name, got %v", err)
	}
}

func TestRegionCountryMismatch(t *testing.T) {
	t.Parallel()
	// First establish region 10 under country 100, then city in country 200 referencing region 10.
	path := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n"+
		"2,CityB,10,AA,RegionA,200,PT,Portugal,,,Europe/Lisbon,\n")
	_, err := runImport(t, path)
	// Second row conflicts on region (different country_id) before city mismatch check.
	if err == nil || !(strings.Contains(err.Error(), "conflicting region") || strings.Contains(err.Error(), "belongs to country")) {
		t.Fatalf("expected region/country mismatch, got %v", err)
	}
}

func TestInvalidIntegerID(t *testing.T) {
	t.Parallel()
	path := writeCSV(t, header()+
		"abc,CityA,10,AA,RegionA,100,BR,Brazil,,,America/Sao_Paulo,\n")
	_, err := runImport(t, path)
	if err == nil || !strings.Contains(err.Error(), "invalid city id") {
		t.Fatalf("expected invalid id error, got %v", err)
	}
}

func TestDoesNotLeaveTempOnFailure(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	out := filepath.Join(dir, "geo.db")
	path := writeCSV(t, header()+
		"1,CityA,10,AA,RegionA,100,BR,Brazil,,,Not/A_Zone,\n")
	_, err := importer.Run(context.Background(), importer.Options{
		InputPath:      path,
		OutputPath:     out,
		DatasetVersion: "v",
		DatasetSHA256:  "x",
	})
	if err == nil {
		t.Fatal("expected failure")
	}
	if _, err := os.Stat(out + ".tmp"); !os.IsNotExist(err) {
		t.Fatalf("temp file should be removed, stat err=%v", err)
	}
	if _, err := os.Stat(out); !os.IsNotExist(err) {
		t.Fatalf("output should not exist, stat err=%v", err)
	}
}

func header() string {
	return "id,name,state_id,state_code,state_name,country_id,country_code,country_name,latitude,longitude,timezone,wikiDataId\n"
}

func writeCSV(t *testing.T, content string) string {
	t.Helper()
	path := filepath.Join(t.TempDir(), "cities.csv")
	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatal(err)
	}
	return path
}

func runImport(t *testing.T, input string) (importer.Result, error) {
	t.Helper()
	out := filepath.Join(t.TempDir(), "geo.db")
	return importer.Run(context.Background(), importer.Options{
		InputPath:      input,
		OutputPath:     out,
		DatasetVersion: "test",
		DatasetSHA256:  "sha",
	})
}
