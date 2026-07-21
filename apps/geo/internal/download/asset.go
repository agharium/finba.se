package download

import (
	"fmt"
	"strings"

	"finba.se/geo/internal/release"
)

// FindAsset returns the release asset with an exact name match.
func FindAsset(rel release.Release, name string) (release.Asset, error) {
	name = strings.TrimSpace(name)
	if name == "" {
		return release.Asset{}, fmt.Errorf("%w: empty name", ErrAssetNotFound)
	}

	var matches []release.Asset
	for _, a := range rel.Assets {
		if a.Name == name {
			matches = append(matches, a)
		}
	}
	switch len(matches) {
	case 0:
		return release.Asset{}, fmt.Errorf("%w: %q", ErrAssetNotFound, name)
	case 1:
		return matches[0], nil
	default:
		return release.Asset{}, fmt.Errorf("%w: %q (%d matches)", ErrDuplicateAsset, name, len(matches))
	}
}
