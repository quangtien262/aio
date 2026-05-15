<?php

namespace App\Http\Controllers\Customer;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'login' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'string', 'max:255', 'required_without:login'],
            'password' => ['required', 'string'],
            'redirect_to' => ['nullable', 'string', 'max:255'],
        ]);

        $remember = $request->boolean('remember');
        $identifier = trim((string) ($payload['login'] ?? $payload['email'] ?? ''));
        $errorField = $request->filled('login') ? 'login' : 'email';
        $adminCredentials = [
            'username' => $identifier,
            'password' => $payload['password'],
        ];
        $customerCredentials = [
            'email' => $identifier,
            'password' => $payload['password'],
        ];

        if (Auth::guard('admin')->attempt($adminCredentials, $remember)) {
            /** @var Admin|null $admin */
            $admin = Auth::guard('admin')->user();

            if (! $admin?->is_active || $admin->isLocked()) {
                Auth::guard('admin')->logout();

                return $this->failedResponse(
                    $request,
                    'Tài khoản admin đang bị khóa hoặc vô hiệu hóa.',
                    $errorField,
                );
            }

            $request->session()->regenerate();

            $admin->forceFill([
                'last_login_at' => now(),
            ])->save();

            return $this->successfulResponse($request, route('admin.index'), 'Đăng nhập admin thành công.', 'admin');
        }

        if (! Auth::guard('customer')->attempt($customerCredentials, $remember)) {
            return $this->failedResponse(
                $request,
                'Thông tin đăng nhập không chính xác.',
                $errorField,
            );
        }

        $request->session()->regenerate();

        $redirectTo = $payload['redirect_to'] ?? route('customer.account');

        return $this->successfulResponse($request, $redirectTo, 'Đăng nhập thành công.', 'customer');
    }

    protected function successfulResponse(Request $request, string $redirectTo, string $message, string $guard): RedirectResponse|JsonResponse
    {
        /** @var Redirector $redirector */
        $redirector = app('redirect');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'guard' => $guard,
                    'redirect_to' => $redirectTo,
                ],
            ]);
        }

        return $redirector->intended($redirectTo);
    }

    protected function failedResponse(Request $request, string $message, string $field): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'errors' => [
                    $field => [$message],
                ],
            ], 422);
        }

        return back()
            ->withErrors([$field => $message])
            ->onlyInput($field);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('site.home');
    }
}
