<?php

use Illuminate\Support\Facades\Route;
use Modules\Manufacturing\Http\ManufacturingController;
use Modules\Manufacturing\Http\ManufacturingDocumentController;

Route::get('bootstrap', [ManufacturingController::class, 'bootstrap'])->name('bootstrap');
Route::get('commands/{key}', [ManufacturingController::class, 'commandStatus'])->name('commands.status');
Route::post('documents', [ManufacturingDocumentController::class, 'upload'])->name('documents.upload');
Route::get('documents/{document}', [ManufacturingDocumentController::class, 'download'])->name('documents.download');
Route::post('commands/{operation}', [ManufacturingController::class, 'mutate'])->where('operation', '[a-z][a-z.-]+')->name('commands.run');
Route::get('{kind}', [ManufacturingController::class, 'read'])->where('kind', '[a-z][a-z-]+')->name('read');
