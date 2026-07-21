package access

import (
	"math"
	"time"

	"golang.org/x/time/rate"
)

// Decision is the outcome of a rate-limit check.
//
// ResetAfter / RateLimit-Reset represent token-bucket availability timing
// (when another token is expected), not a fixed calendar window reset.
type Decision struct {
	Allowed    bool
	Limit      int
	Remaining  int
	ResetAfter time.Duration
	RetryAfter time.Duration
}

type clientLimiter struct {
	limiter  *rate.Limiter
	lastSeen time.Time
	limitRPM int
}

func newRateLimiter(cfg LimitConfig) *rate.Limiter {
	return rate.NewLimiter(perMinute(cfg.RequestsPerMinute), cfg.Burst)
}

// perMinute converts requests/minute to tokens/second for x/time/rate.
func perMinute(requestsPerMinute int) rate.Limit {
	return rate.Limit(float64(requestsPerMinute) / 60.0)
}

func decide(lim *rate.Limiter, limitRPM int, now time.Time) Decision {
	res := lim.ReserveN(now, 1)
	delay := res.DelayFrom(now)
	if delay > 0 {
		res.CancelAt(now)
		retry := ceilDuration(delay)
		if retry < time.Second {
			retry = time.Second
		}
		return Decision{
			Allowed:    false,
			Limit:      limitRPM,
			Remaining:  0,
			ResetAfter: retry,
			RetryAfter: retry,
		}
	}

	tokens := lim.TokensAt(now)
	remaining := int(math.Floor(tokens))
	if remaining < 0 {
		remaining = 0
	}
	return Decision{
		Allowed:    true,
		Limit:      limitRPM,
		Remaining:  remaining,
		ResetAfter: resetAfter(lim, now, tokens),
	}
}

func resetAfter(lim *rate.Limiter, now time.Time, tokens float64) time.Duration {
	if tokens >= 1 {
		return 0
	}
	limit := lim.Limit()
	if limit <= 0 {
		return time.Second
	}
	need := 1 - tokens
	if need <= 0 {
		return 0
	}
	secs := need / float64(limit)
	return ceilDuration(time.Duration(secs * float64(time.Second)))
}

func ceilDuration(d time.Duration) time.Duration {
	if d <= 0 {
		return 0
	}
	secs := int(math.Ceil(d.Seconds()))
	if secs < 1 {
		secs = 1
	}
	return time.Duration(secs) * time.Second
}
