# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.3.35
ARG ALPINE_VERSION=3.24

# ---------------------------------------------------------------------------
# base: PHP-FPM runtime with the extensions the application needs.
# ---------------------------------------------------------------------------
FROM docker.io/library/php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS base

COPY --from=ghcr.io/mlocati/php-extension-installer:2.9 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions bcmath intl opcache pcntl pdo_mysql redis zip

WORKDIR /var/www/api

# ---------------------------------------------------------------------------
# dev: source is bind-mounted; adds Composer and coverage tooling.
# ---------------------------------------------------------------------------
FROM base AS dev

ARG UID=1000
ARG GID=1000

RUN install-php-extensions pcov \
    && cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
    && sed -i '/^user = /d; /^group = /d' /usr/local/etc/php-fpm.d/www.conf \
    && addgroup -g "${GID}" app \
    && adduser -D -u "${UID}" -G app app

COPY --from=docker.io/library/composer:2.10.3 /usr/bin/composer /usr/local/bin/composer
COPY infra/docker/api/php-dev.ini "$PHP_INI_DIR/conf.d/zz-app.ini"
COPY infra/docker/api/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf

USER app
