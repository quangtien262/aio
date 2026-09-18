<?php

namespace App\Models;

use App\Models\Concerns\HasWebsiteScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'file_path', 'file_url', 'mime_type', 'size', 'alt_text', 'folder_path', 'website_key'])]
class CmsMedia extends Model
{
    use HasFactory;
    use HasWebsiteScope;

    protected $table = 'cms_media';

    public static function buildPublicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        $configuredUrl = (string) config('filesystems.disks.public.url', '/storage');
        $basePath = parse_url($configuredUrl, PHP_URL_PATH) ?: '/storage';

        return url(rtrim($basePath, '/').'/'.ltrim($path, '/'));
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
