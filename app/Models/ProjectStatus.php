<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'color', 'sort_order', 'is_active'])]
class ProjectStatus extends Model
{
    use HasFactory;

    protected $table = 'pro__project_statuses';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
