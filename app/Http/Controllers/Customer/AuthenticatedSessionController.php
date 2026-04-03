<?php

namespace App\Http\Controllers\Customer;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController
{
    public function create(): View
    {
        return view('auth.customer-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('customer')->attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Thông tin đăng nhập khách hàng không chính xác.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->intended(route('customer.account'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('customer.auth.login');
    }
}
