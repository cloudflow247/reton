<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are versioned. The active version lives in routes/api/v1.php
| and is mounted under the /api/v1 prefix with the `api.v1.` route-name space.
|
*/

Route::prefix('v1')
    ->name('v1.')
    ->group(base_path('routes/api/v1.php'));
