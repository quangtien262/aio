<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'path', 'sort_order', 'website_key'])]
class CmsMediaFolder extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_media_folders';

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
