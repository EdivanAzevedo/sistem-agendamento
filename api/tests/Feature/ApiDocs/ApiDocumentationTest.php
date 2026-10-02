<?php

declare(strict_types=1);

use App\Support\Problems\ProblemDetails;
use Illuminate\Support\Facades\Route;
use Tests\Feature\ApiDocs\Fixtures\DocumentedController;

beforeEach(function () {
    Route::prefix('api/v1/__docs')->group(function () {
        Route::post('/things', [DocumentedController::class, 'store']);
        Route::get('/conflict', [DocumentedController::class, 'conflict']);
        Route::get('/forbidden', [DocumentedController::class, 'forbidden']);
        Route::get('/unauthenticated', [DocumentedController::class, 'unauthenticated']);
        Route::get('/denied', [DocumentedController::class, 'denied']);
        Route::get('/missing', [DocumentedController::class, 'missing']);
    });
});

/**
 * Returns a documented response, following `$ref`s into `components.responses`.
 *
 * @param  array<string, mixed>  $document
 * @return array<string, mixed>
 */
function documentedResponse(array $document, string $path, string $method, int $status): array
{
    $response = data_get($document, "paths.{$path}.{$method}.responses.{$status}");

    if (is_array($response) && isset($response['$ref']) && is_string($response['$ref'])) {
        $response = data_get($document, 'components.responses.'.basename($response['$ref']));
    }

    expect($response)->toBeArray();

    /** @var array<string, mixed> $response */
    return $response;
}

it('serves the documentation publicly, even in production', function () {
    app()['env'] = 'production';

    $this->get('/docs/api')->assertOk();
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('servers.0.url', '/api/v1');
});

it('loads the documentation UI from a pinned, integrity-checked script without a third-party proxy', function () {
    $this->get('/docs/api')
        ->assertOk()
        ->assertSee('@scalar/api-reference@1.69.0/dist/browser/standalone.js', false)
        ->assertSee('integrity="sha384-', false)
        ->assertSee('crossorigin="anonymous"', false)
        ->assertDontSee('proxy.scalar.com', false);
});

it('throttles the documentation routes', function (string $route) {
    expect(Route::getRoutes()->getByName($route)?->gatherMiddleware())->toContain('throttle:30,1');
})->with(['scramble.docs.ui', 'scramble.docs.document']);

it('always documents the RFC 9457 error schemas', function () {
    $schemas = $this->getJson('/docs/api.json')->json('components.schemas');

    expect($schemas['ProblemDetails']['required'])->toBe(['type', 'title', 'status', 'trace_id'])
        ->and($schemas['ValidationProblemDetails']['required'])->toContain('errors')
        ->and($schemas['ProblemDetails']['properties']['trace_id']['pattern'])->toBe('^[0-9a-f]{32}$');
});

it('documents validation errors as problem+json with field messages', function () {
    $response = documentedResponse($this->getJson('/docs/api.json')->json(), '/__docs/things', 'post', 422);

    expect($response['description'])->toBe('Dados inválidos')
        ->and($response['content'])->toHaveKey(ProblemDetails::CONTENT_TYPE)
        ->and($response['content'])->not->toHaveKey('application/json')
        ->and(data_get($response, 'content.application/problem+json.schema.$ref'))
        ->toBe('#/components/schemas/ValidationProblemDetails');
});

it('documents framework failures with their status as problem+json', function (string $path, int $status, string $title) {
    $response = documentedResponse($this->getJson('/docs/api.json')->json(), $path, 'get', $status);

    expect($response['description'])->toBe($title)
        ->and($response['content'])->not->toHaveKey('application/json')
        ->and(data_get($response, 'content.application/problem+json.schema.$ref'))
        ->toBe('#/components/schemas/ProblemDetails');
})->with([
    'abort(403)' => ['/__docs/forbidden', 403, 'Acesso negado'],
    'authentication' => ['/__docs/unauthenticated', 401, 'Não autenticado'],
    'authorization' => ['/__docs/denied', 403, 'Acesso negado'],
    'model not found' => ['/__docs/missing', 404, 'Não encontrado'],
]);

it('documents business-rule failures with their own status, title and slug', function () {
    $response = documentedResponse($this->getJson('/docs/api.json')->json(), '/__docs/conflict', 'get', 409);

    expect($response['description'])->toContain('Horário indisponível')
        ->and($response['description'])->toContain('/problems/slot-taken')
        ->and(data_get($response, 'content.application/problem+json.schema.$ref'))
        ->toBe('#/components/schemas/ProblemDetails');
});
