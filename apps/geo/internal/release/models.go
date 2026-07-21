package release

import "time"

// Release is a repository release mapped from the GitHub API.
type Release struct {
	Tag         string
	Name        string
	Body        string
	Draft       bool
	Prerelease  bool
	PublishedAt time.Time
	Assets      []Asset
}

// Asset is a release attachment mapped from the GitHub API.
type Asset struct {
	ID          int64
	Name        string
	ContentType string
	Size        int64
	DownloadURL string
}

// RateLimit holds the latest observed GitHub secondary rate-limit counters.
type RateLimit struct {
	Remaining int
	Reset     time.Time
}
