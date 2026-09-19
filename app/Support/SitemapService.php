<?php

namespace App\Support;

use App\Core\Modules\ModuleCapabilityChecker;
use App\Models\{CmsPage, CmsPageTranslation, LandingPage, LandingPageData, LocalizedRoute, Site, SiteProfile};
use App\Support\Localization\{LocaleContext, LocalizedContentRepository};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Cache, DB, Schema};

class SitemapService
{
    private const TYPES = [
        'cms_page' => ['pages', 'cms'], 'landing_page' => ['pages', 'cms'],
        'cms_post' => ['posts', 'cms'], 'cms_category' => ['categories', 'cms'],
        'cms_tag' => ['tags', 'cms'], 'catalog_product' => ['products', 'catalog'],
        'catalog_category' => ['categories', 'catalog'], 'cms_service' => ['services', 'cms'],
        'cms_service_category' => ['categories', 'cms'], 'cms_project' => ['projects', 'cms'],
        'cms_project_category' => ['categories', 'cms'],
    ];

    public function __construct(private LocaleContext $locales, private LocalizedContentRepository $content) {}

    public function baseUrl(string $website): string
    {
        $domain = Site::query()->where('website_key', $website)->where('status', 'active')->orderBy('id')->value('domain');
        if ($domain) {
            return 'https://'.strtolower(trim($domain, '/'));
        }
        return rtrim((string) config('app.url'), '/');
    }

    public function snapshot(string $website, bool $refresh = false): array
    {
        $base = $this->baseUrl($website);
        $key = 'sitemap:v1:'.hash('sha256', $website.'|'.$base.'|'.$this->fingerprint($website));
        if ($refresh) Cache::forget($key);
        // One-minute bound also handles scheduled publication and bulk SQL writes.
        return Cache::remember($key, 60, function () use ($website, $base) {
            $context = app(SiteContext::class);
            $site = $context->site(); $previous = $context->websiteKey();
            $context->set(null, $website);
            try { return $this->build($website, $base); }
            finally { $context->set($site, $previous); }
        });
    }

    private function fingerprint(string $website): string
    {
        $tables = ['localized_routes', 'content_translations', 'cms_pages', 'cms_page_translations',
            'landing_pages', 'landing_page_data', 'landing_page_block_data', 'website_locales', 'sites', 'site_profiles', 'module_installations'];
        foreach (array_keys(self::TYPES) as $type) {
            $class = config("localized-content.resources.$type.model");
            if ($class) $tables[] = (new $class)->getTable();
        }
        $state = [Cache::get('sitemap:revision', ''), $this->locales->publicLocales($website)];
        foreach (array_unique($tables) as $table) {
            if (! Schema::hasTable($table)) continue;
            $query = DB::table($table);
            if (Schema::hasColumn($table, 'website_key')) $query->where('website_key', $website);
            $state[$table] = [$query->count(), Schema::hasColumn($table, 'updated_at') ? $query->max('updated_at') : null];
        }
        return hash('sha256', json_encode($state));
    }

    private function enabled(string $module): bool
    {
        return app(ModuleCapabilityChecker::class)->enabled($module)
            || (app()->runningUnitTests() && ! DB::table('module_installations')->where('key', $module)->exists());
    }

