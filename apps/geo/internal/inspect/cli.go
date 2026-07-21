package inspect

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"io"
	"time"
)

// Exit codes for the inspect command.
const (
	ExitOK          = 0
	ExitNotReady    = 1
	ExitOperational = 2
)

// CLIConfig holds parsed command-line options.
type CLIConfig struct {
	Database string
	JSON     bool
	Strict   bool
	Timeout  time.Duration
}

// ParseArgs parses inspect CLI arguments.
// args should be the process arguments excluding the program name.
func ParseArgs(args []string) (CLIConfig, error) {
	fs := flag.NewFlagSet("inspect", flag.ContinueOnError)
	fs.SetOutput(io.Discard)

	var cfg CLIConfig
	var timeoutStr string
	fs.StringVar(&cfg.Database, "database", "", "path to Geo SQLite database")
	fs.BoolVar(&cfg.JSON, "json", false, "emit machine-readable JSON")
	fs.BoolVar(&cfg.Strict, "strict", false, "treat warnings as readiness failures")
	fs.StringVar(&timeoutStr, "timeout", "30s", "inspection timeout (Go duration)")

	fs.Usage = func() {}
	if err := fs.Parse(args); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return CLIConfig{}, err
		}
		return CLIConfig{}, fmt.Errorf("%w", err)
	}

	d, err := time.ParseDuration(timeoutStr)
	if err != nil || d <= 0 {
		return CLIConfig{}, fmt.Errorf("invalid --timeout %q: use a positive Go duration such as 30s", timeoutStr)
	}
	cfg.Timeout = d

	pos := fs.Args()
	switch {
	case cfg.Database != "" && len(pos) > 0:
		return CLIConfig{}, fmt.Errorf("provide either --database or a positional path, not both")
	case cfg.Database == "" && len(pos) == 1:
		cfg.Database = pos[0]
	case cfg.Database == "" && len(pos) == 0:
		cfg.Database = "./data/geo.db"
	case len(pos) > 1:
		return CLIConfig{}, fmt.Errorf("unexpected arguments: %v", pos)
	}

	if cfg.Database == "" {
		return CLIConfig{}, fmt.Errorf("database path must not be empty")
	}
	return cfg, nil
}

// UsageText returns the help text for the inspect command.
func UsageText() string {
	return `Usage:
  inspect [flags] [database]
  inspect --database ./data/geo.db
  inspect ./data/geo.db

Inspect a Finba Geo SQLite catalog (read-only).

Flags:
  --database string   path to Geo SQLite database (default ./data/geo.db)
  --json              emit machine-readable JSON on stdout
  --strict            treat warnings as readiness failures
  --timeout duration  inspection timeout (default 30s)
  -h, --help          show help

Exit codes:
  0  all required checks passed (READY)
  1  database opened, but one or more checks failed (NOT READY)
  2  usage error, missing/unreadable database, timeout, or operational failure
`
}

// Execute runs the inspect command and returns a process exit code.
func Execute(ctx context.Context, cfg CLIConfig, stdout, stderr io.Writer) int {
	ctx, cancel := context.WithTimeout(ctx, cfg.Timeout)
	defer cancel()

	rep, err := Run(ctx, Options{Path: cfg.Database, Strict: cfg.Strict})
	if err != nil {
		if errors.Is(err, context.DeadlineExceeded) || errors.Is(ctx.Err(), context.DeadlineExceeded) {
			fmt.Fprintf(stderr, "inspect timed out after %s\n", cfg.Timeout)
			return ExitOperational
		}
		if errors.Is(err, context.Canceled) {
			fmt.Fprintf(stderr, "inspect canceled\n")
			return ExitOperational
		}
		fmt.Fprintf(stderr, "inspect failed: %v\n", err)
		return ExitOperational
	}

	if cfg.JSON {
		if err := WriteJSON(stdout, rep); err != nil {
			fmt.Fprintf(stderr, "write json: %v\n", err)
			return ExitOperational
		}
	} else {
		if err := WriteHuman(stdout, rep); err != nil {
			fmt.Fprintf(stderr, "write report: %v\n", err)
			return ExitOperational
		}
	}

	if rep.Ready {
		return ExitOK
	}
	return ExitNotReady
}
