<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'description', 'project_type_id', 'project_status_id', 'priority_id', 'manager_admin_id', 'start_date', 'due_date', 'completed_at', 'progress', 'color', 'meta'])]
class Project extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'pro__projects';

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'date',
            'meta' => 'array',
        ];
    }

    public function projectType(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(ProjectPriority::class, 'priority_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'manager_admin_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ProjectChecklist::class);
    }

    public function taskChecklists(): HasMany
    {
        return $this->hasMany(ProjectTaskChecklist::class);
    }

    public function taskComments(): HasMany
    {
        return $this->hasMany(ProjectTaskComment::class);
    }

    public function taskTimeEntries(): HasMany
    {
        return $this->hasMany(ProjectTaskTimeEntry::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProjectReport::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class);
    }
}
