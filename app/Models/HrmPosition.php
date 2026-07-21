<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class HrmPosition extends Model
{
    use HasFactory;

    protected $table = 'hrm_positions';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(HrmEmployee::class, 'position_id');
    }
}
