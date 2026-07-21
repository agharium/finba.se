# syntax=docker/dockerfile:1

# Multi-stage build for Cloud Run.
#
# Runtime image choice: gcr.io/distroless/static-debian12:nonroot
# - modernc.org/sqlite is pure Go → CGO_ENABLED=0 static binary
# - no shell / package manager attack surface
# - nonroot by default
# Alpine would only be needed if we required libc/CGO (we do not).

FROM golang:1.26-bookworm AS builder

WORKDIR /src

# Cache module downloads separately from source for faster rebuilds.
COPY go.mod go.sum ./
RUN --mount=type=cache,target=/go/pkg/mod \
    go mod download

COPY . .

ARG VERSION=dev
ARG GIT_COMMIT=unknown
ARG BUILD_DATE=unknown

RUN --mount=type=cache,target=/go/pkg/mod \
    --mount=type=cache,target=/root/.cache/go-build \
    CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
    go build -trimpath \
      -ldflags="-s -w \
        -X finba.se/geo/internal/buildinfo.Version=${VERSION} \
        -X finba.se/geo/internal/buildinfo.GitCommit=${GIT_COMMIT} \
        -X finba.se/geo/internal/buildinfo.BuildDate=${BUILD_DATE}" \
      -o /out/geo-api ./cmd/api

FROM gcr.io/distroless/static-debian12:nonroot

WORKDIR /app

COPY --from=builder /out/geo-api /app/geo-api
# Immutable catalog baked at image build time (run importer / make update first).
COPY data/geo.db /app/data/geo.db

ENV PORT=8080 \
    GEO_ENV=production \
    GEO_DATABASE_PATH=/app/data/geo.db \
    LOG_LEVEL=info \
    GEO_TRUST_PROXY_HEADERS=true

EXPOSE 8080

USER nonroot:nonroot

ENTRYPOINT ["/app/geo-api"]
