<?php

declare(strict_types=1);

namespace App\Support\Tracing;

use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\Span;

/**
 * Identifiers that correlate a request across logs, error responses and distributed traces.
 *
 * The trace id is the active OpenTelemetry trace id, so the `X-Trace-Id` header, the `trace_id`
 * of error responses and every log line point to the same trace. Without an active span (tracing
 * disabled, CLI), a W3C-format id is generated instead, keeping the public contract unchanged.
 * Bound as a scoped service: one instance per request (or queued job).
 */
final class TraceContext
{
    private const INVALID_TRACE_ID = '00000000000000000000000000000000';

    private ?string $fallbackTraceId = null;

    private ?string $requestId = null;

    public function traceId(): string
    {
        $span = Span::getCurrent()->getContext();

        if ($span->isValid()) {
            return $span->getTraceId();
        }

        return $this->fallbackTraceId ??= self::generateTraceId();
    }

    public function spanId(): ?string
    {
        $span = Span::getCurrent()->getContext();

        return $span->isValid() ? $span->getSpanId() : null;
    }

    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::uuid();
    }

    /**
     * Adopts the request id assigned by the edge proxy when it is well formed. The proxy overwrites
     * any client-sent value, so external input never reaches this point in production.
     */
    public function useRequestId(?string $candidate): void
    {
        if ($candidate !== null && preg_match('/^[A-Za-z0-9-]{8,64}$/', $candidate) === 1) {
            $this->requestId = $candidate;
        }
    }

    private static function generateTraceId(): string
    {
        do {
            $id = bin2hex(random_bytes(16));
        } while ($id === self::INVALID_TRACE_ID);

        return $id;
    }
}
