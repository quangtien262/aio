<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectChecklist;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectChecklistManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $parent = Project::query()->findOrFail($project);
        $validated = $this->validatePayload($request);

        $item = $parent->checklists()->create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? ($parent->checklists()->max('sort_order') + 1),
        ]);

        ProjectActivityLogger::log($parent, 'checklist', $item->id, 'created', 'Đã tạo checklist dự án.', $request->user('admin'), ['title' => $item->title]);

        return response()->json([
            'message' => 'Đã tạo checklist.',
            'data' => ProjectDataSerializer::checklist($item->fresh(['assignee'])),
        ], 201);
    }

    public function update(Request $request, int $checklist): JsonResponse
    {
        $item = ProjectChecklist::query()->with('project')->findOrFail($checklist);
        $validated = $this->validatePayload($request);
        $item->update($validated);

        ProjectActivityLogger::log($item->project, 'checklist', $item->id, 'updated', 'Đã cập nhật checklist dự án.', $request->user('admin'), ['title' => $item->title]);

        return response()->json([
            'message' => 'Đã cập nhật checklist.',
            'data' => ProjectDataSerializer::checklist($item->fresh(['assignee'])),
        ]);
    }

    public function destroy(Request $request, int $checklist): JsonResponse
    {
        $item = ProjectChecklist::query()->with('project')->findOrFail($checklist);

        ProjectActivityLogger::log($item->project, 'checklist', $item->id, 'deleted', 'Đã xóa checklist dự án.', $request->user('admin'), ['title' => $item->title]);
        $item->delete();

        return response()->json([
            'message' => 'Đã xóa checklist.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_completed' => ['nullable', 'boolean'],
            'assigned_admin_id' => ['nullable', 'integer', Rule::exists('admins', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
