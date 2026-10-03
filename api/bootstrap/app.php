<?php

declare(strict_types=1);

use App\Http\Middleware\AssignCorrelationIds;
use App\Support\Logging\ReportQueryExceptionSafely;
use App\Support\Problems\ProblemException;
use App\Support\Problems\ProblemRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], __DIR__.'/../routes/health.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // First in the stack, so every response (including errors) carries the correlation ids.
        $middleware->prepend(AssignCorrelationIds::class);

        // The SPA and the API share the same origin: cross-origin access is never granted.
        $middleware->remove(HandleCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected business-rule failures are part of the API contract, not application errors.
        $exceptions->dontReport(ProblemException::class);

        // Database errors are reported without the query values (personal data must not be logged).
        $exceptions->report(new ReportQueryExceptionSafely);

        $exceptions->render(
            fn (Throwable $e, Request $request) => app(ProblemRenderer::class)->render($e, $request),
        );
    })->create();
