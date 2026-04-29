<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'color', 'sort_order', 'is_done', 'is_active'])]
class ProjectTaskStatus extends Model
{
    use HasFactory;

    protected $table = 'pro__task_statuses';

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
