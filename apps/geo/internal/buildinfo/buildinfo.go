// Package buildinfo exposes compile-time service metadata injected via -ldflags.
package buildinfo

import "runtime"

// Populated at link time. Defaults are for local `go run` / `go test`.
var (
	Version   = "dev"
	GitCommit = "unknown"
	BuildDate = "unknown"
)

// Info is a JSON-safe snapshot of build metadata (no secrets).
type Info struct {
	Version     string `json:"version"`
	GitCommit   string `json:"gitCommit"`
	BuildDate   string `json:"buildDate"`
	GoVersion   string `json:"goVersion"`
	Environment string `json:"environment,omitempty"`
}

// Snapshot returns build metadata, optionally tagged with GEO_ENV.
func Snapshot(environment string) Info {
	return Info{
		Version:     Version,
		GitCommit:   GitCommit,
		BuildDate:   BuildDate,
		GoVersion:   runtime.Version(),
		Environment: environment,
	}
}
