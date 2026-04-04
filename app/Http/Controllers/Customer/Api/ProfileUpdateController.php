<?php

namespace App\Http\Controllers\Customer\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileUpdateController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $customer = $request->user('customer');
        $customer?->forceFill($validated)->save();

        return response()->json([
            'message' => 'Đã cập nhật thông tin cá nhân.',
            'data' => [
                'name' => $customer?->name,
                'email' => $customer?->email,
                'phone' => $customer?->phone,
            ],
        ]);
    }
}