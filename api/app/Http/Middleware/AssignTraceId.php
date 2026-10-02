<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tracing\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exposes the request's trace id to every log line and to the client (`X-Trace-Id`),
 * so a user-reported error can be matched to the server-side logs.
 */
final class AssignTraceId
{
    public const HEADER = 'X-Trace-Id';

    public function __construct(private readonly TraceContext $trace) {}

    public function handle(Request $request, Closure $next): Response
    {
        $traceId = $this->trace->traceId();

        Log::shareContext(['trace_id' => $traceId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $traceId);

        return $response;
    }
}
