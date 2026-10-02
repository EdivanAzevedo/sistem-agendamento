<?php

declare(strict_types=1);

namespace Tests\Feature\Problems\Fixtures;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that short-circuits the request with an explicit response.
 */
final class RespondsEarly
{
    public function handle(Request $request, Closure $next): Response
    {
        throw new HttpResponseException(new JsonResponse(['accepted' => true], 202));
    }
}
