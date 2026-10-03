#!/usr/bin/env sh
# Runs the browser tests (web/e2e) against the production images: edge + api on an isolated
# network, no database. Playwright runs inside its official image, matching the installed version.
#
# Usage: e2e.sh <api-image> <edge-image>   (DOCKER=podman to run with Podman)
set -eu

api_image=${1:?usage: e2e.sh <api-image> <edge-image>}
edge_image=${2:?usage: e2e.sh <api-image> <edge-image>}
docker=${DOCKER:-docker}
root=$(cd "$(dirname "$0")/../.." && pwd)
id=$$
network="e2e-$id"

version=$(jq -r '.packages["node_modules/@playwright/test"].version' "$root/web/package-lock.json")
playwright_image="mcr.microsoft.com/playwright:v$version-noble"

cleanup() {
    status=$?
    if [ "$status" -ne 0 ]; then
        echo "--- api logs" >&2
        $docker logs "e2e-api-$id" 2>&1 | tail -20 >&2 || true
        echo "--- edge logs" >&2
        $docker logs "e2e-edge-$id" 2>&1 | tail -20 >&2 || true
    fi
    $docker rm -f "e2e-api-$id" "e2e-edge-$id" >/dev/null 2>&1 || true
    $docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT

$docker network create "$network" >/dev/null

$docker run -d --name "e2e-api-$id" --network "$network" --network-alias api \
    -e APP_KEY="base64:$(openssl rand -base64 32)" -e APP_ENV=production -e APP_DEBUG=false \
    -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync \
    "$api_image" >/dev/null
# Production warms the docs cache at startup; do the same here.
$docker exec "e2e-api-$id" php artisan scramble:cache >/dev/null

$docker run -d --name "e2e-edge-$id" --network "$network" --network-alias edge "$edge_image" >/dev/null

# Playwright shares the edge's network namespace and reaches it as http://localhost:8080: browsers
# treat localhost as a secure origin (like HTTPS in production), so policies such as COOP apply.
$docker run --rm --network "container:e2e-edge-$id" \
    -e CI="${CI:-}" -e E2E_BASE_URL=http://localhost:8080 \
    -v "$root/web:/work" -w /work \
    "$playwright_image" \
    sh -c 'node -e "
        const wait = async () => {
            for (let i = 0; i < 100; i++) {
                try { if ((await fetch(\"http://localhost:8080/up\")).ok) return; } catch {}
                await new Promise((r) => setTimeout(r, 200));
            }
            throw new Error(\"edge did not become ready\");
        };
        wait();
    " && npx playwright test'
