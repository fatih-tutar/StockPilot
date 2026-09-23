<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name', 'StockPilot'),
        'phase' => 'Phase 0 · Inertia + Vue',
    ]);
});
