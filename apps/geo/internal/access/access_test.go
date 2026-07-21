package access_test

import (
	"bytes"
	"encoding/json"
	"fmt"
	"log/slog"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
	"time"

	"finba.se/geo/internal/access"
)

func clearAccessEnv(t *testing.T) {
	t.Helper()
	keys := []string{
		"GEO_PUBLIC_RATE_LIMIT_PER_MINUTE",
		"GEO_PUBLIC_RATE_LIMIT_BURST",
		"GEO_TRUSTED_RATE_LIMIT_PER_MINUTE",
		"GEO_TRUSTED_RATE_LIMIT_BURST",
		"GEO_INTERNAL_RATE_LIMIT_PER_MINUTE",
		"GEO_INTERNAL_RATE_LIMIT_BURST",
		"GEO_INTERNAL_API_KEY",
		"GEO_TRUSTED_API_KEYS",
		"GEO_ACCESS_CLIENT_TTL",
		"GEO_ACCESS_CLEANUP_INTERVAL",
		"GEO_TRUST_PROXY_HEADERS",
	}
	for _, k := range keys {
		t.Setenv(k, "")
	}
}

func TestConfigDefaults(t *testing.T) {
	clearAccessEnv(t)
	cfg, err := access.LoadFromEnv()
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Public.RequestsPerMinute != 60 || cfg.Public.Burst != 15 {
		t.Fatalf("public=%+v", cfg.Public)
	}
	if cfg.Trusted.RequestsPerMinute != 300 || cfg.Trusted.Burst != 60 {
		t.Fatalf("trusted=%+v", cfg.Trusted)
	}
	if cfg.Internal.RequestsPerMinute != 10000 || cfg.Internal.Burst != 500 {
		t.Fatalf("internal=%+v", cfg.Internal)
	}
	if cfg.ClientTTL != 30*time.Minute || cfg.CleanupInterval != 5*time.Minute {
		t.Fatalf("ttl=%v cleanup=%v", cfg.ClientTTL, cfg.CleanupInterval)
	}
	if cfg.TrustProxyHeaders {
		t.Fatal("proxy trust should default false")
	}
}

func TestConfigEnvOverrides(t *testing.T) {
	clearAccessEnv(t)
	t.Setenv("GEO_PUBLIC_RATE_LIMIT_PER_MINUTE", "10")
	t.Setenv("GEO_PUBLIC_RATE_LIMIT_BURST", "2")
	t.Setenv("GEO_TRUSTED_API_KEYS", " a ,b, a, ,c ")
	t.Setenv("GEO_INTERNAL_API_KEY", " secret-internal ")
	t.Setenv("GEO_ACCESS_CLIENT_TTL", "10m")
	t.Setenv("GEO_ACCESS_CLEANUP_INTERVAL", "30")
	t.Setenv("GEO_TRUST_PROXY_HEADERS", "true")

	cfg, err := access.LoadFromEnv()
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Public.RequestsPerMinute != 10 || cfg.Public.Burst != 2 {
		t.Fatalf("public=%+v", cfg.Public)
	}
	if got := strings.Join(cfg.TrustedAPIKeys, ","); got != "a,b,c" {
		t.Fatalf("trusted keys=%v", cfg.TrustedAPIKeys)
	}
	if cfg.InternalAPIKey != "secret-internal" {
		t.Fatalf("internal key trimmed=%q", cfg.InternalAPIKey)
	}
	if cfg.ClientTTL != 10*time.Minute || cfg.CleanupInterval != 30*time.Second {
		t.Fatalf("ttl=%v cleanup=%v", cfg.ClientTTL, cfg.CleanupInterval)
	}
	if !cfg.TrustProxyHeaders {
		t.Fatal("expected trust proxy")
	}
	s := cfg.String()
	if strings.Contains(s, "secret-internal") || strings.Contains(s, ",a,") {
		t.Fatalf("secrets leaked in String: %s", s)
	}
	if !strings.Contains(s, "trusted_keys=3") || !strings.Contains(s, "internal_key=true") {
		t.Fatalf("String=%s", s)
	}
}

func TestConfigRejectsInvalid(t *testing.T) {
	cases := []struct {
		key, val string
	}{
		{"GEO_PUBLIC_RATE_LIMIT_PER_MINUTE", "0"},
		{"GEO_PUBLIC_RATE_LIMIT_PER_MINUTE", "-1"},
		{"GEO_PUBLIC_RATE_LIMIT_BURST", "0"},
		{"GEO_PUBLIC_RATE_LIMIT_PER_MINUTE", "abc"},
		{"GEO_TRUST_PROXY_HEADERS", "maybe"},
	}
	for _, tc := range cases {
		t.Run(tc.key+"="+tc.val, func(t *testing.T) {
			clearAccessEnv(t)
			t.Setenv(tc.key, tc.val)
			if _, err := access.LoadFromEnv(); err == nil {
				t.Fatal("expected error")
			}
		})
	}
}

