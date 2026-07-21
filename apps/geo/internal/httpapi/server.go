// Package httpapi serves the Finba Geo read-only REST API.
package httpapi

import (
	"context"
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"runtime/debug"
	"strconv"
	"strings"
	"time"

	"finba.se/geo/internal/access"
	"finba.se/geo/internal/buildinfo"
	"finba.se/geo/internal/repository"
)

const (
	defaultSearchLimit = 20
	maxSearchLimit     = 100
	minSearchQueryLen  = 2
)

// Options configures optional HTTP server behavior without changing API contracts.
type Options struct {
	Environment string
}

// Server hosts Geo HTTP handlers.
type Server struct {
	repo   *repository.Geo
	logger *slog.Logger
	access *access.Middleware
	env    string
	mux    *http.ServeMux
}

// New constructs an API server.
// accessMW may be nil in tests that do not exercise rate limiting.
// opts is optional; the first Options value wins when provided.
func New(repo *repository.Geo, logger *slog.Logger, accessMW *access.Middleware, opts ...Options) *Server {
	if logger == nil {
		logger = slog.Default()
	}
	var o Options
	if len(opts) > 0 {
		o = opts[0]
	}
	s := &Server{
		repo:   repo,
		logger: logger,
		access: accessMW,
		env:    o.Environment,
		mux:    http.NewServeMux(),
	}
	s.routes()
	return s
}

// Handler returns the root HTTP handler with middleware applied.
func (s *Server) Handler() http.Handler {
	return s.withMiddleware(s.mux)
}

func (s *Server) routes() {
	s.mux.HandleFunc("GET /health", s.handleHealth)
	s.mux.HandleFunc("GET /version", s.handleBuildVersion)
	s.mux.HandleFunc("GET /v1/version", s.handleDatasetVersion)
	s.mux.HandleFunc("GET /v1/countries/search", s.handleSearchCountries)
	s.mux.HandleFunc("GET /v1/countries", s.handleListCountries)
	s.mux.HandleFunc("GET /v1/countries/{code}", s.handleGetCountry)
	s.mux.HandleFunc("GET /v1/countries/{code}/regions", s.handleListCountryRegions)
	s.mux.HandleFunc("GET /v1/regions/search", s.handleSearchRegions)
	s.mux.HandleFunc("GET /v1/regions/{id}", s.handleGetRegion)
	s.mux.HandleFunc("GET /v1/regions/{id}/cities", s.handleListRegionCities)
	s.mux.HandleFunc("GET /v1/cities/search", s.handleSearchCities)
	s.mux.HandleFunc("GET /v1/cities/{id}", s.handleGetCity)
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	// Lightweight liveness: no DB queries. Additive fields keep status=="ok" stable.
	body := map[string]string{
		"status":  "ok",
		"version": buildinfo.Version,
	}
	if s.env != "" {
		body["environment"] = s.env
	}
	writeJSON(w, http.StatusOK, body)
}

// handleBuildVersion returns service binary metadata (not dataset catalog version).
func (s *Server) handleBuildVersion(w http.ResponseWriter, r *http.Request) {
	writeJSON(w, http.StatusOK, buildinfo.Snapshot(s.env))
}

// handleDatasetVersion returns catalog metadata from SQLite (public contract unchanged).
func (s *Server) handleDatasetVersion(w http.ResponseWriter, r *http.Request) {
	v, err := s.repo.Version(r.Context())
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, v)
}

func (s *Server) handleListCountries(w http.ResponseWriter, r *http.Request) {
	countries, err := s.repo.ListCountries(r.Context())
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, countries)
}

func (s *Server) handleGetCountry(w http.ResponseWriter, r *http.Request) {
	ref := r.PathValue("code")
	country, err := s.repo.GetCountry(r.Context(), ref)
	if errors.Is(err, repository.ErrNotFound) {
		writeError(w, http.StatusNotFound, "country_not_found", "Country not found.")
		return
	}
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, country)
}

