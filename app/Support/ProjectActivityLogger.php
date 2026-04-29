<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\Project;
use App\Models\ProjectActivity;

class ProjectActivityLogger
{
    public static function log(Project $project, string $entityType, ?int $entityId, string $action, string $description, ?Admin $admin = null, array $properties = []): void
    {
        ProjectActivity::query()->create([
            'project_id' => $project->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'created_by_admin_id' => $admin?->id,
        ]);
    }
}
