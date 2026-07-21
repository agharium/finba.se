package httpapi_test

import (
	"context"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"testing"
	"time"

	"finba.se/geo/internal/access"
	"finba.se/geo/internal/database"
	"finba.se/geo/internal/httpapi"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/repository"
)

func TestAccessIntegration(t *testing.T) {
	t.Parallel()

	cfg := access.DefaultConfig()
	cfg.TrustedAPIKeys = []string{"trusted-test-key"}
	cfg.InternalAPIKey = "internal-test-key"
	cfg.CleanupInterval = time.Hour

	mgr := access.NewLimiterManager(cfg, slog.Default())
	t.Cleanup(func() { _ = mgr.Close() })
	mw := access.NewMiddleware(cfg, mgr, slog.Default())
	handler := newTestHandlerWithAccess(t, mw)

	paths := []string{
		"/v1/countries",
		"/v1/countries/search?q=brazil",
		"/v1/regions/search?q=rio",
		"/v1/cities/search?q=tramandai",
		"/v1/cities/1001",
	}

	for _, path := range paths {
		t.Run("anonymous "+path, func(t *testing.T) {
			req := httptest.NewRequest(http.MethodGet, path, nil)
			req.RemoteAddr = "203.0.113.20:1"
			rr := httptest.NewRecorder()
			handler.ServeHTTP(rr, req)
			if rr.Code != http.StatusOK {
				t.Fatalf("status=%d body=%s", rr.Code, rr.Body.String())
			}
			if rateHeader(rr, "RateLimit-Limit") != "60" {
				t.Fatalf("limit=%s", rateHeader(rr, "RateLimit-Limit"))
			}
			if rateHeader(rr, "RateLimit-Remaining") == "" || rateHeader(rr, "RateLimit-Reset") == "" {
				t.Fatalf("missing remaining/reset: %v", rr.Header())
			}
		})
	}

	t.Run("trusted", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.Header.Set("Authorization", "Bearer trusted-test-key")
		rr := httptest.NewRecorder()
		handler.ServeHTTP(rr, req)
		if rr.Code != http.StatusOK || rateHeader(rr, "RateLimit-Limit") != "300" {
			t.Fatalf("status=%d limit=%s", rr.Code, rateHeader(rr, "RateLimit-Limit"))
		}
	})

	t.Run("internal", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.Header.Set("X-API-Key", "internal-test-key")
		rr := httptest.NewRecorder()
		handler.ServeHTTP(rr, req)
		if rr.Code != http.StatusOK || rateHeader(rr, "RateLimit-Limit") != "10000" {
			t.Fatalf("status=%d limit=%s", rr.Code, rateHeader(rr, "RateLimit-Limit"))
		}
	})

	t.Run("invalid key", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.Header.Set("Authorization", "Bearer nope")
		rr := httptest.NewRecorder()
		handler.ServeHTTP(rr, req)
		assertError(t, rr, http.StatusUnauthorized, "invalid_api_key")
	})

	t.Run("health excluded", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/health", "")
		if rr.Code != http.StatusOK || rr.Header().Get("RateLimit-Limit") != "" {
			t.Fatalf("status=%d headers=%v", rr.Code, rr.Header())
		}
	})

	t.Run("build version excluded", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/version", "")
		if rr.Code != http.StatusOK || rr.Header().Get("RateLimit-Limit") != "" {
			t.Fatalf("status=%d headers=%v", rr.Code, rr.Header())
		}
	})

	t.Run("dataset version excluded", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/version", "")
		if rr.Code != http.StatusOK || rr.Header().Get("RateLimit-Limit") != "" {
			t.Fatalf("status=%d headers=%v", rr.Code, rr.Header())
		}
	})
}

func newTestHandlerWithAccess(t *testing.T, mw *access.Middleware) http.Handler {
	t.Helper()
	out := filepath.Join(t.TempDir(), "geo.db")
	_, err := importer.Run(context.Background(), importer.Options{
		InputPath:      filepath.Join("..", "..", "testdata", "cities_ok.csv"),
		OutputPath:     out,
		DatasetVersion: "fixture",
		DatasetSHA256:  "fixture-sha",
	})
	if err != nil {
		t.Fatalf("import: %v", err)
	}
	db, err := database.OpenReadOnly(out)
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = db.Close() })
	return httpapi.New(repository.New(db), nil, mw).Handler()
}

func rateHeader(rr *httptest.ResponseRecorder, key string) string {
	if vals := rr.Header()[key]; len(vals) > 0 {
		return vals[0]
	}
	return rr.Header().Get(key)
}