    private function build(string $website, string $base): array
    {
        $locales = $this->locales->publicLocales($website);
        $enabled = ['cms' => $this->enabled('cms'), 'catalog' => $this->enabled('catalog')];
        $entries = [];
        if ($enabled['cms']) {
            $alternates = [];
            foreach ($locales as $locale) $alternates[$locale] = $base.'/'.$locale;
            foreach ($locales as $locale) $entries[$base.'/'.$locale] = [
                'url' => $base.'/'.$locale, 'last_modified' => null, 'group' => 'pages',
                'identity' => 'home', 'locale' => $locale, 'alternates' => $alternates,
            ];
            foreach (['contact', 'c', 's', 'pj'] as $path) {
                foreach ($locales as $locale) {
                    $url = $base.'/'.$locale.'/'.$path;
                    $entries[$url] = ['url' => $url, 'last_modified' => null, 'group' => 'pages',
                        'identity' => 'listing:'.$path, 'locale' => $locale, 'alternates' => []];
                }
            }
        }
        $models = [];
        foreach (LocalizedRoute::withoutGlobalScopes()->where('website_key', $website)
            ->where('is_canonical', true)->where('is_published', true)->whereNull('redirect_to')
            ->whereIn('locale', $locales)->whereIn('resource_type', array_keys(self::TYPES))->orderBy('id')->cursor() as $route) {
            [$group, $module] = self::TYPES[$route->resource_type];
            if (! $enabled[$module] || ! $enabled['cms']) continue;
            $identity = $route->resource_type.':'.$route->resource_id;
            if (! array_key_exists($identity, $models)) {
                if (count($models) >= 200) $models = [];
                $class = match ($route->resource_type) {
                    'cms_page' => CmsPage::class, 'landing_page' => LandingPage::class,
                    default => config('localized-content.resources.'.$route->resource_type.'.model'),
                };
                $models[$identity] = $class && Schema::hasTable((new $class)->getTable())
                    ? $class::withoutGlobalScopes()->where('website_key', $website)->find($route->resource_id) : null;
            }
            $model = $models[$identity];
            if (! $model || ! $this->publicModel($model)) continue;
            $lastmod = $model->updated_at;
            if (in_array($route->resource_type, ['cms_page', 'landing_page'], true)) {
                $class = $route->resource_type === 'cms_page' ? CmsPageTranslation::class : LandingPageData::class;
                $foreignKey = $route->resource_type === 'cms_page' ? 'cms_page_id' : 'landing_page_id';
                $translation = $class::withoutGlobalScopes()->where($foreignKey, $model->id)->where('locale', $route->locale)
                    ->where('translation_status', 'published')->first();
                if (! $translation) continue;
                if ($translation->updated_at > $lastmod) $lastmod = $translation->updated_at;
                if ($route->resource_type === 'landing_page') {
                    $theme = SiteProfile::withoutGlobalScopes()->where('website_key', $website)->value('active_theme_key');
                    if ($model->theme_key !== $theme) continue;
                }
            } else {
                if (! $this->content->isPublishedForLocale($model, $route->resource_type, $route->locale, $website)) continue;
                if ($route->resource_type === 'cms_tag' && (! CmsPostTags::available()
                    || ! app(CmsPostTags::class)->publishedPosts($model, $route->locale)->exists())) continue;
                $translatedAt = DB::table('content_translations')->where('website_key', $website)
                    ->where('resource_type', $route->resource_type)->where('resource_id', $route->resource_id)
                    ->where('locale', $route->locale)->where('translation_status', 'published')->max('updated_at');
                if ($translatedAt && (! $lastmod || $translatedAt > $lastmod)) $lastmod = \Illuminate\Support\Carbon::parse($translatedAt);
            }
            $isHome = $route->resource_type === 'landing_page' && $model->is_home;
            $path = $isHome ? '/' : (string) $route->path;
            if ($isHome) $identity = 'home';
            if (! str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#') || str_starts_with($path, '//')) continue;
            $url = $base.'/'.$route->locale.($path === '/' ? '' : $path);
            $entries[$url] = ['url' => $url, 'group' => $group, 'identity' => $identity, 'locale' => $route->locale,
                'last_modified' => $lastmod?->toAtomString(), 'alternates' => []];
        }
        $alternates = [];
        foreach ($entries as $entry) $alternates[$entry['identity']][$entry['locale']] = $entry['url'];
        ksort($entries);
        $files = [];
        $limit = min(50000, max(1, (int) config('sitemap.urls_per_file', 10000)));
        foreach (collect($entries)->groupBy('group') as $group => $items) {
            $part = 1; $chunk = []; $bytes = 200;
            foreach ($items as $entry) {
                $entry['alternates'] = $alternates[$entry['identity']];
                $entryBytes = strlen(view('sitemaps.entry', compact('entry'))->render());
                if ($chunk && (count($chunk) >= $limit || $bytes + $entryBytes > 49000000)) {
                    $files[$group.'-'.$part++] = $chunk; $chunk = []; $bytes = 200;
                }
                $chunk[] = $entry; $bytes += $entryBytes;
            }
            if ($chunk) $files[$group.'-'.$part] = $chunk;
        }
        return ['base_url' => $base, 'url' => $base.'/sitemap.xml', 'generated_at' => now()->toAtomString(),
            'total' => count($entries), 'files' => $files];
    }

    private function publicModel(Model $model): bool
    {
        $attributes = $model->getAttributes();
        if (isset($attributes['deleted_at'])) return false;
        if (array_key_exists('status', $attributes) && $attributes['status'] !== 'published') return false;
        if (array_key_exists('is_active', $attributes) && ! $attributes['is_active']) return false;
        foreach (['publish_at', 'published_at'] as $field) {
            if (! empty($attributes[$field]) && \Illuminate\Support\Carbon::parse($attributes[$field])->isFuture()) return false;
        }
        return true;
    }
}
