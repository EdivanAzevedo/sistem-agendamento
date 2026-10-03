<?php

declare(strict_types=1);

it('protects the docs page in production with a nonce-based policy', function () {
    app()['env'] = 'production';

    $response = $this->get('/docs/api')->assertOk();

    $policy = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $policy, $matches);
    $nonce = $matches[1] ?? null;

    expect($nonce)->not->toBeNull()
        ->and($policy)->toContain("default-src 'none'")
        ->and($policy)->toContain((string) config('scramble.renderers.scalar.cdn'))
        ->and($policy)->toContain("frame-ancestors 'none'")
        ->and($policy)->not->toMatch("/script-src[^;]*'unsafe-inline'/")
        ->and(substr_count((string) $response->getContent(), "nonce=\"{$nonce}\""))->toBe(2);
});

it('uses a new nonce for every request', function () {
    app()['env'] = 'production';

    $first = $this->get('/docs/api')->headers->get('Content-Security-Policy');
    $this->refreshApplication();
    app()['env'] = 'production';
    $second = $this->get('/docs/api')->headers->get('Content-Security-Policy');

    expect($first)->not->toBe($second);
});

it('leaves the policy to the edge outside production', function () {
    $response = $this->get('/docs/api')->assertOk();

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and((string) $response->getContent())->not->toContain('nonce=');
});

it('disables Scalar fonts from third-party hosts', function () {
    expect($this->get('/docs/api')->getContent())->toContain('"withDefaultFonts":false');
});
