#!/usr/bin/env sh
# Boot checks for a production image, run before it is scanned and published.
# The image has no .env file, so these checks also prove the production fallbacks apply.
#
# Usage: smoke-image.sh <api|edge> <image-ref>
# Set DOCKER=podman to run it locally with Podman. To reproduce CI faithfully, build the image from
# a clean checkout (e.g. `git worktree add`): untracked local files would otherwise enter the build.
set -eu

target=${1:?usage: smoke-image.sh <api|edge> <image-ref>}
image=${2:?usage: smoke-image.sh <api|edge> <image-ref>}
docker=${DOCKER:-docker}

case "$target" in
api)
    echo "PHP-FPM configuration"
    $docker run --rm --entrypoint php-fpm "$image" -t

    echo "PHP extensions"
    # shellcheck disable=SC2016 # PHP code: `$` must reach PHP unexpanded.
    $docker run --rm "$image" php -r '
        $missing = array_filter(
            ["bcmath", "intl", "pcntl", "pdo_mysql", "redis", "zip", "Zend OPcache", "opentelemetry", "protobuf"],
            fn (string $ext): bool => ! extension_loaded($ext),
        );
        if ($missing !== []) { fwrite(STDERR, "missing: ".implode(", ", $missing)."\n"); exit(1); }
        echo "all present\n";'

    echo "Laravel caches (config, routes, views, events) with read-only code"
    $docker run --rm "$image" php artisan optimize

    echo "API documentation cache (public docs must never be generated per request)"
    $docker run --rm "$image" php artisan scramble:cache

    echo "OpenTelemetry SDK boots with production settings (collector unreachable is not an error)"
    $docker run --rm \
        -e OTEL_PHP_AUTOLOAD_ENABLED=true -e OTEL_SERVICE_NAME=smoke-test -e OTEL_TRACES_EXPORTER=otlp \
        -e OTEL_METRICS_EXPORTER=none -e OTEL_LOGS_EXPORTER=none -e OTEL_PROPAGATORS=tracecontext \
        -e OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf -e OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318 \
        "$image" php artisan about --only=environment >/dev/null

    echo "Drivers without any environment variables"
    drivers=$($docker run --rm "$image" php artisan about --only=drivers --json)
    echo "$drivers"
    echo "$drivers" | jq -e '
        .drivers
        | .database == "mysql" and .cache == "redis" and .session == "redis"
          and .queue == "redis" and .mail == "smtp"
          and ((.logs | if type == "array" then . else [.] end) == ["stderr"])' >/dev/null
    ;;
edge)
    echo "Caddy configuration"
    $docker run --rm "$image" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile

    echo "Compiled SPA"
    $docker run --rm "$image" test -s /srv/web/index.html
    ;;
*)
    echo "Unknown target: $target" >&2
    exit 1
    ;;
esac

echo "Smoke checks passed for $target"
