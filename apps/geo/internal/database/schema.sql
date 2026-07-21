PRAGMA foreign_keys = ON;

CREATE TABLE countries (
    id              INTEGER PRIMARY KEY,
    code            TEXT    NOT NULL UNIQUE,
    name            TEXT    NOT NULL,
    normalized_name TEXT    NOT NULL
);

CREATE TABLE regions (
    id              INTEGER PRIMARY KEY,
    country_id      INTEGER NOT NULL REFERENCES countries (id),
    code            TEXT    NULL,
    name            TEXT    NOT NULL,
    normalized_name TEXT    NOT NULL
);

CREATE TABLE cities (
    id              INTEGER PRIMARY KEY,
    country_id      INTEGER NOT NULL REFERENCES countries (id),
    region_id       INTEGER NULL REFERENCES regions (id),
    name            TEXT    NOT NULL,
    normalized_name TEXT    NOT NULL,
    timezone        TEXT    NULL
);

CREATE TABLE metadata (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE INDEX idx_regions_country_id ON regions (country_id);
CREATE INDEX idx_cities_region_id ON cities (region_id);
CREATE INDEX idx_cities_country_id ON cities (country_id);
CREATE INDEX idx_countries_normalized_name ON countries (normalized_name);
CREATE INDEX idx_regions_normalized_name ON regions (normalized_name);
CREATE INDEX idx_cities_normalized_name ON cities (normalized_name);
