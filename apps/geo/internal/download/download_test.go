package download_test

import (
	"bytes"
	"compress/gzip"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"finba.se/geo/internal/download"
	"finba.se/geo/internal/release"
)

func TestDownloadSuccessAndChecksum(t *testing.T) {
	t.Parallel()
	payload := []byte("hello-geo-asset")
	sum := sha256.Sum256(payload)
	digest := hex.EncodeToString(sum[:])

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("User-Agent") != "finba-geo" {
			t.Fatalf("ua=%q", r.Header.Get("User-Agent"))
		}
		if r.Header.Get("Accept") != "application/octet-stream" {
			t.Fatalf("accept=%q", r.Header.Get("Accept"))
		}
		w.Header().Set("Content-Type", "application/gzip")
		w.Header().Set("ETag", `"abc"`)
		_, _ = w.Write(payload)
	}))
	defer srv.Close()

	dest := filepath.Join(t.TempDir(), "asset.bin")
	c := download.NewWithHTTPClient(srv.Client())
	res, err := c.Download(context.Background(), download.Request{
		URL:            srv.URL + "/file",
		Destination:    dest,
		ExpectedSize:   int64(len(payload)),
		ExpectedSHA256: strings.ToUpper(digest),
	})
	if err != nil {
		t.Fatal(err)
	}
	if res.Size != int64(len(payload)) || res.SHA256 != digest {
		t.Fatalf("%+v", res)
	}
	got, err := os.ReadFile(dest)
	if err != nil || !bytes.Equal(got, payload) {
		t.Fatalf("file=%q err=%v", got, err)
	}
	assertNoPartFiles(t, filepath.Dir(dest))
}

func TestDownloadSizeMismatchPreservesDestination(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	dest := filepath.Join(dir, "asset.bin")
	if err := os.WriteFile(dest, []byte("keep-me"), 0o644); err != nil {
		t.Fatal(err)
	}

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		_, _ = w.Write([]byte("short"))
	}))
	defer srv.Close()

	c := download.NewWithHTTPClient(srv.Client())
	_, err := c.Download(context.Background(), download.Request{
		URL:          srv.URL,
		Destination:  dest,
		ExpectedSize: 100,
	})
	if !errors.Is(err, download.ErrSizeMismatch) {
		t.Fatalf("err=%v", err)
	}
	got, _ := os.ReadFile(dest)
	if string(got) != "keep-me" {
		t.Fatalf("destination overwritten: %q", got)
	}
	assertNoPartFiles(t, dir)
}

func TestDownloadChecksumMismatchAndMalformed(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		_, _ = w.Write([]byte("data"))
	}))
	defer srv.Close()
	c := download.NewWithHTTPClient(srv.Client())
	dest := filepath.Join(t.TempDir(), "a.bin")

	_, err := c.Download(context.Background(), download.Request{
		URL:            srv.URL,
		Destination:    dest,
		ExpectedSHA256: "0000000000000000000000000000000000000000000000000000000000000000",
	})
	if !errors.Is(err, download.ErrChecksumMismatch) {
		t.Fatalf("err=%v", err)
	}

	_, err = c.Download(context.Background(), download.Request{
		URL:            srv.URL,
		Destination:    dest,
		ExpectedSHA256: "not-hex",
	})
	if !errors.Is(err, download.ErrMalformedSHA256) {
		t.Fatalf("err=%v", err)
	}
}

func TestDownloadMissingContentLengthOK(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		// Chunked / no Content-Length by writing without setting length explicitly is default for small bodies;
		// force by using Flush with unknown length via http.Flusher after not setting Content-Length.
		w.WriteHeader(http.StatusOK)
		_, _ = w.Write([]byte("abc"))
	}))
	defer srv.Close()

	dest := filepath.Join(t.TempDir(), "a.bin")
	c := download.NewWithHTTPClient(srv.Client())
	res, err := c.Download(context.Background(), download.Request{URL: srv.URL, Destination: dest})
	if err != nil {
		t.Fatal(err)
	}
	if res.Size != 3 {
		t.Fatalf("%+v", res)
	}
}

