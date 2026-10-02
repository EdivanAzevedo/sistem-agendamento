<?php

declare(strict_types=1);

namespace App\Support\Problems;

use App\Support\Tracing\TraceContext;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Converts any exception raised while serving the API into an RFC 9457 problem response.
 *
 * Generic HTTP failures use `about:blank` (the title is the status phrase); application-specific
 * failures get their own `type` URI. Internal details are never exposed unless debug mode is on.
 *
 * Runs after Laravel's own exception preparation, so authorization, model-not-found and CSRF
 * failures arrive here already converted into HTTP exceptions.
 */
final readonly class ProblemRenderer
{
    public function __construct(
        private TraceContext $trace,
        private Config $config,
        private Translator $translator,
    ) {}

    public function render(Throwable $e, Request $request): ?Response
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        // An explicit response (e.g. thrown by a middleware) is the intended outcome, not a failure.
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        $response = $this->toProblem($e)
            ->withExtensions(['trace_id' => $this->trace->traceId()])
            ->toResponse();
        $response->headers->set('Content-Language', str_replace('_', '-', $this->translator->getLocale()));

        return $response;
    }

    private function toProblem(Throwable $e): ProblemDetails
    {
        return match (true) {
            $e instanceof ProblemException => new ProblemDetails(
                status: $e->status(),
                type: $this->typeUri($e->type()),
                title: $e->title(),
                detail: $e->detail(),
                extensions: $e->extensions(),
                headers: $e->headers(),
            ),
            $e instanceof ValidationException => new ProblemDetails(
                status: 422,
                type: $this->typeUri('validation-error'),
                title: ProblemMessages::get('problems.types.validation-error.title'),
                detail: ProblemMessages::get('problems.types.validation-error.detail'),
                extensions: ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => $this->generic(401),
            $e instanceof HttpExceptionInterface => $this->generic(
                $e->getStatusCode(),
                $this->stringHeaders($e->getHeaders()),
            ),
            default => $this->unexpected($e),
        };
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function generic(int $status, array $headers = []): ProblemDetails
    {
        return new ProblemDetails(
            status: $status,
            type: ProblemDetails::BLANK_TYPE,
            title: ProblemMessages::statusTitle($status),
            detail: ProblemMessages::statusDetail($status),
            headers: $headers,
        );
    }

    private function unexpected(Throwable $e): ProblemDetails
    {
        $problem = $this->generic(500);

        if ($this->config->get('app.debug') !== true) {
            return $problem;
        }

        return $problem->withExtensions(['debug' => [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]]);
    }

    private function typeUri(string $slug): string
    {
        $baseUrl = $this->config->get('app.url');

        return rtrim(is_string($baseUrl) ? $baseUrl : '', '/').'/problems/'.$slug;
    }

    /**
     * @param  array<mixed>  $headers
     * @return array<string, string>
     */
    private function stringHeaders(array $headers): array
    {
        $result = [];

        foreach ($headers as $name => $value) {
            if (is_string($name) && (is_string($value) || is_int($value))) {
                $result[$name] = (string) $value;
            }
        }

        return $result;
    }
}
