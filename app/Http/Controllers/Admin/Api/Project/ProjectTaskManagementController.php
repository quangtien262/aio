<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTaskManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $parent = Project::query()->findOrFail($project);
        $validated = $this->validatePayload($request, $parent);

        $task = $parent->tasks()->create([
            ...$validated,
            'created_by_admin_id' => $request->user('admin')?->id,
            'sort_order' => $validated['sort_order'] ?? $parent->tasks()->where('task_status_id', $validated['task_status_id'])->max('sort_order') + 1,
        ]);

        ProjectActivityLogger::log($parent, 'task', $task->id, 'created', 'Đã tạo công việc mới.', $request->user('admin'), ['title' => $task->title]);

        return response()->json([
            'message' => 'Đã tạo công việc.',
            'data' => ProjectDataSerializer::task($task->fresh(['status', 'priority', 'assignee'])),
        ], 201);
    }

    public function update(Request $request, int $task): JsonResponse
    {
        $record = ProjectTask::query()->with('project')->findOrFail($task);
        $validated = $this->validatePayload($request, $record->project, $record);

        $record->update($validated);

        ProjectActivityLogger::log($record->project, 'task', $record->id, 'updated', 'Đã cập nhật công việc.', $request->user('admin'), ['title' => $record->title]);

        return response()->json([
            'message' => 'Đã cập nhật công việc.',
            'data' => ProjectDataSerializer::task($record->fresh(['status', 'priority', 'assignee'])),
        ]);
    }

    public function destroy(Request $request, int $task): JsonResponse
    {
        $record = ProjectTask::query()->with('project')->findOrFail($task);

        ProjectActivityLogger::log($record->project, 'task', $record->id, 'deleted', 'Đã xóa công việc.', $request->user('admin'), ['title' => $record->title]);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa công việc.',
        ]);
    }

    private function validatePayload(Request $request, Project $project, ?ProjectTask $task = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'task_status_id' => ['required', 'integer', Rule::exists('pro__task_statuses', 'id')],
            'priority_id' => ['required', 'integer', Rule::exists('pro__priorities', 'id')],
            'assignee_admin_id' => ['nullable', 'integer', Rule::exists('admins', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
    }
}
