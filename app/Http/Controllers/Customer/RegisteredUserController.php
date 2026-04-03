<?php

namespace App\Http\Controllers\Customer;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController
{
    public function create(): View
    {
        return view('auth.customer-register');
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $customer = Customer::query()->create($payload);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return to_route('customer.account');
    }
}
