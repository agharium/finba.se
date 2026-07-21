package download

import (
	"context"
	"crypto/rand"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

// Download streams req.URL to req.Destination with size and checksum checks.
func (c *Client) Download(ctx context.Context, req Request) (Result, error) {
	if err := validateRequest(req); err != nil {
		return Result{}, err
	}

	expectedSHA, err := normalizeExpectedSHA(req.ExpectedSHA256)
	if err != nil {
		return Result{}, err
	}

	httpReq, err := http.NewRequestWithContext(ctx, http.MethodGet, req.URL, nil)
	if err != nil {
		return Result{}, fmt.Errorf("%w: %v", ErrInvalidURL, err)
	}
	httpReq.Header.Set("User-Agent", c.userAgent)
	httpReq.Header.Set("Accept", "application/octet-stream")
	if c.token != "" {
		httpReq.Header.Set("Authorization", "Bearer "+c.token)
	}

	resp, err := c.httpClient.Do(httpReq)
	if err != nil {
		return Result{}, fmt.Errorf("download request: %w", sanitizeNetErr(err))
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		return Result{}, fmt.Errorf("%w: status %d", ErrHTTP, resp.StatusCode)
	}

	if cl, ok := contentLength(resp.Header); ok && req.ExpectedSize > 0 && cl != req.ExpectedSize {
		return Result{}, fmt.Errorf("%w: content-length %d != expected %d", ErrSizeMismatch, cl, req.ExpectedSize)
	}

	if err := os.MkdirAll(filepath.Dir(req.Destination), 0o755); err != nil {
		return Result{}, fmt.Errorf("create destination directory: %w", err)
	}

	tmpPath, err := uniqueTempPath(req.Destination, "part")
	if err != nil {
		return Result{}, err
	}
	cleanup := true
	defer func() {
		if cleanup {
			_ = os.Remove(tmpPath)
		}
	}()

	f, err := os.OpenFile(tmpPath, os.O_CREATE|os.O_WRONLY|os.O_EXCL, 0o644)
	if err != nil {
		return Result{}, fmt.Errorf("create temporary download file: %w", err)
	}

	hasher := sha256.New()
	writer := io.MultiWriter(f, hasher)
	reader := &contextReader{ctx: ctx, r: resp.Body}

	written, copyErr := io.Copy(writer, reader)
	closeErr := f.Close()
	if copyErr != nil {
		return Result{}, fmt.Errorf("stream download: %w", copyErr)
	}
	if closeErr != nil {
		return Result{}, fmt.Errorf("close temporary download file: %w", closeErr)
	}

	if req.ExpectedSize > 0 && written != req.ExpectedSize {
		return Result{}, fmt.Errorf("%w: expected %d bytes, got %d", ErrSizeMismatch, req.ExpectedSize, written)
	}

	digest := hex.EncodeToString(hasher.Sum(nil))
	if expectedSHA != "" && digest != expectedSHA {
		return Result{}, fmt.Errorf("%w: expected %s, got %s", ErrChecksumMismatch, expectedSHA, digest)
	}

	if err := publishFile(tmpPath, req.Destination); err != nil {
		return Result{}, err
	}
	cleanup = false

	return Result{
		Path:         req.Destination,
		Size:         written,
		SHA256:       digest,
		ContentType:  resp.Header.Get("Content-Type"),
		ETag:         resp.Header.Get("ETag"),
		LastModified: resp.Header.Get("Last-Modified"),
	}, nil
}

func validateRequest(req Request) error {
	if strings.TrimSpace(req.Destination) == "" {
		return fmt.Errorf("destination must not be empty")
	}
	raw := strings.TrimSpace(req.URL)
	if raw == "" {
		return fmt.Errorf("%w", ErrInvalidURL)
	}
	u, err := url.Parse(raw)
	if err != nil {
		return fmt.Errorf("%w: %v", ErrInvalidURL, err)
	}
	switch strings.ToLower(u.Scheme) {
	case "http", "https":
		if u.Host == "" {
			return fmt.Errorf("%w", ErrInvalidURL)
		}
		return nil
	case "":
		return fmt.Errorf("%w", ErrInvalidURL)
	default:
		return fmt.Errorf("%w: %s", ErrUnsupportedScheme, u.Scheme)
	}
}

func normalizeExpectedSHA(raw string) (string, error) {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return "", nil
	}
	lower := strings.ToLower(raw)
	if len(lower) != 64 {
		return "", fmt.Errorf("%w: want 64 hex characters", ErrMalformedSHA256)
	}
	for _, r := range lower {
		if (r < '0' || r > '9') && (r < 'a' || r > 'f') {
			return "", fmt.Errorf("%w: non-hex character", ErrMalformedSHA256)
		}
	}
	return lower, nil
}

func uniqueTempPath(destination, suffix string) (string, error) {
	var b [8]byte
	if _, err := rand.Read(b[:]); err != nil {
		return "", fmt.Errorf("generate temporary name: %w", err)
	}
	return destination + "." + hex.EncodeToString(b[:]) + "." + suffix, nil
}

func publishFile(tmpPath, destination string) error {
	if err := os.Remove(destination); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("remove existing destination: %w", err)
	}
	if err := os.Rename(tmpPath, destination); err != nil {
		return fmt.Errorf("publish destination: %w", err)
	}
	return nil
}

// contextReader interrupts reads when ctx is canceled.
type contextReader struct {
	ctx context.Context
	r   io.Reader
}

func (c *contextReader) Read(p []byte) (int, error) {
	if err := c.ctx.Err(); err != nil {
		return 0, err
	}
	return c.r.Read(p)
}

func sanitizeNetErr(err error) error {
	// Avoid leaking Authorization headers if they ever appear in error text.
	msg := err.Error()
	if strings.Contains(strings.ToLower(msg), "authorization") {
		return fmt.Errorf("network error")
	}
	return err
}

// contentLengthMismatch is retained for optional early checks by callers/tests.
func contentLength(header http.Header) (int64, bool) {
	raw := header.Get("Content-Length")
	if raw == "" {
		return 0, false
	}
	n, err := strconv.ParseInt(raw, 10, 64)
	if err != nil {
		return 0, false
	}
	return n, true
}
