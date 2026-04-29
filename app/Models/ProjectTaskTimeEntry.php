<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'task_id', 'tracked_by_admin_id', 'tracked_at', 'duration_minutes', 'note'])]
class ProjectTaskTimeEntry extends Model
{
    use HasFactory;

    protected $table = 'pro__task_time_entries';

    protected function casts(): array
    {
        return [
            'tracked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'tracked_by_admin_id');
    }
}
