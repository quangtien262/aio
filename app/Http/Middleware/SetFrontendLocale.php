<?php

namespace App\Http\Middleware;

use App\Support\FrontendLocalization;
use App\Support\Localization\LocaleCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetFrontendLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeLocale = LocaleCode::tryNormalize((string) $request->route('locale'));

        if ($request->route('locale') !== null && ! FrontendLocalization::isSupported($routeLocale)) {
            abort(404);
        }

        $locale = FrontendLocalization::resolveLocale(
            $routeLocale ?: $request->session()->get('frontend_locale'),
        );

        app()->setLocale($locale);
        $request->session()->put('frontend_locale', $locale);
        URL::defaults(FrontendLocalization::routeParameterDefaults($locale));

        return $next($request);
    }
}
