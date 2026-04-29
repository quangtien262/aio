<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklist;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTaskChecklistManagementController
{
    public function store(Request $request, int $task): JsonResponse
    {
        $parent = ProjectTask::query()->with('project')->findOrFail($task);
        $validated = $this->validatePayload($request);

        $item = $parent->taskChecklists()->create([
            ...$validated,
            'project_id' => $parent->project_id,
            'sort_order' => $validated['sort_order'] ?? ($parent->taskChecklists()->max('sort_order') + 1),
        ]);

        ProjectActivityLogger::log($parent->project, 'task_checklist', $item->id, 'created', 'Đã tạo checklist công việc.', $request->user('admin'), ['title' => $item->title, 'task_id' => $parent->id]);

        return response()->json([
            'message' => 'Đã tạo checklist công việc.',
            'data' => ProjectDataSerializer::taskChecklist($item->fresh(['assignee'])),
        ], 201);
    }

    public function update(Request $request, int $checklist): JsonResponse
    {
        $item = ProjectTaskChecklist::query()->with(['project', 'task'])->findOrFail($checklist);
        $validated = $this->validatePayload($request);
        $item->update($validated);

        ProjectActivityLogger::log($item->project, 'task_checklist', $item->id, 'updated', 'Đã cập nhật checklist công việc.', $request->user('admin'), ['title' => $item->title, 'task_id' => $item->task_id]);

        return response()->json([
            'message' => 'Đã cập nhật checklist công việc.',
            'data' => ProjectDataSerializer::taskChecklist($item->fresh(['assignee'])),
        ]);
    }

    public function destroy(Request $request, int $checklist): JsonResponse
    {
        $item = ProjectTaskChecklist::query()->with(['project', 'task'])->findOrFail($checklist);

        ProjectActivityLogger::log($item->project, 'task_checklist', $item->id, 'deleted', 'Đã xóa checklist công việc.', $request->user('admin'), ['title' => $item->title, 'task_id' => $item->task_id]);
        $item->delete();

        return response()->json([
            'message' => 'Đã xóa checklist công việc.',
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
