<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'slug', 'status', 'body', 'website_key', 'owner_key', 'tenant_key'])]
class CmsPage extends Model
{
    use HasFactory;

    protected $table = 'cms_pages';
}
