<?php

declare(strict_types=1);

use App\Http\Middleware\AssignCorrelationIds;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use OpenTelemetry\SDK\Trace\SpanDataInterface;

it('traces every request as a server span with its route and status', function () {
    $response = $this->get('/up');

    $span = serverSpan($this->spans);
    $attributes = $span->getAttributes();

    expect($attributes->get('http.request.method'))->toBe('GET')
        ->and($attributes->get('http.route'))->toBe('up')
        ->and($attributes->get('http.response.status_code'))->toBe(200)
        ->and($response->headers->get(AssignCorrelationIds::TRACE_HEADER))->toBe($span->getTraceId());
});

it('returns the trace id of the request in error responses', function () {
    $response = $this->getJson('/api/v1/does-not-exist');

    expect($response->json('trace_id'))->toBe(serverSpan($this->spans)->getTraceId());
});

it('records database queries as child spans, with placeholders instead of values', function () {
    Route::get('/api/v1/__trace/query', fn () => DB::select('select ? as value', ['secret-value']));

    $this->getJson('/api/v1/__trace/query')->assertOk();

    $query = collect($this->spans->getSpans())
        ->first(fn (SpanDataInterface $span): bool => $span->getName() === 'sql SELECT');

    expect($query)->toBeInstanceOf(SpanDataInterface::class)
        ->and($query->getParentSpanId())->toBe(serverSpan($this->spans)->getSpanId())
        ->and($query->getAttributes()->get('db.query.text'))->toBe('select ? as value')
        ->and(serializedSpans($this->spans))->not->toContain('secret-value');
});
