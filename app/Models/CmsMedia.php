<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'file_path', 'file_url', 'mime_type', 'size', 'alt_text'])]
class CmsMedia extends Model
{
    use HasFactory;

    protected $table = 'cms_media';

    public static function buildPublicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $baseUrl = rtrim((string) config('filesystems.disks.public.url', url('/storage')), '/');

        return $baseUrl.'/'.ltrim($path, '/');
    }

    public function getFileUrlAttribute(?string $value): ?string
    {
        $filePath = $this->attributes['file_path'] ?? null;

        if (blank($filePath)) {
            return $value;
        }

        return static::buildPublicUrl($filePath);
    }
}
