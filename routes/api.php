<?php

use App\Http\Controllers\Api\DtsApiController;
use App\Http\Middleware\VerifyDtsApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTS API v1
|--------------------------------------------------------------------------
|
| Base URL:
| /api/v1/dts
|
| Health endpoint is public.
| All other endpoints require the DTS API token.
|
*/

Route::prefix('v1/dts')->group(function () {
    Route::get('/health', [DtsApiController::class, 'health'])
        ->middleware('throttle:30,1');

    Route::middleware([
        VerifyDtsApiToken::class,
        'throttle:120,1',
    ])->group(function () {
        Route::get('/summary', [DtsApiController::class, 'summary']);

        Route::get('/documents', [DtsApiController::class, 'documents']);

        Route::get('/documents/{document}', [DtsApiController::class, 'show'])
            ->whereNumber('document');

        Route::get('/personnel', [DtsApiController::class, 'personnel']);
    });
});
