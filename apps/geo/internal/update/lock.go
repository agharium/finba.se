package update

import (
	"fmt"
	"os"
	"path/filepath"
)

// lock holds an exclusive update lock file.
type lock struct {
	path string
}

func acquireLock(path string) (*lock, error) {
	if path == "" {
		return nil, fmt.Errorf("lock path must not be empty")
	}
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		return nil, fmt.Errorf("create lock directory: %w", err)
	}
	f, err := os.OpenFile(path, os.O_CREATE|os.O_EXCL|os.O_WRONLY, 0o644)
	if err != nil {
		if os.IsExist(err) {
			return nil, fmt.Errorf("%w", ErrAlreadyLocked)
		}
		return nil, fmt.Errorf("create lock file: %w", err)
	}
	_, _ = fmt.Fprintf(f, "pid=%d\n", os.Getpid())
	if err := f.Close(); err != nil {
		_ = os.Remove(path)
		return nil, fmt.Errorf("close lock file: %w", err)
	}
	return &lock{path: path}, nil
}

func (l *lock) release() {
	if l == nil || l.path == "" {
		return
	}
	_ = os.Remove(l.path)
	l.path = ""
}
