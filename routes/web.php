<?php

use App\Http\Controllers\Admin\AdminUserManagementController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\DtsController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Public / Welcome
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dts.index');
    }

    return redirect()->route('login');
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
|--------------------------------------------------------------------------`
*/

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/users', [AdminUserManagementController::class, 'index'])
            ->name('users.index');

        Route::post('/users', [AdminUserManagementController::class, 'store'])
            ->name('users.store');

        Route::patch('/users/{user}/role', [AdminUserManagementController::class, 'updateRole'])
            ->name('users.role.update');

        Route::post('/announcements', [AdminUserManagementController::class, 'storeAnnouncement'])
            ->name('announcements.store');

        Route::delete('/announcements/{announcement}', [AdminUserManagementController::class, 'destroyAnnouncement'])
            ->name('announcements.destroy');
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
        | Active Announcements for DTS Users
        |--------------------------------------------------------------------------
        */

        Route::get('/announcements/active', [AnnouncementController::class, 'active'])
            ->name('announcements.active');

        /*
        |--------------------------------------------------------------------------
        | Library Routes
        |--------------------------------------------------------------------------
        */

        Route::get('/library', [DtsController::class, 'library'])
            ->name('library');

        /*
        |--------------------------------------------------------------------------
        | Inventory Routes
        |--------------------------------------------------------------------------
        | GET    /dts/inventory
        | POST   /dts/inventory
        | PUT    /dts/inventory/{inventoryItem}
        | DELETE /dts/inventory/{inventoryItem}
        |--------------------------------------------------------------------------
        */

        Route::get('/inventory', [InventoryController::class, 'index'])
         ->name('inventory');

        Route::post('/inventory', [InventoryController::class, 'store'])
            ->name('inventory.store');

        Route::get(
            '/inventory/{inventoryItem}/history',
            [InventoryController::class, 'history']
        )->name('inventory.history');

        Route::put('/inventory/{inventoryItem}', [InventoryController::class, 'update'])
            ->name('inventory.update');

        Route::delete('/inventory/{inventoryItem}', [InventoryController::class, 'destroy'])
         ->name('inventory.destroy');

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

        Route::post('/library/action-types', [DtsController::class, 'storeActionType'])
            ->name('library.action-types.store');

        Route::patch('/library/action-types/{id}', [DtsController::class, 'updateActionType'])
            ->name('library.action-types.update');

        Route::delete('/library/action-types', [DtsController::class, 'deleteActionType'])
            ->name('library.action-types.delete');

        Route::get('/files/{file}/view', [DtsController::class, 'viewFile'])
            ->name('files.view');

        Route::post('/{id}/attachments', [DtsController::class, 'storeAttachment'])
            ->whereNumber('id')
            ->name('attachments.store');

        Route::delete('/{id}/attachments/{file}', [DtsController::class, 'destroyAttachment'])
            ->whereNumber('id')
            ->whereNumber('file')
            ->name('attachments.destroy');

        

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


        Route::get('/{id}', [DtsController::class, 'show'])
            ->name('show');
    });



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');
});

require __DIR__ . '/auth.php';