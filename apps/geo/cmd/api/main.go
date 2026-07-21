package main

import (
	"context"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"finba.se/geo/internal/access"
	"finba.se/geo/internal/buildinfo"
	"finba.se/geo/internal/config"
	"finba.se/geo/internal/database"
	"finba.se/geo/internal/httpapi"
	"finba.se/geo/internal/repository"
)

func main() {
	cfg, err := config.Load()
	if err != nil {
		slog.Error("invalid configuration", "error", err)
		os.Exit(1)
	}

	logger := slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{
		Level: parseLevel(cfg.LogLevel),
	}))

	logger.Info("geo api starting",
		"version", buildinfo.Version,
		"git_commit", buildinfo.GitCommit,
		"build_date", buildinfo.BuildDate,
		"go_version", buildinfo.Snapshot("").GoVersion,
		"environment", cfg.Env,
		"port", cfg.Port,
		"database", cfg.DatabasePath,
		"access", cfg.Access.String(),
	)

	db, err := database.OpenReadOnlyVerified(cfg.DatabasePath)
	if err != nil {
		logger.Error("failed to open geo database", "path", cfg.DatabasePath, "error", err)
		os.Exit(1)
	}
	defer db.Close()
	logger.Info("geo database loaded", "path", cfg.DatabasePath)

	limiter := access.NewLimiterManager(cfg.Access, logger)
	defer func() {
		if err := limiter.Close(); err != nil {
			logger.Error("access limiter shutdown failed", "error", err)
		}
	}()

	accessMW := access.NewMiddleware(cfg.Access, limiter, logger)
	repo := repository.New(db)
	api := httpapi.New(repo, logger, accessMW, httpapi.Options{Environment: cfg.Env})

	srv := &http.Server{
		Addr:              ":" + cfg.Port,
		Handler:           api.Handler(),
		ReadTimeout:       cfg.ReadTimeout,
		ReadHeaderTimeout: cfg.ReadHeaderTimeout,
		WriteTimeout:      cfg.WriteTimeout,
		IdleTimeout:       cfg.IdleTimeout,
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	errCh := make(chan error, 1)
	go func() {
		logger.Info("geo api ready",
			"addr", srv.Addr,
			"environment", cfg.Env,
			"version", buildinfo.Version,
		)
		errCh <- srv.ListenAndServe()
	}()

	select {
	case <-ctx.Done():
		logger.Info("shutdown signal received")
	case err := <-errCh:
		if err != nil && !errors.Is(err, http.ErrServerClosed) {
			logger.Error("server failed", "error", err)
			os.Exit(1)
		}
	}

	shutdownTimeout := cfg.ShutdownTimeout
	if shutdownTimeout <= 0 {
		shutdownTimeout = 10 * time.Second
	}
	shutdownCtx, cancel := context.WithTimeout(context.Background(), shutdownTimeout)
	defer cancel()
	if err := srv.Shutdown(shutdownCtx); err != nil {
		logger.Error("graceful shutdown failed", "error", err)
		os.Exit(1)
	}
	logger.Info("server stopped")
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
