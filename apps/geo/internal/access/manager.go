package access

import (
	"log/slog"
	"sync"
	"time"
)

// LimiterManager owns per-client token buckets with TTL cleanup.
type LimiterManager struct {
	cfg    Config
	logger *slog.Logger

	mu      sync.Mutex
	clients map[string]*clientLimiter

	now       func() time.Time
	newTicker func(d time.Duration) ticker
	stopCh    chan struct{}
	doneCh    chan struct{}
	closeOnce sync.Once
}

type ticker interface {
	C() <-chan time.Time
	Stop()
}

type timeTicker struct {
	*time.Ticker
}

func (t timeTicker) C() <-chan time.Time { return t.Ticker.C }

// NewLimiterManager creates a manager and starts the cleanup goroutine.
func NewLimiterManager(cfg Config, logger *slog.Logger) *LimiterManager {
	if logger == nil {
		logger = slog.Default()
	}
	m := &LimiterManager{
		cfg:       cfg,
		logger:    logger,
		clients:   make(map[string]*clientLimiter),
		now:       time.Now,
		newTicker: func(d time.Duration) ticker { return timeTicker{time.NewTicker(d)} },
		stopCh:    make(chan struct{}),
		doneCh:    make(chan struct{}),
	}
	go m.cleanupLoop()
	return m
}

// Allow checks and consumes one token for the client bucket.
func (m *LimiterManager) Allow(client Client) Decision {
	key := LimiterKey(client)
	limCfg := m.cfg.LimitFor(client.Level)
	now := m.now()

	m.mu.Lock()
	entry, ok := m.clients[key]
	if !ok {
		entry = &clientLimiter{
			limiter:  newRateLimiter(limCfg),
			limitRPM: limCfg.RequestsPerMinute,
		}
		m.clients[key] = entry
	}
	entry.lastSeen = now
	lim := entry.limiter
	rpm := entry.limitRPM
	m.mu.Unlock()

	return decide(lim, rpm, now)
}

// Close stops the cleanup goroutine. It is safe to call multiple times.
func (m *LimiterManager) Close() error {
	m.closeOnce.Do(func() {
		close(m.stopCh)
		<-m.doneCh
	})
	return nil
}

// Len returns the number of tracked client buckets (for tests).
func (m *LimiterManager) Len() int {
	m.mu.Lock()
	defer m.mu.Unlock()
	return len(m.clients)
}

// CleanupExpired removes buckets inactive longer than ClientTTL.
// Exported for deterministic tests.
func (m *LimiterManager) CleanupExpired() {
	cutoff := m.now().Add(-m.cfg.ClientTTL)
	m.mu.Lock()
	defer m.mu.Unlock()
	for key, entry := range m.clients {
		if entry.lastSeen.Before(cutoff) {
			delete(m.clients, key)
		}
	}
}

func (m *LimiterManager) cleanupLoop() {
	defer close(m.doneCh)
	t := m.newTicker(m.cfg.CleanupInterval)
	defer t.Stop()
	for {
		select {
		case <-m.stopCh:
			return
		case <-t.C():
			m.CleanupExpired()
		}
	}
}
