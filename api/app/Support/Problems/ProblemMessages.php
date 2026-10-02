<?php

declare(strict_types=1);

namespace App\Support\Problems;

use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

/**
 * Localized titles and details for problem responses (`lang/{locale}/problems.php`).
 */
final class ProblemMessages
{
    /**
     * @param  array<string, string|int>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        $message = Lang::get($key, $replace);

        return is_string($message) ? $message : $key;
    }

    public static function statusTitle(int $status): string
    {
        return match (true) {
            Lang::has("problems.status.{$status}.title") => self::get("problems.status.{$status}.title"),
            Lang::has("http-statuses.{$status}") => self::get("http-statuses.{$status}"),
            default => Response::$statusTexts[$status] ?? 'Error',
        };
    }

    public static function statusDetail(int $status): ?string
    {
        return Lang::has("problems.status.{$status}.detail")
            ? self::get("problems.status.{$status}.detail")
            : null;
    }
}
