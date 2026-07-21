// Package textutil provides shared text normalization for Geo search.
package textutil

import (
	"strings"
	"unicode"

	"golang.org/x/text/unicode/norm"
)

// NormalizeSearch prepares a value for accent-insensitive, case-insensitive search.
//
// Steps:
//  1. Trim surrounding whitespace
//  2. Lowercase
//  3. Unicode NFD decomposition
//  4. Remove combining marks (diacritics)
//  5. Collapse repeated whitespace to a single space
func NormalizeSearch(value string) string {
	value = strings.TrimSpace(value)
	if value == "" {
		return ""
	}
	value = strings.ToLower(value)
	value = norm.NFD.String(value)

	var b strings.Builder
	b.Grow(len(value))
	prevSpace := false
	for _, r := range value {
		if unicode.Is(unicode.Mn, r) {
			continue // strip combining marks
		}
		if unicode.IsSpace(r) {
			if prevSpace || b.Len() == 0 {
				continue
			}
			b.WriteByte(' ')
			prevSpace = true
			continue
		}
		prevSpace = false
		b.WriteRune(r)
	}
	return strings.TrimSpace(b.String())
}
