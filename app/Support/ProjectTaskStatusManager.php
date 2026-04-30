<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectTaskStatus;
use Illuminate\Support\Collection;

class ProjectTaskStatusManager
{
    public static function defaultDefinitions(): array
    {
        return [
            ['name' => 'Chưa bắt đầu', 'color' => 'default', 'sort_order' => 1, 'is_done' => false],
            ['name' => 'Đang làm', 'color' => 'processing', 'sort_order' => 2, 'is_done' => false],
            ['name' => 'Đang review', 'color' => 'warning', 'sort_order' => 3, 'is_done' => false],
            ['name' => 'Hoàn thành', 'color' => 'success', 'sort_order' => 4, 'is_done' => true],
            ['name' => 'Dừng/Hủy', 'color' => 'error', 'sort_order' => 5, 'is_done' => false],
        ];
    }

    public static function ensureTemplateStatuses(): Collection
    {
        foreach (self::defaultDefinitions() as $definition) {
            ProjectTaskStatus::query()->updateOrCreate(
                ['project_id' => null, 'name' => $definition['name']],
                [...$definition, 'is_active' => true],
            );
        }

        return ProjectTaskStatus::query()
            ->whereNull('project_id')
            ->orderBy('sort_order')
            ->get();
    }

    public static function ensureProjectStatuses(Project $project): Collection
    {
        $existing = $project->taskStatuses()->orderBy('sort_order')->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $templates = self::ensureTemplateStatuses();

        foreach ($templates as $template) {
            $project->taskStatuses()->create([
                'name' => $template->name,
                'color' => $template->color,
                'sort_order' => $template->sort_order,
                'is_done' => $template->is_done,
                'is_active' => $template->is_active,
            ]);
        }

        return $project->taskStatuses()->orderBy('sort_order')->get();
    }

    public static function normalizeSortOrder(Project $project): void
    {
        $project->taskStatuses()->orderBy('sort_order')->orderBy('id')->get()->each(
            fn (ProjectTaskStatus $status, int $index) => $status->update(['sort_order' => $index + 1]),
        );
    }
}
