<?php

namespace App\Support;

use App\Enums\TranslationStatus;
use App\Support\ThemeBlockRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsFeaturedCategory;
use App\Models\CmsSidePromo;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\CmsTeamMember;
use App\Models\CmsTestimonial;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Models\ThemeTranslation;
use App\Support\Localization\TranslationRevision;
use App\Support\Localization\CmsPageLocalization;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\LocalizationRollout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class BusinessContentTranslationService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly ThemeBlockRegistry $themeBlockRegistry,
        private readonly LocalizedContentRepository $localizedContent,
        private readonly LocalizationRollout $localizationRollout,
        private readonly CmsPageLocalization $cmsPageLocalization,
    ) {}

    public function editableEntries(string $websiteKey, string $locale, ?string $themeKey = null): array
    {
        $resolvedLocale = FrontendLocalization::resolveEditableLocale($locale);
        $defaults = $this->defaultEntries($websiteKey, $themeKey);
        $overrides = $this->overrides($websiteKey, $resolvedLocale);

        return collect($defaults)
            ->map(function (array $entry) use ($overrides, $resolvedLocale, $websiteKey): array {
                $defaultValue = $this->localizedDefaultValue((string) ($entry['source_value'] ?? ''), $resolvedLocale);

                return [
                    'key' => $entry['key'],
                    'group' => 'content',
                    'theme_key' => $this->contentThemeKey($websiteKey),
                    'locale' => $resolvedLocale,
                    'label' => $entry['label'],
                    'source_value' => $entry['source_value'],
                    'default_value' => $defaultValue,
                    'override_value' => $overrides[$entry['key']] ?? null,
                    'effective_value' => $overrides[$entry['key']] ?? $defaultValue,
                ];
            })
            ->sortBy('key')
            ->values()
            ->all();
    }

    public function text(string $websiteKey, string $key, ?string $default): string
    {
        $value = (string) ($default ?? '');

        if ($value === '') {
            return '';
        }

        $locale = FrontendLocalization::resolveLocale(app()->getLocale());
        $canonicalKey = $this->canonicalContentKey($websiteKey, $key);
        $localizedValue = $this->localizedContent->textByKey(
            $websiteKey,
            $locale,
            $canonicalKey,
        );

        if ($localizedValue !== null) {
            return $localizedValue;
        }

        if ($locale === FrontendLocalization::fallbackLocale()) {
            return $value;
        }

        $overrides = $this->overrides($websiteKey, $locale, true);

        if (array_key_exists($key, $overrides) && trim((string) $overrides[$key]) !== '') {
            return (string) $overrides[$key];
        }

        return $this->localizedDefaultValue($value, $locale);
    }

    public function saveOverrides(string $websiteKey, string $locale, array $entries, ?string $themeKey = null): void
    {
        $resolvedLocale = FrontendLocalization::resolveEditableLocale($locale);
        $baseline = collect($this->editableEntries($websiteKey, $resolvedLocale, $themeKey))
            ->mapWithKeys(fn (array $entry): array => [$entry['key'] => $entry['default_value']])
            ->all();

        foreach ($entries as $entry) {
            $key = trim((string) ($entry['key'] ?? ''));

            if ($key === '') {
                continue;
            }

            $value = trim((string) ($entry['value'] ?? ''));
            $defaultValue = trim((string) ($baseline[$key] ?? ''));
            $canonicalKey = $this->canonicalContentKey($websiteKey, $key);

            if ($value !== '' && $value !== $defaultValue) {
                if (! $this->saveCmsPageField($websiteKey, $resolvedLocale, $key, $value)) {
                    $this->localizedContent->savePublishedFieldByKey(
                        $websiteKey,
                        $resolvedLocale,
                        $canonicalKey,
                        $value,
                    );
                }
            }

            if ($value === '' || $value === $defaultValue) {
                if (! $this->saveCmsPageField($websiteKey, $resolvedLocale, $key, null)) {
                    $this->localizedContent->clearFieldByKey(
                        $websiteKey,
                        $resolvedLocale,
                        $canonicalKey,
                    );
                }

                ThemeTranslation::query()
                    ->withoutGlobalScope('current_website')
                    ->where('theme_key', $this->contentThemeKey($websiteKey))
                    ->where('locale', $resolvedLocale)
                    ->where('group', 'content')
                    ->where('translation_key', $key)
                    ->delete();

                continue;
            }

            if (! $this->localizationRollout->legacyFallbackEnabled()) {
                continue;
            }

            ThemeTranslation::query()
                ->withoutGlobalScope('current_website')
                ->updateOrCreate(
                [
                    'theme_key' => $this->contentThemeKey($websiteKey),
                    'locale' => $resolvedLocale,
                    'group' => 'content',
                    'translation_key' => $key,
                ],
                [
                    'website_key' => $websiteKey,
                    'value' => $value,
                    'translation_status' => TranslationStatus::Published,
                    'translation_revision' => TranslationRevision::fingerprint(['value' => $value]),
                    'is_machine_translated' => false,
                    'translated_at' => now(),
                    'translation_published_at' => now(),
                ],
            );
        }

        Cache::forget($this->cacheKey($websiteKey, $resolvedLocale, false));
        Cache::forget($this->cacheKey($websiteKey, $resolvedLocale, true));
    }

    private function canonicalContentKey(string $websiteKey, string $key): string
    {
        $profile = SiteProfile::query()->forWebsite($websiteKey)->first();

        if ($profile !== null && preg_match('/^site_profile\.(.+)$/', $key, $matches)) {
            return sprintf('site_profile.%s.%s', $profile->id, $matches[1]);
        }

        if ($profile !== null && preg_match('/^branding\.(.+)$/', $key, $matches)) {
            return sprintf('site_profile.%s.branding.%s', $profile->id, $matches[1]);
        }

        if (preg_match('/^cms_menu\.([^.]+)\.(.+)$/', $key, $matches)) {
            $menu = CmsMenu::query()
                ->forWebsite($websiteKey)
                ->where('location', $matches[1])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($menu !== null) {
                return sprintf('cms_menu.%s.items.%s', $menu->id, $matches[2]);
            }
        }

        return $key;
    }

    private function saveCmsPageField(
        string $websiteKey,
        string $locale,
        string $key,
        ?string $value,
    ): bool {
        if (! preg_match('/^cms_page\.(\d+)\.([a-z_]+)$/', $key, $matches)) {
            return false;
        }

        $field = $matches[2];

        if (! in_array($field, CmsPageLocalization::TRANSLATABLE_FIELDS, true)) {
            return false;
        }

        $page = CmsPage::query()
            ->forWebsite($websiteKey)
            ->with('translations')
            ->find($matches[1]);

        if ($page === null) {
            return false;
        }

        $sourceLocale = FrontendLocalization::sourceLocale();
        $source = $page->translations->firstWhere('locale', $sourceLocale);
        $existing = $page->translations->firstWhere('locale', $locale);
        $payload = collect(CmsPageLocalization::TRANSLATABLE_FIELDS)
            ->mapWithKeys(fn (string $item): array => [
                $item => $existing?->getAttribute($item)
                    ?? $source?->getAttribute($item)
                    ?? $page->getAttribute($item),
            ])
            ->all();
        $payload[$field] = $value
            ?? $source?->getAttribute($field)
            ?? $page->getAttribute($field);

        $translation = $this->cmsPageLocalization->saveDraft(
            $page,
            $locale,
            $payload,
        );
        $translation = $this->cmsPageLocalization->transition(
            $page,
            $locale,
            TranslationStatus::Ready,
        );
        $this->cmsPageLocalization->transition(
            $page,
            $locale,
            TranslationStatus::Published,
        );

        return true;
    }

    private function overrides(string $websiteKey, string $locale, bool $publishedOnly = false): array
    {
        return Cache::remember(
            $this->cacheKey($websiteKey, $locale, $publishedOnly),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($websiteKey, $locale, $publishedOnly): array {
                return ThemeTranslation::query()
                    ->withoutGlobalScope('current_website')
                    ->where('theme_key', $this->contentThemeKey($websiteKey))
                    ->where('locale', $locale)
                    ->where('group', 'content')
                    ->when($publishedOnly, fn ($query) => $query->publishedTranslation())
                    ->pluck('value', 'translation_key')
                    ->all();
            },
        );
    }

    private function defaultEntries(string $websiteKey, ?string $themeKey = null): array
    {
        return Cache::remember(
            $this->defaultEntriesCacheKey($websiteKey, $themeKey),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): array => $this->buildDefaultEntries($websiteKey, $themeKey),
        );
    }

    private function buildDefaultEntries(string $websiteKey, ?string $themeKey = null): array
    {
        $entries = collect();
        $siteProfile = SiteProfile::query()->first();
        $branding = $siteProfile?->branding ?? [];

        if ($siteProfile) {
            $entries->push([
                'key' => 'site_profile.site_name',
                'label' => 'Site profile / Site name',
                'source_value' => (string) $siteProfile->site_name,
            ]);
        }

        foreach ([
            'company_name' => 'Site profile / Company name',
            'slogan' => 'Site profile / Slogan',
            'support_location' => 'Site profile / Support location',
        ] as $field => $label) {
            if (filled($branding[$field] ?? null)) {
                $entries->push([
                    'key' => sprintf('branding.%s', $field),
                    'label' => $label,
                    'source_value' => (string) $branding[$field],
                ]);
            }
        }

        CmsMenu::query()
            ->orderBy('location')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('location')
            ->each(function (CmsMenu $menu) use ($entries): void {
                collect($menu->items ?? [])->values()->each(function (array $item, int $index) use ($entries, $menu): void {
                    $entries->push([
                        'key' => sprintf('cms_menu.%s.%d.label', $menu->location, $index),
                        'label' => sprintf('Menu / %s / Item %d', $menu->location, $index + 1),
                        'source_value' => (string) ($item['label'] ?? ''),
                    ]);

                    collect($item['children'] ?? [])->values()->each(function (array $child, int $childIndex) use ($entries, $menu, $index): void {
                        $entries->push([
                            'key' => sprintf('cms_menu.%s.%d.children.%d.label', $menu->location, $index, $childIndex),
                            'label' => sprintf('Menu / %s / Item %d / Child %d', $menu->location, $index + 1, $childIndex + 1),
                            'source_value' => (string) ($child['label'] ?? ''),
                        ]);
                    });
                });
            });

        CmsFeaturedCategory::query()->orderBy('location')->orderBy('id')->get()->each(function (CmsFeaturedCategory $group) use ($entries): void {
            collect($group->items ?? [])->values()->each(function (array $item, int $index) use ($entries, $group): void {
                $entries->push([
                    'key' => sprintf('cms_featured_category.%s.%d.label', $group->location, $index),
                    'label' => sprintf('Featured category / %s / Item %d', $group->location, $index + 1),
                    'source_value' => (string) ($item['label'] ?? ''),
                ]);
            });
        });

        CmsSidePromo::query()->orderBy('location')->orderBy('id')->get()->each(function (CmsSidePromo $group) use ($entries): void {
            collect($group->items ?? [])->values()->each(function (array $item, int $index) use ($entries, $group): void {
                foreach ([
                    'badge' => sprintf('Side promo / %s / Item %d / Badge', $group->location, $index + 1),
                    'title' => sprintf('Side promo / %s / Item %d / Title', $group->location, $index + 1),
                    'subtitle' => sprintf('Side promo / %s / Item %d / Subtitle', $group->location, $index + 1),
                    'cta_label' => sprintf('Side promo / %s / Item %d / CTA', $group->location, $index + 1),
                ] as $field => $label) {
                    if (! filled($item[$field] ?? null)) {
                        continue;
                    }

                    $entries->push([
                        'key' => sprintf('cms_side_promo.%s.%d.%s', $group->location, $index, $field),
                        'label' => $label,
                        'source_value' => (string) $item[$field],
                    ]);
                }
            });
        });

        SiteBanner::query()->orderBy('placement')->orderBy('sort_order')->orderBy('id')->get()->each(function (SiteBanner $banner) use ($entries): void {
            foreach ([
                'title' => $banner->title,
                'subtitle' => $banner->subtitle,
                'badge' => $banner->badge,
                'metadata.eyebrow' => data_get($banner->metadata, 'eyebrow'),
                'metadata.summary' => data_get($banner->metadata, 'summary'),
                'metadata.button_label' => data_get($banner->metadata, 'button_label'),
            ] as $field => $value) {
                if (! filled($value)) {
                    continue;
                }

                $entries->push([
                    'key' => sprintf('site_banner.%d.%s', $banner->id, $field),
                    'label' => sprintf('Banner / %s / %s', $banner->placement, $field),
                    'source_value' => (string) $value,
                ]);
            }
        });

        CatalogCategory::query()->orderBy('parent_id')->orderBy('sort_order')->orderBy('id')->get()->each(function (CatalogCategory $category) use ($entries): void {
            $entries->push([
                'key' => sprintf('catalog_category.%d.name', $category->id),
                'label' => sprintf('Category / %s / Name', $category->slug),
                'source_value' => (string) $category->name,
            ]);

            if (filled($category->description)) {
                $entries->push([
                    'key' => sprintf('catalog_category.%d.description', $category->id),
                    'label' => sprintf('Category / %s / Description', $category->slug),
                    'source_value' => (string) $category->description,
                ]);
            }
        });

        CatalogProduct::query()->orderBy('id')->get()->each(function (CatalogProduct $product) use ($entries): void {
            foreach ([
                'name' => $product->name,
                'short_description' => $product->short_description,
                'detail_content' => $product->detail_content,
                'highlights' => $product->highlights,
                'usage_terms' => $product->usage_terms,
                'usage_location' => $product->usage_location,
            ] as $field => $value) {
                if (! filled($value)) {
                    continue;
                }

                $entries->push([
                    'key' => sprintf('catalog_product.%d.%s', $product->id, $field),
                    'label' => sprintf('Product / %s / %s', $product->slug ?: $product->id, $field),
                    'source_value' => (string) $value,
                ]);
            }
        });

        CmsPage::query()->orderBy('id')->get()->each(function (CmsPage $page) use ($entries): void {
            foreach ([
                'title' => $page->title,
                'excerpt' => $page->excerpt,
                'body' => $page->body,
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
            ] as $field => $value) {
                if (! filled($value)) {
                    continue;
                }

                $entries->push([
                    'key' => sprintf('cms_page.%d.%s', $page->id, $field),
                    'label' => sprintf('CMS page / %s / %s', $page->slug ?: $page->id, $field),
                    'source_value' => (string) $value,
                ]);
            }
        });

        CmsCategory::query()->orderBy('id')->get()->each(function (CmsCategory $category) use ($entries): void {
            foreach ([
                'name' => $category->name,
                'description' => $category->description,
                'meta_title' => $category->meta_title,
                'meta_description' => $category->meta_description,
            ] as $field => $value) {
                if (! filled($value)) {
                    continue;
                }

                $entries->push([
                    'key' => sprintf('cms_category.%d.%s', $category->id, $field),
                    'label' => sprintf('CMS category / %s / %s', $category->slug ?: $category->id, $field),
                    'source_value' => (string) $value,
                ]);
            }
        });

        CmsPost::query()->orderBy('id')->get()->each(function (CmsPost $post) use ($entries): void {
            foreach ([
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'meta_title' => $post->meta_title,
                'meta_description' => $post->meta_description,
                'meta_keywords' => $post->meta_keywords,
            ] as $field => $value) {
                if (! filled($value)) {
                    continue;
                }

                $entries->push([
                    'key' => sprintf('cms_post.%d.%s', $post->id, $field),
                    'label' => sprintf('CMS post / %s / %s', $post->slug ?: $post->id, $field),
                    'source_value' => (string) $value,
                ]);
            }
        });

        if (Schema::hasTable('cms_services')) {
            CmsService::query()->orderBy('id')->get()->each(function (CmsService $service) use ($entries): void {
                foreach ([
                    'title' => $service->title,
                    'summary' => $service->summary,
                    'content' => $service->content,
                    'button_label' => $service->button_label,
                    'meta_title' => $service->meta_title,
                    'meta_description' => $service->meta_description,
                ] as $field => $value) {
                    if (! filled($value)) {
                        continue;
                    }

                    $entries->push([
                        'key' => sprintf('cms_service.%d.%s', $service->id, $field),
                        'label' => sprintf('CMS service / %s / %s', $service->slug ?: $service->id, $field),
                        'source_value' => (string) $value,
                    ]);
                }
            });
        }

        if (Schema::hasTable('cms_projects')) {
            CmsProject::query()->orderBy('id')->get()->each(function (CmsProject $project) use ($entries): void {
                foreach ([
                    'title' => $project->title,
                    'summary' => $project->summary,
                    'content' => $project->content,
                    'button_label' => $project->button_label,
                    'meta_title' => $project->meta_title,
                    'meta_description' => $project->meta_description,
                ] as $field => $value) {
                    if (! filled($value)) {
                        continue;
                    }

                    $entries->push([
                        'key' => sprintf('cms_project.%d.%s', $project->id, $field),
                        'label' => sprintf('CMS project / %s / %s', $project->slug ?: $project->id, $field),
                        'source_value' => (string) $value,
                    ]);
                }
            });
        }

        if (Schema::hasTable('cms_testimonials')) {
            CmsTestimonial::query()->orderBy('id')->get()->each(function (CmsTestimonial $testimonial) use ($entries): void {
                foreach ([
                    'name' => $testimonial->name,
                    'role' => $testimonial->role,
                    'company' => $testimonial->company,
                    'quote' => $testimonial->quote,
                ] as $field => $value) {
                    if (! filled($value)) {
                        continue;
                    }

                    $entries->push([
                        'key' => sprintf('cms_testimonial.%d.%s', $testimonial->id, $field),
                        'label' => sprintf('CMS testimonial / %s / %s', $testimonial->name ?: $testimonial->id, $field),
                        'source_value' => (string) $value,
                    ]);
                }
            });
        }

        if (Schema::hasTable('cms_team_members')) {
            CmsTeamMember::query()->orderBy('id')->get()->each(function (CmsTeamMember $member) use ($entries): void {
                foreach ([
                    'name' => $member->name,
                    'role' => $member->role,
                    'department' => $member->department,
                    'summary' => $member->summary,
                    'bio' => $member->bio,
                ] as $field => $value) {
                    if (! filled($value)) {
                        continue;
                    }

                    $entries->push([
                        'key' => sprintf('cms_team_member.%d.%s', $member->id, $field),
                        'label' => sprintf('CMS team member / %s / %s', $member->slug ?: $member->id, $field),
                        'source_value' => (string) $value,
                    ]);
                }
            });
        }

        if (Schema::hasTable('cms_partners')) {
            CmsPartner::query()->orderBy('id')->get()->each(function (CmsPartner $partner) use ($entries): void {
                foreach ([
                    'title' => $partner->title,
                    'description' => $partner->description,
                ] as $field => $value) {
                    if (! filled($value)) {
                        continue;
                    }

                    $entries->push([
                        'key' => sprintf('cms_partner.%d.%s', $partner->id, $field),
                        'label' => sprintf('CMS partner / %s / %s', $partner->slug ?: $partner->id, $field),
                        'source_value' => (string) $value,
                    ]);
                }
            });
        }

        foreach ($this->themeBlockRegistry->editableEntries((string) $themeKey, $websiteKey) as $entry) {
            $entries->push($entry);
        }

        return $entries
            ->filter(fn (array $entry): bool => trim((string) ($entry['source_value'] ?? '')) !== '')
            ->unique('key')
            ->values()
            ->all();
    }

    private function localizedDefaultValue(string $value, string $locale): string
    {
        if ($locale === FrontendLocalization::fallbackLocale()) {
            return $value;
        }

        return $this->autoTranslate($value);
    }

    private function contentThemeKey(string $websiteKey): string
    {
        return 'site-content:'.strtolower(trim($websiteKey) !== '' ? $websiteKey : 'default');
    }

    private function cacheKey(string $websiteKey, string $locale, bool $publishedOnly = false): string
    {
        return sprintf(
            'business-content-translations:%s:%s:%s',
            strtolower($websiteKey),
            strtolower($locale),
            $publishedOnly ? 'public' : 'editor',
        );
    }

    private function defaultEntriesCacheKey(string $websiteKey, ?string $themeKey = null): string
    {
        return sprintf(
            'business-content-default-entries:%s:%s:%s',
            strtolower($websiteKey),
            strtolower(trim((string) $themeKey)) ?: 'all',
            $this->defaultEntriesSignature(),
        );
    }

    private function defaultEntriesSignature(): string
    {
        $models = [
            SiteProfile::class,
            CmsMenu::class,
            SiteBanner::class,
            CatalogCategory::class,
            CatalogProduct::class,
            CmsPage::class,
            CmsCategory::class,
            CmsPost::class,
            CmsService::class,
            CmsProject::class,
            CmsTestimonial::class,
            CmsTeamMember::class,
            CmsPartner::class,
        ];

        $signature = collect($models)
            ->map(fn (string $modelClass): string => $this->modelSignature($modelClass))
            ->implode('|');

        return substr(sha1($signature), 0, 12);
    }

    private function modelSignature(string $modelClass): string
    {
        /** @var Model $model */
        $model = new $modelClass();
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            return $table.':missing';
        }

        $query = $modelClass::query();

        $count = (int) (clone $query)->count();
        $maxId = Schema::hasColumn($table, 'id') ? (string) ((clone $query)->max('id') ?? 'null') : 'no-id';
        $updatedAt = Schema::hasColumn($table, 'updated_at') ? (string) ((clone $query)->max('updated_at') ?? 'null') : 'no-updated-at';

        return sprintf('%s:%d:%s:%s', $table, $count, $maxId, $updatedAt);
    }

    private function autoTranslate(string $value): string
    {
        $translated = preg_replace_callback(
            '/^Danh mục (.+) cho preset (.+)$/m',
            fn (array $matches): string => 'Category '.$this->autoTranslate($matches[1]).' for the '.$this->autoTranslate($matches[2]).' preset',
            $value,
        ) ?? $value;

        $translated = preg_replace_callback(
            '/^Nhóm (.+) thuộc (.+)$/m',
            fn (array $matches): string => $this->autoTranslate($matches[1]).' group in '.$this->autoTranslate($matches[2]),
            $translated,
        ) ?? $translated;

        $translated = preg_replace_callback(
            '/^Mẫu demo cho (.+) trong preset (.+)\.$/m',
            fn (array $matches): string => 'Demo item for '.$this->autoTranslate($matches[1]).' in the '.$this->autoTranslate($matches[2]).' preset.',
            $translated,
        ) ?? $translated;

        $translated = preg_replace_callback(
            '/^Ưu đãi mới cho (.+)$/m',
            fn (array $matches): string => 'Fresh deals for '.$this->autoTranslate($matches[1]),
            $translated,
        ) ?? $translated;

        $translated = preg_replace('/Giảm đến\s+(\d+)%/u', 'Up to $1% off', $translated) ?? $translated;
        $translated = preg_replace('/Chỉ từ\s+([0-9\.]+K)/u', 'From $1', $translated) ?? $translated;

        $replacements = [
            ' là dữ liệu demo được sinh cho preset ' => ' is demo data generated for the ',
            ', giúp kiểm thử đầy đủ luồng hiển thị trang chi tiết sản phẩm theo phong cách deal page.' => ' preset, built to validate the full product detail flow in a deal-page layout.',
            'Sản phẩm thuộc nhóm ' => 'This product belongs to the ',
            ' trong ngành ' => ' line in the ',
            ', vì vậy phần nội dung dài được thiết kế để hiển thị đẹp ở các block mô tả, điều kiện sử dụng và vị trí áp dụng trên theme AIO Commerce.' => ' category, so the long-form content is written to display cleanly across the description, usage terms, and usage location blocks in theme AIO Commerce.',
            'Sếp có thể sửa trực tiếp phần mô tả này, gallery ảnh, số lượng đã mua và thời gian kết thúc deal trong admin Catalog để biến trang từ demo thành nội dung vận hành thật.' => 'You can edit this description, the gallery, sold count, and deal end time in Catalog admin to turn the demo page into live storefront content.',
            'Ưu đãi nổi bật cho nhóm ' => 'Featured deals for the ',
            ' thuộc ngành ' => ' line in the ',
            'Phù hợp để test bố cục deal nhiều khối như banner, card và trang detail.' => 'Useful for testing a deal layout with multiple blocks such as banners, cards, and the product detail page.',
            'Có thể dùng ngay để review gallery nhiều ảnh và nội dung dài của theme ' => 'Ready to use for reviewing multi-image galleries and long-form content in theme ',
            'Thời hạn ưu đãi linh hoạt theo chiến dịch của ' => 'Offer timing varies by campaign from ',
            'Khuyến nghị liên hệ trước để xác nhận tình trạng áp dụng cho nhóm ' => 'Please contact us first to confirm availability for the ',
            'Vui lòng cung cấp mã SKU khi cần CSKH xử lý đơn hoặc hậu mãi nhanh.' => 'Please provide the SKU when you need customer support for order handling or after-sales support.',
            'Không áp dụng đồng thời với các chương trình giảm giá nội bộ khác nếu không có ghi chú riêng.' => 'Cannot be combined with other in-house promotions unless stated otherwise.',
            'Preset cho showroom điện thoại, phụ kiện và dịch vụ bảo hành mở rộng.' => 'Preset for a mobile showroom with accessories and extended protection services.',
            'Hồ sơ năng lực demo cho ' => 'Demo capability profile for ',
            'Website demo này được tạo để review theme showroom mobile và khả năng mapping dữ liệu thật từ CMS/Catalog.' => 'This demo website was created to review the mobile showroom theme and its ability to map real data from CMS/Catalog.',
            'Kênh liên hệ tư vấn và CSKH' => 'Consulting and customer support contact channel',
            '<h2>Liên hệ tư vấn</h2>' => '<h2>Contact for consultation</h2>',
            'Nội dung demo cho ngành ' => 'Demo content for the ',
            ' nhằm kiểm tra block tin tức của theme.' => ' sector to validate the theme news block.',
            'Tin tức demo cho ' => 'Demo news for ',
            'Top deal mới tuần này cho ' => 'Top new deals this week for ',
            '5 xu hướng mua sắm ' => '5 shopping trends for ',
            ' đang tăng mạnh' => ' that are gaining momentum',
            'Gợi ý chọn sản phẩm nổi bật cho chiến dịch cuối tuần' => 'Suggestions for featured products in the weekend campaign',
            'Cách tối ưu landing page bán ' => 'How to optimize a landing page selling ',
            ' theo mùa' => ' by season',
            'Bài viết demo số ' => 'Demo article #',
            ' dùng để hiển thị tin mới trên website.' => ' used to display the latest news on the website.',
            'Mua ngay' => 'Shop now',
            'Lên đời smartphone và phụ kiện chính hãng' => 'Upgrade your smartphone and genuine accessories',
            'Combo máy mới, trả góp linh hoạt và hậu mãi tại cửa hàng.' => 'New device bundles, flexible installment plans, and in-store after-sales support.',
            'Điện thoại và phụ kiện' => 'Phones & Accessories',
            'điện thoại' => 'phones',
            'Smartphone' => 'Smartphones',
            'Tầm trung' => 'Mid-range',
            'Giá tốt' => 'Best value',
            'Ốp lưng' => 'Cases',
            'Chống sốc' => 'Shockproof',
            'Trong suốt' => 'Clear',
            'Da cao cấp' => 'Premium leather',
            'Tai nghe' => 'Headphones',
            'Sạc' => 'Chargers',
            'Sạc nhanh' => 'Fast charging',
            'Sạc không dây' => 'Wireless charging',
            'Củ cáp combo' => 'Adapter and cable bundles',
            'Đồng hồ' => 'Wearables',
            'Vòng tay' => 'Bands',
            'Đồng hồ trẻ em' => 'Kids watches',
            'Loa mini' => 'Mini speakers',
            'Loa karaoke' => 'Karaoke speakers',
            'Loa du lịch' => 'Travel speakers',
            'Thiết bị ghi hình' => 'Creator gear',
            'Đèn livestream' => 'Streaming lights',
            'Bảo hành' => 'Protection plans',
            'Gói rơi vỡ' => 'Accidental damage',
            'Bảo hành pin' => 'Battery coverage',
            'Thu cũ đổi mới' => 'Trade-in',
            'Máy cũ' => 'Pre-owned',
            'Phụ kiện xe' => 'Car accessories',
            'Giá đỡ' => 'Mounts',
            'Sạc ô tô' => 'Car chargers',
            'Tin tức' => 'News',
            'Tin điện thoại' => 'Phone news',
            'Giới thiệu' => 'About',
            'Liên hệ' => 'Contact',
            'Hà Nội' => 'Hanoi',
            'Quận 1' => 'District 1',
            'TP.HCM' => 'Ho Chi Minh City',
        ];

        return strtr($translated, $replacements);
    }
}
