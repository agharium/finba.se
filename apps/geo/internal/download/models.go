package download

import "time"

// Request configures a streaming asset download.
type Request struct {
	URL            string
	Destination    string
	ExpectedSize   int64
	ExpectedSHA256 string
}

// Result describes a successfully published downloaded file.
type Result struct {
	Path         string
	Size         int64
	SHA256       string
	ContentType  string
	ETag         string
	LastModified string
}

// ExtractOptions configures gzip extraction.
type ExtractOptions struct {
	// MaxSize limits decompressed bytes. Zero means DefaultMaxExtractedSize.
	MaxSize int64
}

// ExtractionResult describes a successfully published extracted file.
type ExtractionResult struct {
	Path   string
	Size   int64
	SHA256 string
}

// HTTPMeta captures optional response metadata from the final download response.
type HTTPMeta struct {
	ContentType  string
	ETag         string
	LastModified string
	Status       int
	FetchedAt    time.Time
}
