<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Admin;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectChecklist;
use App\Models\ProjectFile;
use App\Models\ProjectMember;
use App\Models\ProjectPriority;
use App\Models\ProjectReport;
use App\Models\ProjectStatus;
use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklist;
use App\Models\ProjectTaskComment;
use App\Models\ProjectTaskStatus;
use App\Models\ProjectTaskTimeEntry;
use App\Models\ProjectType;

class ProjectDataSerializer
{
    public static function references(): array
    {
        return [
            'project_statuses' => ProjectStatus::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (ProjectStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'sort_order' => $status->sort_order,
            ])->values()->all(),
            'project_types' => ProjectType::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (ProjectType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
                'color' => $type->color,
                'sort_order' => $type->sort_order,
            ])->values()->all(),
            'priorities' => ProjectPriority::query()->orderBy('sort_order')->get()->map(fn (ProjectPriority $priority): array => [
                'id' => $priority->id,
                'name' => $priority->name,
                'color' => $priority->color,
                'sort_order' => $priority->sort_order,
            ])->values()->all(),
            'task_statuses' => ProjectTaskStatus::query()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (ProjectTaskStatus $status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'color' => $status->color,
                'sort_order' => $status->sort_order,
                'is_done' => $status->is_done,
            ])->values()->all(),
            'admins' => Admin::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Admin $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ])->values()->all(),
        ];
    }

    public static function projectSummary(Project $project): array
    {
        return [
            'id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'description' => $project->description,
            'project_type_id' => $project->project_type_id,
            'project_status_id' => $project->project_status_id,
            'priority_id' => $project->priority_id,
            'manager_admin_id' => $project->manager_admin_id,
            'start_date' => $project->start_date?->format('Y-m-d'),
            'due_date' => $project->due_date?->format('Y-m-d'),
            'completed_at' => $project->completed_at?->format('Y-m-d'),
            'progress' => $project->progress,
            'color' => $project->color,
            'meta' => $project->meta ?? [],
            'project_type' => $project->projectType ? [
                'id' => $project->projectType->id,
                'name' => $project->projectType->name,
                'color' => $project->projectType->color,
            ] : null,
            'status' => $project->status ? [
                'id' => $project->status->id,
                'name' => $project->status->name,
                'color' => $project->status->color,
            ] : null,
            'priority' => $project->priority ? [
                'id' => $project->priority->id,
                'name' => $project->priority->name,
                'color' => $project->priority->color,
            ] : null,
            'manager' => $project->manager ? [
                'id' => $project->manager->id,
                'name' => $project->manager->name,
                'email' => $project->manager->email,
            ] : null,
            'tasks_count' => $project->tasks_count ?? $project->tasks()->count(),
            'reports_count' => $project->reports_count ?? $project->reports()->count(),
            'files_count' => $project->files_count ?? $project->files()->count(),
            'members_count' => $project->members_count ?? $project->members()->count(),
            'created_at' => $project->created_at?->toAtomString(),
            'updated_at' => $project->updated_at?->toAtomString(),
        ];
    }

    public static function projectDetail(Project $project): array
    {
        return [
            ...self::projectSummary($project),
            'members' => $project->members->map(fn (ProjectMember $member): array => [
                'id' => $member->id,
                'role' => $member->role,
                'admin' => $member->admin ? [
                    'id' => $member->admin->id,
                    'name' => $member->admin->name,
                    'email' => $member->admin->email,
                ] : null,
            ])->values()->all(),
            'tasks' => $project->tasks->map(fn (ProjectTask $task): array => self::task($task))->values()->all(),
            'checklists' => $project->checklists->map(fn (ProjectChecklist $item): array => self::checklist($item))->values()->all(),
            'task_checklists' => $project->taskChecklists->sortBy('sort_order')->map(fn (ProjectTaskChecklist $item): array => self::taskChecklist($item))->values()->all(),
            'task_comments' => $project->taskComments->sortByDesc('created_at')->map(fn (ProjectTaskComment $comment): array => self::taskComment($comment))->values()->all(),
            'task_time_entries' => $project->taskTimeEntries->sortByDesc('tracked_at')->map(fn (ProjectTaskTimeEntry $entry): array => self::taskTimeEntry($entry))->values()->all(),
            'files' => $project->files->map(fn (ProjectFile $file): array => self::file($file))->values()->all(),
            'reports' => $project->reports->map(fn (ProjectReport $report): array => self::report($report))->values()->all(),
            'activities' => $project->activities->map(fn (ProjectActivity $activity): array => self::activity($activity))->values()->all(),
        ];
    }

    public static function task(ProjectTask $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'description' => $task->description,
            'task_status_id' => $task->task_status_id,
            'priority_id' => $task->priority_id,
            'assignee_admin_id' => $task->assignee_admin_id,
            'start_date' => $task->start_date?->format('Y-m-d'),
            'due_date' => $task->due_date?->format('Y-m-d'),
            'completed_at' => $task->completed_at?->toAtomString(),
            'sort_order' => $task->sort_order,
            'progress' => $task->progress,
            'status' => $task->status ? [
                'id' => $task->status->id,
                'name' => $task->status->name,
                'color' => $task->status->color,
                'is_done' => $task->status->is_done,
            ] : null,
            'priority' => $task->priority ? [
                'id' => $task->priority->id,
                'name' => $task->priority->name,
                'color' => $task->priority->color,
            ] : null,
            'assignee' => $task->assignee ? [
                'id' => $task->assignee->id,
                'name' => $task->assignee->name,
                'email' => $task->assignee->email,
            ] : null,
            'created_at' => $task->created_at?->toAtomString(),
            'updated_at' => $task->updated_at?->toAtomString(),
        ];
    }

    public static function checklist(ProjectChecklist $item): array
    {
        return [
            'id' => $item->id,
            'project_id' => $item->project_id,
            'title' => $item->title,
            'description' => $item->description,
            'is_completed' => $item->is_completed,
            'assigned_admin_id' => $item->assigned_admin_id,
            'sort_order' => $item->sort_order,
            'assignee' => $item->assignee ? [
                'id' => $item->assignee->id,
                'name' => $item->assignee->name,
                'email' => $item->assignee->email,
            ] : null,
            'created_at' => $item->created_at?->toAtomString(),
            'updated_at' => $item->updated_at?->toAtomString(),
        ];
    }

    public static function file(ProjectFile $file): array
    {
        return [
            'id' => $file->id,
            'project_id' => $file->project_id,
            'task_id' => $file->task_id,
            'title' => $file->title,
            'original_name' => $file->original_name,
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'download_url' => route('admin.api.project.files.download', ['file' => $file->id]),
            'uploader' => $file->uploader ? [
                'id' => $file->uploader->id,
                'name' => $file->uploader->name,
                'email' => $file->uploader->email,
            ] : null,
            'created_at' => $file->created_at?->toAtomString(),
        ];
    }

    public static function taskChecklist(ProjectTaskChecklist $item): array
    {
        return [
            'id' => $item->id,
            'project_id' => $item->project_id,
            'task_id' => $item->task_id,
            'title' => $item->title,
            'description' => $item->description,
            'is_completed' => $item->is_completed,
            'assigned_admin_id' => $item->assigned_admin_id,
            'sort_order' => $item->sort_order,
            'assignee' => $item->assignee ? [
                'id' => $item->assignee->id,
                'name' => $item->assignee->name,
                'email' => $item->assignee->email,
            ] : null,
            'created_at' => $item->created_at?->toAtomString(),
            'updated_at' => $item->updated_at?->toAtomString(),
        ];
    }

    public static function taskComment(ProjectTaskComment $comment): array
    {
        return [
            'id' => $comment->id,
            'project_id' => $comment->project_id,
            'task_id' => $comment->task_id,
            'content' => $comment->content,
            'author' => $comment->author ? [
                'id' => $comment->author->id,
                'name' => $comment->author->name,
                'email' => $comment->author->email,
            ] : null,
            'editor' => $comment->editor ? [
                'id' => $comment->editor->id,
                'name' => $comment->editor->name,
                'email' => $comment->editor->email,
            ] : null,
            'created_at' => $comment->created_at?->toAtomString(),
            'updated_at' => $comment->updated_at?->toAtomString(),
        ];
    }

    public static function taskTimeEntry(ProjectTaskTimeEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'project_id' => $entry->project_id,
            'task_id' => $entry->task_id,
            'tracked_at' => $entry->tracked_at?->toAtomString(),
            'duration_minutes' => $entry->duration_minutes,
            'note' => $entry->note,
            'author' => $entry->author ? [
                'id' => $entry->author->id,
                'name' => $entry->author->name,
                'email' => $entry->author->email,
            ] : null,
            'created_at' => $entry->created_at?->toAtomString(),
            'updated_at' => $entry->updated_at?->toAtomString(),
        ];
    }

    public static function report(ProjectReport $report): array
    {
        return [
            'id' => $report->id,
            'project_id' => $report->project_id,
            'title' => $report->title,
            'report_date' => $report->report_date?->format('Y-m-d'),
            'summary' => $report->summary,
            'content' => $report->content,
            'author' => $report->author ? [
                'id' => $report->author->id,
                'name' => $report->author->name,
                'email' => $report->author->email,
            ] : null,
            'created_at' => $report->created_at?->toAtomString(),
            'updated_at' => $report->updated_at?->toAtomString(),
        ];
    }

    public static function activity(ProjectActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'entity_type' => $activity->entity_type,
            'entity_id' => $activity->entity_id,
            'action' => $activity->action,
            'description' => $activity->description,
            'properties' => $activity->properties ?? [],
            'author' => $activity->author ? [
                'id' => $activity->author->id,
                'name' => $activity->author->name,
                'email' => $activity->author->email,
            ] : null,
            'created_at' => $activity->created_at?->toAtomString(),
        ];
    }
}
