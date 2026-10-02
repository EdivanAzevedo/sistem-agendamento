<?php

declare(strict_types=1);

namespace App\Support\Problems\OpenApi;

use App\Support\Problems\ProblemMessages;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Illuminate\Database\RecordsNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Documents missing resources (404), including models resolved by route binding.
 */
final class NotFoundProblemResponse extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && ($type->isInstanceOf(RecordsNotFoundException::class) || $type->isInstanceOf(NotFoundHttpException::class));
    }

    public function toResponse(Type $type): Response
    {
        return ProblemResponses::make($this->components, 404, ProblemMessages::statusTitle(404));
    }

    public function reference(ObjectType $type): Reference
    {
        return new Reference('responses', 'NotFound', $this->components);
    }
}
