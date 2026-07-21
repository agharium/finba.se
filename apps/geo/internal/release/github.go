package release

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"time"
)

// githubRelease is the GitHub API JSON shape; kept private to this package.
type githubRelease struct {
	TagName     string        `json:"tag_name"`
	Name        string        `json:"name"`
	Body        string        `json:"body"`
	Draft       bool          `json:"draft"`
	Prerelease  bool          `json:"prerelease"`
	PublishedAt time.Time     `json:"published_at"`
	Assets      []githubAsset `json:"assets"`
}

type githubAsset struct {
	ID                 int64  `json:"id"`
	Name               string `json:"name"`
	ContentType        string `json:"content_type"`
	Size               int64  `json:"size"`
	BrowserDownloadURL string `json:"browser_download_url"`
}

// Latest returns the latest published non-prerelease release for the repository.
func (c *Client) Latest(ctx context.Context) (*Release, error) {
	path := fmt.Sprintf("/repos/%s/%s/releases/latest", url.PathEscape(c.owner), url.PathEscape(c.repository))
	var raw githubRelease
	if err := c.getJSON(ctx, path, &raw); err != nil {
		return nil, err
	}
	rel := mapRelease(raw)
	return &rel, nil
}

// Releases lists releases for the repository (first page from GitHub).
func (c *Client) Releases(ctx context.Context) ([]Release, error) {
	path := fmt.Sprintf("/repos/%s/%s/releases", url.PathEscape(c.owner), url.PathEscape(c.repository))
	var raw []githubRelease
	if err := c.getJSON(ctx, path, &raw); err != nil {
		return nil, err
	}
	out := make([]Release, 0, len(raw))
	for _, item := range raw {
		out = append(out, mapRelease(item))
	}
	return out, nil
}

// GetByTag returns the release for the given tag.
func (c *Client) GetByTag(ctx context.Context, tag string) (*Release, error) {
	tag = strings.TrimSpace(tag)
	if tag == "" {
		return nil, fmt.Errorf("tag must not be empty")
	}
	path := fmt.Sprintf("/repos/%s/%s/releases/tags/%s",
		url.PathEscape(c.owner),
		url.PathEscape(c.repository),
		url.PathEscape(tag),
	)
	var raw githubRelease
	if err := c.getJSON(ctx, path, &raw); err != nil {
		return nil, err
	}
	rel := mapRelease(raw)
	return &rel, nil
}

func (c *Client) getJSON(ctx context.Context, path string, dest any) error {
	reqURL := c.baseURL + path
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, reqURL, nil)
	if err != nil {
		return fmt.Errorf("build request: %w", err)
	}
	req.Header.Set("Accept", "application/vnd.github+json")
	req.Header.Set("User-Agent", c.userAgent)
	if c.token != "" {
		req.Header.Set("Authorization", "Bearer "+c.token)
	}

	resp, err := c.httpClient.Do(req)
	if err != nil {
		return fmt.Errorf("github request: %w", err)
	}
	defer resp.Body.Close()

	c.storeRateLimit(parseRateLimit(resp.Header))

	body, err := io.ReadAll(io.LimitReader(resp.Body, 8<<20))
	if err != nil {
		return fmt.Errorf("read github response: %w", err)
	}

	if resp.StatusCode >= 200 && resp.StatusCode < 300 {
		if err := json.Unmarshal(body, dest); err != nil {
			return fmt.Errorf("decode github response: %w", err)
		}
		return nil
	}

	return mapStatusError(resp.StatusCode)
}

func mapStatusError(status int) error {
	switch status {
	case http.StatusNotFound:
		return fmt.Errorf("%w", ErrReleaseNotFound)
	case http.StatusForbidden, http.StatusTooManyRequests:
		return fmt.Errorf("%w", ErrRateLimited)
	default:
		if status >= 500 {
			return fmt.Errorf("%w: status %d", ErrGitHub, status)
		}
		return fmt.Errorf("%w: unexpected status %d", ErrGitHub, status)
	}
}

func parseRateLimit(h http.Header) RateLimit {
	var rl RateLimit
	if rem := h.Get("X-RateLimit-Remaining"); rem != "" {
		if n, err := strconv.Atoi(rem); err == nil {
			rl.Remaining = n
		}
	}
	if reset := h.Get("X-RateLimit-Reset"); reset != "" {
		if secs, err := strconv.ParseInt(reset, 10, 64); err == nil {
			rl.Reset = time.Unix(secs, 0).UTC()
		}
	}
	return rl
}

func mapRelease(raw githubRelease) Release {
	assets := make([]Asset, 0, len(raw.Assets))
	for _, a := range raw.Assets {
		assets = append(assets, Asset{
			ID:          a.ID,
			Name:        a.Name,
			ContentType: a.ContentType,
			Size:        a.Size,
			DownloadURL: a.BrowserDownloadURL,
		})
	}
	return Release{
		Tag:         raw.TagName,
		Name:        raw.Name,
		Body:        raw.Body,
		Draft:       raw.Draft,
		Prerelease:  raw.Prerelease,
		PublishedAt: raw.PublishedAt,
		Assets:      assets,
	}
}
