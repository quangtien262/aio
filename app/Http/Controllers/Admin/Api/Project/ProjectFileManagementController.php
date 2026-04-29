<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectFileManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $parent = Project::query()->findOrFail($project);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'task_id' => ['nullable', 'integer', Rule::exists('pro__tasks', 'id')],
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $uploadedFile = $request->file('file');
        $path = $uploadedFile->store("project-files/{$parent->id}");

        $file = $parent->files()->create([
            'task_id' => $validated['task_id'] ?? null,
            'title' => $validated['title'],
            'path' => $path,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getClientMimeType(),
            'uploaded_by_admin_id' => $request->user('admin')?->id,
        ]);

        ProjectActivityLogger::log($parent, 'file', $file->id, 'uploaded', 'Đã tải file mới lên dự án.', $request->user('admin'), ['title' => $file->title]);

        return response()->json([
            'message' => 'Đã tải file lên.',
            'data' => ProjectDataSerializer::file($file->fresh(['uploader'])),
        ], 201);
    }

    public function download(int $file)
    {
        $record = ProjectFile::query()->findOrFail($file);

        abort_unless(Storage::disk($record->disk)->exists($record->path), 404);

        return Storage::disk($record->disk)->download($record->path, $record->original_name);
    }

    public function destroy(Request $request, int $file): JsonResponse
    {
        $record = ProjectFile::query()->with('project')->findOrFail($file);

        if (Storage::disk($record->disk)->exists($record->path)) {
            Storage::disk($record->disk)->delete($record->path);
        }

        ProjectActivityLogger::log($record->project, 'file', $record->id, 'deleted', 'Đã xóa file khỏi dự án.', $request->user('admin'), ['title' => $record->title]);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa file.',
        ]);
    }
}
