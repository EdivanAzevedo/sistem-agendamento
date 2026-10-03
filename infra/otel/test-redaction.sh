#!/usr/bin/env sh
# Proves the collector strips personal data and secrets before traces leave it: sends a trace with
# sensitive values through the real redaction processors and inspects what comes out.
#
# Usage: infra/otel/test-redaction.sh   (DOCKER=podman to run with Podman)
set -eu

docker=${DOCKER:-docker}
root=$(cd "$(dirname "$0")/../.." && pwd)
port=${PORT:-14318}
name="otel-redaction-test-$$"
out=$(mktemp -d)

# Same collector version as local development (single source: compose.yaml).
image=$(sed -nE 's|^ *image: (docker\.io/otel/opentelemetry-collector-contrib:[0-9.]+)$|\1|p' "$root/compose.yaml")
[ -n "$image" ] || { echo "Collector image not found in compose.yaml" >&2; exit 1; }

cleanup() {
    $docker rm -f "$name" >/dev/null 2>&1 || true
    rm -rf "$out"
}
trap cleanup EXIT
chmod 777 "$out" # the collector image runs as a non-root user

$docker run -d --name "$name" -p "127.0.0.1:$port:4318" \
    -v "$root/infra/otel:/etc/otel:ro" -v "$out:/out" "$image" \
    --config=/etc/otel/processors.yaml --config=/etc/otel/collector.test.yaml >/dev/null

send() {
    curl -s -o /dev/null -w '%{http_code}' -X POST "http://127.0.0.1:$port/v1/traces" \
        -H 'Content-Type: application/json' --data "@$root/infra/otel/redaction-fixture.json"
}

attempt=0
until [ "$(send || true)" = "200" ]; do
    attempt=$((attempt + 1))
    if [ "$attempt" -gt 50 ]; then
        echo "Collector did not accept traces:" >&2
        $docker logs "$name" >&2
        exit 1
    fi
    sleep 0.2
done

attempt=0
until [ -s "$out/spans.json" ]; do
    attempt=$((attempt + 1))
    [ "$attempt" -gt 50 ] && { echo "No output from the collector" >&2; $docker logs "$name" >&2; exit 1; }
    sleep 0.2
done

spans="$out/spans.json" # read directly: `echo` would mangle backslashes in the JSON
failures=0
check() {
    if [ "$1" = "true" ]; then echo "ok   - $2"; else echo "FAIL - $2"; failures=$((failures + 1)); fi
}
attr() { # span name, attribute key
    jq -r --arg span "$1" --arg key "$2" \
        'first(.resourceSpans[].scopeSpans[].spans[] | select(.name == $span) | .attributes[]? | select(.key == $key) | .value.stringValue) // "<absent>"' \
        "$spans"
}

for secret in SECRET-TOKEN SESSION-ID-SECRET joao.silva@example.com; do
    check "$(grep -q "$secret" "$spans" && echo false || echo true)" "'$secret' does not leave the collector"
done
check "$([ "$(attr 'POST api/v1/invitations/accept' url.full)" = 'https://agendamento.test/api/v1/invitations/accept' ] && echo true || echo false)" "url.full keeps the path without the query string"
check "$([ "$(attr 'POST api/v1/invitations/accept' url.query)" = '<absent>' ] && echo true || echo false)" "url.query is removed"
check "$([ "$(attr 'POST api/v1/invitations/accept' url.path)" = '/api/v1/invitations/accept' ] && echo true || echo false)" "url.path is kept"
check "$([ "$(attr GET db.query.text)" = 'GET' ] && echo true || echo false)" "Redis commands keep only the command name"
check "$([ "$(attr 'sql SELECT' db.query.text)" = 'select * from users where email = ?' ] && echo true || echo false)" "SQL statements with placeholders are kept"
check "$(jq -e 'any(.resourceSpans[].scopeSpans[].spans[].events[]?; .name == "cache hit" and (.attributes | map(.key) | index("tags") != null))' "$spans" >/dev/null && echo true || echo false)" "cache events are kept (without their key)"
check "$(grep -q 'SQLSTATE\[23000\]: Duplicate entry (details redacted)' "$spans" && echo true || echo false)" "database errors keep the SQLSTATE and drop the details"

[ "$failures" -eq 0 ] || { echo "$failures redaction check(s) failed" >&2; exit 1; }
echo "All redaction checks passed"
