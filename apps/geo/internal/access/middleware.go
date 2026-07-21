package access

import (
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"strconv"
	"time"
)

// Middleware authenticates API keys, classifies clients, and enforces rate limits.
type Middleware struct {
	resolver *Resolver
	manager  *LimiterManager
	logger   *slog.Logger
}

// NewMiddleware constructs access middleware.
func NewMiddleware(cfg Config, manager *LimiterManager, logger *slog.Logger) *Middleware {
	if logger == nil {
		logger = slog.Default()
	}
	return &Middleware{
		resolver: NewResolver(cfg),
		manager:  manager,
		logger:   logger,
	}
}

// Handler wraps next with access control.
func (m *Middleware) Handler(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if isExcluded(r) {
			next.ServeHTTP(w, r)
			return
		}

		client, err := m.resolver.Resolve(r)
		if err != nil {
			m.writeAuthError(w, r, err)
			return
		}

		decision := m.manager.Allow(client)
		writeRateHeaders(w, decision)

		if !decision.Allowed {
			m.logger.Warn("rate limit exceeded",
				"access_level", string(client.Level),
				"client_id", LogClientID(client),
				"method", r.Method,
				"path", r.URL.Path,
				"status", http.StatusTooManyRequests,
				"retry_after", int(decision.RetryAfter.Seconds()),
				"request_id", r.Header.Get("X-Request-ID"),
			)
			writeRateLimitError(w, decision.RetryAfter)
			return
		}

		ctx := withClient(r.Context(), client)
		next.ServeHTTP(w, r.WithContext(ctx))
	})
}

func isExcluded(r *http.Request) bool {
	if r.Method != http.MethodGet {
		return false
	}
	switch r.URL.Path {
	case "/health", "/version", "/v1/version":
		return true
	default:
		return false
	}
}

func writeRateHeaders(w http.ResponseWriter, d Decision) {
	h := w.Header()
	// Assign directly so Go does not canonicalize RateLimit-* to Ratelimit-*.
	h["RateLimit-Limit"] = []string{strconv.Itoa(d.Limit)}
	h["RateLimit-Remaining"] = []string{strconv.Itoa(d.Remaining)}
	h["RateLimit-Reset"] = []string{strconv.Itoa(int(d.ResetAfter.Seconds()))}
	if !d.Allowed {
		secs := int(d.RetryAfter.Seconds())
		if secs < 1 {
			secs = 1
		}
		h["Retry-After"] = []string{strconv.Itoa(secs)}
	}
}

func (m *Middleware) writeAuthError(w http.ResponseWriter, r *http.Request, err error) {
	code := "invalid_api_key"
	message := "Invalid API credentials."
	if errors.Is(err, ErrMalformedAuthorization) {
		code = "invalid_authorization"
		message = "Invalid authorization header."
	}

	m.logger.Warn("authentication failed",
		"method", r.Method,
		"path", r.URL.Path,
		"status", http.StatusUnauthorized,
		"error_code", code,
		"request_id", r.Header.Get("X-Request-ID"),
	)

	writeJSONError(w, http.StatusUnauthorized, errorBody{
		Error: errorDetail{Code: code, Message: message},
	})
}

func writeRateLimitError(w http.ResponseWriter, retryAfter time.Duration) {
	secs := int(retryAfter.Seconds())
	if secs < 1 {
		secs = 1
	}
	writeJSONError(w, http.StatusTooManyRequests, errorBody{
		Error: errorDetail{
			Code:       "rate_limit_exceeded",
			Message:    "Too many requests.",
			RetryAfter: secs,
		},
	})
}

type errorBody struct {
	Error errorDetail `json:"error"`
}

type errorDetail struct {
	Code       string `json:"code"`
	Message    string `json:"message"`
	RetryAfter int    `json:"retryAfter,omitempty"`
}

func writeJSONError(w http.ResponseWriter, status int, body errorBody) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	enc := json.NewEncoder(w)
	enc.SetEscapeHTML(true)
	_ = enc.Encode(body)
}
