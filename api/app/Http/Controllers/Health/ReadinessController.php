<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Readiness probe: the app can serve traffic only when its dependencies respond.
 * Liveness (`/up`) is handled by the framework and touches no dependency.
 */
final class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check('database', fn () => DB::select('select 1')),
            'redis' => $this->check('redis', fn () => Redis::connection()->ping()),
            'queue' => $this->check('queue', fn () => Queue::connection()->size()),
        ];

        $ready = ! in_array(false, $checks, true);

        return new JsonResponse(
            [
                'status' => $ready ? 'ok' : 'unavailable',
                'checks' => array_map(fn (bool $ok): string => $ok ? 'ok' : 'failing', $checks),
            ],
            $ready ? 200 : 503,
        );
    }

    /**
     * Failure details go to the log only, never to the response body.
     */
    private function check(string $name, Closure $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $e) {
            Log::warning('Readiness check failed', ['check' => $name, 'exception' => $e]);

            return false;
        }
    }
}
