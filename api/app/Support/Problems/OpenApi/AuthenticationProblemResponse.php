<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemMessages;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Auth\AuthenticationException;

/**
 * Documents missing or expired authentication (401).
 */
final class AuthenticationProblemResponse extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(AuthenticationException::class);
    }

    public function toResponse(Type $type): Response
    {
        return ProblemResponses::make($this->components, 401, ProblemMessages::statusTitle(401));
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', 'Unauthenticated', $this->components);
    }
}
