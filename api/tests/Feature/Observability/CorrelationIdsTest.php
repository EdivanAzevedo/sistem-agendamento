<?php

declare(strict_types=1);

use App\Http\Middleware\AssignCorrelationIds;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('returns the trace id and the request id on every response', function () {
    $this->get('/up')
        ->assertOk()
        ->assertHeader(AssignCorrelationIds::TRACE_HEADER)
        ->assertHeader(AssignCorrelationIds::REQUEST_HEADER);
});

it('adopts the request id assigned by the edge proxy', function () {
    $requestId = (string) Str::uuid();

    $this->get('/up', [AssignCorrelationIds::REQUEST_HEADER => $requestId])
        ->assertHeader(AssignCorrelationIds::REQUEST_HEADER, $requestId);
});

it('replaces a malformed request id', function () {
    $response = $this->get('/up', [AssignCorrelationIds::REQUEST_HEADER => 'not valid; injected']);

    expect($response->headers->get(AssignCorrelationIds::REQUEST_HEADER))->toBeUuid();
});

it('assigns new ids to each request', function () {
    $first = $this->get('/up')->headers;

    $this->refreshApplication();

    $second = $this->get('/up')->headers;

    expect($first->get(AssignCorrelationIds::TRACE_HEADER))->not->toBe($second->get(AssignCorrelationIds::TRACE_HEADER))
        ->and($first->get(AssignCorrelationIds::REQUEST_HEADER))->not->toBe($second->get(AssignCorrelationIds::REQUEST_HEADER));
});

it('adds the trace, span and request ids to every log line', function () {
    $logs = captureLogs();
    Route::get('/api/v1/__log', function () {
        Log::info('Something happened');

        return ['ok' => true];
    });

    $requestId = (string) Str::uuid();
    $this->getJson('/api/v1/__log', [AssignCorrelationIds::REQUEST_HEADER => $requestId])->assertOk();

    $record = collect($logs->getRecords())->firstWhere('message', 'Something happened');

    expect($record?->extra['trace_id'])->toBe(serverSpan($this->spans)->getTraceId())
        ->and($record?->extra['request_id'])->toBe($requestId)
        ->and($record?->extra['span_id'])->toMatch('/^[0-9a-f]{16}$/');
});
