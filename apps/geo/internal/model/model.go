// Package model defines Finba Geo entities independent of any supplier schema.
package model

// SchemaVersion is the SQLite schema version written into metadata.
const SchemaVersion = "2"

// GeneratorVersion identifies this importer/API generator release.
const GeneratorVersion = "0.1.0"

// Metadata keys persisted in the SQLite metadata table.
const (
	MetaProvider         = "provider"
	MetaDatasetVersion   = "dataset_version"
	MetaDatasetSHA256    = "dataset_sha256"
	MetaGeneratedAt      = "generated_at"
	MetaGeneratorVersion = "generator_version"
	MetaSchemaVersion    = "schema_version"
)

// Provider identifies the upstream geographical dataset.
const Provider = "dr5hn/countries-states-cities-database"

// Country is a nation in the geographical catalog.
type Country struct {
	ID   int64  `json:"id"`
	Code string `json:"code"`
	Name string `json:"name"`
}

// Region is a first-level subdivision of a country (supplier "state").
type Region struct {
	ID        int64  `json:"id"`
	CountryID int64  `json:"countryId"`
	Code      string `json:"code,omitempty"`
	Name      string `json:"name"`
}

// City is a populated place within a country, optionally in a region.
type City struct {
	ID        int64  `json:"id"`
	CountryID int64  `json:"countryId"`
	RegionID  *int64 `json:"regionId,omitempty"`
	Name      string `json:"name"`
	Timezone  string `json:"timezone,omitempty"`
}

// CityDetail embeds nested region and country summaries.
type CityDetail struct {
	ID       int64          `json:"id"`
	Name     string         `json:"name"`
	Timezone string         `json:"timezone,omitempty"`
	Region   *RegionSummary `json:"region,omitempty"`
	Country  CountrySummary `json:"country"`
}

// CountrySummary is a compact country projection for nested responses.
type CountrySummary struct {
	ID   int64  `json:"id"`
	Code string `json:"code"`
	Name string `json:"name"`
}

// RegionSummary is a compact region projection for nested responses.
type RegionSummary struct {
	ID   int64  `json:"id"`
	Code string `json:"code,omitempty"`
	Name string `json:"name"`
}

// Version holds dataset and generator metadata from the database.
type Version struct {
	Provider         string `json:"provider"`
	DatasetVersion   string `json:"datasetVersion"`
	DatasetSHA256    string `json:"datasetSha256"`
	GeneratedAt      string `json:"generatedAt"`
	GeneratorVersion string `json:"generatorVersion"`
	SchemaVersion    string `json:"schemaVersion"`
}
