// Package access provides API key authentication, client classification,
// and per-client token-bucket rate limiting for the Geo HTTP API.
package access

import (
	"context"
	"errors"
	"net/http"
)

// Level is the access tier assigned to a request.
type Level string

const (
	LevelPublic   Level = "public"
	LevelTrusted  Level = "trusted"
	LevelInternal Level = "internal"
)

// Client is a safe identity for rate limiting and logging.
// It never contains a raw API key.
type Client struct {
	Level      Level
	Identifier string
}

type contextKey struct{}

// ErrMalformedAuthorization is returned for invalid Authorization header syntax.
var ErrMalformedAuthorization = errors.New("malformed authorization header")

// ErrInvalidAPIKey is returned when a supplied API key is not recognized.
var ErrInvalidAPIKey = errors.New("invalid api key")

// ErrMissingClientIP is returned when a public client IP cannot be determined.
var ErrMissingClientIP = errors.New("missing client ip")

// ClientFromContext returns the Client attached by Middleware, if any.
func ClientFromContext(ctx context.Context) (Client, bool) {
	c, ok := ctx.Value(contextKey{}).(Client)
	return c, ok
}

// LevelFromContext returns the access Level attached by Middleware, if any.
func LevelFromContext(ctx context.Context) (Level, bool) {
	c, ok := ClientFromContext(ctx)
	if !ok {
		return "", false
	}
	return c.Level, true
}

func withClient(ctx context.Context, client Client) context.Context {
	return context.WithValue(ctx, contextKey{}, client)
}

// Resolver classifies requests into access levels.
type Resolver struct {
	cfg Config
}

// NewResolver constructs a Resolver from validated configuration.
func NewResolver(cfg Config) *Resolver {
	return &Resolver{cfg: cfg}
}

// Resolve inspects credentials and returns a safe Client identity.
func (r *Resolver) Resolve(req *http.Request) (Client, error) {
	key, _, err := extractAPIKey(req)
	if err != nil {
		return Client{}, err
	}
	if key == "" {
		ip := RealClientIP(req, r.cfg.TrustProxyHeaders)
		if ip == "" {
			return Client{}, ErrMissingClientIP
		}
		return Client{Level: LevelPublic, Identifier: ip}, nil
	}

	level, ok := r.cfg.matchKey(key)
	if !ok {
		return Client{}, ErrInvalidAPIKey
	}
	return Client{
		Level:      level,
		Identifier: FingerprintKey(key),
	}, nil
}
