<?php

declare(strict_types=1);

namespace Tests\Feature\ApiDocs\Fixtures;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\Feature\Problems\Fixtures\SlotTakenException;

/**
 * Endpoints whose failures the API documentation must describe as RFC 9457 problems.
 */
final class DocumentedController
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['name' => ['required', 'string']]);

        return new JsonResponse(['created' => true], 201);
    }

    public function conflict(): JsonResponse
    {
        throw new SlotTakenException;
    }

    public function forbidden(): JsonResponse
    {
        abort(403);
    }

    public function unauthenticated(): JsonResponse
    {
        throw new AuthenticationException;
    }

    public function denied(): JsonResponse
    {
        throw new AuthorizationException;
    }

    public function missing(): JsonResponse
    {
        throw new ModelNotFoundException;
    }
}
