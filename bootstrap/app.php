<?php

use App\Http\Middleware\EnsureAdminHasPermission;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.permission' => EnsureAdminHasPermission::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            return $request->is('admin') || $request->is('admin/*')
                ? route('admin.auth.login')
                : route('customer.auth.login');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            return $request->is('admin') || $request->is('admin/*')
                ? route('admin.index')
                : route('customer.account');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
