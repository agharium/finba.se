package repository_test

import (
	"context"
	"path/filepath"
	"testing"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/repository"
)

func TestRepositoryQueries(t *testing.T) {
	t.Parallel()
	dbPath := importFixture(t)
	db, err := database.OpenReadOnly(dbPath)
	if err != nil {
		t.Fatal(err)
	}
	defer db.Close()
	repo := repository.New(db)
	ctx := context.Background()

	countries, err := repo.ListCountries(ctx)
	if err != nil {
		t.Fatal(err)
	}
	if len(countries) != 3 {
		t.Fatalf("countries=%d", len(countries))
	}
	if countries[0].Name != "Brazil" && countries[1].Name != "Brazil" {
		t.Fatalf("expected Brazil in %+v", countries)
	}

	br, err := repo.GetCountryByCode(ctx, "br")
	if err != nil {
		t.Fatal(err)
	}
	if br.Code != "BR" || br.ID != 31 {
		t.Fatalf("country: %+v", br)
	}

	byID, err := repo.GetCountryByID(ctx, 31)
	if err != nil {
		t.Fatal(err)
	}
	if byID != br {
		t.Fatalf("GetCountryByID=%+v want %+v", byID, br)
	}

	resolved, err := repo.GetCountry(ctx, "31")
	if err != nil || resolved != br {
		t.Fatalf("GetCountry(id)=%+v err=%v", resolved, err)
	}
	resolved, err = repo.GetCountry(ctx, "BR")
	if err != nil || resolved != br {
		t.Fatalf("GetCountry(code)=%+v err=%v", resolved, err)
	}

	regions, err := repo.ListRegionsByCountryID(ctx, br.ID)
	if err != nil {
		t.Fatal(err)
	}
	if len(regions) != 3 {
		t.Fatalf("regions=%d %+v", len(regions), regions)
	}

	region, err := repo.GetRegion(ctx, 2021)
	if err != nil {
		t.Fatal(err)
	}
	if region.Name != "Rio Grande do Sul" {
		t.Fatalf("region: %+v", region)
	}

	cities, err := repo.ListCitiesByRegionID(ctx, 2021)
	if err != nil {
		t.Fatal(err)
	}
	if len(cities) != 2 {
		t.Fatalf("cities=%d", len(cities))
	}

	detail, err := repo.GetCityDetail(ctx, 1001)
	if err != nil {
		t.Fatal(err)
	}
	if detail.Name != "Tramandaí" || detail.Country.Code != "BR" || detail.Region == nil || detail.Region.Code != "RS" {
		t.Fatalf("detail: %+v", detail)
	}

	found, err := repo.SearchCities(ctx, "tram", 20)
	if err != nil {
		t.Fatal(err)
	}
	if len(found) != 1 || found[0].ID != 1001 || found[0].Name != "Tramandaí" {
		t.Fatalf("search: %+v", found)
	}

	unaccented, err := repo.SearchCities(ctx, "tramandai", 20)
	if err != nil {
		t.Fatal(err)
	}
	if len(unaccented) != 1 || unaccented[0].Name != "Tramandaí" {
		t.Fatalf("unaccented city search: %+v", unaccented)
	}
	sao, err := repo.SearchCities(ctx, "sao paulo", 20)
	if err != nil {
		t.Fatal(err)
	}
	if len(sao) != 1 || sao[0].Name != "São Paulo" {
		t.Fatalf("sao paulo: %+v", sao)
	}
	countriesFound, err := repo.SearchCountries(ctx, "brazil", 20)
	if err != nil {
		t.Fatal(err)
	}
	if len(countriesFound) != 1 || countriesFound[0].Name != "Brazil" {
		t.Fatalf("countries: %+v", countriesFound)
	}
	regionsFound, err := repo.SearchRegions(ctx, "rio grande", 20)
	if err != nil {
		t.Fatal(err)
	}
	if len(regionsFound) != 1 || regionsFound[0].Name != "Rio Grande do Sul" {
		t.Fatalf("regions: %+v", regionsFound)
	}
	empty, err := repo.SearchCities(ctx, "   ", 20)
	if err != nil || len(empty) != 0 {
		t.Fatalf("empty search: %+v err=%v", empty, err)
	}

	_, err = repo.GetCity(ctx, 999999)
	if err != repository.ErrNotFound {
		t.Fatalf("expected not found, got %v", err)
	}
	_, err = repo.GetCountryByCode(ctx, "ZZ")
	if err != repository.ErrNotFound {
		t.Fatalf("expected not found country, got %v", err)
	}
}

func importFixture(t *testing.T) string {
	t.Helper()
	out := filepath.Join(t.TempDir(), "geo.db")
	_, err := importer.Run(context.Background(), importer.Options{
		InputPath:      filepath.Join("..", "..", "testdata", "cities_ok.csv"),
		OutputPath:     out,
		DatasetVersion: "fixture",
		DatasetSHA256:  "fixture-sha",
	})
	if err != nil {
		t.Fatalf("import fixture: %v", err)
	}
	return out
}