func (s *Server) handleListCountryRegions(w http.ResponseWriter, r *http.Request) {
	ref := r.PathValue("code")
	country, err := s.repo.GetCountry(r.Context(), ref)
	if errors.Is(err, repository.ErrNotFound) {
		writeError(w, http.StatusNotFound, "country_not_found", "Country not found.")
		return
	}
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	regions, err := s.repo.ListRegionsByCountryID(r.Context(), country.ID)
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, regions)
}

func (s *Server) handleGetRegion(w http.ResponseWriter, r *http.Request) {
	id, ok := parsePathInt64(w, r.PathValue("id"), "region")
	if !ok {
		return
	}
	region, err := s.repo.GetRegion(r.Context(), id)
	if errors.Is(err, repository.ErrNotFound) {
		writeError(w, http.StatusNotFound, "region_not_found", "Region not found.")
		return
	}
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, region)
}

func (s *Server) handleListRegionCities(w http.ResponseWriter, r *http.Request) {
	id, ok := parsePathInt64(w, r.PathValue("id"), "region")
	if !ok {
		return
	}
	if _, err := s.repo.GetRegion(r.Context(), id); errors.Is(err, repository.ErrNotFound) {
		writeError(w, http.StatusNotFound, "region_not_found", "Region not found.")
		return
	} else if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	cities, err := s.repo.ListCitiesByRegionID(r.Context(), id)
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, cities)
}

func (s *Server) handleGetCity(w http.ResponseWriter, r *http.Request) {
	id, ok := parsePathInt64(w, r.PathValue("id"), "city")
	if !ok {
		return
	}
	city, err := s.repo.GetCityDetail(r.Context(), id)
	if errors.Is(err, repository.ErrNotFound) {
		writeError(w, http.StatusNotFound, "city_not_found", "City not found.")
		return
	}
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, city)
}

func (s *Server) handleSearchCities(w http.ResponseWriter, r *http.Request) {
	q, limit, ok := parseSearchParams(w, r)
	if !ok {
		return
	}
	cities, err := s.repo.SearchCities(r.Context(), q, limit)
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, cities)
}

func (s *Server) handleSearchCountries(w http.ResponseWriter, r *http.Request) {
	q, limit, ok := parseSearchParams(w, r)
	if !ok {
		return
	}
	countries, err := s.repo.SearchCountries(r.Context(), q, limit)
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, countries)
}

func (s *Server) handleSearchRegions(w http.ResponseWriter, r *http.Request) {
	q, limit, ok := parseSearchParams(w, r)
	if !ok {
		return
	}
	regions, err := s.repo.SearchRegions(r.Context(), q, limit)
	if err != nil {
		s.writeInternalError(w, r, err)
		return
	}
	writeJSON(w, http.StatusOK, regions)
}

func parseSearchParams(w http.ResponseWriter, r *http.Request) (q string, limit int, ok bool) {
	q = strings.TrimSpace(r.URL.Query().Get("q"))
	if len([]rune(q)) < minSearchQueryLen {
		writeError(w, http.StatusBadRequest, "invalid_query", "Search query must contain at least 2 non-whitespace characters.")
		return "", 0, false
	}

	limit = defaultSearchLimit
	if raw := r.URL.Query().Get("limit"); raw != "" {
		n, err := strconv.Atoi(raw)
		if err != nil || n < 1 {
			writeError(w, http.StatusBadRequest, "invalid_limit", "limit must be a positive integer.")
			return "", 0, false
		}
		if n > maxSearchLimit {
			writeError(w, http.StatusBadRequest, "invalid_limit", "limit must not exceed 100.")
			return "", 0, false
		}
		limit = n
	}
	return q, limit, true
}

func parsePathInt64(w http.ResponseWriter, raw, resource string) (int64, bool) {
	id, err := strconv.ParseInt(raw, 10, 64)
	if err != nil {
		writeError(w, http.StatusBadRequest, "invalid_"+resource+"_id", "Invalid "+resource+" id.")
		return 0, false
	}
	return id, true
}

