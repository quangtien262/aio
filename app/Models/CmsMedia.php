<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'file_path', 'file_url', 'mime_type', 'size', 'alt_text', 'website_key', 'owner_key', 'tenant_key'])]
class CmsMedia extends Model
{
    use HasFactory;

    protected $table = 'cms_media';
}
