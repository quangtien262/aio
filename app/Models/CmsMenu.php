<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'location', 'items'])]
class CmsMenu extends Model
{
    use HasFactory;

    protected $table = 'cms_menus';

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
