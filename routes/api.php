<?php

use App\Http\Controllers\Api\Dts\AdminController;
use App\Http\Controllers\Api\Dts\AttachmentController;
use App\Http\Controllers\Api\Dts\DocumentController;
use App\Http\Controllers\Api\Dts\InventoryController;
use App\Http\Controllers\Api\Dts\LibraryController;
use App\Http\Controllers\Api\Dts\ReportController;
use App\Http\Controllers\Api\Dts\SystemController;
use App\Http\Controllers\Api\Dts\WorkflowController;
use App\Http\Middleware\VerifyDtsApiToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DTS API v1
|--------------------------------------------------------------------------
| Base URL: /api/v1/dts
*/

Route::prefix('v1/dts')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */
    Route::get('/health', [SystemController::class, 'health'])
        ->middleware('throttle:30,1');

    /*
    |--------------------------------------------------------------------------
    | Protected API
    |--------------------------------------------------------------------------
    */
    Route::middleware([
        VerifyDtsApiToken::class,
        'throttle:120,1',
    ])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | System
        |--------------------------------------------------------------------------
        */
        Route::get('/summary', [SystemController::class, 'summary']);

        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */
        Route::get('/documents', [DocumentController::class, 'index']);

        Route::get('/documents/{document}/history', [DocumentController::class, 'history'])
            ->whereNumber('document');

        Route::get('/documents/{document}/remarks', [DocumentController::class, 'remarks'])
            ->whereNumber('document');

        Route::get('/documents/{document}/attachments', [DocumentController::class, 'attachments'])
            ->whereNumber('document');

        Route::get('/documents/{document}', [DocumentController::class, 'show'])
            ->whereNumber('document');

        /*
        |--------------------------------------------------------------------------
        | Attachment File Access
        |--------------------------------------------------------------------------
        */
        Route::get('/attachments/{file}', [AttachmentController::class, 'show'])
            ->whereNumber('file');

        /*
        |--------------------------------------------------------------------------
        | Workflow
        |--------------------------------------------------------------------------
        */
        Route::get('/incoming', [WorkflowController::class, 'incoming']);
        Route::get('/returned', [WorkflowController::class, 'returned']);

        /*
        |--------------------------------------------------------------------------
        | Library
        |--------------------------------------------------------------------------
        */
        Route::prefix('library')->group(function () {
            Route::get('/personnel', [LibraryController::class, 'personnel']);
            Route::get('/offices', [LibraryController::class, 'offices']);
            Route::get('/document-types', [LibraryController::class, 'documentTypes']);
            Route::get('/classifications', [LibraryController::class, 'classifications']);
            Route::get('/action-types', [LibraryController::class, 'actionTypes']);
        });

        /*
        |--------------------------------------------------------------------------
        | Backward-Compatible Personnel Endpoint
        |--------------------------------------------------------------------------
        */
        Route::get('/personnel', [LibraryController::class, 'personnel']);

        /*
        |--------------------------------------------------------------------------
        | Inventory
        |--------------------------------------------------------------------------
        */
        Route::prefix('inventory')->group(function () {
            Route::get('/', [InventoryController::class, 'index']);
            Route::get('/summary', [InventoryController::class, 'summary']);
            Route::get('/returned', [InventoryController::class, 'returned']);

            Route::get('/{inventoryItem}', [InventoryController::class, 'show'])
                ->whereNumber('inventoryItem');

            Route::get('/{inventoryItem}/history', [InventoryController::class, 'history'])
                ->whereNumber('inventoryItem');
        });

        /*
        |--------------------------------------------------------------------------
        | Reports
        |--------------------------------------------------------------------------
        */
        Route::prefix('reports')->group(function () {
            Route::get('/documents', [ReportController::class, 'documents']);
            Route::get('/inventory', [ReportController::class, 'inventory']);
            Route::get('/status-summary', [ReportController::class, 'statusSummary']);
        });

        /*
        |--------------------------------------------------------------------------
        | Admin / System Information
        |--------------------------------------------------------------------------
        */
        Route::get('/activity-logs', [AdminController::class, 'activityLogs']);
        Route::get('/announcements', [AdminController::class, 'announcements']);
    });
});