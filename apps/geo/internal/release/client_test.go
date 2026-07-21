package release_test

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"finba.se/geo/internal/release"
)

func TestLatest(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		assertHeaders(t, r)
		if r.URL.Path != "/repos/acme/widgets/releases/latest" {
			t.Fatalf("path=%s", r.URL.Path)
		}
		w.Header().Set("X-RateLimit-Remaining", "42")
		w.Header().Set("X-RateLimit-Reset", "1700000000")
		writeJSON(t, w, map[string]any{
			"tag_name":     "v1.2.3",
			"name":         "Widgets 1.2.3",
			"body":         "notes",
			"draft":        false,
			"prerelease":   false,
			"published_at": "2024-01-02T03:04:05Z",
			"assets": []map[string]any{
				{
					"id":                   9,
					"name":                 "cities.csv.gz",
					"content_type":         "application/gzip",
					"size":                 1234,
					"browser_download_url": "https://example.com/cities.csv.gz",
					"url":                  "https://api.example/ignored",
				},
			},
		})
	}))
	defer srv.Close()

	c := testClient(t, srv, "acme", "widgets")
	rel, err := c.Latest(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if rel.Tag != "v1.2.3" || rel.Name != "Widgets 1.2.3" || rel.Body != "notes" {
		t.Fatalf("%+v", rel)
	}
	if rel.Draft || rel.Prerelease {
		t.Fatalf("%+v", rel)
	}
	if !rel.PublishedAt.Equal(time.Date(2024, 1, 2, 3, 4, 5, 0, time.UTC)) {
		t.Fatalf("published=%v", rel.PublishedAt)
	}
	if len(rel.Assets) != 1 {
		t.Fatalf("assets=%+v", rel.Assets)
	}
	a := rel.Assets[0]
	if a.ID != 9 || a.Name != "cities.csv.gz" || a.ContentType != "application/gzip" || a.Size != 1234 {
		t.Fatalf("%+v", a)
	}
	if a.DownloadURL != "https://example.com/cities.csv.gz" {
		t.Fatalf("url=%s", a.DownloadURL)
	}

	rl := c.RateLimit()
	if rl.Remaining != 42 {
		t.Fatalf("remaining=%d", rl.Remaining)
	}
	if !rl.Reset.Equal(time.Unix(1700000000, 0).UTC()) {
		t.Fatalf("reset=%v", rl.Reset)
	}
}

func TestReleasesEmpty(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path != "/repos/acme/widgets/releases" {
			t.Fatalf("path=%s", r.URL.Path)
		}
		writeJSON(t, w, []any{})
	}))
	defer srv.Close()

	c := testClient(t, srv, "acme", "widgets")
	rels, err := c.Releases(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(rels) != 0 {
		t.Fatalf("%+v", rels)
	}
}

func TestReleasesAndGetByTag(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/repos/acme/widgets/releases":
			writeJSON(t, w, []map[string]any{
				{"tag_name": "v2", "name": "Two", "published_at": "2024-02-01T00:00:00Z", "assets": []any{}},
				{"tag_name": "v1", "name": "One", "published_at": "2024-01-01T00:00:00Z", "assets": []any{}},
			})
		case "/repos/acme/widgets/releases/tags/v2":
			writeJSON(t, w, map[string]any{
				"tag_name":     "v2",
				"name":         "Two",
				"draft":        true,
				"prerelease":   true,
				"published_at": "2024-02-01T00:00:00Z",
				"assets":       []any{},
			})
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	c := testClient(t, srv, "acme", "widgets")
	rels, err := c.Releases(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	if len(rels) != 2 || rels[0].Tag != "v2" || rels[1].Tag != "v1" {
		t.Fatalf("%+v", rels)
	}

	rel, err := c.GetByTag(context.Background(), "v2")
	if err != nil {
		t.Fatal(err)
	}
	if !rel.Draft || !rel.Prerelease || rel.Tag != "v2" {
		t.Fatalf("%+v", rel)
	}
}

func TestStatusErrors(t *testing.T) {
	t.Parallel()
	cases := []struct {
		name   string
		status int
		want   error
	}{
		{"404", http.StatusNotFound, release.ErrReleaseNotFound},
		{"403", http.StatusForbidden, release.ErrRateLimited},
		{"429", http.StatusTooManyRequests, release.ErrRateLimited},
		{"500", http.StatusInternalServerError, release.ErrGitHub},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()
			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				w.WriteHeader(tc.status)
				_, _ = w.Write([]byte(`{"message":"nope"}`))
			}))
			defer srv.Close()
			c := testClient(t, srv, "acme", "widgets")
			_, err := c.Latest(context.Background())
			if !errors.Is(err, tc.want) {
				t.Fatalf("err=%v want %v", err, tc.want)
			}
			if strings.Contains(err.Error(), `"message"`) {
				t.Fatalf("leaked json: %v", err)
			}
		})
	}
}

func TestMalformedJSON(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{not-json`))
	}))
	defer srv.Close()

	c := testClient(t, srv, "acme", "widgets")
	_, err := c.Latest(context.Background())
	if err == nil || !strings.Contains(err.Error(), "decode github response") {
		t.Fatalf("err=%v", err)
	}
}

func TestOptionalToken(t *testing.T) {
	var sawAuth string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		sawAuth = r.Header.Get("Authorization")
		writeJSON(t, w, map[string]any{
			"tag_name": "v1", "name": "v1", "published_at": "2024-01-01T00:00:00Z", "assets": []any{},
		})
	}))
	defer srv.Close()

	t.Setenv("GITHUB_TOKEN", "secret-token")
	c := release.NewWithHTTPClient("acme", "widgets", srv.Client())
	c.SetBaseURL(srv.URL)
	if _, err := c.Latest(context.Background()); err != nil {
		t.Fatal(err)
	}
	if sawAuth != "Bearer secret-token" {
		t.Fatalf("auth=%q", sawAuth)
	}
}

func TestGetByTagEmpty(t *testing.T) {
	t.Parallel()
	c := release.New("acme", "widgets")
	_, err := c.GetByTag(context.Background(), "  ")
	if err == nil {
		t.Fatal("expected error")
	}
}

func testClient(t *testing.T, srv *httptest.Server, owner, repo string) *release.Client {
	t.Helper()
	c := release.NewWithHTTPClient(owner, repo, srv.Client())
	c.SetBaseURL(srv.URL)
	return c
}

func assertHeaders(t *testing.T, r *http.Request) {
	t.Helper()
	if got := r.Header.Get("Accept"); got != "application/vnd.github+json" {
		t.Fatalf("Accept=%q", got)
	}
	if got := r.Header.Get("User-Agent"); got != "finba-geo" {
		t.Fatalf("User-Agent=%q", got)
	}
}

func writeJSON(t *testing.T, w http.ResponseWriter, v any) {
	t.Helper()
	w.Header().Set("Content-Type", "application/json")
	if err := json.NewEncoder(w).Encode(v); err != nil {
		t.Fatal(err)
	}
}
