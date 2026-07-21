<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Admin|null $admin */
        $admin = $request->user('admin');

        if (! $admin || ! $admin->isAvailable()) {
            return $this->invalidateSession($request, 'Tài khoản quản trị đã bị khóa hoặc ngừng hoạt động.');
        }

        $sessionVersion = $request->session()->get('admin_auth_version');

        if ($sessionVersion === null) {
            $request->session()->put('admin_auth_version', $admin->auth_version);
        } elseif ((int) $sessionVersion !== (int) $admin->auth_version) {
            return $this->invalidateSession($request, 'Phiên đăng nhập đã được thu hồi. Vui lòng đăng nhập lại.');
        }

        return $next($request);
    }

    private function invalidateSession(Request $request, string $message): Response
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect('/')->withErrors(['login' => $message]);
    }
}
