<?php

declare(strict_types=1);

use App\Support\Tracing\TraceContext;

it('generates a W3C trace id', function () {
    expect((new TraceContext)->traceId())
        ->toMatch('/^[0-9a-f]{32}$/')
        ->not->toBe(str_repeat('0', 32));
});

it('keeps the same trace id for its whole lifetime', function () {
    $trace = new TraceContext;

    expect($trace->traceId())->toBe($trace->traceId());
});

it('generates a different trace id per instance', function () {
    expect((new TraceContext)->traceId())->not->toBe((new TraceContext)->traceId());
});
