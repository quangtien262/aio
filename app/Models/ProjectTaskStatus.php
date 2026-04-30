<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'name', 'color', 'sort_order', 'is_done', 'is_active'])]
class ProjectTaskStatus extends Model
{
    use HasFactory;

    protected $table = 'pro__task_statuses';

    protected function casts(): array
    {
        return [
            'project_id' => 'integer',
            'is_done' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'task_status_id');
    }
}
