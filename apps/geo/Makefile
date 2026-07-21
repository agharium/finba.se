.PHONY: tidy fmt vet test build run import inspect inspect-json release release-json download download-json download-extract update force-update update-json docker-build docker-run gen-api-key clean

INPUT ?= ./data/cities.csv
OUTPUT ?= ./data/geo.db
DATASET_VERSION ?=
DATASET_SHA256 ?=
DATABASE ?= ./data/geo.db
OWNER ?= dr5hn
REPO ?= countries-states-cities-database
DOWNLOAD_ASSET ?= csv-cities.csv.gz
DOWNLOAD_OUTPUT ?= ./data/downloads/csv-cities.csv.gz
EXTRACT_OUTPUT ?= ./data/downloads/cities.csv
WORKSPACE ?= ./data/work

VERSION ?= dev
GIT_COMMIT ?= $(shell git rev-parse --short HEAD 2>/dev/null || echo unknown)
BUILD_DATE ?= $(shell date -u +%Y-%m-%dT%H:%M:%SZ)
LDFLAGS := -s -w \
	-X finba.se/geo/internal/buildinfo.Version=$(VERSION) \
	-X finba.se/geo/internal/buildinfo.GitCommit=$(GIT_COMMIT) \
	-X finba.se/geo/internal/buildinfo.BuildDate=$(BUILD_DATE)

DOCKER_IMAGE ?= finba-geo:local

tidy:
	go mod tidy

fmt:
	go fmt ./...

vet:
	go vet ./...

test:
	go test ./...

build:
	go build -trimpath -ldflags="$(LDFLAGS)" -o bin/api ./cmd/api
	go build -o bin/importer ./cmd/importer
	go build -o bin/inspect ./cmd/inspect
	go build -o bin/release ./cmd/release
	go build -o bin/download ./cmd/download
	go build -o bin/update ./cmd/update

run: build
	GEO_DATABASE_PATH=$(OUTPUT) ./bin/api

import:
	@if [ -z "$(DATASET_VERSION)" ] || [ -z "$(DATASET_SHA256)" ]; then \
		echo "DATASET_VERSION and DATASET_SHA256 are required"; \
		echo "Example: make import INPUT=./data/cities.csv DATASET_VERSION=v3.2-export.6 DATASET_SHA256=abc123"; \
		exit 1; \
	fi
	go run ./cmd/importer \
		--input "$(INPUT)" \
		--output "$(OUTPUT)" \
		--dataset-version "$(DATASET_VERSION)" \
		--dataset-sha256 "$(DATASET_SHA256)"

inspect:
	go run ./cmd/inspect --database "$(DATABASE)"

inspect-json:
	go run ./cmd/inspect --database "$(DATABASE)" --json

release:
	go run ./cmd/release --owner "$(OWNER)" --repo "$(REPO)" --latest

release-json:
	go run ./cmd/release --owner "$(OWNER)" --repo "$(REPO)" --latest --json

download:
	go run ./cmd/download --owner "$(OWNER)" --repo "$(REPO)" --asset "$(DOWNLOAD_ASSET)" --output "$(DOWNLOAD_OUTPUT)"

download-json:
	go run ./cmd/download --owner "$(OWNER)" --repo "$(REPO)" --asset "$(DOWNLOAD_ASSET)" --output "$(DOWNLOAD_OUTPUT)" --json

download-extract:
	go run ./cmd/download --owner "$(OWNER)" --repo "$(REPO)" --asset "$(DOWNLOAD_ASSET)" --output "$(DOWNLOAD_OUTPUT)" --extract --extract-output "$(EXTRACT_OUTPUT)"

update:
	go run ./cmd/update --owner "$(OWNER)" --repo "$(REPO)" --asset "$(DOWNLOAD_ASSET)" --database "$(DATABASE)" --workspace "$(WORKSPACE)"

force-update:
	go run ./cmd/update --owner "$(OWNER)" --repo "$(REPO)" --asset "$(DOWNLOAD_ASSET)" --database "$(DATABASE)" --workspace "$(WORKSPACE)" --force

update-json:
	go run ./cmd/update --owner "$(OWNER)" --repo "$(REPO)" --asset "$(DOWNLOAD_ASSET)" --database "$(DATABASE)" --workspace "$(WORKSPACE)" --json

docker-build:
	@if [ ! -f data/geo.db ]; then \
		echo "data/geo.db is required. Run make update (or make import) first."; \
		exit 1; \
	fi
	docker build \
		--build-arg VERSION="$(VERSION)" \
		--build-arg GIT_COMMIT="$(GIT_COMMIT)" \
		--build-arg BUILD_DATE="$(BUILD_DATE)" \
		-t "$(DOCKER_IMAGE)" .

docker-run:
	docker run --rm -p 8080:8080 \
		-e PORT=8080 \
		-e GEO_ENV=development \
		-e GEO_DATABASE_PATH=/app/data/geo.db \
		-e LOG_LEVEL=info \
		"$(DOCKER_IMAGE)"

# Cryptographically random opaque API key (base64, 32 bytes).
# Copy into Secret Manager / Laravel — never commit the output.
gen-api-key:
	@openssl rand -base64 32

clean:
	rm -rf bin/
	rm -f coverage.out
