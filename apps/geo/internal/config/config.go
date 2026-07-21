package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"

	"finba.se/geo/internal/access"
)

// Config holds HTTP API runtime settings.
type Config struct {
	Port              string
	Env               string
	DatabasePath      string
	LogLevel          string
	ReadTimeout       time.Duration
	ReadHeaderTimeout time.Duration
	WriteTimeout      time.Duration
	IdleTimeout       time.Duration
	ShutdownTimeout   time.Duration
	Access            access.Config
}

// Load reads configuration from environment variables with sensible defaults.
func Load() (Config, error) {
	cfg := Config{
		Port:         getenv("PORT", "8080"),
		Env:          strings.ToLower(getenv("GEO_ENV", "development")),
		DatabasePath: getenv("GEO_DATABASE_PATH", "./data/geo.db"),
		LogLevel:     strings.ToLower(getenv("LOG_LEVEL", "info")),
	}

	var err error
	cfg.ReadTimeout, err = durationEnv("HTTP_READ_TIMEOUT", 5*time.Second)
	if err != nil {
		return Config{}, err
	}
	cfg.ReadHeaderTimeout, err = durationEnv("HTTP_READ_HEADER_TIMEOUT", 5*time.Second)
	if err != nil {
		return Config{}, err
	}
	cfg.WriteTimeout, err = durationEnv("HTTP_WRITE_TIMEOUT", 10*time.Second)
	if err != nil {
		return Config{}, err
	}
	cfg.IdleTimeout, err = durationEnv("HTTP_IDLE_TIMEOUT", 60*time.Second)
	if err != nil {
		return Config{}, err
	}
	cfg.ShutdownTimeout, err = durationEnv("HTTP_SHUTDOWN_TIMEOUT", 10*time.Second)
	if err != nil {
		return Config{}, err
	}

	cfg.Access, err = access.LoadFromEnv()
	if err != nil {
		return Config{}, err
	}

	if cfg.Port == "" {
		return Config{}, fmt.Errorf("PORT must not be empty")
	}
	if cfg.DatabasePath == "" {
		return Config{}, fmt.Errorf("GEO_DATABASE_PATH must not be empty")
	}

	switch cfg.LogLevel {
	case "debug", "info", "warn", "error":
	default:
		return Config{}, fmt.Errorf("invalid LOG_LEVEL %q (want debug|info|warn|error)", cfg.LogLevel)
	}

	switch cfg.Env {
	case "development", "staging", "production", "test":
	default:
		return Config{}, fmt.Errorf("invalid GEO_ENV %q (want development|staging|production|test)", cfg.Env)
	}

	return cfg, nil
}

func getenv(key, fallback string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return fallback
}

func durationEnv(key string, fallback time.Duration) (time.Duration, error) {
	raw := os.Getenv(key)
	if raw == "" {
		return fallback, nil
	}
	// Accept Go durations ("5s") or integer seconds.
	if d, err := time.ParseDuration(raw); err == nil {
		return d, nil
	}
	secs, err := strconv.Atoi(raw)
	if err != nil {
		return 0, fmt.Errorf("invalid %s %q: use Go duration or integer seconds", key, raw)
	}
	return time.Duration(secs) * time.Second, nil
}
