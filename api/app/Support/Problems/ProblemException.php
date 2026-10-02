<?php

declare(strict_types=1);

namespace App\Support\Problems;

use RuntimeException;

/**
 * Base class for expected, client-facing failures (business rule violations).
 *
 * Each subclass declares its HTTP status and a stable `type` slug; title and detail come from
 * `lang/{locale}/problems.php` under `types.{slug}`. These are not reported as application errors.
 *
 * `status()`, `type()` and `title()` must not depend on constructor arguments: the API
 * documentation reads them from an instance created without calling the constructor.
 */
abstract class ProblemException extends RuntimeException
{
    abstract public function status(): int;

    /**
     * Stable identifier published as `{APP_URL}/problems/{slug}`. Changing it breaks clients.
     */
    abstract public function type(): string;

    public function title(): string
    {
        return ProblemMessages::get("problems.types.{$this->type()}.title");
    }

    public function detail(): string
    {
        return ProblemMessages::get("problems.types.{$this->type()}.detail", $this->detailParameters());
    }

    /**
     * Extension members added to the response body (e.g. a list of conflicts).
     *
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }

    /**
     * @return array<string, string|int>
     */
    protected function detailParameters(): array
    {
        return [];
    }
}
