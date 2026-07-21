package download

import (
	"compress/gzip"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"io"
	"os"
	"path/filepath"
	"strings"
)

// ExtractGzip decompresses a single gzip stream from source to destination.
func ExtractGzip(ctx context.Context, source, destination string, opts ExtractOptions) (ExtractionResult, error) {
	if strings.TrimSpace(source) == "" || strings.TrimSpace(destination) == "" {
		return ExtractionResult{}, fmt.Errorf("source and destination must not be empty")
	}
	maxSize := opts.MaxSize
	if maxSize <= 0 {
		maxSize = DefaultMaxExtractedSize
	}

	in, err := os.Open(source)
	if err != nil {
		return ExtractionResult{}, fmt.Errorf("open gzip source: %w", err)
	}
	defer in.Close()

	gz, err := gzip.NewReader(in)
	if err != nil {
		return ExtractionResult{}, fmt.Errorf("%w: %v", ErrCorruptGzip, err)
	}
	defer gz.Close()

	if err := os.MkdirAll(filepath.Dir(destination), 0o755); err != nil {
		return ExtractionResult{}, fmt.Errorf("create extraction directory: %w", err)
	}

	tmpPath, err := uniqueTempPath(destination, "extract")
	if err != nil {
		return ExtractionResult{}, err
	}
	cleanup := true
	defer func() {
		if cleanup {
			_ = os.Remove(tmpPath)
		}
	}()

	out, err := os.OpenFile(tmpPath, os.O_CREATE|os.O_WRONLY|os.O_EXCL, 0o644)
	if err != nil {
		return ExtractionResult{}, fmt.Errorf("create temporary extraction file: %w", err)
	}

	hasher := sha256.New()
	limited := &limitedWriter{w: io.MultiWriter(out, hasher), limit: maxSize}
	reader := &contextReader{ctx: ctx, r: gz}

	written, copyErr := io.Copy(limited, reader)
	closeOut := out.Close()
	closeGz := gz.Close()

	if copyErr != nil {
		if limited.hitLimit {
			return ExtractionResult{}, fmt.Errorf("%w: limit %d bytes", ErrExtractedSizeLimit, maxSize)
		}
		return ExtractionResult{}, fmt.Errorf("%w: %v", ErrCorruptGzip, copyErr)
	}
	if closeOut != nil {
		return ExtractionResult{}, fmt.Errorf("close temporary extraction file: %w", closeOut)
	}
	if closeGz != nil {
		return ExtractionResult{}, fmt.Errorf("%w: %v", ErrCorruptGzip, closeGz)
	}
	if written == 0 {
		return ExtractionResult{}, fmt.Errorf("%w", ErrEmptyExtracted)
	}

	digest := hex.EncodeToString(hasher.Sum(nil))
	if err := publishFile(tmpPath, destination); err != nil {
		return ExtractionResult{}, err
	}
	cleanup = false

	return ExtractionResult{
		Path:   destination,
		Size:   written,
		SHA256: digest,
	}, nil
}

type limitedWriter struct {
	w        io.Writer
	n        int64
	limit    int64
	hitLimit bool
}

func (l *limitedWriter) Write(p []byte) (int, error) {
	if l.n+int64(len(p)) > l.limit {
		l.hitLimit = true
		return 0, ErrExtractedSizeLimit
	}
	n, err := l.w.Write(p)
	l.n += int64(n)
	return n, err
}
