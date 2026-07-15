# syntax=docker/dockerfile:1.7

# -----------------------------------------------------------------------------
# Stage 1: frontend assets
# -----------------------------------------------------------------------------
FROM node:24-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 2: PHP dependencies
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction

# -----------------------------------------------------------------------------
# Stage 3: production runtime (FrankenPHP)
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4 AS runtime

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
    && install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-finba.ini"
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/finba-entrypoint

RUN chmod +x /usr/local/bin/finba-entrypoint \
    && addgroup --system --gid 10001 finba \
    && adduser --system --uid 10001 --ingroup finba --home /home/finba finba \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R finba:finba /data/caddy /config/caddy

COPY --from=vendor --chown=finba:finba /app /app
COPY --from=frontend --chown=finba:finba /app/public/build /app/public/build

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/private \
        bootstrap/cache \
    && chown -R finba:finba storage bootstrap/cache \
    && rm -f .env .env.* \
    && rm -rf tests node_modules

USER finba

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PORT=8080 \
    SERVER_NAME=:8080 \
    CADDY_GLOBAL_OPTIONS="auto_https off" \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/up" || exit 1

ENTRYPOINT ["/usr/local/bin/finba-entrypoint"]
