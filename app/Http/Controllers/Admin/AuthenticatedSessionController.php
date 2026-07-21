<?php

namespace App\Http\Controllers\Admin;

use App\Support\FrontendLocalization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use App\Support\AuditLogger;

class AuthenticatedSessionController
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function destroy(Request $request): RedirectResponse
    {
        $locale = FrontendLocalization::resolveLocale($request->session()->get('frontend_locale'));

        $admin = $request->user('admin');
        $this->auditLogger->record('auth.admin.logout', $admin, null, null, $admin);
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->to('/'.$locale);
    }
}
