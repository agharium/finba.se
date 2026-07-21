package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	"finba.se/geo/internal/inspect"
)

func main() {
	cfg, err := inspect.ParseArgs(os.Args[1:])
	if err != nil {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(os.Stdout, inspect.UsageText())
			os.Exit(0)
		}
		fmt.Fprintf(os.Stderr, "usage error: %v\n\n%s", err, inspect.UsageText())
		os.Exit(inspect.ExitOperational)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	os.Exit(inspect.Execute(ctx, cfg, os.Stdout, os.Stderr))
}
