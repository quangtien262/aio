<?php

use Illuminate\Support\Facades\Route;
use Modules\Customers\Http\CustomerController;

Route::prefix('customers')->middleware(['module.enabled:customers', 'throttle:120,1'])->group(function (): void {
    Route::get('books', [CustomerController::class, 'books'])->middleware('admin.permission:customers.view');
    Route::post('books', [CustomerController::class, 'createBook'])->middleware('admin.permission:customers.settings');
    Route::get('commands/{key}', [CustomerController::class, 'command'])->middleware('admin.permission:customers.view');
    Route::post('bindings/{binding}/migration', [CustomerController::class, 'migrate'])->middleware('admin.permission:customers.settings');
    Route::get('exports/{export}', [CustomerController::class, 'exportStatus'])->middleware('admin.permission:customers.export');
    Route::get('exports/{export}/download', [CustomerController::class, 'download'])->middleware('admin.permission:customers.export');
    Route::prefix('books/{book}')->group(function (): void {
        Route::get('settings-options', [CustomerController::class, 'options'])->middleware('admin.permission:customers.settings');
        Route::get('saved-filters', [CustomerController::class, 'savedFilters'])->middleware('admin.permission:customers.view');
        Route::post('saved-filters', [CustomerController::class, 'saveFilter'])->middleware('admin.permission:customers.view');
        Route::delete('saved-filters/{filter}', [CustomerController::class, 'deleteFilter'])->middleware('admin.permission:customers.view');
        foreach (['profiles' => 'listing', 'duplicates' => 'duplicates', 'fields' => 'fields', 'grants' => 'grants', 'bindings' => 'bindings'] as $path => $action) {
            Route::get($path, [CustomerController::class, $action])->middleware('admin.permission:'.(in_array($path, ['grants', 'bindings']) ? 'customers.settings' : ($path === 'duplicates' ? 'customers.merge' : 'customers.view')));
        }
        foreach (['profiles' => ['save', 'create'], 'fields' => ['saveField', 'settings'], 'grants' => ['saveGrant', 'settings'], 'bindings' => ['bind', 'settings'], 'imports' => ['import', 'import'], 'exports' => ['export', 'export']] as $path => [$action,$permission]) {
            Route::post($path, [CustomerController::class, $action])->middleware('admin.permission:customers.'.$permission);
        }
        Route::get('profiles/{profile}', [CustomerController::class, 'show'])->middleware('admin.permission:customers.view');
        Route::put('profiles/{profile}', [CustomerController::class, 'save'])->middleware('admin.permission:customers.update');
        Route::get('profiles/{profile}/timeline', [CustomerController::class, 'timeline'])->middleware('admin.permission:customers.view');
        Route::get('profiles/{profile}/consents', [CustomerController::class, 'consents'])->middleware('admin.permission:customers.view');
        Route::post('profiles/{profile}/account-link-invitations', [CustomerController::class, 'invite'])->middleware('admin.permission:customers.account.link');
        foreach (['archive' => 'archive', 'merge' => 'merge', 'consents' => 'consent', 'relationships' => 'relationship', 'accounting-sync' => 'accounting'] as $path => $action) {
            $permission = match ($path) {
                'consents' => 'consent.manage','relationships' => 'update','accounting-sync' => 'view',default => $path
            };
            Route::post('profiles/{profile}/'.$path, [CustomerController::class, $action])->middleware('admin.permission:customers.'.$permission);
        }
    });
});
