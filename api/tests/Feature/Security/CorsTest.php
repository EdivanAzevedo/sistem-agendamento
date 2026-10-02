<?php

declare(strict_types=1);

it('never grants cross-origin access to the API', function () {
    $this->getJson('/api/v1/anything', ['Origin' => 'https://evil.example'])
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('does not answer CORS preflight requests', function () {
    $this->call('OPTIONS', '/api/v1/anything', server: [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ])->assertHeaderMissing('Access-Control-Allow-Origin');
});
