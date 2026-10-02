<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemMessages;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Documents denied authorization (403).
 */
final class AuthorizationProblemResponse extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(AuthorizationException::class);
    }

    public function toResponse(Type $type): Response
    {
        return ProblemResponses::make($this->components, 403, ProblemMessages::statusTitle(403));
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', 'Forbidden', $this->components);
    }
}
