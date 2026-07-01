<?php

namespace App\Http\Controllers\Customer\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordUpdateController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $customer = $request->user('customer');

        if (! Hash::check($validated['current_password'], $customer?->password)) {
            return response()->json([
                'message' => 'Mật khẩu hiện tại không đúng.',
                'errors' => ['current_password' => ['Mật khẩu hiện tại không đúng.']],
            ], 422);
        }

        $customer?->forceFill(['password' => $validated['password']])->save();

        return response()->json(['message' => 'Đã đổi mật khẩu.']);
    }
}
