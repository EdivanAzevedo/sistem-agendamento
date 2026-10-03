<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

it('writes one JSON object per line, with correlation ids and exception stack traces', function () {
    $stream = fopen('php://memory', 'w+');
    config(['logging.channels.stderr.handler_with' => ['stream' => $stream]]);

    Log::channel('stderr')->error('Something failed', ['exception' => new RuntimeException('Boom')]);

    rewind($stream);
    $line = json_decode((string) fgets($stream), true, flags: JSON_THROW_ON_ERROR);

    expect($line['message'])->toBe('Something failed')
        ->and($line['level_name'])->toBe('ERROR')
        ->and($line['extra'])->toHaveKeys(['trace_id', 'request_id'])
        ->and($line['context']['exception']['class'])->toBe(RuntimeException::class)
        ->and($line['context']['exception']['trace'])->not->toBeEmpty();
});
