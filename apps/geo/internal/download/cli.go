package download

import (
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"strings"
	"time"

	"finba.se/geo/internal/release"
)

// Exit codes for the download command.
const (
	ExitOK          = 0
	ExitValidation  = 1
	ExitOperational = 2
)

// CLIConfig holds parsed download CLI options.
type CLIConfig struct {
	Owner         string
	Repo          string
	Tag           string
	Asset         string
	Output        string
	Extract       bool
	ExtractOutput string
	SHA256        string
	Timeout       time.Duration
	JSON          bool
	MaxExtracted  int64
}

// ParseArgs parses download CLI arguments (excluding program name).
func ParseArgs(args []string) (CLIConfig, error) {
	fs := flag.NewFlagSet("download", flag.ContinueOnError)
	fs.SetOutput(io.Discard)

	var cfg CLIConfig
	var timeoutStr string
	fs.StringVar(&cfg.Owner, "owner", "dr5hn", "GitHub repository owner")
	fs.StringVar(&cfg.Repo, "repo", "countries-states-cities-database", "GitHub repository name")
	fs.StringVar(&cfg.Tag, "tag", "", "release tag (default: latest)")
	fs.StringVar(&cfg.Asset, "asset", "csv-cities.csv.gz", "exact asset file name")
	fs.StringVar(&cfg.Output, "output", "./data/downloads/csv-cities.csv.gz", "download destination path")
	fs.BoolVar(&cfg.Extract, "extract", false, "extract gzip after download")
	fs.StringVar(&cfg.ExtractOutput, "extract-output", "./data/downloads/cities.csv", "extracted CSV destination")
	fs.StringVar(&cfg.SHA256, "sha256", "", "optional expected SHA-256 of the downloaded asset")
	fs.StringVar(&timeoutStr, "timeout", "2m", "operation timeout (Go duration)")
	fs.BoolVar(&cfg.JSON, "json", false, "emit machine-readable JSON")

	fs.Usage = func() {}
	if err := fs.Parse(args); err != nil {
		return CLIConfig{}, err
	}
	if len(fs.Args()) > 0 {
		return CLIConfig{}, fmt.Errorf("unexpected arguments: %v", fs.Args())
	}

	d, err := time.ParseDuration(timeoutStr)
	if err != nil || d <= 0 {
		return CLIConfig{}, fmt.Errorf("invalid --timeout %q: use a positive Go duration such as 2m", timeoutStr)
	}
	cfg.Timeout = d
	cfg.MaxExtracted = DefaultMaxExtractedSize

	if cfg.Owner == "" || cfg.Repo == "" {
		return CLIConfig{}, fmt.Errorf("owner and repo must not be empty")
	}
	if cfg.Asset == "" || cfg.Output == "" {
		return CLIConfig{}, fmt.Errorf("asset and output must not be empty")
	}
	if cfg.Extract && cfg.ExtractOutput == "" {
		return CLIConfig{}, fmt.Errorf("extract-output must not be empty when --extract is set")
	}
	return cfg, nil
}

// UsageText returns help text for the download command.
func UsageText() string {
	return `Usage:
  download [flags]

Download a GitHub release asset (and optionally extract gzip).

Flags:
  --owner string           repository owner (default dr5hn)
  --repo string            repository name (default countries-states-cities-database)
  --tag string             release tag (default: latest release)
  --asset string           exact asset name (default csv-cities.csv.gz)
  --output string          download path (default ./data/downloads/csv-cities.csv.gz)
  --extract                extract gzip after download
  --extract-output string  extracted CSV path (default ./data/downloads/cities.csv)
  --sha256 string          optional expected SHA-256 of the downloaded asset
  --timeout duration       operation timeout (default 2m)
  --json                   emit machine-readable JSON
  -h, --help               show help

Exit codes:
  0  success
  1  validation failure (size/checksum/gzip/limit)
  2  usage or operational failure
`
}

// Execute runs the download command and returns a process exit code.
func Execute(ctx context.Context, cfg CLIConfig, stdout, stderr io.Writer) int {
	ctx, cancel := context.WithTimeout(ctx, cfg.Timeout)
	defer cancel()

	relClient := release.New(cfg.Owner, cfg.Repo)
	var (
		rel *release.Release
		err error
	)
	if cfg.Tag != "" {
		rel, err = relClient.GetByTag(ctx, cfg.Tag)
	} else {
		rel, err = relClient.Latest(ctx)
	}
	if err != nil {
		fmt.Fprintf(stderr, "release lookup failed: %v\n", err)
		return ExitOperational
	}

	asset, err := FindAsset(*rel, cfg.Asset)
	if err != nil {
		fmt.Fprintf(stderr, "asset selection failed: %v\n", err)
		if errors.Is(err, ErrAssetNotFound) || errors.Is(err, ErrDuplicateAsset) {
			return ExitValidation
		}
		return ExitOperational
	}

	dl := New()
	result, err := dl.Download(ctx, Request{
		URL:            asset.DownloadURL,
		Destination:    cfg.Output,
		ExpectedSize:   asset.Size,
		ExpectedSHA256: cfg.SHA256,
	})
	if err != nil {
		fmt.Fprintf(stderr, "download failed: %v\n", err)
		return classifyErr(err)
	}

	var extraction *ExtractionResult
	if cfg.Extract {
		ext, err := ExtractGzip(ctx, result.Path, cfg.ExtractOutput, ExtractOptions{MaxSize: cfg.MaxExtracted})
		if err != nil {
			fmt.Fprintf(stderr, "extraction failed: %v\n", err)
			return classifyErr(err)
		}
		extraction = &ext
	}

	report := Report{
		Repository: cfg.Owner + "/" + cfg.Repo,
		Release: ReportRelease{
			Tag:         rel.Tag,
			PublishedAt: rel.PublishedAt.UTC().Format(time.RFC3339),
		},
		Asset: ReportAsset{
			Name:         asset.Name,
			ExpectedSize: asset.Size,
		},
		Download: ReportDownload{
			Path:   result.Path,
			Size:   result.Size,
			SHA256: result.SHA256,
		},
		RateLimit: ReportRateLimit{
			Remaining: relClient.RateLimit().Remaining,
			Reset:     formatTime(relClient.RateLimit().Reset),
		},
		Complete: true,
	}
	if extraction != nil {
		report.Extraction = &ReportExtraction{
			Path:   extraction.Path,
			Size:   extraction.Size,
			SHA256: extraction.SHA256,
		}
	}

	if cfg.JSON {
		if err := writeJSON(stdout, report); err != nil {
			fmt.Fprintf(stderr, "write json: %v\n", err)
			return ExitOperational
		}
		return ExitOK
	}
	if err := writeHuman(stdout, report); err != nil {
		fmt.Fprintf(stderr, "write report: %v\n", err)
		return ExitOperational
	}
	return ExitOK
}

