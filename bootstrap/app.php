<?php

use App\Http\Middleware\EnsureAdminHasPermission;
use App\Http\Middleware\SetFrontendLocale;
use App\Support\FrontendLocalization;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.permission' => EnsureAdminHasPermission::class,
            'frontend.locale' => SetFrontendLocale::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            return $request->is('admin') || $request->is('admin/*')
                ? route('admin.auth.login')
                : route('site.home', FrontendLocalization::routeParameterDefaults($request->session()->get('frontend_locale')));
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            return $request->is('admin') || $request->is('admin/*')
                ? route('admin.index')
                : route('customer.account', FrontendLocalization::routeParameterDefaults($request->session()->get('frontend_locale')));
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
