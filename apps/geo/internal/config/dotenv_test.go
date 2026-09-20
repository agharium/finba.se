package config

import (
	"os"
	"path/filepath"
	"testing"
)

func TestLoadDotEnvDoesNotOverrideExistingEnv(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, ".env")
	if err := os.WriteFile(path, []byte("GEO_DOTENV_TEST_KEY=from-file\n"), 0o600); err != nil {
		t.Fatal(err)
	}

	t.Setenv("GEO_DOTENV_TEST_KEY", "from-process")
	if err := loadDotEnvFile(path); err != nil {
		t.Fatal(err)
	}
	if got := os.Getenv("GEO_DOTENV_TEST_KEY"); got != "from-process" {
		t.Fatalf("got %q", got)
	}
}

func TestLoadDotEnvSetsMissingKeys(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, ".env")
	if err := os.WriteFile(path, []byte("# comment\nGEO_DOTENV_MISSING_KEY=hello\n"), 0o600); err != nil {
		t.Fatal(err)
	}

	_ = os.Unsetenv("GEO_DOTENV_MISSING_KEY")
	if err := loadDotEnvFile(path); err != nil {
		t.Fatal(err)
	}
	if got := os.Getenv("GEO_DOTENV_MISSING_KEY"); got != "hello" {
		t.Fatalf("got %q", got)
	}
	t.Cleanup(func() { _ = os.Unsetenv("GEO_DOTENV_MISSING_KEY") })
}

func TestLoadDotEnvMissingFileIsOK(t *testing.T) {
	if err := loadDotEnvFile(filepath.Join(t.TempDir(), "nope.env")); err != nil {
		t.Fatal(err)
	}
}
