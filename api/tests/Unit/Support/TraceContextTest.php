<?php

declare(strict_types=1);

use App\Support\Tracing\TraceContext;
use OpenTelemetry\SDK\Trace\TracerProvider;

it('generates a W3C trace id when no span is active', function () {
    expect((new TraceContext)->traceId())
        ->toMatch('/^[0-9a-f]{32}$/')
        ->not->toBe(str_repeat('0', 32))
        ->and((new TraceContext)->spanId())->toBeNull();
});

it('keeps the generated trace id for its whole lifetime', function () {
    $trace = new TraceContext;

    expect($trace->traceId())->toBe($trace->traceId());
});

it('generates a different trace id per instance', function () {
    expect((new TraceContext)->traceId())->not->toBe((new TraceContext)->traceId());
});

it('uses the active OpenTelemetry trace and span ids', function () {
    $span = (new TracerProvider)->getTracer('test')->spanBuilder('operation')->startSpan();
    $scope = $span->activate();

    try {
        $trace = new TraceContext;

        expect($trace->traceId())->toBe($span->getContext()->getTraceId())
            ->and($trace->spanId())->toBe($span->getContext()->getSpanId());
    } finally {
        $scope->detach();
        $span->end();
    }
});

it('generates a request id when none is assigned', function () {
    $trace = new TraceContext;

    expect($trace->requestId())->toBeUuid()
        ->and($trace->requestId())->toBe($trace->requestId());
});

it('adopts well-formed request ids only', function (string $candidate, bool $adopted) {
    $trace = new TraceContext;
    $trace->useRequestId($candidate);

    expect($trace->requestId() === $candidate)->toBe($adopted);
})->with([
    'uuid from the proxy' => ['5f0c9c1e-7f53-4c3a-9d2b-1f6e8b2a4c10', true],
    'too short' => ['abc', false],
    'too long' => [str_repeat('a', 65), false],
    'log injection attempt' => ["abc12345\n{\"level\":\"emergency\"}", false],
    'spaces' => ['abc 12345', false],
]);