type errorBody struct {
	Error errorDetail `json:"error"`
}

type errorDetail struct {
	Code    string `json:"code"`
	Message string `json:"message"`
}

func writeJSON(w http.ResponseWriter, status int, payload any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	enc := json.NewEncoder(w)
	enc.SetEscapeHTML(true)
	_ = enc.Encode(payload)
}

func writeError(w http.ResponseWriter, status int, code, message string) {
	writeJSON(w, status, errorBody{Error: errorDetail{Code: code, Message: message}})
}

func (s *Server) writeInternalError(w http.ResponseWriter, r *http.Request, err error) {
	s.logger.Error("request failed",
		"error", err,
		"method", r.Method,
		"path", r.URL.Path,
		"request_id", requestIDFrom(r.Context()),
	)
	writeError(w, http.StatusInternalServerError, "internal_error", "Internal server error.")
}

type ctxKey string

const requestIDKey ctxKey = "request_id"

func requestIDFrom(ctx context.Context) string {
	if v, ok := ctx.Value(requestIDKey).(string); ok {
		return v
	}
	return ""
}

func (s *Server) withMiddleware(next http.Handler) http.Handler {
	inner := http.Handler(next)
	if s.access != nil {
		inner = s.access.Handler(inner)
	}
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		reqID := r.Header.Get("X-Request-ID")
		if reqID == "" {
			reqID = newRequestID()
		}
		setSecurityHeaders(w)
		w.Header().Set("X-Request-ID", reqID)
		r.Header.Set("X-Request-ID", reqID)
		ctx := context.WithValue(r.Context(), requestIDKey, reqID)
		r = r.WithContext(ctx)

		rw := &statusWriter{ResponseWriter: w, status: http.StatusOK}
		func() {
			defer func() {
				if rec := recover(); rec != nil {
					s.logger.Error("panic recovered",
						"recover", rec,
						"method", r.Method,
						"path", r.URL.Path,
						"request_id", reqID,
						"stack", string(debug.Stack()),
					)
					if !rw.wrote {
						writeError(w, http.StatusInternalServerError, "internal_error", "Internal server error.")
						rw.status = http.StatusInternalServerError
						rw.wrote = true
					}
				}
			}()
			inner.ServeHTTP(rw, r)
		}()

		attrs := []any{
			"method", r.Method,
			"path", r.URL.Path,
			"status", rw.status,
			"duration_ms", time.Since(start).Milliseconds(),
			"request_id", reqID,
		}
		// Keep Cloud Run probe / version traffic out of info logs.
		if isQuietPath(r.URL.Path) {
			s.logger.Debug("request", attrs...)
			return
		}
		s.logger.Info("request", attrs...)
	})
}

func isQuietPath(path string) bool {
	switch path {
	case "/health", "/version", "/v1/version":
		return true
	default:
		return false
	}
}

func setSecurityHeaders(w http.ResponseWriter) {
	h := w.Header()
	h.Set("X-Content-Type-Options", "nosniff")
	h.Set("X-Frame-Options", "DENY")
	h.Set("Referrer-Policy", "no-referrer")
	h.Set("Cache-Control", "no-store")
	// Intentionally no Access-Control-* headers: this API is server-to-server only.
}

type statusWriter struct {
	http.ResponseWriter
	status int
	wrote  bool
}

func (w *statusWriter) WriteHeader(status int) {
	if w.wrote {
		return
	}
	w.status = status
	w.wrote = true
	w.ResponseWriter.WriteHeader(status)
}

func (w *statusWriter) Write(b []byte) (int, error) {
	if !w.wrote {
		w.WriteHeader(http.StatusOK)
	}
	return w.ResponseWriter.Write(b)
}

func newRequestID() string {
	var b [16]byte
	if _, err := rand.Read(b[:]); err != nil {
		return strconv.FormatInt(time.Now().UnixNano(), 16)
	}
	return hex.EncodeToString(b[:])
}