func TestResolveCredentials(t *testing.T) {
	cfg := access.DefaultConfig()
	cfg.InternalAPIKey = "internal-key"
	cfg.TrustedAPIKeys = []string{"trusted-key"}
	r := access.NewResolver(cfg)

	t.Run("anonymous public", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.RemoteAddr = "203.0.113.10:1234"
		c, err := r.Resolve(req)
		if err != nil {
			t.Fatal(err)
		}
		if c.Level != access.LevelPublic || c.Identifier != "203.0.113.10" {
			t.Fatalf("%+v", c)
		}
	})

	t.Run("trusted bearer", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("Authorization", "Bearer trusted-key")
		c, err := r.Resolve(req)
		if err != nil || c.Level != access.LevelTrusted {
			t.Fatalf("%+v %v", c, err)
		}
		if c.Identifier != access.FingerprintKey("trusted-key") {
			t.Fatalf("id=%s", c.Identifier)
		}
		if strings.Contains(c.Identifier, "trusted-key") {
			t.Fatal("raw key retained")
		}
	})

	t.Run("internal x-api-key", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("X-API-Key", "internal-key")
		c, err := r.Resolve(req)
		if err != nil || c.Level != access.LevelInternal {
			t.Fatalf("%+v %v", c, err)
		}
	})

	t.Run("bearer precedence over x-api-key", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("Authorization", "Bearer trusted-key")
		req.Header.Set("X-API-Key", "internal-key")
		c, err := r.Resolve(req)
		if err != nil || c.Level != access.LevelTrusted {
			t.Fatalf("%+v %v", c, err)
		}
	})

	t.Run("invalid bearer does not fall back to x-api-key", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("Authorization", "Bearer wrong")
		req.Header.Set("X-API-Key", "trusted-key")
		_, err := r.Resolve(req)
		if err == nil || !strings.Contains(err.Error(), "invalid") {
			t.Fatalf("err=%v", err)
		}
	})

	t.Run("malformed bearer", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("Authorization", "Basic abc")
		_, err := r.Resolve(req)
		if err != access.ErrMalformedAuthorization {
			t.Fatalf("err=%v", err)
		}
	})

	t.Run("empty bearer", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("Authorization", "Bearer   ")
		_, err := r.Resolve(req)
		if err != access.ErrMalformedAuthorization {
			t.Fatalf("err=%v", err)
		}
	})

	t.Run("case insensitive bearer", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.Header.Set("Authorization", "bEaReR trusted-key")
		c, err := r.Resolve(req)
		if err != nil || c.Level != access.LevelTrusted {
			t.Fatalf("%+v %v", c, err)
		}
	})

	t.Run("fingerprints stable and distinct", func(t *testing.T) {
		a := access.FingerprintKey("trusted-key")
		b := access.FingerprintKey("trusted-key")
		c := access.FingerprintKey("other-key")
		if a != b || a == c || len(a) != 12 {
			t.Fatalf("a=%s b=%s c=%s", a, b, c)
		}
	})
}

func TestRealClientIP(t *testing.T) {
	t.Run("ignore proxies when disabled", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.RemoteAddr = "198.51.100.1:443"
		req.Header.Set("CF-Connecting-IP", "203.0.113.9")
		req.Header.Set("X-Forwarded-For", "203.0.113.8")
		if got := access.RealClientIP(req, false); got != "198.51.100.1" {
			t.Fatalf("got=%s", got)
		}
	})

	t.Run("prefer cf when trusted", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.RemoteAddr = "198.51.100.1:443"
		req.Header.Set("CF-Connecting-IP", "203.0.113.9")
		req.Header.Set("X-Forwarded-For", "203.0.113.8")
		if got := access.RealClientIP(req, true); got != "203.0.113.9" {
			t.Fatalf("got=%s", got)
		}
	})

	t.Run("invalid cf falls back to xff", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.RemoteAddr = "198.51.100.1:443"
		req.Header.Set("CF-Connecting-IP", "not-an-ip")
		req.Header.Set("X-Forwarded-For", " 203.0.113.8 , 10.0.0.1")
		if got := access.RealClientIP(req, true); got != "203.0.113.8" {
			t.Fatalf("got=%s", got)
		}
	})

	t.Run("ipv6 remote", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.RemoteAddr = "[2001:db8::1]:443"
		if got := access.RealClientIP(req, false); got != "2001:db8::1" {
			t.Fatalf("got=%s", got)
		}
	})

	t.Run("malformed xff falls back", func(t *testing.T) {
		req := httptest.NewRequest(http.MethodGet, "/", nil)
		req.RemoteAddr = "198.51.100.2:9"
		req.Header.Set("X-Forwarded-For", "garbage")
		if got := access.RealClientIP(req, true); got != "198.51.100.2" {
			t.Fatalf("got=%s", got)
		}
	})
}

