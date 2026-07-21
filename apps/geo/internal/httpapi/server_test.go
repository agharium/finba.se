package httpapi_test

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"testing"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/httpapi"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/repository"
)

func TestHTTPAPI(t *testing.T) {
	t.Parallel()
	handler := newTestHandler(t)

	t.Run("health", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/health", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d body=%s", rr.Code, rr.Body.String())
		}
		var body map[string]string
		decode(t, rr, &body)
		if body["status"] != "ok" {
			t.Fatalf("body=%v", body)
		}
		if body["version"] == "" {
			t.Fatalf("expected version in health body=%v", body)
		}
		if rr.Header().Get("X-Content-Type-Options") != "nosniff" {
			t.Fatalf("missing security header")
		}
	})

	t.Run("build version", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/version", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d body=%s", rr.Code, rr.Body.String())
		}
		var body map[string]string
		decode(t, rr, &body)
		if body["version"] == "" || body["goVersion"] == "" {
			t.Fatalf("body=%v", body)
		}
	})

	t.Run("dataset version", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/version", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d", rr.Code)
		}
		var body map[string]string
		decode(t, rr, &body)
		if body["provider"] == "" || body["schemaVersion"] == "" {
			t.Fatalf("body=%v", body)
		}
	})

	t.Run("countries", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/countries", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d", rr.Code)
		}
		var body []map[string]any
		decode(t, rr, &body)
		if len(body) != 3 {
			t.Fatalf("len=%d", len(body))
		}
	})

	t.Run("country and regions", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/countries/br", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d", rr.Code)
		}
		rr = do(t, handler, http.MethodGet, "/v1/countries/BR/regions", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d", rr.Code)
		}
		var regions []map[string]any
		decode(t, rr, &regions)
		if len(regions) != 3 {
			t.Fatalf("regions=%d", len(regions))
		}
	})

	t.Run("region cities", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/regions/2021/cities", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d body=%s", rr.Code, rr.Body.String())
		}
		var cities []map[string]any
		decode(t, rr, &cities)
		if len(cities) != 2 {
			t.Fatalf("cities=%d", len(cities))
		}
	})

	t.Run("city detail", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/cities/1001", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d", rr.Code)
		}
		var body map[string]any
		decode(t, rr, &body)
		if body["name"] != "Tramandaí" {
			t.Fatalf("body=%v", body)
		}
		country, ok := body["country"].(map[string]any)
		if !ok || country["code"] != "BR" {
			t.Fatalf("country=%v", body["country"])
		}
	})

	t.Run("search", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/cities/search?q=tra", "")
		if rr.Code != http.StatusOK {
			t.Fatalf("status=%d", rr.Code)
		}
		var cities []map[string]any
		decode(t, rr, &cities)
		if len(cities) != 1 {
			t.Fatalf("cities=%v", cities)
		}
		if cities[0]["name"] != "Tramandaí" {
			t.Fatalf("name=%v", cities[0]["name"])
		}
		if _, ok := cities[0]["normalizedName"]; ok {
			t.Fatal("normalizedName must not be exposed")
		}

		rr = do(t, handler, http.MethodGet, "/v1/cities/search?q=tramandai", "")
		decode(t, rr, &cities)
		if len(cities) != 1 || cities[0]["name"] != "Tramandaí" {
			t.Fatalf("unaccented=%v", cities)
		}
		rr = do(t, handler, http.MethodGet, "/v1/cities/search?q=sao%20paulo", "")
		decode(t, rr, &cities)
		if len(cities) != 1 || cities[0]["name"] != "São Paulo" {
			t.Fatalf("sao=%v", cities)
		}

		rr = do(t, handler, http.MethodGet, "/v1/countries/search?q=brazil", "")
		var countries []map[string]any
		decode(t, rr, &countries)
		if len(countries) != 1 || countries[0]["name"] != "Brazil" {
			t.Fatalf("countries=%v", countries)
		}
		if _, ok := countries[0]["normalizedName"]; ok {
			t.Fatal("normalizedName leaked")
		}

		rr = do(t, handler, http.MethodGet, "/v1/regions/search?q=rio%20grande", "")
		var regions []map[string]any
		decode(t, rr, &regions)
		if len(regions) != 1 || regions[0]["name"] != "Rio Grande do Sul" {
			t.Fatalf("regions=%v", regions)
		}
	})

	t.Run("search validation", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/cities/search?q=a", "")
		assertError(t, rr, http.StatusBadRequest, "invalid_query")

		rr = do(t, handler, http.MethodGet, "/v1/cities/search?q=tra&limit=0", "")
		assertError(t, rr, http.StatusBadRequest, "invalid_limit")

		rr = do(t, handler, http.MethodGet, "/v1/cities/search?q=tra&limit=101", "")
		assertError(t, rr, http.StatusBadRequest, "invalid_limit")
	})

	t.Run("404 shape", func(t *testing.T) {
		rr := do(t, handler, http.MethodGet, "/v1/cities/999999", "")
		assertError(t, rr, http.StatusNotFound, "city_not_found")
	})

	t.Run("request id", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/health", nil)
		req.Header.Set("X-Request-ID", "test-req-1")
		rr := httptest.NewRecorder()
		handler.ServeHTTP(rr, req)
		if got := rr.Header().Get("X-Request-ID"); got != "test-req-1" {
			t.Fatalf("request id=%q", got)
		}

		rr = do(t, handler, http.MethodGet, "/health", "")
		if rr.Header().Get("X-Request-ID") == "" {
			t.Fatal("expected generated request id")
		}
	})

	t.Run("method not allowed", func(t *testing.T) {
		rr := do(t, handler, http.MethodPost, "/health", "")
		if rr.Code != http.StatusMethodNotAllowed && rr.Code != http.StatusNotFound {
			// Go ServeMux returns 405 when another method is registered for the path.
			t.Fatalf("status=%d", rr.Code)
		}
	})
}

func newTestHandler(t *testing.T) http.Handler {
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
	return httpapi.New(repository.New(db), nil, nil).Handler()
}

func do(t *testing.T, h http.Handler, method, path, reqID string) *httptest.ResponseRecorder {
	t.Helper()
	req := httptest.NewRequest(method, path, nil)
	if reqID != "" {
		req.Header.Set("X-Request-ID", reqID)
	}
	rr := httptest.NewRecorder()
	h.ServeHTTP(rr, req)
	return rr
}

func decode(t *testing.T, rr *httptest.ResponseRecorder, dest any) {
	t.Helper()
	if ct := rr.Header().Get("Content-Type"); ct != "application/json" {
		t.Fatalf("content-type=%q", ct)
	}
	if err := json.Unmarshal(rr.Body.Bytes(), dest); err != nil {
		t.Fatalf("json: %v body=%s", err, rr.Body.String())
	}
}

func assertError(t *testing.T, rr *httptest.ResponseRecorder, status int, code string) {
	t.Helper()
	if rr.Code != status {
		t.Fatalf("status=%d body=%s", rr.Code, rr.Body.String())
	}
	var body struct {
		Error struct {
			Code    string `json:"code"`
			Message string `json:"message"`
		} `json:"error"`
	}
	decode(t, rr, &body)
	if body.Error.Code != code || body.Error.Message == "" {
		t.Fatalf("error body=%+v", body)
	}
}
