<?php

namespace App\Http\Controllers\Admin;

use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController
{
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('site.home', [
            'locale' => FrontendLocalization::resolveLocale($request->session()->get('frontend_locale')),
        ]);
    }
}
