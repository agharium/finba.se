// Package repository provides read access to the Geo SQLite catalog.
package repository

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"strconv"
	"strings"

	"finba.se/geo/internal/model"
	"finba.se/geo/internal/textutil"
)

// ErrNotFound indicates a requested resource does not exist.
var ErrNotFound = errors.New("not found")

// Geo is a read-only repository over the Geo database.
type Geo struct {
	db *sql.DB
}

// New creates a Geo repository.
func New(db *sql.DB) *Geo {
	return &Geo{db: db}
}

// Version returns dataset metadata.
func (g *Geo) Version(ctx context.Context) (model.Version, error) {
	keys := []string{
		model.MetaProvider,
		model.MetaDatasetVersion,
		model.MetaDatasetSHA256,
		model.MetaGeneratedAt,
		model.MetaGeneratorVersion,
		model.MetaSchemaVersion,
	}
	values := make(map[string]string, len(keys))
	for _, key := range keys {
		var value string
		err := g.db.QueryRowContext(ctx, `SELECT value FROM metadata WHERE key = ?`, key).Scan(&value)
		if err != nil {
			return model.Version{}, fmt.Errorf("read metadata %q: %w", key, err)
		}
		values[key] = value
	}
	return model.Version{
		Provider:         values[model.MetaProvider],
		DatasetVersion:   values[model.MetaDatasetVersion],
		DatasetSHA256:    values[model.MetaDatasetSHA256],
		GeneratedAt:      values[model.MetaGeneratedAt],
		GeneratorVersion: values[model.MetaGeneratorVersion],
		SchemaVersion:    values[model.MetaSchemaVersion],
	}, nil
}

// ListCountries returns all countries ordered by name.
func (g *Geo) ListCountries(ctx context.Context) ([]model.Country, error) {
	rows, err := g.db.QueryContext(ctx, `
		SELECT id, code, name
		FROM countries
		ORDER BY name COLLATE NOCASE
	`)
	if err != nil {
		return nil, fmt.Errorf("list countries: %w", err)
	}
	defer rows.Close()

	var out []model.Country
	for rows.Next() {
		var c model.Country
		if err := rows.Scan(&c.ID, &c.Code, &c.Name); err != nil {
			return nil, fmt.Errorf("scan country: %w", err)
		}
		out = append(out, c)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("list countries rows: %w", err)
	}
	if out == nil {
		out = []model.Country{}
	}
	return out, nil
}

// GetCountryByCode looks up a country by case-insensitive ISO code.
func (g *Geo) GetCountryByCode(ctx context.Context, code string) (model.Country, error) {
	var c model.Country
	err := g.db.QueryRowContext(ctx, `
		SELECT id, code, name
		FROM countries
		WHERE code = ? COLLATE NOCASE
	`, code).Scan(&c.ID, &c.Code, &c.Name)
	if errors.Is(err, sql.ErrNoRows) {
		return model.Country{}, ErrNotFound
	}
	if err != nil {
		return model.Country{}, fmt.Errorf("get country by code: %w", err)
	}
	return c, nil
}

// GetCountryByID looks up a country by primary key.
func (g *Geo) GetCountryByID(ctx context.Context, id int64) (model.Country, error) {
	var c model.Country
	err := g.db.QueryRowContext(ctx, `
		SELECT id, code, name
		FROM countries
		WHERE id = ?
	`, id).Scan(&c.ID, &c.Code, &c.Name)
	if errors.Is(err, sql.ErrNoRows) {
		return model.Country{}, ErrNotFound
	}
	if err != nil {
		return model.Country{}, fmt.Errorf("get country by id: %w", err)
	}
	return c, nil
}

// GetCountry resolves a country by ISO code or numeric id.
// A path segment that is a canonical decimal integer (e.g. "31") is treated as id;
// otherwise it is treated as a case-insensitive ISO code (e.g. "BR").
func (g *Geo) GetCountry(ctx context.Context, ref string) (model.Country, error) {
	ref = strings.TrimSpace(ref)
	if ref == "" {
		return model.Country{}, ErrNotFound
	}
	if id, err := strconv.ParseInt(ref, 10, 64); err == nil && strconv.FormatInt(id, 10) == ref {
		return g.GetCountryByID(ctx, id)
	}
	return g.GetCountryByCode(ctx, ref)
}

