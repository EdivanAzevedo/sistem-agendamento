<?php

declare(strict_types=1);

use App\Support\Logging\CorrelationProcessor;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * Routes the application's logs to an in-memory handler (same processors as production).
 */
function captureLogs(): TestHandler
{
    config([
        'logging.default' => 'capture',
        'logging.channels.capture' => [
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'processors' => [CorrelationProcessor::class],
        ],
    ]);

    $logger = Log::channel('capture')->getLogger();
    expect($logger)->toBeInstanceOf(Logger::class);

    /** @var Logger $logger */
    $handler = $logger->getHandlers()[0];
    expect($handler)->toBeInstanceOf(TestHandler::class);

    /** @var TestHandler $handler */
    return $handler;
}

/**
 * The single server span recorded for the request made in the test.
 */
function serverSpan(InMemoryExporter $spans): SpanDataInterface
{
    $servers = array_values(array_filter(
        $spans->getSpans(),
        fn (mixed $span): bool => $span instanceof SpanDataInterface && $span->getKind() === SpanKind::KIND_SERVER,
    ));

    expect($servers)->toHaveCount(1);

    return $servers[0];
}

/**
 * Everything recorded in the spans (attributes, events, status), to assert that a value never leaked.
 */
function serializedSpans(InMemoryExporter $spans): string
{
    return (string) json_encode(array_map(fn (SpanDataInterface $span): array => [
        'name' => $span->getName(),
        'status' => $span->getStatus()->getDescription(),
        'attributes' => $span->getAttributes()->toArray(),
        'events' => array_map(fn ($event): array => [
            'name' => $event->getName(),
            'attributes' => $event->getAttributes()->toArray(),
        ], $span->getEvents()),
    ], array_values(array_filter($spans->getSpans(), fn (mixed $span): bool => $span instanceof SpanDataInterface))));
}
