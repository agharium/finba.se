package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log/slog"
	"os"
	"os/signal"
	"strings"
	"syscall"
	"time"

	"finba.se/geo/internal/release"
)

func main() {
	var (
		owner  = flag.String("owner", "dr5hn", "GitHub repository owner")
		repo   = flag.String("repo", "countries-states-cities-database", "GitHub repository name")
		tag    = flag.String("tag", "", "fetch a specific release tag")
		latest = flag.Bool("latest", false, "fetch only the latest release")
		asJSON = flag.Bool("json", false, "emit machine-readable JSON")
	)
	flag.Parse()

	logger := slog.New(slog.NewJSONHandler(os.Stderr, &slog.HandlerOptions{Level: slog.LevelInfo}))

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	client := release.New(*owner, *repo)

	var (
		rels []release.Release
		err  error
	)

	switch {
	case *tag != "" && *latest:
		fmt.Fprintln(os.Stderr, "provide either --tag or --latest, not both")
		os.Exit(2)
	case *tag != "":
		rel, e := client.GetByTag(ctx, *tag)
		err = e
		if err == nil {
			rels = []release.Release{*rel}
		}
	case *latest:
		rel, e := client.Latest(ctx)
		err = e
		if err == nil {
			rels = []release.Release{*rel}
		}
	default:
		rels, err = client.Releases(ctx)
	}

	if err != nil {
		logger.Error("release lookup failed",
			"error", err,
			"owner", *owner,
			"repo", *repo,
		)
		os.Exit(1)
	}

	rl := client.RateLimit()
	logger.Info("github rate limit",
		"remaining", rl.Remaining,
		"reset", rl.Reset.Format(time.RFC3339),
	)

	if *asJSON {
		if err := writeJSON(os.Stdout, *owner, *repo, rels, rl); err != nil {
			logger.Error("write json failed", "error", err)
			os.Exit(1)
		}
		return
	}

	if err := writeHuman(os.Stdout, *owner, *repo, rels, rl); err != nil {
		logger.Error("write report failed", "error", err)
		os.Exit(1)
	}
}

type jsonOut struct {
	Owner     string        `json:"owner"`
	Repo      string        `json:"repo"`
	Releases  []jsonRelease `json:"releases"`
	RateLimit jsonRateLimit `json:"rateLimit"`
}

type jsonRelease struct {
	Tag         string      `json:"tag"`
	Name        string      `json:"name"`
	Body        string      `json:"body,omitempty"`
	Draft       bool        `json:"draft"`
	Prerelease  bool        `json:"prerelease"`
	PublishedAt string      `json:"publishedAt"`
	Assets      []jsonAsset `json:"assets"`
}

type jsonAsset struct {
	ID          int64  `json:"id"`
	Name        string `json:"name"`
	ContentType string `json:"contentType"`
	Size        int64  `json:"size"`
	DownloadURL string `json:"downloadUrl"`
}

type jsonRateLimit struct {
	Remaining int    `json:"remaining"`
	Reset     string `json:"reset"`
}

func writeJSON(w io.Writer, owner, repo string, rels []release.Release, rl release.RateLimit) error {
	out := jsonOut{
		Owner:    owner,
		Repo:     repo,
		Releases: make([]jsonRelease, 0, len(rels)),
		RateLimit: jsonRateLimit{
			Remaining: rl.Remaining,
			Reset:     formatTime(rl.Reset),
		},
	}
	for _, r := range rels {
		jr := jsonRelease{
			Tag:         r.Tag,
			Name:        r.Name,
			Body:        r.Body,
			Draft:       r.Draft,
			Prerelease:  r.Prerelease,
			PublishedAt: formatTime(r.PublishedAt),
			Assets:      make([]jsonAsset, 0, len(r.Assets)),
		}
		for _, a := range r.Assets {
			jr.Assets = append(jr.Assets, jsonAsset{
				ID:          a.ID,
				Name:        a.Name,
				ContentType: a.ContentType,
				Size:        a.Size,
				DownloadURL: a.DownloadURL,
			})
		}
		out.Releases = append(out.Releases, jr)
	}
	enc := json.NewEncoder(w)
	enc.SetIndent("", "  ")
	enc.SetEscapeHTML(true)
	return enc.Encode(out)
}

func writeHuman(w io.Writer, owner, repo string, rels []release.Release, rl release.RateLimit) error {
	b := &strings.Builder{}
	fmt.Fprintf(b, "%s %s/%s\n", padKey("Repository"), owner, repo)
	if !rl.Reset.IsZero() || rl.Remaining > 0 {
		fmt.Fprintf(b, "%s %d\n", padKey("Rate limit remaining"), rl.Remaining)
		if !rl.Reset.IsZero() {
			fmt.Fprintf(b, "%s %s\n", padKey("Rate limit reset"), formatTime(rl.Reset))
		}
	}
	fmt.Fprintln(b)

	if len(rels) == 0 {
		fmt.Fprintln(b, "No releases found.")
		_, err := io.WriteString(w, b.String())
		return err
	}

	for i, r := range rels {
		if i > 0 {
			fmt.Fprintln(b)
			fmt.Fprintln(b, "----------------------")
			fmt.Fprintln(b)
		}
		title := "Release"
		if i == 0 {
			title = "Latest Release"
		}
		fmt.Fprintf(b, "%s %s\n", padKey(title), dash(r.Tag))
		fmt.Fprintf(b, "%s %s\n", padKey("Name"), dash(r.Name))
		fmt.Fprintf(b, "%s %s\n", padKey("Published"), formatTime(r.PublishedAt))
		fmt.Fprintf(b, "%s %t\n", padKey("Draft"), r.Draft)
		fmt.Fprintf(b, "%s %t\n", padKey("Prerelease"), r.Prerelease)
		fmt.Fprintf(b, "%s %d\n", padKey("Assets"), len(r.Assets))
		for _, a := range r.Assets {
			fmt.Fprintln(b)
			fmt.Fprintf(b, "  - %s\n", a.Name)
			fmt.Fprintf(b, "    %s %s\n", padKey("Size"), formatSize(a.Size))
			fmt.Fprintf(b, "    %s %s\n", padKey("Content-Type"), dash(a.ContentType))
			fmt.Fprintf(b, "    %s %s\n", padKey("Download URL"), dash(a.DownloadURL))
		}
	}

	_, err := io.WriteString(w, b.String())
	return err
}

func formatTime(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	return t.UTC().Format(time.RFC3339)
}

func dash(s string) string {
	if s == "" {
		return "-"
	}
	return s
}

func padKey(s string) string {
	const width = 22
	dots := width - len(s)
	if dots < 2 {
		dots = 2
	}
	return s + strings.Repeat(".", dots)
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
