<?php

namespace App\Http\Controllers\Customer;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController
{
    public function create(): View
    {
        return view('auth.customer-register');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'redirect_to' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->create($payload);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $redirectTo = (string) ($payload['redirect_to'] ?? route('customer.account'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Đăng ký tài khoản thành công.',
                'data' => [
                    'redirect_to' => $redirectTo,
                ],
            ]);
        }

        /** @var Redirector $redirector */
        $redirector = app('redirect');

        return $redirector->to($redirectTo);
    }
}
