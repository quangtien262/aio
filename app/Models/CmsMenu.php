<?php

namespace App\Models;

use App\Core\Cms\CmsMenuItemKeyNormalizer;
use App\Core\Cms\CmsMenuLinkRegistry;
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

    protected static function booted(): void
    {
        static::saving(function (CmsMenu $menu): void {
            if (! $menu->isDirty('items')) {
                return;
            }

            $rawOriginalItems = $menu->exists
                ? $menu->getRawOriginal('items')
                : null;
            $originalItems = is_array($rawOriginalItems)
                ? $rawOriginalItems
                : json_decode((string) $rawOriginalItems, true);

            $menu->items = app(CmsMenuLinkRegistry::class)->normalize(
                app(CmsMenuItemKeyNormalizer::class)->normalize(
                    is_array($menu->items) ? $menu->items : [],
                    is_array($originalItems) ? $originalItems : [],
                ),
            );
        });
    }
}
