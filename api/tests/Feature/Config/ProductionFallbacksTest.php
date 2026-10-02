<?php

declare(strict_types=1);

/**
 * Loads a config file as if the given environment variables were not set at all.
 *
 * @param  list<string>  $missing
 * @return array<string, mixed>
 */
function configWithout(string $file, array $missing): array
{
    $saved = [];

    foreach ($missing as $name) {
        $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }

    try {
        return require config_path("{$file}.php");
    } finally {
        foreach ($saved as $name => [$env, $server, $process]) {
            if ($env !== null) {
                $_ENV[$name] = $env;
            }
            if ($server !== null) {
                $_SERVER[$name] = $server;
            }
            if ($process !== false) {
                putenv("{$name}={$process}");
            }
        }
    }
}

it('falls back to the production setup when a variable is missing', function (string $file, string $key, mixed $expected, string $variable) {
    expect(data_get(configWithout($file, [$variable]), $key))->toBe($expected);
})->with([
    'database' => ['database', 'default', 'mysql', 'DB_CONNECTION'],
    'failed jobs store' => ['queue', 'failed.database', 'mysql', 'DB_CONNECTION'],
    'cache' => ['cache', 'default', 'redis', 'CACHE_STORE'],
    'session store' => ['session', 'driver', 'redis', 'SESSION_DRIVER'],
    'HTTPS-only session cookie' => ['session', 'secure', true, 'SESSION_SECURE_COOKIE'],
    'queue' => ['queue', 'default', 'redis', 'QUEUE_CONNECTION'],
    'log channel' => ['logging', 'default', 'stderr', 'LOG_CHANNEL'],
    'log level' => ['logging', 'channels.stderr.level', 'info', 'LOG_LEVEL'],
    'mailer' => ['mail', 'default', 'smtp', 'MAIL_MAILER'],
    'debug mode' => ['app', 'debug', false, 'APP_DEBUG'],
]);

it('restores the environment after loading a config file', function () {
    $before = env('DB_CONNECTION');

    configWithout('database', ['DB_CONNECTION']);

    expect(env('DB_CONNECTION'))->toBe($before);
});
