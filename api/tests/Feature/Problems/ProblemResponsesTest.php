<?php

declare(strict_types=1);

use App\Http\Middleware\AssignTraceId;
use App\Support\Problems\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\Feature\Problems\Fixtures\RespondsEarly;
use Tests\Feature\Problems\Fixtures\SlotTakenException;

beforeEach(function () {
    config(['app.url' => 'https://agendamento.test', 'app.debug' => false]);

    Route::prefix('api/v1/__problems')->group(function () {
        Route::post('/validation', fn (Request $request) => $request->validate([
            'email' => ['required', 'email'],
        ]));
        Route::get('/domain', fn () => throw new SlotTakenException);
        Route::get('/unauthenticated', fn () => throw new AuthenticationException);
        Route::get('/forbidden', fn () => throw new AuthorizationException('Internal policy reason'));
        Route::get('/csrf', fn () => throw new TokenMismatchException);
        Route::get('/throttled', fn () => throw new TooManyRequestsHttpException(30));
        Route::get('/crash', fn () => throw new RuntimeException('SQLSTATE secret connection string'));
        Route::get('/responds-early', fn () => 'unreachable')->middleware(RespondsEarly::class);
    });
});

it('renders a missing route as an about:blank problem', function () {
    $response = $this->getJson('/api/v1/does-not-exist');

    $response->assertNotFound()
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertJson([
            'type' => 'about:blank',
            'title' => 'Não encontrado',
            'status' => 404,
        ]);

    expect($response->json('trace_id'))
        ->toMatch('/^[0-9a-f]{32}$/')
        ->toBe($response->headers->get(AssignTraceId::HEADER));
});

it('renders validation errors with field messages in Portuguese', function () {
    $this->postJson('/api/v1/__problems/validation', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE)
        ->assertJsonPath('type', 'https://agendamento.test/problems/validation-error')
        ->assertJsonPath('title', 'Dados inválidos')
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['errors' => ['email'], 'trace_id'])
        ->assertJsonPath('errors.email.0', 'O campo e-mail não contém um endereço de e-mail válido.');
});

it('renders domain exceptions with their own type and extensions', function () {
    $this->getJson('/api/v1/__problems/domain')
        ->assertConflict()
        ->assertJsonPath('type', 'https://agendamento.test/problems/slot-taken')
        ->assertJsonPath('title', 'Horário indisponível')
        ->assertJsonPath('detail', 'Esse horário acabou de ser reservado.')
        ->assertJsonPath('suggestions', ['2026-10-05T10:00:00-03:00']);
});

it('does not report domain exceptions as application errors', function () {
    Exceptions::fake();

    $this->getJson('/api/v1/__problems/domain');

    Exceptions::assertNotReported(SlotTakenException::class);
});

it('reports unexpected errors so they reach the logs with their stack trace', function () {
    Exceptions::fake();

    $this->getJson('/api/v1/__problems/crash');

    Exceptions::assertReported(RuntimeException::class);
});

it('keeps explicit responses thrown by middleware', function () {
    $this->getJson('/api/v1/__problems/responds-early')
        ->assertStatus(202)
        ->assertExactJson(['accepted' => true]);
});

it('declares the language of the problem document', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertHeader('Content-Language', 'pt-BR');
});

it('maps framework exceptions to generic problems', function (string $uri, int $status, string $title) {
    $this->getJson("/api/v1/__problems/{$uri}")
        ->assertStatus($status)
        ->assertJsonPath('type', 'about:blank')
        ->assertJsonPath('status', $status)
        ->assertJsonPath('title', $title);
})->with([
    'unauthenticated' => ['unauthenticated', 401, 'Não autenticado'],
    'forbidden' => ['forbidden', 403, 'Acesso negado'],
    'csrf token mismatch' => ['csrf', 419, 'Sessão expirada'],
    'rate limited' => ['throttled', 429, 'Muitas tentativas'],
]);

it('never exposes the internal reason of a denied authorization', function () {
    expect($this->getJson('/api/v1/__problems/forbidden')->getContent())
        ->not->toContain('Internal policy reason');
});

it('keeps the Retry-After header on rate limited responses', function () {
    $this->getJson('/api/v1/__problems/throttled')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '30');
});

it('keeps the Allow header on method not allowed responses', function () {
    $this->getJson('/api/v1/__problems/validation')
        ->assertMethodNotAllowed()
        ->assertJsonPath('title', 'Método não permitido')
        ->assertHeader('Allow');
});

it('hides internal details of unexpected errors', function () {
    $response = $this->getJson('/api/v1/__problems/crash');

    $response->assertInternalServerError()
        ->assertJsonPath('type', 'about:blank')
        ->assertJsonPath('title', 'Erro interno')
        ->assertJsonMissingPath('debug');

    expect($response->getContent())->not->toContain('SQLSTATE');
});

it('includes debug details for unexpected errors only in debug mode', function () {
    config(['app.debug' => true]);

    $this->getJson('/api/v1/__problems/crash')
        ->assertInternalServerError()
        ->assertJsonPath('debug.exception', RuntimeException::class)
        ->assertJsonPath('debug.message', 'SQLSTATE secret connection string');
});

it('does not render problems for non-JSON requests outside the API', function () {
    $response = $this->get('/does-not-exist', ['Accept' => 'text/html']);

    $response->assertNotFound();
    expect($response->headers->get('Content-Type'))->not->toBe(ProblemDetails::CONTENT_TYPE);
});
