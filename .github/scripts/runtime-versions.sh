#!/usr/bin/env sh
# Prints the runtime versions of the production images as `key=value` lines (GITHUB_OUTPUT format),
# so CI tests on the same PHP, Node and Composer versions that ship.
# Single source of truth: infra/docker/Dockerfile, kept up to date by Dependabot.
set -eu

dockerfile="$(dirname "$0")/../../infra/docker/Dockerfile"

php=$(sed -nE 's|^FROM docker\.io/library/php:([0-9]+\.[0-9]+)\.[0-9]+-.*|\1|p' "$dockerfile")
node=$(sed -nE 's|^FROM docker\.io/library/node:([0-9]+\.[0-9]+\.[0-9]+)-.*|\1|p' "$dockerfile")
composer=$(sed -nE 's|^FROM docker\.io/library/composer:([0-9]+\.[0-9]+\.[0-9]+) .*|\1|p' "$dockerfile")

# PHP extensions of the production runtime (first `install-php-extensions` line, i.e. the base
# stage), in setup-php's comma-separated format, with the same pinned versions.
php_extensions=$(awk '
    $1 == "RUN" && $2 == "install-php-extensions" {
        for (i = 3; i <= NF && $i != "\\" && $i != "&&"; i++) printf "%s%s", (i > 3 ? ", " : ""), $i
        exit
    }' "$dockerfile")

if [ -z "$php" ] || [ -z "$node" ] || [ -z "$composer" ] || [ -z "$php_extensions" ]; then
    echo "Could not read runtime versions from $dockerfile" \
        "(php='$php' node='$node' composer='$composer' php_extensions='$php_extensions')" >&2
    exit 1
fi

printf 'php=%s\nnode=%s\ncomposer=%s\nphp_extensions=%s\n' "$php" "$node" "$composer" "$php_extensions"
