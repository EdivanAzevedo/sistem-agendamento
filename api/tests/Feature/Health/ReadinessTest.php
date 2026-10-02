<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // The test suite defaults to the sync queue; readiness must probe the real driver.
    config(['queue.default' => 'redis']);
});

it('is ready when database, redis and queue respond', function () {
    $this->getJson('/ready')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'redis' => 'ok', 'queue' => 'ok'],
        ]);
});

it('is unavailable when redis is unreachable', function () {
    config([
        'database.redis.default.host' => '127.0.0.1',
        'database.redis.default.port' => 1,
        'database.redis.default.max_retries' => 0,
    ]);
    Redis::purge();

    $this->getJson('/ready')
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'unavailable')
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonPath('checks.redis', 'failing')
        ->assertJsonPath('checks.queue', 'failing');
});

it('is unavailable when the database is unreachable', function () {
    config(['database.connections.mysql.port' => 1]);
    DB::purge('mysql');

    $this->getJson('/ready')
        ->assertServiceUnavailable()
        ->assertJsonPath('checks.database', 'failing')
        ->assertJsonPath('checks.redis', 'ok');
});

it('does not leak failure details in the response', function () {
    config(['database.connections.mysql.port' => 1]);
    DB::purge('mysql');

    $body = $this->getJson('/ready')->getContent();

    expect($body)->not->toContain('SQLSTATE')->not->toContain('Connection refused');
});
