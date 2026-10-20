<?php

use Illuminate\Support\Facades\Route;
use Modules\Merchandise\Http\MerchandiseController;

Route::prefix('merchandise')->middleware('module.enabled:merchandise')->group(function (): void {
    Route::get('catalogs', [MerchandiseController::class, 'catalogs'])->middleware('admin.permission:merchandise.view,global');
    Route::post('catalogs', [MerchandiseController::class, 'createCatalog'])->middleware('admin.permission:merchandise.manage,global');
    Route::get('catalogs/{catalog}/skus', [MerchandiseController::class, 'skus'])->middleware('admin.permission:merchandise.view,global');
    Route::post('catalogs/{catalog}/skus', [MerchandiseController::class, 'saveSku'])->middleware('admin.permission:merchandise.manage,global');
    Route::put('catalogs/{catalog}/skus/{sku}', [MerchandiseController::class, 'saveSku'])->middleware('admin.permission:merchandise.manage,global');
    Route::post('catalogs/{catalog}/import', [MerchandiseController::class, 'import'])->middleware('admin.permission:merchandise.import,global');
});
