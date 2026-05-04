<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetFrontendLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = \App\Support\FrontendLocalization::resolveLocale($request->route('locale') ?: $request->session()->get('frontend_locale'));

        app()->setLocale($locale);
        $request->session()->put('frontend_locale', $locale);
        URL::defaults(\App\Support\FrontendLocalization::routeParameterDefaults($locale));

        return $next($request);
    }
}
