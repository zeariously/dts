<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DtsController;
use App\Http\Controllers\Admin\AdminUserManagementController;
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
| NOTE:
| This group already has prefix('dts'), so do NOT add /dts again
| inside the routes. Example:
|
| Correct:   Route::post('/{id}/attachments', ...)
| Wrong:     Route::post('/dts/{id}/attachments', ...)
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('dts')
    ->name('dts.')
    ->group(function () {
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
        | Final route name:
        | dts.monitoring-dashboard
        |
        | IMPORTANT:
        | This must be placed BEFORE Route::get('/{id}', ...)
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

        Route::post('/library/personnel/store', [DtsController::class, 'storePersonnel'])
            ->name('library.personnel.store');

        Route::post('/library/personnel/delete', [DtsController::class, 'deletePersonnel'])
            ->name('library.personnel.delete');

        Route::post('/library/personnel/{id}/update', [DtsController::class, 'updatePersonnel'])
            ->name('library.personnel.update');

        Route::post('/library/office/store', [DtsController::class, 'storeOffice'])
            ->name('library.office.store');

        Route::post('/library/office/delete', [DtsController::class, 'deleteOffice'])
            ->name('library.office.delete');

        Route::post('/library/office/{id}/update', [DtsController::class, 'updateOffice'])
            ->name('library.office.update');

        Route::post('/library/doctype/store', [DtsController::class, 'storeDocType'])
            ->name('library.doctype.store');

        Route::post('/library/doctype/delete', [DtsController::class, 'deleteDocType'])
            ->name('library.doctype.delete');

        Route::post('/library/doctype/{id}/update', [DtsController::class, 'updateDocType'])
            ->name('library.doctype.update');

        Route::post('/library/attachment/store', [DtsController::class, 'storeLibraryAttachment'])
            ->name('library.attachment.store');

        Route::post('/library/attachment/delete', [DtsController::class, 'deleteLibraryAttachment'])
            ->name('library.attachment.delete');

        Route::post('/library/attachment/{id}/update', [DtsController::class, 'updateLibraryAttachment'])
            ->name('library.attachment.update');

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
        */

        Route::post('/{id}/receive', [DtsController::class, 'receive'])
            ->name('receive');

        Route::post('/{id}/forward', [DtsController::class, 'forward'])
            ->name('forward');

        Route::post('/{id}/return', [DtsController::class, 'returnDocument'])
            ->name('return');

        Route::post('/{id}/remarks', [DtsController::class, 'storeRemark'])
            ->name('remarks.store');

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
        Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('dts')->name('dts.')->group(function () {
        Route::get('/', [DtsController::class, 'index'])->name('index');

        Route::get('/monitoring-dashboard', [DtsController::class, 'monitoringDashboard'])
            ->name('monitoring-dashboard');

        // IMPORTANT: this must stay below monitoring-dashboard
        Route::get('/{id}', [DtsController::class, 'show'])->name('show');
    });
});

});

require __DIR__ . '/auth.php';