<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const THEME_KEYS = ['TH0001', 'TH0201'];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->deleteRecordedDemoModels();
            $this->deleteLandingPages();

            $this->deleteThemeRows('landing_page_blocks', 'theme_key');
            $this->deleteThemeRows('site_banners', 'theme_key');
            $this->deleteThemeRows('theme_translations', 'theme_key');
            $this->deleteThemeRows('theme_demo_records', 'theme_key');
            $this->deleteThemeRows('theme_installations', 'key');

            if (Schema::hasTable('cms_featured_categories')) {
                DB::table('cms_featured_categories')
                    ->whereIn('name', ['TH0001 Home featured categories'])
                    ->delete();
            }

            if (Schema::hasTable('cms_side_promos')) {
                DB::table('cms_side_promos')
                    ->whereIn('name', ['TH0001 Hero side promos'])
                    ->delete();
            }

            if (Schema::hasTable('sites') && Schema::hasColumn('sites', 'theme_key')) {
                DB::table('sites')
                    ->whereIn('theme_key', self::THEME_KEYS)
                    ->update(['theme_key' => null, 'updated_at' => now()]);
            }

            $this->cleanSiteProfiles();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: removed themes and their generated data
        // must not be recreated by rolling a production database backward.
    }

    private function deleteRecordedDemoModels(): void
    {
        if (! Schema::hasTable('theme_demo_records')) {
            return;
        }

        $records = DB::table('theme_demo_records')
            ->whereIn('theme_key', self::THEME_KEYS)
            ->get(['model_type', 'model_id']);

        $preferredOrder = [
            App\Models\CatalogProduct::class,
            App\Models\CmsPost::class,
            App\Models\LandingPage::class,
            App\Models\CmsPage::class,
            App\Models\CmsMenu::class,
            App\Models\CmsTestimonial::class,
            App\Models\CmsPartner::class,
            App\Models\SiteBanner::class,
            App\Models\CatalogCategory::class,
            App\Models\CmsCategory::class,
        ];
        $modelTypes = collect($preferredOrder)
            ->merge($records->pluck('model_type'))
            ->unique()
            ->values();

        foreach ($modelTypes as $modelType) {
            if (! is_string($modelType) || ! class_exists($modelType)) {
                continue;
            }

            $ids = $records
                ->where('model_type', $modelType)
                ->pluck('model_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($ids === []) {
                continue;
            }

            $modelType::query()
                ->withoutGlobalScopes()
                ->whereKey($ids)
                ->get()
                ->each
                ->delete();
        }
    }

    private function deleteLandingPages(): void
    {
        if (! Schema::hasTable('landing_pages')) {
            return;
        }

        $pageIds = DB::table('landing_pages')
            ->whereIn('theme_key', self::THEME_KEYS)
            ->pluck('id');

        if (
            $pageIds->isNotEmpty()
            && Schema::hasTable('localized_routes')
        ) {
            DB::table('localized_routes')
                ->where('resource_type', 'landing_page')
                ->whereIn('resource_id', $pageIds->map(fn ($id): string => (string) $id))
                ->delete();
        }

        DB::table('landing_pages')
            ->whereIn('theme_key', self::THEME_KEYS)
            ->delete();
    }

    private function cleanSiteProfiles(): void
    {
        if (! Schema::hasTable('site_profiles')) {
            return;
        }

        if (Schema::hasColumn('site_profiles', 'active_theme_key')) {
            DB::table('site_profiles')
                ->whereIn('active_theme_key', self::THEME_KEYS)
                ->update(['active_theme_key' => null, 'updated_at' => now()]);
        }

        if (! Schema::hasColumn('site_profiles', 'theme_palettes')) {
            return;
        }

        DB::table('site_profiles')
            ->whereNotNull('theme_palettes')
            ->orderBy('id')
            ->each(function (object $profile): void {
                $palettes = json_decode((string) $profile->theme_palettes, true);

                if (! is_array($palettes)) {
                    return;
                }

                foreach (array_keys($palettes) as $key) {
                    if (in_array(strtoupper((string) $key), self::THEME_KEYS, true)) {
                        unset($palettes[$key]);
                    }
                }

                DB::table('site_profiles')->where('id', $profile->id)->update([
                    'theme_palettes' => $palettes === []
                        ? null
                        : json_encode($palettes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            });
    }

    private function deleteThemeRows(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, self::THEME_KEYS)->delete();
    }
};
