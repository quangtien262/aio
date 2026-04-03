<?php

namespace App\Http\Controllers\Admin;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController
{
    public function create(): View
    {
        return view('auth.admin-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            return back()
                ->withErrors(['email' => 'Thông tin đăng nhập admin không chính xác.'])
                ->onlyInput('email');
        }

        /** @var Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if (! $admin?->is_active || $admin->isLocked()) {
            Auth::guard('admin')->logout();

            return back()
                ->withErrors(['email' => 'Tài khoản admin đang bị khóa hoặc vô hiệu hóa.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $admin->forceFill([
            'last_login_at' => now(),
        ])->save();

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->intended(route('admin.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.auth.login');
    }
}
