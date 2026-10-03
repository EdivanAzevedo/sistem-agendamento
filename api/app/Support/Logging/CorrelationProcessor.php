<?php

declare(strict_types=1);

namespace App\Support\Logging;

use App\Support\Tracing\TraceContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds the trace id, span id and request id to every log record.
 */
final class CorrelationProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        // Resolved per record: the context is scoped to the current request or job.
        $trace = app(TraceContext::class);

        $record->extra['trace_id'] = $trace->traceId();
        $record->extra['request_id'] = $trace->requestId();

        if (($spanId = $trace->spanId()) !== null) {
            $record->extra['span_id'] = $spanId;
        }

        return $record;
    }
}
