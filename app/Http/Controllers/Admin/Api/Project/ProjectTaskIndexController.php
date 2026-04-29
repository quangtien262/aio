<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskIndexController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = ProjectTask::query()->with(['project', 'status', 'priority', 'assignee'])->orderByDesc('updated_at');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->integer('project_id'));
        }

        $items = $query->get()->map(fn (ProjectTask $task): array => [
            ...ProjectDataSerializer::task($task),
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
                'code' => $task->project->code,
            ] : null,
        ])->values()->all();

        return response()->json([
            'data' => [
                'items' => $items,
                'total' => count($items),
                'references' => ProjectDataSerializer::references(),
            ],
        ]);
    }
}
