// Package release retrieves GitHub Releases metadata over the GitHub REST API.
package release

import (
	"net/http"
	"os"
	"strings"
	"sync"
	"time"
)

const (
	defaultBaseURL   = "https://api.github.com"
	defaultUserAgent = "finba-geo"
	defaultTimeout   = 30 * time.Second
)

// Client talks to the GitHub Releases API for a single repository.
type Client struct {
	httpClient *http.Client
	baseURL    string
	owner      string
	repository string
	userAgent  string
	token      string

	mu        sync.RWMutex
	rateLimit RateLimit
}

// New creates a Client for owner/repository using a default HTTP client.
func New(owner, repository string) *Client {
	return NewWithHTTPClient(owner, repository, &http.Client{Timeout: defaultTimeout})
}

// NewWithHTTPClient creates a Client with a custom HTTP client (useful for tests).
func NewWithHTTPClient(owner, repository string, httpClient *http.Client) *Client {
	if httpClient == nil {
		httpClient = &http.Client{Timeout: defaultTimeout}
	}
	return &Client{
		httpClient: httpClient,
		baseURL:    defaultBaseURL,
		owner:      strings.TrimSpace(owner),
		repository: strings.TrimSpace(repository),
		userAgent:  defaultUserAgent,
		token:      strings.TrimSpace(os.Getenv("GITHUB_TOKEN")),
	}
}

// SetBaseURL overrides the API base URL (intended for tests).
func (c *Client) SetBaseURL(baseURL string) {
	c.baseURL = strings.TrimRight(baseURL, "/")
}

// Owner returns the configured repository owner.
func (c *Client) Owner() string { return c.owner }

// Repository returns the configured repository name.
func (c *Client) Repository() string { return c.repository }

// RateLimit returns the most recently observed GitHub rate-limit headers.
func (c *Client) RateLimit() RateLimit {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.rateLimit
}

func (c *Client) storeRateLimit(rl RateLimit) {
	c.mu.Lock()
	c.rateLimit = rl
	c.mu.Unlock()
}
