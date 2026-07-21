package access

import (
	"net"
	"net/http"
	"strings"
)

// RealClientIP returns the best-effort client IP for rate limiting.
//
// When trustProxyHeaders is false (default), only RemoteAddr is used.
// When true, priority is:
//  1. CF-Connecting-IP
//  2. first valid IP in X-Forwarded-For
//  3. RemoteAddr
//
// Enable proxy-header trust only when every request is guaranteed to arrive
// through a trusted proxy path (for example Cloudflare → Cloud Run).
func RealClientIP(r *http.Request, trustProxyHeaders bool) string {
	if trustProxyHeaders {
		if ip := normalizeIP(r.Header.Get("CF-Connecting-IP")); ip != "" {
			return ip
		}
		if ip := firstForwardedIP(r.Header.Get("X-Forwarded-For")); ip != "" {
			return ip
		}
	}
	return normalizeIP(stripHostPort(r.RemoteAddr))
}

func firstForwardedIP(header string) string {
	for _, part := range strings.Split(header, ",") {
		if ip := normalizeIP(part); ip != "" {
			return ip
		}
	}
	return ""
}

func stripHostPort(addr string) string {
	addr = strings.TrimSpace(addr)
	if addr == "" {
		return ""
	}
	host, _, err := net.SplitHostPort(addr)
	if err == nil {
		return host
	}
	// Bare IPv6 without brackets may fail SplitHostPort; return as-is for ParseIP.
	return addr
}

func normalizeIP(raw string) string {
	raw = strings.TrimSpace(raw)
	if raw == "" {
		return ""
	}
	// Some proxies wrap IPv6 in brackets.
	raw = strings.Trim(raw, "[]")
	ip := net.ParseIP(raw)
	if ip == nil {
		return ""
	}
	return ip.String()
}
