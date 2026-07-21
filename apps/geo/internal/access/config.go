package access

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

// LimitConfig holds token-bucket limits for one access level.
type LimitConfig struct {
	RequestsPerMinute int
	Burst             int
}

// Config holds access-control and rate-limit settings.
// Secrets must never appear in String() or logs.
type Config struct {
	Public   LimitConfig
	Trusted  LimitConfig
	Internal LimitConfig

	InternalAPIKey string
	TrustedAPIKeys []string

	ClientTTL       time.Duration
	CleanupInterval time.Duration

	TrustProxyHeaders bool
}

// DefaultConfig returns production defaults with no API keys configured.
func DefaultConfig() Config {
	return Config{
		Public: LimitConfig{
			RequestsPerMinute: 60,
			Burst:             15,
		},
		Trusted: LimitConfig{
			RequestsPerMinute: 300,
			Burst:             60,
		},
		Internal: LimitConfig{
			RequestsPerMinute: 10000,
			Burst:             500,
		},
		ClientTTL:         30 * time.Minute,
		CleanupInterval:   5 * time.Minute,
		TrustProxyHeaders: false,
	}
}

// LoadFromEnv reads access configuration from environment variables.
func LoadFromEnv() (Config, error) {
	cfg := DefaultConfig()

	var err error
	if cfg.Public, err = limitFromEnv("GEO_PUBLIC_RATE_LIMIT_PER_MINUTE", "GEO_PUBLIC_RATE_LIMIT_BURST", cfg.Public); err != nil {
		return Config{}, err
	}
	if cfg.Trusted, err = limitFromEnv("GEO_TRUSTED_RATE_LIMIT_PER_MINUTE", "GEO_TRUSTED_RATE_LIMIT_BURST", cfg.Trusted); err != nil {
		return Config{}, err
	}
	if cfg.Internal, err = limitFromEnv("GEO_INTERNAL_RATE_LIMIT_PER_MINUTE", "GEO_INTERNAL_RATE_LIMIT_BURST", cfg.Internal); err != nil {
		return Config{}, err
	}

	cfg.InternalAPIKey = strings.TrimSpace(os.Getenv("GEO_INTERNAL_API_KEY"))
	cfg.TrustedAPIKeys = parseTrustedKeys(os.Getenv("GEO_TRUSTED_API_KEYS"))

	cfg.ClientTTL, err = durationEnv("GEO_ACCESS_CLIENT_TTL", cfg.ClientTTL)
	if err != nil {
		return Config{}, err
	}
	cfg.CleanupInterval, err = durationEnv("GEO_ACCESS_CLEANUP_INTERVAL", cfg.CleanupInterval)
	if err != nil {
		return Config{}, err
	}

	if raw := strings.TrimSpace(os.Getenv("GEO_TRUST_PROXY_HEADERS")); raw != "" {
		v, err := strconv.ParseBool(raw)
		if err != nil {
			return Config{}, fmt.Errorf("invalid GEO_TRUST_PROXY_HEADERS %q: use true|false", raw)
		}
		cfg.TrustProxyHeaders = v
	}

	if err := cfg.Validate(); err != nil {
		return Config{}, err
	}
	return cfg, nil
}

// Validate checks limit and TTL settings.
func (c Config) Validate() error {
	for name, lim := range map[string]LimitConfig{
		"public":   c.Public,
		"trusted":  c.Trusted,
		"internal": c.Internal,
	} {
		if lim.RequestsPerMinute <= 0 {
			return fmt.Errorf("%s requests per minute must be > 0", name)
		}
		if lim.Burst < 1 {
			return fmt.Errorf("%s burst must be >= 1", name)
		}
	}
	if c.ClientTTL <= 0 {
		return fmt.Errorf("client TTL must be > 0")
	}
	if c.CleanupInterval <= 0 {
		return fmt.Errorf("cleanup interval must be > 0")
	}
	return nil
}

// LimitFor returns the LimitConfig for a level.
func (c Config) LimitFor(level Level) LimitConfig {
	switch level {
	case LevelTrusted:
		return c.Trusted
	case LevelInternal:
		return c.Internal
	default:
		return c.Public
	}
}

// String redacts secrets for safe logging.
func (c Config) String() string {
	return fmt.Sprintf(
		"access{public=%d/%d trusted=%d/%d internal=%d/%d trusted_keys=%d internal_key=%t ttl=%s cleanup=%s trust_proxy=%t}",
		c.Public.RequestsPerMinute, c.Public.Burst,
		c.Trusted.RequestsPerMinute, c.Trusted.Burst,
		c.Internal.RequestsPerMinute, c.Internal.Burst,
		len(c.TrustedAPIKeys),
		c.InternalAPIKey != "",
		c.ClientTTL,
		c.CleanupInterval,
		c.TrustProxyHeaders,
	)
}

func (c Config) matchKey(key string) (Level, bool) {
	if c.InternalAPIKey != "" && secureEqual(key, c.InternalAPIKey) {
		return LevelInternal, true
	}
	for _, trusted := range c.TrustedAPIKeys {
		if secureEqual(key, trusted) {
			return LevelTrusted, true
		}
	}
	return "", false
}

func limitFromEnv(rpmKey, burstKey string, fallback LimitConfig) (LimitConfig, error) {
	out := fallback
	if raw := os.Getenv(rpmKey); raw != "" {
		n, err := strconv.Atoi(raw)
		if err != nil {
			return LimitConfig{}, fmt.Errorf("invalid %s %q: want integer", rpmKey, raw)
		}
		out.RequestsPerMinute = n
	}
	if raw := os.Getenv(burstKey); raw != "" {
		n, err := strconv.Atoi(raw)
		if err != nil {
			return LimitConfig{}, fmt.Errorf("invalid %s %q: want integer", burstKey, raw)
		}
		out.Burst = n
	}
	return out, nil
}

func parseTrustedKeys(raw string) []string {
	if strings.TrimSpace(raw) == "" {
		return nil
	}
	seen := make(map[string]struct{})
	var out []string
	for _, part := range strings.Split(raw, ",") {
		key := strings.TrimSpace(part)
		if key == "" {
			continue
		}
		if _, ok := seen[key]; ok {
			continue
		}
		seen[key] = struct{}{}
		out = append(out, key)
	}
	return out
}

func durationEnv(key string, fallback time.Duration) (time.Duration, error) {
	raw := os.Getenv(key)
	if raw == "" {
		return fallback, nil
	}
	if d, err := time.ParseDuration(raw); err == nil {
		return d, nil
	}
	secs, err := strconv.Atoi(raw)
	if err != nil {
		return 0, fmt.Errorf("invalid %s %q: use Go duration or integer seconds", key, raw)
	}
	return time.Duration(secs) * time.Second, nil
}
