// Package download streams remote release assets to disk and extracts gzip payloads.
package download

import (
	"net/http"
	"os"
	"strings"
	"time"
)

const (
	defaultUserAgent   = "finba-geo"
	defaultTimeout     = 2 * time.Minute
	defaultMaxRedirect = 10
	// DefaultMaxExtractedSize is 512 MiB.
	DefaultMaxExtractedSize int64 = 512 << 20
)

// Client downloads release assets over HTTP(S).
type Client struct {
	httpClient  *http.Client
	userAgent   string
	token       string
	maxRedirect int
}

// New creates a Client with a default timeout and redirect policy.
func New() *Client {
	return NewWithHTTPClient(nil)
}

// NewWithHTTPClient creates a Client using the provided HTTP client.
// When httpClient is nil, a client with a 2-minute timeout is created.
// The redirect policy is always installed on a shallow copy of the client.
func NewWithHTTPClient(httpClient *http.Client) *Client {
	c := &Client{
		userAgent:   defaultUserAgent,
		token:       strings.TrimSpace(os.Getenv("GITHUB_TOKEN")),
		maxRedirect: defaultMaxRedirect,
	}
	base := httpClient
	if base == nil {
		base = &http.Client{Timeout: defaultTimeout}
	}
	cloned := *base
	cloned.CheckRedirect = c.checkRedirect
	c.httpClient = &cloned
	return c
}

func (c *Client) checkRedirect(req *http.Request, via []*http.Request) error {
	if len(via) >= c.maxRedirect {
		return ErrRedirectLimit
	}
	if req.URL == nil {
		return ErrInvalidURL
	}
	switch req.URL.Scheme {
	case "http", "https":
		return nil
	default:
		return ErrUnsupportedScheme
	}
}