func TestLimiterManagerTiers(t *testing.T) {
	cfg := access.DefaultConfig()
	cfg.Public = access.LimitConfig{RequestsPerMinute: 60, Burst: 2}
	cfg.Trusted = access.LimitConfig{RequestsPerMinute: 300, Burst: 3}
	cfg.Internal = access.LimitConfig{RequestsPerMinute: 10000, Burst: 4}
	cfg.ClientTTL = time.Hour
	cfg.CleanupInterval = time.Hour

	m := access.NewLimiterManager(cfg, slog.Default())
	defer m.Close()

	pub := access.Client{Level: access.LevelPublic, Identifier: "203.0.113.1"}
	for i := 0; i < 2; i++ {
		d := m.Allow(pub)
		if !d.Allowed || d.Limit != 60 {
			t.Fatalf("public allow %d: %+v", i, d)
		}
	}
	d := m.Allow(pub)
	if d.Allowed || d.RetryAfter < time.Second || d.Remaining != 0 {
		t.Fatalf("expected deny %+v", d)
	}

	trusted := access.Client{Level: access.LevelTrusted, Identifier: "abc"}
	for i := 0; i < 3; i++ {
		if !m.Allow(trusted).Allowed {
			t.Fatalf("trusted %d denied", i)
		}
	}
	if m.Allow(trusted).Allowed {
		t.Fatal("trusted should deny after burst")
	}

	// Same identifier, different level → independent bucket.
	cross := access.Client{Level: access.LevelPublic, Identifier: "abc"}
	if !m.Allow(cross).Allowed {
		t.Fatal("public:abc should be independent")
	}
}

func TestLimiterManagerCleanup(t *testing.T) {
	cfg := access.DefaultConfig()
	cfg.ClientTTL = 50 * time.Millisecond
	cfg.CleanupInterval = time.Hour
	m := access.NewLimiterManager(cfg, slog.Default())
	defer m.Close()

	_ = m.Allow(access.Client{Level: access.LevelPublic, Identifier: "1.1.1.1"})
	if m.Len() != 1 {
		t.Fatalf("len=%d", m.Len())
	}
	time.Sleep(60 * time.Millisecond)
	m.CleanupExpired()
	if m.Len() != 0 {
		t.Fatalf("expected cleanup, len=%d", m.Len())
	}
}

func TestLimiterManagerConcurrent(t *testing.T) {
	cfg := access.DefaultConfig()
	cfg.Public.Burst = 1000
	cfg.Public.RequestsPerMinute = 100000
	m := access.NewLimiterManager(cfg, slog.Default())
	defer m.Close()

	var wg sync.WaitGroup
	for i := 0; i < 32; i++ {
		wg.Add(1)
		go func(i int) {
			defer wg.Done()
			c := access.Client{Level: access.LevelPublic, Identifier: fmt.Sprintf("10.0.0.%d", i%4)}
			for j := 0; j < 50; j++ {
				_ = m.Allow(c)
			}
		}(i)
	}
	wg.Wait()
	_ = m.Close() // idempotent
}

