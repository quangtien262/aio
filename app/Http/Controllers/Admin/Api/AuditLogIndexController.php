<?php

namespace App\Http\Controllers\Admin\Api;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['nullable', 'string', 'max:255'],
            'module_key' => ['nullable', 'string', 'max:100'],
            'actor_admin_id' => ['nullable', 'integer', 'exists:admins,id'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $logs = AuditLog::query()
            ->with('actor:id,name,username')
            ->when($validated['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', '%'.$action.'%'))
            ->when($validated['module_key'] ?? null, fn ($query, $moduleKey) => $query->where('module_key', $moduleKey))
            ->when($validated['actor_admin_id'] ?? null, fn ($query, $actorId) => $query->where('actor_admin_id', $actorId))
            ->latest('id')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json(['data' => $logs]);
    }
}
