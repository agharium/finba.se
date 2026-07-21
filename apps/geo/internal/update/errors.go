// Package update orchestrates catalog refresh using release, download, importer, and inspect.
package update

import "errors"

var (
	// ErrAlreadyLocked indicates another update holds the lock file.
	ErrAlreadyLocked = errors.New("another update is already in progress")

	// ErrNotReady indicates strict inspection rejected the candidate database.
	ErrNotReady = errors.New("candidate database is not ready")

	// ErrValidation indicates a validation failure during the pipeline.
	ErrValidation = errors.New("update validation failed")
)
