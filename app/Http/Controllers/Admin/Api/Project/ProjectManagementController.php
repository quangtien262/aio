<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectManagementController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $project = Project::query()->create([
            ...$validated,
            'code' => $validated['code'] ?? $this->generateCode(),
        ]);

        if (! empty($validated['member_admin_ids'] ?? [])) {
            foreach ($validated['member_admin_ids'] as $adminId) {
                $project->members()->firstOrCreate(['admin_id' => $adminId], ['role' => 'member']);
            }
        }

        ProjectActivityLogger::log($project, 'project', $project->id, 'created', 'Đã tạo dự án mới.', $request->user('admin'));

        $record = $project->fresh(['projectType', 'status', 'priority', 'manager']);

        return response()->json([
            'message' => 'Đã tạo dự án.',
            'data' => ProjectDataSerializer::projectSummary($record),
        ], 201);
    }

    public function update(Request $request, int $project): JsonResponse
    {
        $record = Project::query()->findOrFail($project);
        $validated = $this->validatePayload($request, $record);

        $record->update($validated);

        if (array_key_exists('member_admin_ids', $validated)) {
            $existingMemberIds = $record->members()->pluck('admin_id')->all();
            $requestedIds = $validated['member_admin_ids'] ?? [];

            $record->members()->whereNotIn('admin_id', $requestedIds)->delete();

            foreach ($requestedIds as $adminId) {
                $record->members()->firstOrCreate(['admin_id' => $adminId], ['role' => in_array($adminId, $existingMemberIds, true) ? 'member' : 'member']);
            }
        }

        ProjectActivityLogger::log($record, 'project', $record->id, 'updated', 'Đã cập nhật thông tin dự án.', $request->user('admin'));

        return response()->json([
            'message' => 'Đã cập nhật dự án.',
            'data' => ProjectDataSerializer::projectSummary($record->fresh(['projectType', 'status', 'priority', 'manager'])),
        ]);
    }

    public function destroy(Request $request, int $project): JsonResponse
    {
        $record = Project::query()->findOrFail($project);

        ProjectActivityLogger::log($record, 'project', $record->id, 'deleted', 'Đã xóa dự án.', $request->user('admin'));
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa dự án.',
        ]);
    }

    private function validatePayload(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('pro__projects', 'code')->ignore($project?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_type_id' => ['nullable', 'integer', Rule::exists('pro__project_types', 'id')],
            'project_status_id' => ['required', 'integer', Rule::exists('pro__project_statuses', 'id')],
            'priority_id' => ['required', 'integer', Rule::exists('pro__priorities', 'id')],
            'manager_admin_id' => ['nullable', 'integer', Rule::exists('admins', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'color' => ['nullable', 'string', 'max:32'],
            'meta' => ['nullable', 'array'],
            'member_admin_ids' => ['nullable', 'array'],
            'member_admin_ids.*' => ['integer', Rule::exists('admins', 'id')],
        ]);
    }

    private function generateCode(): string
    {
        $nextId = (Project::query()->max('id') ?? 0) + 1;

        return sprintf('PRO-%04d', $nextId);
    }
}
