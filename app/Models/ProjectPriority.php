<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'color', 'sort_order'])]
class ProjectPriority extends Model
{
    use HasFactory;

    protected $table = 'pro__priorities';
}
