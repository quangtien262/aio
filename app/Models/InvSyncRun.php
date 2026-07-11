<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source', 'created_count', 'updated_count', 'skipped_count', 'failed_count', 'created_by_admin_id', 'started_at', 'finished_at'])]
class InvSyncRun extends Model
{
    protected $table = 'inv_sync_runs';

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvSyncRunLine::class, 'sync_run_id');
    }
}
