<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\ProjectTask;
use App\Models\ProjectTaskComment;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskCommentManagementController
{
    public function store(Request $request, int $task): JsonResponse
    {
        $parent = ProjectTask::query()->with('project')->findOrFail($task);
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $comment = $parent->comments()->create([
            'project_id' => $parent->project_id,
            'content' => $validated['content'],
            'created_by_admin_id' => $request->user('admin')?->id,
        ]);

        ProjectActivityLogger::log($parent->project, 'task_comment', $comment->id, 'created', 'Đã thêm bình luận cho công việc.', $request->user('admin'), ['task_id' => $parent->id]);

        return response()->json([
            'message' => 'Đã thêm bình luận.',
            'data' => ProjectDataSerializer::taskComment($comment->fresh(['author', 'editor'])),
        ], 201);
    }

    public function update(Request $request, int $comment): JsonResponse
    {
        $record = ProjectTaskComment::query()->with(['project', 'task'])->findOrFail($comment);
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $record->update([
            'content' => $validated['content'],
            'updated_by_admin_id' => $request->user('admin')?->id,
        ]);

        ProjectActivityLogger::log($record->project, 'task_comment', $record->id, 'updated', 'Đã cập nhật bình luận công việc.', $request->user('admin'), ['task_id' => $record->task_id]);

        return response()->json([
            'message' => 'Đã cập nhật bình luận.',
            'data' => ProjectDataSerializer::taskComment($record->fresh(['author', 'editor'])),
        ]);
    }

    public function destroy(Request $request, int $comment): JsonResponse
    {
        $record = ProjectTaskComment::query()->with(['project', 'task'])->findOrFail($comment);

        ProjectActivityLogger::log($record->project, 'task_comment', $record->id, 'deleted', 'Đã xóa bình luận công việc.', $request->user('admin'), ['task_id' => $record->task_id]);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa bình luận.',
        ]);
    }
}