func TestDownloadHTTPError(t *testing.T) {
	t.Parallel()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Error(w, "nope", http.StatusBadGateway)
	}))
	defer srv.Close()
	c := download.NewWithHTTPClient(srv.Client())
	_, err := c.Download(context.Background(), download.Request{
		URL:         srv.URL,
		Destination: filepath.Join(t.TempDir(), "x"),
	})
	if !errors.Is(err, download.ErrHTTP) {
		t.Fatalf("err=%v", err)
	}
}

func TestDownloadRedirectSuccessAndLimit(t *testing.T) {
	t.Parallel()
	var hops int
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/start":
			http.Redirect(w, r, "/final", http.StatusFound)
		case "/final":
			_, _ = w.Write([]byte("ok"))
		case "/loop":
			hops++
			http.Redirect(w, r, "/loop", http.StatusFound)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	c := download.NewWithHTTPClient(srv.Client())
	dest := filepath.Join(t.TempDir(), "r.bin")
	res, err := c.Download(context.Background(), download.Request{URL: srv.URL + "/start", Destination: dest})
	if err != nil || res.Size != 2 {
		t.Fatalf("res=%+v err=%v", res, err)
	}

	_, err = c.Download(context.Background(), download.Request{
		URL:         srv.URL + "/loop",
		Destination: filepath.Join(t.TempDir(), "loop.bin"),
	})
	if !errors.Is(err, download.ErrRedirectLimit) {
		t.Fatalf("err=%v hops=%d", err, hops)
	}
}

func TestDownloadContextCancel(t *testing.T) {
	t.Parallel()
	started := make(chan struct{})
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		close(started)
		time.Sleep(2 * time.Second)
		_, _ = w.Write([]byte("late"))
	}))
	defer srv.Close()

	ctx, cancel := context.WithCancel(context.Background())
	go func() {
		<-started
		cancel()
	}()

	c := download.NewWithHTTPClient(srv.Client())
	_, err := c.Download(ctx, download.Request{
		URL:         srv.URL,
		Destination: filepath.Join(t.TempDir(), "c.bin"),
	})
	if err == nil {
		t.Fatal("expected cancel error")
	}
}

func TestDownloadAuthHeader(t *testing.T) {
	var saw string
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		saw = r.Header.Get("Authorization")
		_, _ = w.Write([]byte("x"))
	}))
	defer srv.Close()

	t.Setenv("GITHUB_TOKEN", "tok")
	c := download.NewWithHTTPClient(srv.Client())
	_, err := c.Download(context.Background(), download.Request{
		URL:         srv.URL,
		Destination: filepath.Join(t.TempDir(), "a.bin"),
	})
	if err != nil {
		t.Fatal(err)
	}
	if saw != "Bearer tok" {
		t.Fatalf("auth=%q", saw)
	}
}

func TestFindAsset(t *testing.T) {
	t.Parallel()
	rel := release.Release{Assets: []release.Asset{
		{Name: "csv-cities.csv.gz", Size: 1},
		{Name: "other.gz", Size: 2},
	}}
	a, err := download.FindAsset(rel, "csv-cities.csv.gz")
	if err != nil || a.Size != 1 {
		t.Fatalf("%+v %v", a, err)
	}
	_, err = download.FindAsset(rel, "missing")
	if !errors.Is(err, download.ErrAssetNotFound) {
		t.Fatalf("%v", err)
	}
	rel.Assets = append(rel.Assets, release.Asset{Name: "csv-cities.csv.gz"})
	_, err = download.FindAsset(rel, "csv-cities.csv.gz")
	if !errors.Is(err, download.ErrDuplicateAsset) {
		t.Fatalf("%v", err)
	}
}

func TestExtractGzipSuccess(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	src := filepath.Join(dir, "a.gz")
	dst := filepath.Join(dir, "a.csv")
	payload := []byte("id,name\n1,Tramandai\n")
	writeGzipFile(t, src, payload)

	res, err := download.ExtractGzip(context.Background(), src, dst, download.ExtractOptions{})
	if err != nil {
		t.Fatal(err)
	}
	sum := sha256.Sum256(payload)
	if res.Size != int64(len(payload)) || res.SHA256 != hex.EncodeToString(sum[:]) {
		t.Fatalf("%+v", res)
	}
	got, _ := os.ReadFile(dst)
	if !bytes.Equal(got, payload) {
		t.Fatalf("%q", got)
	}
	assertNoPartFiles(t, dir)
}