// ListRegionsByCountryID returns regions for a country ordered by name.
func (g *Geo) ListRegionsByCountryID(ctx context.Context, countryID int64) ([]model.Region, error) {
	rows, err := g.db.QueryContext(ctx, `
		SELECT id, country_id, code, name
		FROM regions
		WHERE country_id = ?
		ORDER BY name COLLATE NOCASE
	`, countryID)
	if err != nil {
		return nil, fmt.Errorf("list regions: %w", err)
	}
	defer rows.Close()

	var out []model.Region
	for rows.Next() {
		var r model.Region
		var code sql.NullString
		if err := rows.Scan(&r.ID, &r.CountryID, &code, &r.Name); err != nil {
			return nil, fmt.Errorf("scan region: %w", err)
		}
		if code.Valid {
			r.Code = code.String
		}
		out = append(out, r)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("list regions rows: %w", err)
	}
	if out == nil {
		out = []model.Region{}
	}
	return out, nil
}

// GetRegion returns a region by ID.
func (g *Geo) GetRegion(ctx context.Context, id int64) (model.Region, error) {
	var r model.Region
	var code sql.NullString
	err := g.db.QueryRowContext(ctx, `
		SELECT id, country_id, code, name
		FROM regions
		WHERE id = ?
	`, id).Scan(&r.ID, &r.CountryID, &code, &r.Name)
	if errors.Is(err, sql.ErrNoRows) {
		return model.Region{}, ErrNotFound
	}
	if err != nil {
		return model.Region{}, fmt.Errorf("get region: %w", err)
	}
	if code.Valid {
		r.Code = code.String
	}
	return r, nil
}

// ListCitiesByRegionID returns cities for a region ordered by name.
func (g *Geo) ListCitiesByRegionID(ctx context.Context, regionID int64) ([]model.City, error) {
	rows, err := g.db.QueryContext(ctx, `
		SELECT id, country_id, region_id, name, timezone
		FROM cities
		WHERE region_id = ?
		ORDER BY name COLLATE NOCASE
	`, regionID)
	if err != nil {
		return nil, fmt.Errorf("list cities by region: %w", err)
	}
	defer rows.Close()
	return scanCities(rows)
}

// GetCity returns a city summary by ID.
func (g *Geo) GetCity(ctx context.Context, id int64) (model.City, error) {
	var c model.City
	var regionID sql.NullInt64
	var tz sql.NullString
	err := g.db.QueryRowContext(ctx, `
		SELECT id, country_id, region_id, name, timezone
		FROM cities
		WHERE id = ?
	`, id).Scan(&c.ID, &c.CountryID, &regionID, &c.Name, &tz)
	if errors.Is(err, sql.ErrNoRows) {
		return model.City{}, ErrNotFound
	}
	if err != nil {
		return model.City{}, fmt.Errorf("get city: %w", err)
	}
	if regionID.Valid {
		v := regionID.Int64
		c.RegionID = &v
	}
	if tz.Valid {
		c.Timezone = tz.String
	}
	return c, nil
}

// GetCityDetail returns a city with nested region and country.
func (g *Geo) GetCityDetail(ctx context.Context, id int64) (model.CityDetail, error) {
	var detail model.CityDetail
	var tz sql.NullString
	var regionID sql.NullInt64
	var regionCode sql.NullString
	var regionName sql.NullString

	err := g.db.QueryRowContext(ctx, `
		SELECT
			c.id,
			c.name,
			c.timezone,
			c.region_id,
			r.code,
			r.name,
			co.id,
			co.code,
			co.name
		FROM cities c
		JOIN countries co ON co.id = c.country_id
		LEFT JOIN regions r ON r.id = c.region_id
		WHERE c.id = ?
	`, id).Scan(
		&detail.ID,
		&detail.Name,
		&tz,
		&regionID,
		&regionCode,
		&regionName,
		&detail.Country.ID,
		&detail.Country.Code,
		&detail.Country.Name,
	)
	if errors.Is(err, sql.ErrNoRows) {
		return model.CityDetail{}, ErrNotFound
	}
	if err != nil {
		return model.CityDetail{}, fmt.Errorf("get city detail: %w", err)
	}
	if tz.Valid {
		detail.Timezone = tz.String
	}
	if regionID.Valid {
		rs := &model.RegionSummary{
			ID:   regionID.Int64,
			Name: regionName.String,
		}
		if regionCode.Valid {
			rs.Code = regionCode.String
		}
		detail.Region = rs
	}
	return detail, nil
}

