package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"finba.se/geo/internal/download"
)

func main() {
	cfg, err := download.ParseArgs(os.Args[1:])
	if err != nil {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(os.Stdout, download.UsageText())
			os.Exit(0)
		}
		fmt.Fprintf(os.Stderr, "usage error: %v\n\n%s", err, download.UsageText())
		os.Exit(download.ExitOperational)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	os.Exit(download.Execute(ctx, cfg, os.Stdout, os.Stderr))
}
