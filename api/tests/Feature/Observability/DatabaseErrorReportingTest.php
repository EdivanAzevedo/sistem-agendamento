<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\StatusCode;

beforeEach(function () {
    Route::get('/api/v1/__db-error', fn () => DB::select(
        'select * from missing_table where email = ?',
        ['joao.silva@example.com'],
    ));
});

it('reports database errors without the query values', function () {
    $logs = captureLogs();

    $this->getJson('/api/v1/__db-error')->assertInternalServerError();

    $report = collect($logs->getRecords())->firstWhere('message', 'Database query failed.');

    expect($report)->toBeInstanceOf(LogRecord::class)
        ->and($report?->context['sql'])->toBe('select * from missing_table where email = ?')
        ->and($report?->context['sqlstate'])->toBe('42S02')
        ->and($report?->context['trace'])->not->toBeEmpty()
        ->and(json_encode(array_map(fn (LogRecord $record): array => $record->toArray(), $logs->getRecords())))
        ->not->toContain('joao.silva@example.com');
});

it('keeps database error values out of the traces', function () {
    captureLogs();

    $this->getJson('/api/v1/__db-error')->assertInternalServerError();

    expect(serverSpan($this->spans)->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR)
        ->and(serializedSpans($this->spans))->not->toContain('joao.silva@example.com');
});
