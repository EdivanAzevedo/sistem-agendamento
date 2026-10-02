<?php

declare(strict_types=1);

use App\Http\Middleware\AssignTraceId;
use Illuminate\Support\Facades\Log;

it('returns a trace id header on successful responses', function () {
    $this->get('/up')
        ->assertOk()
        ->assertHeader(AssignTraceId::HEADER);
});

it('generates a new trace id for every request', function () {
    $first = $this->get('/up')->headers->get(AssignTraceId::HEADER);

    $this->refreshApplication();

    $second = $this->get('/up')->headers->get(AssignTraceId::HEADER);

    expect($first)->not->toBe($second);
});

it('adds the trace id to the context of every log entry', function () {
    $traceId = $this->get('/up')->headers->get(AssignTraceId::HEADER);

    expect(Log::sharedContext())->toMatchArray(['trace_id' => $traceId]);
});
