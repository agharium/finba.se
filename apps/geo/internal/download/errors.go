package download

import "errors"

var (
	// ErrAssetNotFound indicates no asset matched the requested name.
	ErrAssetNotFound = errors.New("asset not found")

	// ErrDuplicateAsset indicates more than one asset matched the requested name.
	ErrDuplicateAsset = errors.New("duplicate asset name")

	// ErrInvalidURL indicates the download URL is empty or malformed.
	ErrInvalidURL = errors.New("invalid download url")

	// ErrUnsupportedScheme indicates a non-http(s) URL scheme.
	ErrUnsupportedScheme = errors.New("unsupported url scheme")

	// ErrHTTP indicates a non-success HTTP response.
	ErrHTTP = errors.New("http download failed")

	// ErrRedirectLimit indicates too many redirects were followed.
	ErrRedirectLimit = errors.New("too many redirects")

	// ErrSizeMismatch indicates streamed bytes did not match ExpectedSize.
	ErrSizeMismatch = errors.New("downloaded size mismatch")

	// ErrMalformedSHA256 indicates ExpectedSHA256 is not a 64-char hex digest.
	ErrMalformedSHA256 = errors.New("malformed expected sha256")

	// ErrChecksumMismatch indicates the streamed digest did not match ExpectedSHA256.
	ErrChecksumMismatch = errors.New("checksum mismatch")

	// ErrCorruptGzip indicates the gzip stream could not be decompressed.
	ErrCorruptGzip = errors.New("corrupt gzip")

	// ErrEmptyExtracted indicates decompression produced zero bytes.
	ErrEmptyExtracted = errors.New("empty extracted file")

	// ErrExtractedSizeLimit indicates decompression exceeded the configured limit.
	ErrExtractedSizeLimit = errors.New("extracted size limit exceeded")
)
