<?php

declare(strict_types=1);

namespace App\Support\Tracing;

/**
 * Identifies the current request across logs, error responses and (later) distributed traces.
 *
 * The id follows the W3C Trace Context format (32 lowercase hex chars, never all zeros), so it can
 * be replaced by the active OpenTelemetry trace id without changing the public contract.
 * Bound as a scoped service: one instance per request.
 */
final class TraceContext
{
    private const INVALID_TRACE_ID = '00000000000000000000000000000000';

    private ?string $traceId = null;

    public function traceId(): string
    {
        return $this->traceId ??= self::generate();
    }

    private static function generate(): string
    {
        do {
            $id = bin2hex(random_bytes(16));
        } while ($id === self::INVALID_TRACE_ID);

        return $id;
    }
}
