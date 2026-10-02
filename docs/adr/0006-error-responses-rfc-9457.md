# 0006 — Error responses follow RFC 9457 (Problem Details)

- **Status:** Accepted
- **Date:** 2026-10-02

## Context

The API is consumed by our own SPA today and may be consumed by third parties later. Clients need
to tell failures apart reliably (e.g. "slot just got taken" vs. "plan limit reached") without
parsing human-readable messages, and support needs a way to connect a failure a user reports to
the server-side logs. Laravel's default error format differs between exception types and, in debug
mode, exposes stack traces.

## Decision

Every error raised while serving `/api/*` (or any request that expects JSON) is rendered as an
RFC 9457 document with `Content-Type: application/problem+json`:

```json
{
  "type": "https://<app-url>/problems/validation-error",
  "title": "Dados inválidos",
  "status": 422,
  "detail": "Alguns campos não foram preenchidos corretamente.",
  "errors": { "email": ["O campo e-mail é obrigatório."] },
  "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736"
}
```

- **`type`**: generic HTTP failures (401, 403, 404, 405, 419, 429, 500, …) use `about:blank`, and
  `title` is the status phrase, as the RFC recommends. Application-specific failures get an
  absolute URI under `{APP_URL}/problems/{slug}`. The slug is part of the public contract; clients
  branch on it, never on `title` or `detail`. Because the base URL differs per environment, clients
  compare only the slug (the last path segment), never the full URI.
- **Domain failures** extend `App\Support\Problems\ProblemException`, which declares the status and
  the slug. They are expected outcomes, so they are **not reported** as application errors.
- **Language**: `title` and `detail` are localized through Laravel translation files
  (`lang/{locale}/problems.php`), Portuguese (pt-BR) by default, so the SPA can show them as-is.
  The response declares its language in `Content-Language`.
- **Validation** (`422`) carries an `errors` member keyed by field, with a list of messages per
  field — the same shape Laravel uses, which maps directly onto form libraries.
- **`trace_id`**: every response carries an `X-Trace-Id` header, and every problem document repeats
  it in the body. It is also added to the context of every log entry. The id uses the W3C Trace
  Context format (32 hex characters), so it can later be replaced by the OpenTelemetry trace id
  without changing the contract.
- **No internal details leak**: unexpected errors return a generic 500 problem. Exception class,
  message and location are added in a `debug` member **only** when `APP_DEBUG=true`. Messages from
  denied authorizations and from `abort()` calls are never echoed back.
- Headers that carry meaning are preserved (`Retry-After` on 429, `Allow` on 405).
- An explicit response thrown on purpose (`HttpResponseException`, e.g. from a middleware) is
  returned unchanged; it is an intended outcome, not a failure.

## Consequences

- One error shape for every endpoint; the SPA has a single error-handling path and uses `type` to
  trigger specific behavior (e.g. offering an upgrade when a plan limit is reached).
- Adding a new failure means adding a `ProblemException` subclass and its translations; the HTTP
  mapping lives with the exception instead of in a central table.
- `about:blank` titles are localized, which the RFC allows; clients must not compare titles.
- Because the API is served from the same origin as the SPA, CORS is disabled entirely: no
  cross-origin request is ever granted access.
