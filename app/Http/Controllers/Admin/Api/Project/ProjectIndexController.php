<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = Project::query()
            ->with(['projectType', 'status', 'priority', 'manager'])
            ->withCount(['tasks', 'files', 'reports', 'members'])
            ->orderByDesc('updated_at');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('project_status_id')) {
            $query->where('project_status_id', (int) $request->integer('project_status_id'));
        }

        if ($request->filled('project_type_id')) {
            $query->where('project_type_id', (int) $request->integer('project_type_id'));
        }

        $projects = $query->get()->map(fn (Project $project): array => ProjectDataSerializer::projectSummary($project))->values()->all();

        return response()->json([
            'data' => [
                'items' => $projects,
                'total' => count($projects),
                'metrics' => [
                    'active_projects' => Project::query()->count(),
                    'total_tasks' => \App\Models\ProjectTask::query()->count(),
                    'completed_tasks' => \App\Models\ProjectTask::query()->whereHas('status', fn ($builder) => $builder->where('is_done', true))->count(),
                ],
                'references' => ProjectDataSerializer::references(),
            ],
        ]);
    }
}
