<?php

use App\Http\Controllers\Admin\AdminUserManagementController;
use App\Http\Controllers\DtsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public / Welcome
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/users', [AdminUserManagementController::class, 'index'])
            ->name('users.index');

        Route::patch('/users/{user}/role', [AdminUserManagementController::class, 'updateRole'])
            ->name('users.role.update');
    });

/*
|--------------------------------------------------------------------------
| DTS Routes
|--------------------------------------------------------------------------
|
| This group already has prefix('dts'), so do NOT add /dts again
| inside the routes.
|
| Correct:
| Route::post('/{id}/action-taken', ...)
|
| Wrong:
| Route::post('/dts/{id}/action-taken', ...)
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('dts')
    ->name('dts.')
    ->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Main DTS Pages
        |--------------------------------------------------------------------------
        */

        Route::get('/', [DtsController::class, 'index'])
            ->name('index');

        Route::get('/create', [DtsController::class, 'create'])
            ->name('create');

        Route::post('/', [DtsController::class, 'store'])
            ->name('store');

        Route::post('/documents/store', [DtsController::class, 'store'])
            ->name('documents.store');

        /*
        |--------------------------------------------------------------------------
        | Monitoring Dashboard
        |--------------------------------------------------------------------------
        | Final URL:
        | /dts/monitoring-dashboard
        |
        | Must be before Route::get('/{id}', ...)
        |--------------------------------------------------------------------------
        */

        Route::get('/monitoring-dashboard', [DtsController::class, 'monitoringDashboard'])
            ->name('monitoring-dashboard');

        /*
        |--------------------------------------------------------------------------
        | Library Routes
        |--------------------------------------------------------------------------
        */

        Route::get('/library', [DtsController::class, 'library'])
            ->name('library');

        /*
        |--------------------------------------------------------------------------
        | Library - Personnel
        |--------------------------------------------------------------------------
        */

        Route::post('/library/personnel/store', [DtsController::class, 'storePersonnel'])
            ->name('library.personnel.store');

        Route::post('/library/personnel/delete', [DtsController::class, 'deletePersonnel'])
            ->name('library.personnel.delete');

        Route::post('/library/personnel/{id}/update', [DtsController::class, 'updatePersonnel'])
            ->name('library.personnel.update');

        /*
        |--------------------------------------------------------------------------
        | Library - Office
        |--------------------------------------------------------------------------
        */

        Route::post('/library/office/store', [DtsController::class, 'storeOffice'])
            ->name('library.office.store');

        Route::post('/library/office/delete', [DtsController::class, 'deleteOffice'])
            ->name('library.office.delete');

        Route::post('/library/office/{id}/update', [DtsController::class, 'updateOffice'])
            ->name('library.office.update');

        /*
        |--------------------------------------------------------------------------
        | Library - Document Type
        |--------------------------------------------------------------------------
        */

        Route::post('/library/doctype/store', [DtsController::class, 'storeDocType'])
            ->name('library.doctype.store');

        Route::post('/library/doctype/delete', [DtsController::class, 'deleteDocType'])
            ->name('library.doctype.delete');

        Route::post('/library/doctype/{id}/update', [DtsController::class, 'updateDocType'])
            ->name('library.doctype.update');

        /*
        |--------------------------------------------------------------------------
        | Library - Attachment Type
        |--------------------------------------------------------------------------
        */

        Route::post('/library/attachment/store', [DtsController::class, 'storeLibraryAttachment'])
            ->name('library.attachment.store');

        Route::post('/library/attachment/delete', [DtsController::class, 'deleteLibraryAttachment'])
            ->name('library.attachment.delete');

        Route::post('/library/attachment/{id}/update', [DtsController::class, 'updateLibraryAttachment'])
            ->name('library.attachment.update');

        /*
        |--------------------------------------------------------------------------
        | Library - Action Taken Types
        |--------------------------------------------------------------------------
        | Final URLs:
        | POST   /dts/library/action-types
        | PATCH  /dts/library/action-types/{id}
        | DELETE /dts/library/action-types
        |--------------------------------------------------------------------------
        */

        Route::post('/library/action-types', [DtsController::class, 'storeActionType'])
            ->name('library.action-types.store');

        Route::patch('/library/action-types/{id}', [DtsController::class, 'updateActionType'])
            ->name('library.action-types.update');

        Route::delete('/library/action-types', [DtsController::class, 'deleteActionType'])
            ->name('library.action-types.delete');

        /*
        |--------------------------------------------------------------------------
        | File / Attachment Routes
        |--------------------------------------------------------------------------
        */

        Route::get('/files/{file}/view', [DtsController::class, 'viewFile'])
            ->name('files.view');

        Route::post('/{id}/attachments', [DtsController::class, 'storeAttachment'])
            ->name('attachments.store');

        /*
        |--------------------------------------------------------------------------
        | Document Action Routes
        |--------------------------------------------------------------------------
        | Must be before Route::get('/{id}', ...)
        |--------------------------------------------------------------------------
        */

        Route::post('/{id}/receive', [DtsController::class, 'receive'])
            ->name('receive');

        Route::post('/{id}/forward', [DtsController::class, 'forward'])
            ->name('forward');

        Route::post('/{id}/return', [DtsController::class, 'returnDocument'])
            ->name('return');

        Route::post('/{id}/remarks', [DtsController::class, 'storeRemark'])
            ->name('remarks.store');

        Route::post('/{id}/action-taken', [DtsController::class, 'actionTakenDocument'])
            ->name('action-taken');

        Route::post('/{id}/action-taken/{remarkId}/close', [DtsController::class, 'closeActionTaken'])
            ->name('action-taken.close');

        Route::post('/{id}/pullout', [DtsController::class, 'pullout'])
            ->name('pullout');

        Route::patch('/{id}/entry-date', [DtsController::class, 'updateEntryDate'])
            ->name('entry-date.update');

        /*
        |--------------------------------------------------------------------------
        | Show Document
        |--------------------------------------------------------------------------
        | This must always be LAST among /dts routes because it catches /{id}.
        |--------------------------------------------------------------------------
        */

        Route::get('/{id}', [DtsController::class, 'show'])
            ->name('show');
    });

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__ . '/auth.php';