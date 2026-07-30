<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const THEME_KEY = 'SER0100';

    public function up(): void
    {
        DB::transaction(function (): void {
            $activeProfileIds = $this->activeProfileIds();

            $this->deleteRecordedDemoModels();
            $this->deleteLandingPages();

            foreach ([
                ['landing_page_blocks', 'theme_key'],
                ['site_banners', 'theme_key'],
                ['theme_translations', 'theme_key'],
                ['theme_demo_records', 'theme_key'],
                ['theme_installations', 'key'],
                ['site_theme_profiles', 'theme_key'],
            ] as [$table, $column]) {
                $this->deleteThemeRows($table, $column);
            }

            if (Schema::hasTable('theme_translations') && Schema::hasColumn('theme_translations', 'translation_key')) {
                DB::table('theme_translations')
                    ->where('translation_key', 'like', 'theme_block.ser0100.%')
                    ->delete();
            }

            if (Schema::hasTable('sites') && Schema::hasColumn('sites', 'theme_key')) {
                DB::table('sites')
                    ->where('theme_key', self::THEME_KEY)
                    ->update(['theme_key' => null, 'updated_at' => now()]);
            }

            $this->cleanSiteProfiles($activeProfileIds);
        });
    }

    public function down(): void
    {
        // Intentionally irreversible: source files and theme-owned data were removed.
    }

    private function activeProfileIds(): array
    {
        if (! Schema::hasTable('site_profiles') || ! Schema::hasColumn('site_profiles', 'active_theme_key')) {
            return [];
        }

        return DB::table('site_profiles')
            ->where('active_theme_key', self::THEME_KEY)
            ->pluck('id')
            ->all();
    }

    private function deleteRecordedDemoModels(): void
    {
        if (! Schema::hasTable('theme_demo_records')) {
            return;
        }

        $columns = ['model_type', 'model_id'];

        if (Schema::hasColumn('theme_demo_records', 'website_key')) {
            $columns[] = 'website_key';
        }

        $records = DB::table('theme_demo_records')
            ->where('theme_key', self::THEME_KEY)
            ->get($columns);

        $resourceTypes = [
            App\Models\CatalogCategory::class => 'catalog_category',
            App\Models\CatalogProduct::class => 'catalog_product',
            App\Models\CmsCategory::class => 'cms_category',
            App\Models\CmsMenu::class => 'cms_menu',
            App\Models\CmsPartner::class => 'cms_partner',
            App\Models\CmsPost::class => 'cms_post',
            App\Models\CmsProject::class => 'cms_project',
            App\Models\CmsProjectCategory::class => 'cms_project_category',
            App\Models\CmsService::class => 'cms_service',
            App\Models\CmsServiceCategory::class => 'cms_service_category',
            App\Models\CmsTeamMember::class => 'cms_team_member',
            App\Models\CmsTestimonial::class => 'cms_testimonial',
            App\Models\SiteBanner::class => 'site_banner',
        ];

        if (Schema::hasTable('content_translations')) {
            foreach ($records as $record) {
                $resourceType = $resourceTypes[$record->model_type] ?? null;

                if ($resourceType === null) {
                    continue;
                }

                DB::table('content_translations')
                    ->where('resource_type', $resourceType)
                    ->where('resource_id', (string) $record->model_id)
                    ->when(
                        isset($record->website_key),
                        fn ($query) => $query->where('website_key', $record->website_key),
                    )
                    ->delete();
            }
        }

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

        foreach (collect($preferredOrder)->merge($records->pluck('model_type'))->unique() as $modelType) {
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

            if ($ids !== []) {
                $modelType::query()->withoutGlobalScopes()->whereKey($ids)->get()->each->delete();
            }
        }
    }

    private function deleteLandingPages(): void
    {
        if (! Schema::hasTable('landing_pages')) {
            return;
        }

        $pageIds = DB::table('landing_pages')
            ->where('theme_key', self::THEME_KEY)
            ->pluck('id');

        if ($pageIds->isNotEmpty() && Schema::hasTable('localized_routes')) {
            DB::table('localized_routes')
                ->where('resource_type', 'landing_page')
                ->whereIn('resource_id', $pageIds->map(fn ($id): string => (string) $id))
                ->delete();
        }

        if ($pageIds->isNotEmpty() && Schema::hasTable('content_translations')) {
            DB::table('content_translations')
                ->where('resource_type', 'landing_page')
                ->whereIn('resource_id', $pageIds->map(fn ($id): string => (string) $id))
                ->delete();
        }

        DB::table('landing_pages')->where('theme_key', self::THEME_KEY)->delete();
    }

    private function cleanSiteProfiles(array $activeProfileIds): void
    {
        if (! Schema::hasTable('site_profiles')) {
            return;
        }

        if ($activeProfileIds !== []) {
            DB::table('site_profiles')
                ->select(['id', 'branding'])
                ->whereIn('id', $activeProfileIds)
                ->orderBy('id')
                ->each(function (object $profile): void {
                    $branding = json_decode((string) ($profile->branding ?? ''), true);

                    if (is_array($branding)) {
                        foreach (['demo_preset_key', 'demo_preset_label', 'demo_preset_description'] as $key) {
                            unset($branding[$key]);
                        }
                    }

                    DB::table('site_profiles')->where('id', $profile->id)->update([
                        'active_theme_key' => null,
                        'branding' => is_array($branding)
                            ? json_encode($branding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : $profile->branding,
                        'updated_at' => now(),
                    ]);
                });
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
                    if (strcasecmp((string) $key, self::THEME_KEY) === 0) {
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
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->where($column, self::THEME_KEY)->delete();
        }
    }
};
