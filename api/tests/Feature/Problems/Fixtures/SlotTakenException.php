<?php

declare(strict_types=1);

namespace Tests\Feature\Problems\Fixtures;

use App\Support\Problems\ProblemException;

final class SlotTakenException extends ProblemException
{
    public function status(): int
    {
        return 409;
    }

    public function type(): string
    {
        return 'slot-taken';
    }

    public function title(): string
    {
        return 'Horário indisponível';
    }

    public function detail(): string
    {
        return 'Esse horário acabou de ser reservado.';
    }

    public function extensions(): array
    {
        return ['suggestions' => ['2026-10-05T10:00:00-03:00'], 'title' => 'must not override'];
    }
}
