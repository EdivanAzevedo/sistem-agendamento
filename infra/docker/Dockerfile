# syntax=docker/dockerfile:1

# Build context: repository root. Targets:
#   dev   PHP-FPM for local development (source bind-mounted)
#   api   PHP-FPM production runtime
#   edge  Caddy serving the compiled SPA and proxying the API to `api`

# Base image versions are literal (not build args) so Dependabot can update them.

FROM docker.io/library/composer:2.10.3 AS composer

# ---------------------------------------------------------------------------
# base: PHP-FPM runtime with the extensions the application needs.
# ---------------------------------------------------------------------------
FROM docker.io/library/php:8.3.35-fpm-alpine3.24 AS base

COPY --from=ghcr.io/mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions bcmath intl opcache pcntl pdo_mysql redis zip \
    # FPM never runs as root, so the pool-level user/group directives do not apply.
    && sed -i '/^user = /d; /^group = /d' /usr/local/etc/php-fpm.d/www.conf

COPY infra/docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /var/www/api

# ---------------------------------------------------------------------------
# dev: source is bind-mounted; adds Composer and coverage tooling.
# ---------------------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000

RUN install-php-extensions pcov \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY infra/docker/php/php-dev.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

USER app

# ---------------------------------------------------------------------------
# vendor: production dependencies and optimized autoloader (build only).
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY api/ ./
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# ---------------------------------------------------------------------------
# api: production PHP-FPM. Code is owned by root and read-only for the runtime user.
# ---------------------------------------------------------------------------
FROM base AS api

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY infra/docker/php/php-prod.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

COPY --from=vendor /var/www/api /var/www/api
RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

# ---------------------------------------------------------------------------
# spa: compiled single-page application (build only).
# ---------------------------------------------------------------------------
FROM docker.io/library/node:24.21.0-alpine3.24 AS spa

WORKDIR /app
COPY web/package.json web/package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

COPY web/ ./
RUN npm run build-only

# ---------------------------------------------------------------------------
# edge: Caddy with the SPA. Serves static files and forwards the API to `api`.
# ---------------------------------------------------------------------------
FROM docker.io/library/caddy:2.11.4-alpine AS edge

RUN addgroup -S caddy && adduser -S -G caddy caddy \
    && chown -R caddy:caddy /data /config

COPY infra/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=spa /app/dist /srv/web

USER caddy