func classifyErr(err error) int {
	switch {
	case errors.Is(err, ErrSizeMismatch),
		errors.Is(err, ErrChecksumMismatch),
		errors.Is(err, ErrMalformedSHA256),
		errors.Is(err, ErrCorruptGzip),
		errors.Is(err, ErrEmptyExtracted),
		errors.Is(err, ErrExtractedSizeLimit),
		errors.Is(err, ErrAssetNotFound),
		errors.Is(err, ErrDuplicateAsset):
		return ExitValidation
	case errors.Is(err, context.DeadlineExceeded),
		errors.Is(err, context.Canceled):
		return ExitOperational
	default:
		return ExitOperational
	}
}

// Report is the machine/human summary for a download run.
type Report struct {
	Repository string            `json:"repository"`
	Release    ReportRelease     `json:"release"`
	Asset      ReportAsset       `json:"asset"`
	Download   ReportDownload    `json:"download"`
	Extraction *ReportExtraction `json:"extraction,omitempty"`
	RateLimit  ReportRateLimit   `json:"rateLimit"`
	Complete   bool              `json:"complete"`
}

type ReportRelease struct {
	Tag         string `json:"tag"`
	PublishedAt string `json:"publishedAt"`
}

type ReportAsset struct {
	Name         string `json:"name"`
	ExpectedSize int64  `json:"expectedSize"`
}

type ReportDownload struct {
	Path   string `json:"path"`
	Size   int64  `json:"size"`
	SHA256 string `json:"sha256"`
}

type ReportExtraction struct {
	Path   string `json:"path"`
	Size   int64  `json:"size"`
	SHA256 string `json:"sha256"`
}

type ReportRateLimit struct {
	Remaining int    `json:"remaining"`
	Reset     string `json:"reset"`
}

func writeJSON(w io.Writer, report Report) error {
	enc := json.NewEncoder(w)
	enc.SetIndent("", "  ")
	enc.SetEscapeHTML(true)
	return enc.Encode(report)
}

func writeHuman(w io.Writer, report Report) error {
	var b strings.Builder
	writeKV(&b, "Repository", report.Repository)
	writeKV(&b, "Release", report.Release.Tag)
	writeKV(&b, "Asset", report.Asset.Name)
	b.WriteByte('\n')
	writeKV(&b, "Downloaded path", report.Download.Path)
	writeKV(&b, "Downloaded size", formatSize(report.Download.Size))
	writeKV(&b, "Downloaded SHA-256", report.Download.SHA256)
	if report.Extraction != nil {
		b.WriteByte('\n')
		writeKV(&b, "Extracted path", report.Extraction.Path)
		writeKV(&b, "Extracted size", formatSize(report.Extraction.Size))
		writeKV(&b, "Extracted SHA-256", report.Extraction.SHA256)
	}
	b.WriteByte('\n')
	writeKV(&b, "Rate limit remaining", fmt.Sprintf("%d", report.RateLimit.Remaining))
	if report.RateLimit.Reset != "" {
		writeKV(&b, "Rate limit reset", report.RateLimit.Reset)
	}
	writeKV(&b, "Status", "COMPLETE")
	_, err := io.WriteString(w, b.String())
	return err
}

func writeKV(b *strings.Builder, key, value string) {
	const width = 22
	dots := width - len(key)
	if dots < 2 {
		dots = 2
	}
	fmt.Fprintf(b, "%s%s %s\n", key, strings.Repeat(".", dots), value)
}

func formatTime(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	return t.UTC().Format(time.RFC3339)
}

func formatSize(n int64) string {
	const (
		kb = 1024
		mb = 1024 * kb
		gb = 1024 * mb
	)
	switch {
	case n >= gb:
		return fmt.Sprintf("%.1f GB (%d bytes)", float64(n)/float64(gb), n)
	case n >= mb:
		return fmt.Sprintf("%.1f MB (%d bytes)", float64(n)/float64(mb), n)
	case n >= kb:
		return fmt.Sprintf("%.1f KB (%d bytes)", float64(n)/float64(kb), n)
	default:
		return fmt.Sprintf("%d bytes", n)
	}
}
