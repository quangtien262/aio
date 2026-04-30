<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectDetailController
{
    public function __invoke(int $project): JsonResponse
    {
        $record = Project::query()
            ->with([
                'projectType',
                'status',
                'priority',
                'manager',
                'members.admin',
                'tasks.status',
                'tasks.priority',
                'tasks.assignee',
                'checklists.assignee',
                'taskChecklists.assignee',
                'taskComments.author',
                'taskComments.editor',
                'taskTimeEntries.author',
                'files.uploader',
                'reports.author',
                'activities.author',
            ])
            ->findOrFail($project);

        return response()->json([
            'data' => [
                'project' => ProjectDataSerializer::projectDetail($record),
                'references' => ProjectDataSerializer::references($record),
            ],
        ]);
    }
}
