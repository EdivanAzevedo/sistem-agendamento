<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemMessages;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Validation\ValidationException;

/**
 * Documents validation failures (422) with their field messages.
 */
final class ValidationProblemResponse extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $type->isInstanceOf(ValidationException::class);
    }

    public function toResponse(Type $type): Response
    {
        return ProblemResponses::make($this->components, 422, ProblemMessages::get('problems.types.validation-error.title'), validation: true);
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', 'ValidationError', $this->components);
    }
}