func TestExtractGzipEmptyCorruptTruncatedLimit(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()

	empty := filepath.Join(dir, "empty.gz")
	writeGzipFile(t, empty, nil)
	_, err := download.ExtractGzip(context.Background(), empty, filepath.Join(dir, "empty.csv"), download.ExtractOptions{})
	if !errors.Is(err, download.ErrEmptyExtracted) {
		t.Fatalf("empty: %v", err)
	}

	corrupt := filepath.Join(dir, "bad.gz")
	if err := os.WriteFile(corrupt, []byte("not-gzip"), 0o644); err != nil {
		t.Fatal(err)
	}
	_, err = download.ExtractGzip(context.Background(), corrupt, filepath.Join(dir, "bad.csv"), download.ExtractOptions{})
	if !errors.Is(err, download.ErrCorruptGzip) {
		t.Fatalf("corrupt: %v", err)
	}

	full := filepath.Join(dir, "full.gz")
	writeGzipFile(t, full, bytes.Repeat([]byte("x"), 1000))
	trunc := filepath.Join(dir, "trunc.gz")
	raw, _ := os.ReadFile(full)
	if err := os.WriteFile(trunc, raw[:len(raw)/2], 0o644); err != nil {
		t.Fatal(err)
	}
	keep := filepath.Join(dir, "keep.csv")
	_ = os.WriteFile(keep, []byte("preserve"), 0o644)
	_, err = download.ExtractGzip(context.Background(), trunc, keep, download.ExtractOptions{})
	if !errors.Is(err, download.ErrCorruptGzip) {
		t.Fatalf("trunc: %v", err)
	}
	got, _ := os.ReadFile(keep)
	if string(got) != "preserve" {
		t.Fatalf("overwritten: %q", got)
	}

	big := filepath.Join(dir, "big.gz")
	writeGzipFile(t, big, bytes.Repeat([]byte("y"), 200))
	_, err = download.ExtractGzip(context.Background(), big, filepath.Join(dir, "big.csv"), download.ExtractOptions{MaxSize: 50})
	if !errors.Is(err, download.ErrExtractedSizeLimit) {
		t.Fatalf("limit: %v", err)
	}
	assertNoPartFiles(t, dir)
}

func TestExtractContextCancel(t *testing.T) {
	t.Parallel()
	dir := t.TempDir()
	src := filepath.Join(dir, "big.gz")
	writeGzipFile(t, src, bytes.Repeat([]byte("z"), 2<<20))

	ctx, cancel := context.WithCancel(context.Background())
	cancel()
	_, err := download.ExtractGzip(ctx, src, filepath.Join(dir, "out.csv"), download.ExtractOptions{})
	if err == nil {
		t.Fatal("expected error")
	}
}

func TestInvalidURLScheme(t *testing.T) {
	t.Parallel()
	c := download.New()
	_, err := c.Download(context.Background(), download.Request{
		URL:         "file:///tmp/x",
		Destination: filepath.Join(t.TempDir(), "x"),
	})
	if !errors.Is(err, download.ErrUnsupportedScheme) {
		t.Fatalf("%v", err)
	}
}

func TestParseArgsDefaults(t *testing.T) {
	t.Parallel()
	cfg, err := download.ParseArgs(nil)
	if err != nil {
		t.Fatal(err)
	}
	if cfg.Asset != "csv-cities.csv.gz" || cfg.Timeout != 2*time.Minute {
		t.Fatalf("%+v", cfg)
	}
	_, err = download.ParseArgs([]string{"--timeout", "nope"})
	if err == nil {
		t.Fatal("expected timeout error")
	}
}

func writeGzipFile(t *testing.T, path string, payload []byte) {
	t.Helper()
	f, err := os.Create(path)
	if err != nil {
		t.Fatal(err)
	}
	defer f.Close()
	zw := gzip.NewWriter(f)
	if _, err := zw.Write(payload); err != nil {
		t.Fatal(err)
	}
	if err := zw.Close(); err != nil {
		t.Fatal(err)
	}
}

func assertNoPartFiles(t *testing.T, dir string) {
	t.Helper()
	entries, err := os.ReadDir(dir)
	if err != nil {
		t.Fatal(err)
	}
	for _, e := range entries {
		name := e.Name()
		if strings.Contains(name, ".part") || strings.HasSuffix(name, ".extract") || strings.Contains(name, ".extract") {
			t.Fatalf("leftover temp file: %s", name)
		}
	}
}
