<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * Spans recorded during the test. Tracing never leaves the process in tests, even where the
     * environment configures an exporter (the local containers do).
     */
    protected InMemoryExporter $spans;

    private TracerProvider $tracerProvider;

    private ScopeInterface $tracingScope;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spans = new InMemoryExporter;
        $this->tracerProvider = new TracerProvider(new SimpleSpanProcessor($this->spans));
        $this->tracingScope = Configurator::create()
            ->withTracerProvider($this->tracerProvider)
            ->withPropagator(TraceContextPropagator::getInstance())
            ->activate();
    }

    protected function tearDown(): void
    {
        $this->tracingScope->detach();
        $this->tracerProvider->shutdown();

        parent::tearDown();
    }
}
