<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tracing\TraceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns the request's trace id (`X-Trace-Id`) and request id (`X-Request-Id`) to the client,
 * so a user-reported error can be matched to the server-side logs and traces.
 */
final class AssignCorrelationIds
{
    public const TRACE_HEADER = 'X-Trace-Id';

    public const REQUEST_HEADER = 'X-Request-Id';

    public function __construct(private readonly TraceContext $trace) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->trace->useRequestId($request->headers->get(self::REQUEST_HEADER));

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(self::TRACE_HEADER, $this->trace->traceId());
        $response->headers->set(self::REQUEST_HEADER, $this->trace->requestId());

        return $response;
    }
}
