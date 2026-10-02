<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('does not serve or accept files from the app container', function () {
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();
});
