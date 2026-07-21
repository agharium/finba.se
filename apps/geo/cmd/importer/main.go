package main

import (
	"context"
	"flag"
	"fmt"
	"log/slog"
	"os"
	"os/signal"
	"syscall"
	"time"

	"finba.se/geo/internal/importer"
	"finba.se/geo/internal/model"

	_ "time/tzdata"
)

func main() {
	var (
		input          = flag.String("input", "", "path to cities CSV")
		output         = flag.String("output", "./data/geo.db", "path to output SQLite database")
		datasetVersion = flag.String("dataset-version", "", "upstream dataset version label")
		datasetSHA256  = flag.String("dataset-sha256", "", "SHA-256 of the source dataset")
		logLevel       = flag.String("log-level", "info", "log level (debug|info|warn|error)")
	)
	flag.Parse()

	level := parseLevel(*logLevel)
	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: level}))

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	start := time.Now()
	result, err := importer.Run(ctx, importer.Options{
		InputPath:        *input,
		OutputPath:       *output,
		DatasetVersion:   *datasetVersion,
		DatasetSHA256:    *datasetSHA256,
		GeneratorVersion: model.GeneratorVersion,
		SchemaVersion:    model.SchemaVersion,
		Provider:         model.Provider,
	})
	if err != nil {
		logger.Error("import failed", "error", err)
		os.Exit(1)
	}

	logger.Info("import completed",
		"output", result.Output,
		"countries", result.Countries,
		"regions", result.Regions,
		"cities", result.Cities,
		"duration", time.Since(start).String(),
	)
	fmt.Fprintf(os.Stderr, "wrote %s (%d countries, %d regions, %d cities)\n",
		result.Output, result.Countries, result.Regions, result.Cities)
}

func parseLevel(s string) slog.Level {
	switch s {
	case "debug":
		return slog.LevelDebug
	case "warn":
		return slog.LevelWarn
	case "error":
		return slog.LevelError
	default:
		return slog.LevelInfo
	}
}
