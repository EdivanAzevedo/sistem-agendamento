<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemException;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use ReflectionClass;

/**
 * Documents business-rule failures (subclasses of ProblemException) with their own status,
 * title and `type` slug, read from the exception class itself.
 */
final class DomainProblemResponse extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType && $this->problem($type) !== null;
    }

    public function toResponse(Type $type): ?Response
    {
        $problem = $type instanceof ObjectType ? $this->problem($type) : null;

        if ($problem === null) {
            return null;
        }

        $description = sprintf('%s (`type` termina em `/problems/%s`)', $problem->title(), $problem->type());

        return ProblemResponses::make($this->components, $problem->status(), $description);
    }

    public function reference(ObjectType $type): ?Reference
    {
        $problem = $this->problem($type);

        // The slug is unique by contract, so it also names the reusable response component.
        return $problem === null ? null : new Reference('responses', $problem->type(), $this->components);
    }

    private function problem(ObjectType $type): ?ProblemException
    {
        if (! is_a($type->name, ProblemException::class, true)) {
            return null;
        }

        $class = new ReflectionClass($type->name);

        return $class->isAbstract() ? null : $class->newInstanceWithoutConstructor();
    }
}
