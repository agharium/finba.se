package inspect

import (
	"encoding/json"
	"fmt"
	"io"
	"strings"
)

// WriteHuman writes a concise operational report to w.
func WriteHuman(w io.Writer, rep Report) error {
	b := &strings.Builder{}
	fmt.Fprintln(b, "Finba Geo database")
	fmt.Fprintln(b, "==================")
	fmt.Fprintln(b)

	writeKV(b, "Path", rep.Path)
	writeKV(b, "File size", formatSize(rep.FileSizeBytes))
	fmt.Fprintln(b)

	writeKV(b, "Provider", dash(rep.Metadata.Provider))
	writeKV(b, "Dataset version", dash(rep.Metadata.DatasetVersion))
	writeKV(b, "Dataset SHA-256", dash(rep.Metadata.DatasetSHA256))
	writeKV(b, "Generated at", dash(rep.Metadata.GeneratedAt))
	writeKV(b, "Generator version", dash(rep.Metadata.GeneratorVersion))
	if rep.Metadata.SchemaVersion > 0 {
		writeKV(b, "Schema version", fmt.Sprintf("%d", rep.Metadata.SchemaVersion))
	} else {
		writeKV(b, "Schema version", "-")
	}
	fmt.Fprintln(b)

	writeKV(b, "Countries", formatInt(rep.Counts.Countries))
	writeKV(b, "Regions", formatInt(rep.Counts.Regions))
	writeKV(b, "Cities", formatInt(rep.Counts.Cities))
	fmt.Fprintln(b)

	writeCheckLine(b, "SQLite integrity", findCheck(rep.Checks, "sqlite_integrity"))
	writeCheckLine(b, "Foreign keys", findCheck(rep.Checks, "foreign_keys"))
	writeCheckLine(b, "Required tables", findCheck(rep.Checks, "required_tables"))
	writeCheckLine(b, "Required columns", findCheck(rep.Checks, "required_columns"))
	writeCheckLine(b, "Required indexes", findCheck(rep.Checks, "required_indexes"))
	writeCheckLine(b, "Required metadata", findCheck(rep.Checks, "required_metadata"))

	// Surface notable data-sanity failures after the summary lines.
	for _, name := range []string{
		"row_counts",
		"blank_country_code",
		"blank_country_name",
		"blank_region_name",
		"blank_city_name",
		"normalized_countries",
		"normalized_regions",
		"normalized_cities",
		"city_country_orphan",
		"region_country_orphan",
		"city_region_country_mismatch",
		"timezone_validation",
	} {
		if c := findCheck(rep.Checks, name); c != nil && c.Status == StatusFail {
			writeCheckLine(b, humanCheckLabel(name), c)
		}
	}

	ready := "NOT READY"
	if rep.Ready {
		ready = "READY"
	}
	writeKV(b, "API readiness", ready)

	if len(rep.Warnings) > 0 {
		fmt.Fprintln(b)
		fmt.Fprintln(b, "Warnings")
		fmt.Fprintln(b, "--------")
		for _, wcheck := range rep.Warnings {
			fmt.Fprintf(b, "- %s: %s\n", wcheck.Name, wcheck.Message)
			for _, d := range wcheck.Details {
				fmt.Fprintf(b, "    %s\n", d)
			}
		}
	}

	// Detail blocks for failed summary checks.
	for _, c := range rep.Checks {
		if c.Status != StatusFail || len(c.Details) == 0 {
			continue
		}
		fmt.Fprintln(b)
		fmt.Fprintf(b, "%s\n", humanCheckLabel(c.Name))
		for _, d := range c.Details {
			fmt.Fprintf(b, "  - %s\n", d)
		}
	}

	_, err := io.WriteString(w, b.String())
	return err
}

// WriteJSON writes the machine-readable report to w.
func WriteJSON(w io.Writer, rep Report) error {
	enc := json.NewEncoder(w)
	enc.SetEscapeHTML(true)
	enc.SetIndent("", "  ")
	return enc.Encode(rep)
}

func writeKV(b *strings.Builder, key, value string) {
	const width = 22
	dots := width - len(key)
	if dots < 2 {
		dots = 2
	}
	fmt.Fprintf(b, "%s%s %s\n", key, strings.Repeat(".", dots), value)
}

func writeCheckLine(b *strings.Builder, label string, c *Check) {
	status := "OK"
	if c == nil {
		status = "-"
	} else if c.Status == StatusFail {
		status = "FAIL"
	} else if c.Status == StatusWarning {
		status = "WARN"
	}
	writeKV(b, label, status)
}

func findCheck(checks []Check, name string) *Check {
	for i := range checks {
		if checks[i].Name == name {
			return &checks[i]
		}
	}
	return nil
}

func humanCheckLabel(name string) string {
	switch name {
	case "sqlite_integrity":
		return "SQLite integrity"
	case "foreign_keys":
		return "Foreign keys"
	case "required_tables":
		return "Required tables"
	case "required_columns":
		return "Required columns"
	case "required_indexes":
		return "Required indexes"
	case "required_metadata":
		return "Required metadata"
	case "row_counts":
		return "Row counts"
	case "blank_country_code":
		return "Blank country codes"
	case "blank_country_name":
		return "Blank country names"
	case "blank_region_name":
		return "Blank region names"
	case "blank_city_name":
		return "Blank city names"
	case "normalized_countries":
		return "Normalized countries"
	case "normalized_regions":
		return "Normalized regions"
	case "normalized_cities":
		return "Normalized cities"
	case "city_country_orphan":
		return "City-country orphans"
	case "region_country_orphan":
		return "Region-country orphans"
	case "city_region_country_mismatch":
		return "City-region mismatches"
	case "timezone_validation":
		return "Timezone validation"
	default:
		return name
	}
}

func dash(s string) string {
	if s == "" {
		return "-"
	}
	return s
}

func formatInt(n int) string {
	s := fmt.Sprintf("%d", n)
	if n < 0 {
		return s
	}
	var parts []string
	for len(s) > 3 {
		parts = append([]string{s[len(s)-3:]}, parts...)
		s = s[:len(s)-3]
	}
	parts = append([]string{s}, parts...)
	return strings.Join(parts, ",")
}

func formatSize(n int64) string {
	const (
		kb = 1024
		mb = 1024 * kb
		gb = 1024 * mb
	)
	switch {
	case n >= gb:
		return fmt.Sprintf("%.1f GB", float64(n)/float64(gb))
	case n >= mb:
		return fmt.Sprintf("%.1f MB", float64(n)/float64(mb))
	case n >= kb:
		return fmt.Sprintf("%.1f KB", float64(n)/float64(kb))
	default:
		return fmt.Sprintf("%d B", n)
	}
}
