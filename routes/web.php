<?php

use App\Http\Controllers\Customer\AuthenticatedSessionController as CustomerAuthenticatedSessionController;
use App\Http\Controllers\Customer\CustomerAccountController;
use App\Http\Controllers\Customer\RegisteredUserController;
use App\Http\Controllers\Site\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('site.home');

Route::middleware('guest:customer')->group(function (): void {
	Route::get('/login', [CustomerAuthenticatedSessionController::class, 'create'])->name('customer.auth.login');
	Route::post('/login', [CustomerAuthenticatedSessionController::class, 'store'])->name('customer.auth.store');
	Route::get('/register', [RegisteredUserController::class, 'create'])->name('customer.auth.register');
	Route::post('/register', [RegisteredUserController::class, 'store'])->name('customer.auth.register.store');
});

Route::middleware('auth:customer')->group(function (): void {
	Route::get('/account', CustomerAccountController::class)->name('customer.account');
	Route::post('/logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('customer.auth.logout');
});

require __DIR__.'/admin.php';
