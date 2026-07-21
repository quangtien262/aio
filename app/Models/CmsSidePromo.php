<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsSidePromo extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_side_promos';

    protected $fillable = [
        'name',
        'location',
        'items',
        'website_key',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
