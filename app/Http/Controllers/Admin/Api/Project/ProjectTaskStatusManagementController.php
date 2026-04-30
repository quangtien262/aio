<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectTaskStatus;
use App\Support\ProjectTaskStatusManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectTaskStatusManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $record = Project::query()->findOrFail($project);
        ProjectTaskStatusManager::ensureProjectStatuses($record);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pro__task_statuses', 'name')->where(fn ($query) => $query->where('project_id', $record->id)),
            ],
            'color' => ['nullable', 'string', 'max:32'],
            'is_done' => ['nullable', 'boolean'],
        ]);

        $status = $record->taskStatuses()->create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'default',
            'sort_order' => (($record->taskStatuses()->max('sort_order') ?? 0) + 1),
            'is_done' => (bool) ($validated['is_done'] ?? false),
            'is_active' => true,
        ]);

        if ($status->is_done) {
            $record->taskStatuses()->where('id', '!=', $status->id)->update(['is_done' => false]);
        }

        return response()->json([
            'message' => 'Đã thêm trạng thái.',
            'data' => [
                'item' => ProjectDataSerializer::references($record)['task_statuses'][array_search($status->id, array_column(ProjectDataSerializer::references($record)['task_statuses'], 'id'))] ?? null,
                'items' => ProjectDataSerializer::references($record)['task_statuses'],
            ],
        ], 201);
    }

    public function update(Request $request, int $status): JsonResponse
    {
        $record = ProjectTaskStatus::query()->with('project')->findOrFail($status);
        $project = $record->project;

        if (! $project) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pro__task_statuses', 'name')
                    ->ignore($record->id)
                    ->where(fn ($query) => $query->where('project_id', $project->id)),
            ],
            'color' => ['nullable', 'string', 'max:32'],
            'is_done' => ['nullable', 'boolean'],
        ]);

        $nextIsDone = (bool) ($validated['is_done'] ?? false);

        if (! $nextIsDone && $record->is_done && ! $project->taskStatuses()->where('id', '!=', $record->id)->where('is_done', true)->exists()) {
            throw ValidationException::withMessages([
                'is_done' => 'Dự án cần ít nhất một trạng thái hoàn thành.',
            ]);
        }

        $record->update([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? 'default',
            'is_done' => $nextIsDone,
        ]);

        if ($nextIsDone) {
            $project->taskStatuses()->where('id', '!=', $record->id)->update(['is_done' => false]);
        }

        return response()->json([
            'message' => 'Đã cập nhật trạng thái.',
            'data' => [
                'items' => ProjectDataSerializer::references($project->fresh())['task_statuses'],
            ],
        ]);
    }

    public function destroy(int $status): JsonResponse
    {
        $record = ProjectTaskStatus::query()->with(['project', 'tasks'])->findOrFail($status);
        $project = $record->project;

        if (! $project) {
            abort(404);
        }

        if ($record->tasks()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Trạng thái này đang có công việc. Hãy chuyển task sang cột khác trước khi xóa.',
            ]);
        }

        if ($project->taskStatuses()->count() <= 1) {
            throw ValidationException::withMessages([
                'status' => 'Dự án phải có ít nhất một trạng thái.',
            ]);
        }

        if ($record->is_done && ! $project->taskStatuses()->where('id', '!=', $record->id)->where('is_done', true)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Không thể xóa trạng thái hoàn thành cuối cùng của dự án.',
            ]);
        }

        $record->delete();
        ProjectTaskStatusManager::normalizeSortOrder($project);

        return response()->json([
            'message' => 'Đã xóa trạng thái.',
            'data' => [
                'items' => ProjectDataSerializer::references($project->fresh())['task_statuses'],
            ],
        ]);
    }

    public function reorder(Request $request, int $project): JsonResponse
    {
        $record = Project::query()->findOrFail($project);
        ProjectTaskStatusManager::ensureProjectStatuses($record);

        $validated = $request->validate([
            'status_ids' => ['required', 'array', 'min:1'],
            'status_ids.*' => ['integer'],
        ]);

        $statusIds = collect($validated['status_ids'])->map(fn ($id) => (int) $id)->values();
        $currentIds = $record->taskStatuses()->orderBy('sort_order')->pluck('id')->values();

        if ($statusIds->count() !== $currentIds->count() || $statusIds->diff($currentIds)->isNotEmpty() || $currentIds->diff($statusIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'status_ids' => 'Danh sách trạng thái không hợp lệ cho dự án này.',
            ]);
        }

        foreach ($statusIds as $index => $statusId) {
            $record->taskStatuses()->where('id', $statusId)->update(['sort_order' => $index + 1]);
        }

        return response()->json([
            'message' => 'Đã cập nhật thứ tự trạng thái.',
            'data' => [
                'items' => ProjectDataSerializer::references($record->fresh())['task_statuses'],
            ],
        ]);
    }
}
