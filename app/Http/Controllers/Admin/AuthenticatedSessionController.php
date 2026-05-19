<?php

namespace App\Http\Controllers\Admin;

use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController
{
    public function destroy(Request $request): RedirectResponse
    {
        $locale = FrontendLocalization::resolveLocale($request->session()->get('frontend_locale'));

        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->to('/'.$locale);
    }
}
