<?php

declare(strict_types=1);

use App\Http\Controllers\Health\ReadinessController;
use Illuminate\Support\Facades\Route;

Route::get('/ready', ReadinessController::class)->name('health.ready');
