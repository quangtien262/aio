<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'location', 'items', 'website_key'])]
class CmsMenu extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_menus';

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
