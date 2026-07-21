package config_test

import (
	"testing"
	"time"

	"finba.se/geo/internal/config"
)

func TestLoadDefaults(t *testing.T) {
	t.Setenv("PORT", "8080")
	t.Setenv("GEO_ENV", "")
	t.Setenv("GEO_DATABASE_PATH", "./data/geo.db")
	t.Setenv("LOG_LEVEL", "info")
	t.Setenv("HTTP_READ_TIMEOUT", "")
	t.Setenv("HTTP_READ_HEADER_TIMEOUT", "")
	t.Setenv("HTTP_WRITE_TIMEOUT", "")
	t.Setenv("HTTP_IDLE_TIMEOUT", "")
	t.Setenv("HTTP_SHUTDOWN_TIMEOUT", "")
	for _, k := range []string{
		"GEO_PUBLIC_RATE_LIMIT_PER_MINUTE", "GEO_PUBLIC_RATE_LIMIT_BURST",
		"GEO_TRUSTED_RATE_LIMIT_PER_MINUTE", "GEO_TRUSTED_RATE_LIMIT_BURST",
		"GEO_INTERNAL_RATE_LIMIT_PER_MINUTE", "GEO_INTERNAL_RATE_LIMIT_BURST",
		"GEO_INTERNAL_API_KEY", "GEO_TRUSTED_API_KEYS",
		"GEO_ACCESS_CLIENT_TTL", "GEO_ACCESS_CLEANUP_INTERVAL", "GEO_TRUST_PROXY_HEADERS",
	} {
		t.Setenv(k, "")
	}

	cfg, err := config.Load()
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Port != "8080" || cfg.DatabasePath != "./data/geo.db" {
		t.Fatalf("%+v", cfg)
	}
	if cfg.Env != "development" {
		t.Fatalf("env=%q", cfg.Env)
	}
	if cfg.ReadTimeout != 5*time.Second {
		t.Fatalf("read timeout=%v", cfg.ReadTimeout)
	}
	if cfg.ReadHeaderTimeout != 5*time.Second {
		t.Fatalf("read header timeout=%v", cfg.ReadHeaderTimeout)
	}
	if cfg.Access.Public.RequestsPerMinute != 60 {
		t.Fatalf("access defaults=%+v", cfg.Access.Public)
	}
}

func TestLoadInvalidLogLevel(t *testing.T) {
	t.Setenv("PORT", "8080")
	t.Setenv("GEO_DATABASE_PATH", "./data/geo.db")
	t.Setenv("LOG_LEVEL", "verbose")
	_, err := config.Load()
	if err == nil {
		t.Fatal("expected error")
	}
}

func TestLoadInvalidEnv(t *testing.T) {
	t.Setenv("PORT", "8080")
	t.Setenv("GEO_DATABASE_PATH", "./data/geo.db")
	t.Setenv("LOG_LEVEL", "info")
	t.Setenv("GEO_ENV", "prod")
	_, err := config.Load()
	if err == nil {
		t.Fatal("expected error")
	}
}
