package update

import (
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"log/slog"
	"strings"
	"time"

	"finba.se/geo/internal/download"
	"finba.se/geo/internal/release"
)

// Exit codes for the update command.
const (
	ExitOK          = 0
	ExitValidation  = 1
	ExitOperational = 2
)

// CLIConfig holds parsed update CLI options.
type CLIConfig struct {
	Owner         string
	Repo          string
	Tag           string
	Asset         string
	Force         bool
	Workspace     string
	Database      string
	KeepWorkspace bool
	JSON          bool
	Timeout       time.Duration
	LockPath      string
}

// ParseArgs parses update CLI arguments (excluding program name).
func ParseArgs(args []string) (CLIConfig, error) {
	fs := flag.NewFlagSet("update", flag.ContinueOnError)
	fs.SetOutput(io.Discard)

	var cfg CLIConfig
	var timeoutStr string
	fs.StringVar(&cfg.Owner, "owner", "dr5hn", "GitHub repository owner")
	fs.StringVar(&cfg.Repo, "repo", "countries-states-cities-database", "GitHub repository name")
	fs.StringVar(&cfg.Tag, "tag", "", "release tag (default: latest)")
	fs.StringVar(&cfg.Asset, "asset", "csv-cities.csv.gz", "exact asset file name")
	fs.BoolVar(&cfg.Force, "force", false, "ignore version comparison and rebuild")
	fs.StringVar(&cfg.Workspace, "workspace", "./data/work", "temporary workspace directory")
	fs.StringVar(&cfg.Database, "database", "./data/geo.db", "production database path")
	fs.BoolVar(&cfg.KeepWorkspace, "keep-workspace", false, "preserve workspace files after the run")
	fs.BoolVar(&cfg.JSON, "json", false, "emit machine-readable JSON")
	fs.StringVar(&timeoutStr, "timeout", "10m", "operation timeout (Go duration)")
	fs.StringVar(&cfg.LockPath, "lock", "./data/update.lock", "update lock file path")

	fs.Usage = func() {}
	if err := fs.Parse(args); err != nil {
		return CLIConfig{}, err
	}
	if len(fs.Args()) > 0 {
		return CLIConfig{}, fmt.Errorf("unexpected arguments: %v", fs.Args())
	}

	d, err := time.ParseDuration(timeoutStr)
	if err != nil || d <= 0 {
		return CLIConfig{}, fmt.Errorf("invalid --timeout %q: use a positive Go duration such as 10m", timeoutStr)
	}
	cfg.Timeout = d
	return cfg, nil
}

// UsageText returns help text for the update command.
func UsageText() string {
	return `Usage:
  update [flags]

Refresh the Geo SQLite catalog from a GitHub release asset.

Flags:
  --owner string        repository owner (default dr5hn)
  --repo string         repository name (default countries-states-cities-database)
  --tag string          release tag (default: latest)
  --asset string        exact asset name (default csv-cities.csv.gz)
  --force               rebuild even when already current
  --workspace string    temporary workspace (default ./data/work)
  --database string     production database (default ./data/geo.db)
  --keep-workspace      keep workspace files after the run
  --lock string         lock file path (default ./data/update.lock)
  --timeout duration    overall timeout (default 10m)
  --json                emit machine-readable JSON
  -h, --help            show help

Exit codes:
  0  success or already up to date
  1  validation / inspection failure
  2  usage or operational failure
`
}

// Execute runs the update command and returns a process exit code.
func Execute(ctx context.Context, cfg CLIConfig, stdout, stderr io.Writer) int {
	logger := slog.New(slog.NewJSONHandler(stderr, &slog.HandlerOptions{Level: slog.LevelInfo}))

	result, err := Run(ctx, Options{
		Owner:         cfg.Owner,
		Repo:          cfg.Repo,
		Tag:           cfg.Tag,
		Asset:         cfg.Asset,
		Force:         cfg.Force,
		Workspace:     cfg.Workspace,
		Database:      cfg.Database,
		LockPath:      cfg.LockPath,
		KeepWorkspace: cfg.KeepWorkspace,
		Timeout:       cfg.Timeout,
		Logger:        logger,
	})
	if err != nil {
		fmt.Fprintf(stderr, "update failed: %v\n", err)
		return classifyErr(err)
	}

	if cfg.JSON {
		if err := json.NewEncoder(stdout).Encode(result); err != nil {
			fmt.Fprintf(stderr, "write json: %v\n", err)
			return ExitOperational
		}
		return ExitOK
	}

	if err := writeHuman(stdout, result); err != nil {
		fmt.Fprintf(stderr, "write report: %v\n", err)
		return ExitOperational
	}
	return ExitOK
}

func classifyErr(err error) int {
	switch {
	case errors.Is(err, ErrAlreadyLocked):
		return ExitOperational
	case errors.Is(err, ErrNotReady),
		errors.Is(err, ErrValidation),
		errors.Is(err, download.ErrSizeMismatch),
		errors.Is(err, download.ErrChecksumMismatch),
		errors.Is(err, download.ErrMalformedSHA256),
		errors.Is(err, download.ErrCorruptGzip),
		errors.Is(err, download.ErrEmptyExtracted),
		errors.Is(err, download.ErrExtractedSizeLimit),
		errors.Is(err, download.ErrAssetNotFound),
		errors.Is(err, download.ErrDuplicateAsset):
		return ExitValidation
	case errors.Is(err, context.DeadlineExceeded),
		errors.Is(err, context.Canceled),
		errors.Is(err, release.ErrRateLimited),
		errors.Is(err, release.ErrReleaseNotFound),
		errors.Is(err, release.ErrGitHub):
		return ExitOperational
	default:
		// Import failures and most unexpected errors are operational unless clearly validation.
		msg := strings.ToLower(err.Error())
		if strings.Contains(msg, "import:") || strings.Contains(msg, "blank ") || strings.Contains(msg, "invalid timezone") {
			return ExitValidation
		}
		return ExitOperational
	}
}

func writeHuman(w io.Writer, result Result) error {
	var b strings.Builder
	if result.Reason == "already_up_to_date" {
		writeKV(&b, "Current catalog", result.Version)
		writeKV(&b, "Status", "Already up to date")
		_, err := io.WriteString(w, b.String())
		return err
	}

	from := result.FromVersion
	if from == "" {
		from = "(none)"
	}
	writeKV(&b, "Current catalog", from)
	writeKV(&b, "Latest release", result.ToVersion)
	b.WriteByte('\n')
	writeKV(&b, "Downloading", statusOK(result.Downloaded))
	writeKV(&b, "Extracting", statusOK(result.Downloaded))
	writeKV(&b, "Importing", statusOK(result.Validated || result.Published))
	if result.Validated {
		writeKV(&b, "Inspecting", "READY")
	}
	if result.Published {
		writeKV(&b, "Publishing", "OK")
	}
	if result.Updated {
		b.WriteByte('\n')
		fmt.Fprintf(&b, "Updated:\n\n%s\n↓\n%s\n", from, result.ToVersion)
	}
	_, err := io.WriteString(w, b.String())
	return err
}

func statusOK(ok bool) string {
	if ok {
		return "OK"
	}
	return "-"
}

func writeKV(b *strings.Builder, key, value string) {
	const width = 22
	dots := width - len(key)
	if dots < 2 {
		dots = 2
	}
	fmt.Fprintf(b, "%s%s %s\n", key, strings.Repeat(".", dots), value)
}
