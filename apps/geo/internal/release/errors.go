package release

import "errors"

var (
	// ErrReleaseNotFound indicates the release or repository was not found.
	ErrReleaseNotFound = errors.New("release not found")

	// ErrRateLimited indicates GitHub refused the request due to rate limiting.
	ErrRateLimited = errors.New("github rate limit exceeded")

	// ErrGitHub indicates an unexpected GitHub API failure.
	ErrGitHub = errors.New("github api error")
)
