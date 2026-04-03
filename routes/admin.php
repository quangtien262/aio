<?php

use App\Http\Controllers\Admin\AdminShellController;
use App\Http\Controllers\Admin\Api\DashboardController;
use App\Http\Controllers\Admin\Api\ModuleLifecycleController;
use App\Http\Controllers\Admin\Api\ModuleRegistryController;
use App\Http\Controllers\Admin\Api\SetupProfileController;
use App\Http\Controllers\Admin\Api\SetupStepController;
use App\Http\Controllers\Admin\Api\SetupWizardStateController;
use App\Http\Controllers\Admin\Api\ThemeActivationController;
use App\Http\Controllers\Admin\Api\ThemeRegistryController;
use App\Http\Controllers\Admin\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::middleware('guest:admin')->group(function (): void {
            Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('auth.login');
            Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('auth.store');
        });

        Route::middleware('auth:admin')->group(function (): void {
            Route::get('/', AdminShellController::class)->name('index');
            Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');

            Route::prefix('api')->name('api.')->group(function (): void {
                Route::get('/dashboard', DashboardController::class)
                    ->middleware('admin.permission:platform.dashboard.view')
                    ->name('dashboard');
                Route::get('/modules', ModuleRegistryController::class)
                    ->middleware('admin.permission:store.module.view')
                    ->name('modules');
                Route::post('/modules/{key}/install', [ModuleLifecycleController::class, 'install'])
                    ->middleware('admin.permission:store.module.install')
                    ->name('modules.install');
                Route::post('/modules/{key}/enable', [ModuleLifecycleController::class, 'enable'])
                    ->middleware('admin.permission:store.module.enable')
                    ->name('modules.enable');
                Route::post('/modules/{key}/disable', [ModuleLifecycleController::class, 'disable'])
                    ->middleware('admin.permission:store.module.disable')
                    ->name('modules.disable');
                Route::delete('/modules/{key}', [ModuleLifecycleController::class, 'uninstall'])
                    ->middleware('admin.permission:store.module.uninstall')
                    ->name('modules.uninstall');
                Route::get('/themes', ThemeRegistryController::class)
                    ->middleware('admin.permission:theme.view')
                    ->name('themes');
                Route::post('/themes/{key}/activate', ThemeActivationController::class)
                    ->middleware('admin.permission:theme.activate')
                    ->name('themes.activate');
                Route::get('/setup', SetupWizardStateController::class)
                    ->middleware('admin.permission:setup.view')
                    ->name('setup');
                Route::put('/setup', SetupProfileController::class)
                    ->middleware('admin.permission:setup.complete')
                    ->name('setup.update');
                Route::post('/setup/steps/{step}', SetupStepController::class)
                    ->middleware('admin.permission:setup.complete')
                    ->name('setup.steps.complete');
            });
        });
    });
