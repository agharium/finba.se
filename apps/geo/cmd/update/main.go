package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"finba.se/geo/internal/update"
)

func main() {
	cfg, err := update.ParseArgs(os.Args[1:])
	if err != nil {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(os.Stdout, update.UsageText())
			os.Exit(0)
		}
		fmt.Fprintf(os.Stderr, "usage error: %v\n\n%s", err, update.UsageText())
		os.Exit(update.ExitOperational)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	os.Exit(update.Execute(ctx, cfg, os.Stdout, os.Stderr))
}
