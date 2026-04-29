<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\ProjectTask;
use App\Models\ProjectTaskTimeEntry;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskTimeEntryManagementController
{
    public function store(Request $request, int $task): JsonResponse
    {
        $parent = ProjectTask::query()->with('project')->findOrFail($task);
        $validated = $this->validatePayload($request);

        $entry = $parent->timeEntries()->create([
            ...$validated,
            'project_id' => $parent->project_id,
            'tracked_by_admin_id' => $request->user('admin')?->id,
        ]);

        ProjectActivityLogger::log($parent->project, 'task_time_entry', $entry->id, 'created', 'Đã ghi nhận time tracking cho công việc.', $request->user('admin'), ['task_id' => $parent->id, 'duration_minutes' => $entry->duration_minutes]);

        return response()->json([
            'message' => 'Đã thêm time tracking.',
            'data' => ProjectDataSerializer::taskTimeEntry($entry->fresh(['author'])),
        ], 201);
    }

    public function update(Request $request, int $entry): JsonResponse
    {
        $record = ProjectTaskTimeEntry::query()->with(['project', 'task'])->findOrFail($entry);
        $validated = $this->validatePayload($request);
        $record->update($validated);

        ProjectActivityLogger::log($record->project, 'task_time_entry', $record->id, 'updated', 'Đã cập nhật time tracking cho công việc.', $request->user('admin'), ['task_id' => $record->task_id, 'duration_minutes' => $record->duration_minutes]);

        return response()->json([
            'message' => 'Đã cập nhật time tracking.',
            'data' => ProjectDataSerializer::taskTimeEntry($record->fresh(['author'])),
        ]);
    }

    public function destroy(Request $request, int $entry): JsonResponse
    {
        $record = ProjectTaskTimeEntry::query()->with(['project', 'task'])->findOrFail($entry);

        ProjectActivityLogger::log($record->project, 'task_time_entry', $record->id, 'deleted', 'Đã xóa time tracking của công việc.', $request->user('admin'), ['task_id' => $record->task_id]);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa time tracking.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'tracked_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'note' => ['nullable', 'string'],
        ]);
    }
}
