<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'title', 'description', 'task_status_id', 'priority_id', 'assignee_admin_id', 'created_by_admin_id', 'start_date', 'due_date', 'completed_at', 'sort_order', 'progress'])]
class ProjectTask extends Model
{
    use HasFactory;

    protected $table = 'pro__tasks';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectTaskStatus::class, 'task_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(ProjectPriority::class, 'priority_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assignee_admin_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function taskChecklists(): HasMany
    {
        return $this->hasMany(ProjectTaskChecklist::class, 'task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class, 'task_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProjectTaskTimeEntry::class, 'task_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'task_id');
    }
}
