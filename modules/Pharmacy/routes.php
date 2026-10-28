<?php

use Illuminate\Support\Facades\Route;
use Modules\Pharmacy\Http\PharmacyController;
use Modules\Pharmacy\Http\PharmacyDocumentController;

Route::get('bootstrap', [PharmacyController::class, 'bootstrap'])->name('bootstrap');
Route::post('commands', [PharmacyController::class, 'command'])->name('commands');
Route::post('commands/{operation}', [PharmacyController::class, 'command'])->where('operation', '[a-z][a-z.-]+')->name('commands.run');
Route::get('commands/{key}', [PharmacyController::class, 'status'])->name('commands.status');
Route::post('documents', [PharmacyDocumentController::class, 'upload'])->name('documents.upload');
Route::get('documents/{id}', [PharmacyDocumentController::class, 'download'])->name('documents.download');
Route::get('print-jobs/{id}', [PharmacyDocumentController::class, 'printJob'])->name('print-jobs.download');
Route::get('{kind}/{id?}', [PharmacyController::class, 'read'])->where('kind', '[a-z][a-z-]+')->name('read');
