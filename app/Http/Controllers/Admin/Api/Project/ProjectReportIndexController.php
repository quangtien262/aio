<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\ProjectReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectReportIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = ProjectReport::query()->with(['project', 'author'])->orderByDesc('report_date')->orderByDesc('created_at');

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->integer('project_id'));
        }

        $items = $query->get()->map(function (ProjectReport $report): array {
            return [
                ...ProjectDataSerializer::report($report),
                'project' => $report->project ? [
                    'id' => $report->project->id,
                    'name' => $report->project->name,
                    'code' => $report->project->code,
                ] : null,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'references' => ProjectDataSerializer::references(),
            ],
        ]);
    }
}
