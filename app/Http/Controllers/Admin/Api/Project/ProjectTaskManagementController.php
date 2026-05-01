<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Support\ProjectActivityLogger;
use App\Support\ProjectTaskStatusManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ProjectTaskManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $parent = Project::query()->findOrFail($project);
        ProjectTaskStatusManager::ensureProjectStatuses($parent);
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
        $originalStatusId = $record->task_status_id;
        $requestedSortOrder = $validated['sort_order'] ?? null;

        DB::transaction(function () use ($record, $validated, $originalStatusId, $requestedSortOrder): void {
            $record->update($validated);

            if ($requestedSortOrder !== null || $originalStatusId !== $record->task_status_id) {
                $this->syncTaskOrdering($record->fresh(), $originalStatusId, $requestedSortOrder);
            }
        });

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
            'task_status_id' => [
                'required',
                'integer',
                Rule::exists('pro__task_statuses', 'id')->where(fn ($query) => $query->where('project_id', $project->id)),
            ],
            'priority_id' => ['required', 'integer', Rule::exists('pro__priorities', 'id')],
            'assignee_admin_id' => ['nullable', 'integer', Rule::exists('admins', 'id')],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
    }

    private function syncTaskOrdering(ProjectTask $record, int $originalStatusId, ?int $requestedSortOrder): void
    {
        if ($originalStatusId !== $record->task_status_id) {
            $this->normalizeStatusTasks($record->project_id, $originalStatusId);
        }

        $siblings = ProjectTask::query()
            ->where('project_id', $record->project_id)
            ->where('task_status_id', $record->task_status_id)
            ->where('id', '!=', $record->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $insertIndex = $requestedSortOrder !== null
            ? max(0, min($requestedSortOrder - 1, $siblings->count()))
            : $siblings->count();

        $orderedTasks = $siblings->values();
        $orderedTasks->splice($insertIndex, 0, [$record]);

        $orderedTasks->values()->each(function (ProjectTask $task, int $index): void {
            $nextSortOrder = $index + 1;

            if ($task->sort_order === $nextSortOrder) {
                return;
            }

            $task->updateQuietly(['sort_order' => $nextSortOrder]);
        });
    }

    private function normalizeStatusTasks(int $projectId, int $statusId): void
    {
        ProjectTask::query()
            ->where('project_id', $projectId)
            ->where('task_status_id', $statusId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->each(function (ProjectTask $task, int $index): void {
                $nextSortOrder = $index + 1;

                if ($task->sort_order === $nextSortOrder) {
                    return;
                }

                $task->updateQuietly(['sort_order' => $nextSortOrder]);
            });
    }
}
