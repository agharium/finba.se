# Multi-stage build for Cloud Run.
#
# Compatible with classic Docker / Cloud Build (gcr.io/cloud-builders/docker).
# Do not use BuildKit-only syntax (RUN --mount, # syntax=docker/dockerfile, …).
#
# Runtime image choice: gcr.io/distroless/static-debian12:nonroot
# - modernc.org/sqlite is pure Go → CGO_ENABLED=0 static binary
# - no shell / package manager attack surface
# - nonroot by default
# Alpine would only be needed if we required libc/CGO (we do not).

FROM golang:1.26-bookworm AS builder

WORKDIR /src

# Cache module downloads in a Docker layer (invalidates only when go.mod/go.sum change).
COPY go.mod go.sum ./
RUN go mod download

COPY . .

ARG VERSION=dev
ARG GIT_COMMIT=unknown
ARG BUILD_DATE=unknown

RUN CGO_ENABLED=0 GOOS=linux GOARCH=amd64 \
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
    LOG_LEVEL=info

EXPOSE 8080

USER nonroot:nonroot

ENTRYPOINT ["/app/geo-api"]
