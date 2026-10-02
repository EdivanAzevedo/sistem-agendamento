<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemMessages;
use Dedoc\Scramble\Support\ExceptionToResponseExtensions\HttpExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\Generic;
use Dedoc\Scramble\Support\Type\Type;

/**
 * Documents HTTP exceptions (e.g. `abort(403)`) as RFC 9457 problems.
 *
 * Reuses Scramble's status code inference and only replaces the response body.
 */
final class HttpProblemResponse extends HttpExceptionToResponseExtension
{
    public function toResponse(Type $type): ?Response
    {
        $templates = $type instanceof Generic ? $type->templateTypes : [];

        // Index 7 is the inferred `TCode` template of HttpException; index 0 is used when other
        // extensions build the type manually (same convention as the parent class).
        $codeType = count($templates) > 3 ? ($templates[7] ?? null) : ($templates[0] ?? null);

        $status = $this->getResponseCode($codeType, $type);

        return $status === null
            ? null
            : ProblemResponses::make($this->components, $status, ProblemMessages::statusTitle($status));
    }
}
