package update

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"log/slog"
	"os"
	"path/filepath"
	"time"

	"finba.se/geo/internal/database"
	"finba.se/geo/internal/download"
	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/inspect"
	"finba.se/geo/internal/model"
	"finba.se/geo/internal/release"
)

// Options configures an update run.
type Options struct {
	Owner         string
	Repo          string
	Tag           string
	Asset         string
	Force         bool
	Workspace     string
	Database      string
	LockPath      string
	KeepWorkspace bool
	Timeout       time.Duration

	// Optional clients for tests. When nil, production clients are created.
	ReleaseClient  *release.Client
	DownloadClient *download.Client

	// AfterImport is an optional test hook invoked after a successful import.
	AfterImport func(candidateDB string) error

	Logger *slog.Logger
}

// Result summarizes an update attempt.
type Result struct {
	Updated       bool   `json:"updated"`
	Reason        string `json:"reason,omitempty"`
	FromVersion   string `json:"fromVersion,omitempty"`
	ToVersion     string `json:"toVersion,omitempty"`
	Version       string `json:"version,omitempty"`
	Downloaded    bool   `json:"downloaded,omitempty"`
	Validated     bool   `json:"validated,omitempty"`
	Published     bool   `json:"published,omitempty"`
	Workspace     string `json:"workspace,omitempty"`
	Database      string `json:"database,omitempty"`
	DatasetSHA256 string `json:"datasetSha256,omitempty"`
}

// Run executes the full update pipeline.
func Run(ctx context.Context, opts Options) (Result, error) {
	if err := normalizeOptions(&opts); err != nil {
		return Result{}, err
	}
	logger := opts.Logger
	if logger == nil {
		logger = slog.Default()
	}

	if opts.Timeout > 0 {
		var cancel context.CancelFunc
		ctx, cancel = context.WithTimeout(ctx, opts.Timeout)
		defer cancel()
	}

	lk, err := acquireLock(opts.LockPath)
	if err != nil {
		return Result{}, err
	}
	defer lk.release()

	fromVersion, err := readCurrentVersion(ctx, opts.Database)
	if err != nil {
		return Result{}, fmt.Errorf("read current catalog: %w", err)
	}

	relClient := opts.ReleaseClient
	if relClient == nil {
		relClient = release.New(opts.Owner, opts.Repo)
	}

	var rel *release.Release
	if opts.Tag != "" {
		rel, err = relClient.GetByTag(ctx, opts.Tag)
	} else {
		rel, err = relClient.Latest(ctx)
	}
	if err != nil {
		return Result{}, fmt.Errorf("query release: %w", err)
	}

	toVersion := rel.Tag
	result := Result{
		FromVersion: fromVersion,
		ToVersion:   toVersion,
		Workspace:   opts.Workspace,
		Database:    opts.Database,
	}

	if !opts.Force && fromVersion != "" && fromVersion == toVersion {
		logger.Info("catalog already up to date", "version", fromVersion)
		return Result{
			Updated:   false,
			Reason:    "already_up_to_date",
			Version:   fromVersion,
			Workspace: opts.Workspace,
			Database:  opts.Database,
		}, nil
	}

	cleanupWorkspace := !opts.KeepWorkspace
	if cleanupWorkspace {
		defer func() { _ = os.RemoveAll(opts.Workspace) }()
	}
	if err := os.MkdirAll(opts.Workspace, 0o755); err != nil {
		return result, fmt.Errorf("create workspace: %w", err)
	}

	asset, err := download.FindAsset(*rel, opts.Asset)
	if err != nil {
		return result, fmt.Errorf("%w: %v", ErrValidation, err)
	}

	gzPath := filepath.Join(opts.Workspace, "cities.csv.gz")
	csvPath := filepath.Join(opts.Workspace, "cities.csv")
	candidateDB := filepath.Join(opts.Workspace, "geo.db")

	dlClient := opts.DownloadClient
	if dlClient == nil {
		dlClient = download.New()
	}

	logger.Info("downloading asset", "asset", asset.Name, "release", toVersion)
	dlRes, err := dlClient.Download(ctx, download.Request{
		URL:          asset.DownloadURL,
		Destination:  gzPath,
		ExpectedSize: asset.Size,
	})
	if err != nil {
		return result, fmt.Errorf("download: %w", err)
	}
	result.Downloaded = true
	result.DatasetSHA256 = dlRes.SHA256

	logger.Info("extracting gzip", "source", gzPath)
	if _, err := download.ExtractGzip(ctx, gzPath, csvPath, download.ExtractOptions{}); err != nil {
		return result, fmt.Errorf("extract: %w", err)
	}

	logger.Info("importing catalog", "csv", csvPath)
	_, err = importer.Run(ctx, importer.Options{
		InputPath:        csvPath,
		OutputPath:       candidateDB,
		DatasetVersion:   toVersion,
		DatasetSHA256:    dlRes.SHA256,
		Provider:         model.Provider,
		GeneratorVersion: model.GeneratorVersion,
		SchemaVersion:    model.SchemaVersion,
	})
	if err != nil {
		return result, fmt.Errorf("import: %w", err)
	}

	if opts.AfterImport != nil {
		if err := opts.AfterImport(candidateDB); err != nil {
			return result, fmt.Errorf("after import hook: %w", err)
		}
	}

	logger.Info("inspecting candidate", "database", candidateDB)
	report, err := inspect.Run(ctx, inspect.Options{Path: candidateDB, Strict: true})
	if err != nil {
		return result, fmt.Errorf("inspect: %w", err)
	}
	if !report.Ready {
		return result, fmt.Errorf("%w", ErrNotReady)
	}
	result.Validated = true

	logger.Info("publishing database", "destination", opts.Database)
	if err := publishAtomically(candidateDB, opts.Database); err != nil {
		return result, err
	}
	result.Published = true
	result.Updated = true
	return result, nil
}

func normalizeOptions(opts *Options) error {
	if opts.Owner == "" {
		opts.Owner = "dr5hn"
	}
	if opts.Repo == "" {
		opts.Repo = "countries-states-cities-database"
	}
	if opts.Asset == "" {
		opts.Asset = "csv-cities.csv.gz"
	}
	if opts.Workspace == "" {
		opts.Workspace = "./data/work"
	}
	if opts.Database == "" {
		opts.Database = "./data/geo.db"
	}
	if opts.LockPath == "" {
		opts.LockPath = "./data/update.lock"
	}
	if opts.Timeout == 0 {
		opts.Timeout = 10 * time.Minute
	}
	if opts.Timeout < 0 {
		return fmt.Errorf("timeout must be positive")
	}
	return nil
}

func readCurrentVersion(ctx context.Context, databasePath string) (string, error) {
	if _, err := os.Stat(databasePath); err != nil {
		if os.IsNotExist(err) {
			return "", nil
		}
		return "", err
	}

	db, err := database.OpenReadOnly(databasePath)
	if err != nil {
		return "", err
	}
	defer db.Close()

	var version string
	err = db.QueryRowContext(ctx,
		`SELECT value FROM metadata WHERE key = ?`, model.MetaDatasetVersion,
	).Scan(&version)
	if errors.Is(err, sql.ErrNoRows) {
		return "", nil
	}
	if err != nil {
		// Missing metadata table or other schema issues: treat as no version.
		return "", nil
	}
	return version, nil
}
