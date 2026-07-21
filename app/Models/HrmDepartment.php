<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'parent_id', 'description', 'is_active'])]
class HrmDepartment extends Model
{
    use HasFactory;

    protected $table = 'hrm_departments';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrmEmployee::class, 'department_id');
    }
}
