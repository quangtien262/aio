<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectReport;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectReportManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $parent = Project::query()->findOrFail($project);
        $validated = $this->validatePayload($request);

        $report = $parent->reports()->create([
            ...$validated,
            'created_by_admin_id' => $request->user('admin')?->id,
        ]);

        ProjectActivityLogger::log($parent, 'report', $report->id, 'created', 'Đã tạo báo cáo dự án.', $request->user('admin'), ['title' => $report->title]);

        return response()->json([
            'message' => 'Đã tạo báo cáo.',
            'data' => ProjectDataSerializer::report($report->fresh(['author'])),
        ], 201);
    }

    public function update(Request $request, int $report): JsonResponse
    {
        $record = ProjectReport::query()->with('project')->findOrFail($report);
        $validated = $this->validatePayload($request);
        $record->update($validated);

        ProjectActivityLogger::log($record->project, 'report', $record->id, 'updated', 'Đã cập nhật báo cáo dự án.', $request->user('admin'), ['title' => $record->title]);

        return response()->json([
            'message' => 'Đã cập nhật báo cáo.',
            'data' => ProjectDataSerializer::report($record->fresh(['author'])),
        ]);
    }

    public function destroy(Request $request, int $report): JsonResponse
    {
        $record = ProjectReport::query()->with('project')->findOrFail($report);

        ProjectActivityLogger::log($record->project, 'report', $record->id, 'deleted', 'Đã xóa báo cáo dự án.', $request->user('admin'), ['title' => $record->title]);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa báo cáo.',
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'report_date' => ['required', 'date'],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
        ]);
    }
}
