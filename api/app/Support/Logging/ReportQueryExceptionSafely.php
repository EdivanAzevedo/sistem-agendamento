<?php

declare(strict_types=1);

namespace App\Support\Logging;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Reports database errors without the query values.
 *
 * Laravel's QueryException message embeds the SQL with its bindings interpolated (e-mails, codes,
 * password hashes), and the driver message may repeat them (e.g. "Duplicate entry 'x'"). This
 * report keeps what is needed to debug — SQL with placeholders, SQLSTATE, driver code, stack trace —
 * and replaces the default report, so neither logs nor traces receive the values.
 */
final class ReportQueryExceptionSafely
{
    public function __invoke(QueryException $e): false
    {
        Log::error('Database query failed.', [
            'exception_class' => $e::class,
            'connection' => $e->getConnectionName(),
            'sql' => $e->getSql(),
            'sqlstate' => $e->errorInfo[0] ?? null,
            'driver_code' => $e->errorInfo[1] ?? null,
            'location' => $e->getFile().':'.$e->getLine(),
            // Production runs with zend.exception_ignore_args=On, so frames carry no argument values.
            'trace' => $e->getTraceAsString(),
        ]);

        // Stops the default report, which would log the message with the values.
        return false;
    }
}
