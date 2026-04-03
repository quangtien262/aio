<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRoleAssignmentController
{
    public function __invoke(Request $request, Admin $admin): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['required', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $admin->roles()->sync($validated['role_ids']);

        return response()->json([
            'message' => 'Admin roles updated successfully.',
        ]);
    }
}
