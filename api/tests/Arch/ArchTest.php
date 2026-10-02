<?php

declare(strict_types=1);

arch('no debugging helpers are left behind')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch()->preset()->security();

arch('raw query builder is reserved for analytical queries and infrastructure checks')
    ->expect('Illuminate\Support\Facades\DB')
    ->toOnlyBeUsedIn([
        'App\Modules\Reporting',
        'App\Http\Controllers\Health',
    ]);
