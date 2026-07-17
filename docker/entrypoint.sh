#!/usr/bin/env sh
set -eu

PORT="${PORT:-8080}"
export PORT
export SERVER_NAME=":${PORT}"

cd /app

mkdir -p \
  storage/framework/cache/data \
  storage/framework/cache/sushi \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/private \
  bootstrap/cache

# Remove stale on-disk Sushi SQLite files; Country rebuilds in memory at runtime.
rm -f storage/framework/cache/sushi-*.sqlite \
  storage/framework/cache/sushi/sushi-*.sqlite \
  2>/dev/null || true

# Runtime caches must use Cloud Run env/secrets for this revision.
php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan config:clear --ansi >/dev/null 2>&1 || true
php artisan route:clear --ansi >/dev/null 2>&1 || true
php artisan view:clear --ansi >/dev/null 2>&1 || true
php artisan event:clear --ansi >/dev/null 2>&1 || true

# Cache config/routes/views only; do not prebuild Sushi SQLite files.
php artisan optimize --ansi

echo "Finba.se ready on 0.0.0.0:${PORT} (GIT_SHA=${GIT_SHA:-unknown} APP_BUILD=${APP_BUILD:-unknown})"

exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
