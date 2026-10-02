<?php

declare(strict_types=1);

namespace App\Support\Problems;

use Illuminate\Http\JsonResponse;

/**
 * An RFC 9457 "Problem Details for HTTP APIs" document.
 */
final readonly class ProblemDetails
{
    public const CONTENT_TYPE = 'application/problem+json';

    public const BLANK_TYPE = 'about:blank';

    /**
     * @param  array<string, mixed>  $extensions  Extra members (e.g. `errors`, `trace_id`); never override the core members.
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public int $status,
        public string $type,
        public string $title,
        public ?string $detail = null,
        public array $extensions = [],
        public array $headers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $extensions
     */
    public function withExtensions(array $extensions): self
    {
        return new self(
            $this->status,
            $this->type,
            $this->title,
            $this->detail,
            [...$this->extensions, ...$extensions],
            $this->headers,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $core = array_filter([
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ], fn (mixed $value): bool => $value !== null);

        return [...$core, ...array_diff_key($this->extensions, $core)];
    }

    public function toResponse(): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(),
            $this->status,
            [...$this->headers, 'Content-Type' => self::CONTENT_TYPE],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