// SearchCities finds cities whose normalized names contain q.
func (g *Geo) SearchCities(ctx context.Context, q string, limit int) ([]model.City, error) {
	q = textutil.NormalizeSearch(q)
	if q == "" {
		return []model.City{}, nil
	}
	pattern := "%" + q + "%"
	rows, err := g.db.QueryContext(ctx, `
		SELECT id, country_id, region_id, name, timezone
		FROM cities
		WHERE normalized_name LIKE ?
		ORDER BY name COLLATE NOCASE
		LIMIT ?
	`, pattern, limit)
	if err != nil {
		return nil, fmt.Errorf("search cities: %w", err)
	}
	defer rows.Close()
	return scanCities(rows)
}

// SearchCountries finds countries whose normalized names contain q.
func (g *Geo) SearchCountries(ctx context.Context, q string, limit int) ([]model.Country, error) {
	q = textutil.NormalizeSearch(q)
	if q == "" {
		return []model.Country{}, nil
	}
	pattern := "%" + q + "%"
	rows, err := g.db.QueryContext(ctx, `
		SELECT id, code, name
		FROM countries
		WHERE normalized_name LIKE ?
		ORDER BY name COLLATE NOCASE
		LIMIT ?
	`, pattern, limit)
	if err != nil {
		return nil, fmt.Errorf("search countries: %w", err)
	}
	defer rows.Close()

	var out []model.Country
	for rows.Next() {
		var c model.Country
		if err := rows.Scan(&c.ID, &c.Code, &c.Name); err != nil {
			return nil, fmt.Errorf("scan country: %w", err)
		}
		out = append(out, c)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("search countries rows: %w", err)
	}
	if out == nil {
		out = []model.Country{}
	}
	return out, nil
}

// SearchRegions finds regions whose normalized names contain q.
func (g *Geo) SearchRegions(ctx context.Context, q string, limit int) ([]model.Region, error) {
	q = textutil.NormalizeSearch(q)
	if q == "" {
		return []model.Region{}, nil
	}
	pattern := "%" + q + "%"
	rows, err := g.db.QueryContext(ctx, `
		SELECT id, country_id, code, name
		FROM regions
		WHERE normalized_name LIKE ?
		ORDER BY name COLLATE NOCASE
		LIMIT ?
	`, pattern, limit)
	if err != nil {
		return nil, fmt.Errorf("search regions: %w", err)
	}
	defer rows.Close()

	var out []model.Region
	for rows.Next() {
		var r model.Region
		var code sql.NullString
		if err := rows.Scan(&r.ID, &r.CountryID, &code, &r.Name); err != nil {
			return nil, fmt.Errorf("scan region: %w", err)
		}
		if code.Valid {
			r.Code = code.String
		}
		out = append(out, r)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("search regions rows: %w", err)
	}
	if out == nil {
		out = []model.Region{}
	}
	return out, nil
}

func scanCities(rows *sql.Rows) ([]model.City, error) {
	var out []model.City
	for rows.Next() {
		var c model.City
		var regionID sql.NullInt64
		var tz sql.NullString
		if err := rows.Scan(&c.ID, &c.CountryID, &regionID, &c.Name, &tz); err != nil {
			return nil, fmt.Errorf("scan city: %w", err)
		}
		if regionID.Valid {
			v := regionID.Int64
			c.RegionID = &v
		}
		if tz.Valid {
			c.Timezone = tz.String
		}
		out = append(out, c)
	}
	if err := rows.Err(); err != nil {
		return nil, fmt.Errorf("cities rows: %w", err)
	}
	if out == nil {
		out = []model.City{}
	}
	return out, nil
}
