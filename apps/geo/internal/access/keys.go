package access

import (
	"crypto/sha256"
	"crypto/subtle"
	"encoding/hex"
	"net/http"
	"strings"
)

const fingerprintHexLen = 12

// extractAPIKey returns the supplied API key and whether it came from Authorization.
// Precedence: Authorization Bearer, then X-API-Key.
// An empty key with nil error means anonymous Public access.
func extractAPIKey(r *http.Request) (key string, fromAuthorization bool, err error) {
	auth := strings.TrimSpace(r.Header.Get("Authorization"))
	if auth != "" {
		token, err := parseBearer(auth)
		if err != nil {
			return "", true, err
		}
		return token, true, nil
	}

	apiKey := strings.TrimSpace(r.Header.Get("X-API-Key"))
	if apiKey == "" {
		return "", false, nil
	}
	return apiKey, false, nil
}

func parseBearer(authorization string) (string, error) {
	const prefix = "bearer "
	lower := strings.ToLower(authorization)
	if !strings.HasPrefix(lower, prefix) {
		return "", ErrMalformedAuthorization
	}
	// Preserve original length for slice after scheme.
	token := strings.TrimSpace(authorization[len(prefix):])
	if token == "" {
		return "", ErrMalformedAuthorization
	}
	// Reject extra scheme-like garbage without a space (already handled by prefix).
	if strings.ContainsAny(token, " \t") {
		// Allow tokens that somehow contain spaces? Spec says trim surrounding only.
		// Internal spaces are unusual for API keys; treat as malformed for safety.
		return "", ErrMalformedAuthorization
	}
	return token, nil
}

// FingerprintKey returns a short stable fingerprint of an API key (hex prefix of SHA-256).
func FingerprintKey(key string) string {
	sum := sha256.Sum256([]byte(key))
	return hex.EncodeToString(sum[:])[:fingerprintHexLen]
}

// HashClientIP returns a short stable hash of an IP for safe logging.
func HashClientIP(ip string) string {
	sum := sha256.Sum256([]byte(ip))
	return hex.EncodeToString(sum[:])[:fingerprintHexLen]
}

func secureEqual(a, b string) bool {
	if len(a) != len(b) {
		// Perform a compare against itself to keep work roughly constant.
		subtle.ConstantTimeCompare([]byte(a), []byte(a))
		return false
	}
	return subtle.ConstantTimeCompare([]byte(a), []byte(b)) == 1
}

// LogClientID returns a safe identifier for structured logs.
func LogClientID(client Client) string {
	switch client.Level {
	case LevelTrusted, LevelInternal:
		return string(client.Level) + ":" + client.Identifier
	default:
		return "ip:" + HashClientIP(client.Identifier)
	}
}

// LimiterKey builds the unique bucket identity for a client.
func LimiterKey(client Client) string {
	return string(client.Level) + ":" + client.Identifier
}
