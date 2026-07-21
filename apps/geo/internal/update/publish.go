package update

import (
	"fmt"
	"io"
	"os"
	"path/filepath"
)

// publishAtomically copies candidateDB to databasePath.new then renames over databasePath.
func publishAtomically(candidateDB, databasePath string) error {
	if err := os.MkdirAll(filepath.Dir(databasePath), 0o755); err != nil {
		return fmt.Errorf("create database directory: %w", err)
	}

	staging := databasePath + ".new"
	_ = os.Remove(staging)

	if err := copyFile(candidateDB, staging); err != nil {
		_ = os.Remove(staging)
		return fmt.Errorf("stage candidate database: %w", err)
	}

	if err := os.Remove(databasePath); err != nil && !os.IsNotExist(err) {
		_ = os.Remove(staging)
		return fmt.Errorf("remove existing database: %w", err)
	}
	if err := os.Rename(staging, databasePath); err != nil {
		_ = os.Remove(staging)
		return fmt.Errorf("publish database: %w", err)
	}
	return nil
}

func copyFile(src, dst string) error {
	in, err := os.Open(src)
	if err != nil {
		return err
	}
	defer in.Close()

	out, err := os.OpenFile(dst, os.O_CREATE|os.O_WRONLY|os.O_TRUNC, 0o644)
	if err != nil {
		return err
	}
	defer out.Close()

	if _, err := io.Copy(out, in); err != nil {
		return err
	}
	return out.Close()
}