func TestMiddleware(t *testing.T) {
	cfg := access.DefaultConfig()
	cfg.Public = access.LimitConfig{RequestsPerMinute: 60, Burst: 1}
	cfg.Trusted = access.LimitConfig{RequestsPerMinute: 300, Burst: 2}
	cfg.Internal = access.LimitConfig{RequestsPerMinute: 10000, Burst: 2}
	cfg.TrustedAPIKeys = []string{"trusted-secret"}
	cfg.InternalAPIKey = "internal-secret"
	cfg.CleanupInterval = time.Hour

	var logBuf bytes.Buffer
	logger := slog.New(slog.NewTextHandler(&logBuf, nil))
	mgr := access.NewLimiterManager(cfg, logger)
	defer mgr.Close()
	mw := access.NewMiddleware(cfg, mgr, logger)

	okHandler := http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if c, ok := access.ClientFromContext(r.Context()); ok {
			w.Header().Set("X-Test-Level", string(c.Level))
		}
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte(`{"ok":true}`))
	})
	h := mw.Handler(okHandler)

	t.Run("health bypass", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/health", nil)
		h.ServeHTTP(rr, req)
		if rr.Code != 200 || rr.Header().Get("RateLimit-Limit") != "" {
			t.Fatalf("status=%d headers=%v", rr.Code, rr.Header())
		}
	})

	t.Run("version bypass", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/v1/version", nil)
		h.ServeHTTP(rr, req)
		if rr.Code != 200 || rr.Header().Get("RateLimit-Limit") != "" {
			t.Fatalf("status=%d", rr.Code)
		}
	})

	t.Run("build version bypass", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/version", nil)
		h.ServeHTTP(rr, req)
		if rr.Code != 200 || rr.Header().Get("RateLimit-Limit") != "" {
			t.Fatalf("status=%d", rr.Code)
		}
	})

	t.Run("health substring not excluded", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/v1/unhealthy", nil)
		req.RemoteAddr = "203.0.113.50:1"
		h.ServeHTTP(rr, req)
		if rateHeader(rr, "RateLimit-Limit") != "60" {
			t.Fatalf("expected rate headers, got %v", rr.Header())
		}
	})

	t.Run("public then 429", func(t *testing.T) {
		req := func() *http.Request {
			r := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
			r.RemoteAddr = "203.0.113.77:9"
			return r
		}
		rr := httptest.NewRecorder()
		h.ServeHTTP(rr, req())
		if rr.Code != 200 || rateHeader(rr, "RateLimit-Limit") != "60" {
			t.Fatalf("first=%d limit=%s", rr.Code, rateHeader(rr, "RateLimit-Limit"))
		}
		if rem := rateHeader(rr, "RateLimit-Remaining"); rem == "" {
			t.Fatal("missing remaining")
		}
		rr = httptest.NewRecorder()
		h.ServeHTTP(rr, req())
		if rr.Code != 429 {
			t.Fatalf("second=%d body=%s", rr.Code, rr.Body.String())
		}
		if rateHeader(rr, "Retry-After") == "" {
			t.Fatal("missing Retry-After")
		}
		var body struct {
			Error struct {
				Code       string `json:"code"`
				RetryAfter int    `json:"retryAfter"`
			} `json:"error"`
		}
		if err := jsonDecode(rr, &body); err != nil {
			t.Fatal(err)
		}
		if body.Error.Code != "rate_limit_exceeded" || body.Error.RetryAfter < 1 {
			t.Fatalf("%+v", body)
		}
	})

	t.Run("invalid key 401", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.Header.Set("Authorization", "Bearer invalid")
		h.ServeHTTP(rr, req)
		if rr.Code != 401 {
			t.Fatalf("status=%d", rr.Code)
		}
		if strings.Contains(rr.Body.String(), "invalid") && strings.Contains(rr.Body.String(), "Bearer invalid") {
			t.Fatal("raw key leaked")
		}
	})

	t.Run("trusted limit", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.Header.Set("Authorization", "Bearer trusted-secret")
		h.ServeHTTP(rr, req)
		if rr.Code != 200 || rateHeader(rr, "RateLimit-Limit") != "300" {
			t.Fatalf("status=%d limit=%s", rr.Code, rateHeader(rr, "RateLimit-Limit"))
		}
		if rr.Header().Get("X-Test-Level") != "trusted" {
			t.Fatal("context missing")
		}
	})

	t.Run("internal limit", func(t *testing.T) {
		rr := httptest.NewRecorder()
		req := httptest.NewRequest(http.MethodGet, "/v1/countries", nil)
		req.Header.Set("X-API-Key", "internal-secret")
		h.ServeHTTP(rr, req)
		if rr.Code != 200 || rateHeader(rr, "RateLimit-Limit") != "10000" {
			t.Fatalf("status=%d limit=%s", rr.Code, rateHeader(rr, "RateLimit-Limit"))
		}
	})

	logs := logBuf.String()
	if strings.Contains(logs, "trusted-secret") || strings.Contains(logs, "internal-secret") {
		t.Fatalf("secrets in logs: %s", logs)
	}
}

func jsonDecode(rr *httptest.ResponseRecorder, dest any) error {
	return json.Unmarshal(rr.Body.Bytes(), dest)
}

// rateHeader reads RateLimit-* keys that bypass Go MIME canonicalization.
func rateHeader(rr *httptest.ResponseRecorder, key string) string {
	if vals := rr.Header()[key]; len(vals) > 0 {
		return vals[0]
	}
	return rr.Header().Get(key)
}
