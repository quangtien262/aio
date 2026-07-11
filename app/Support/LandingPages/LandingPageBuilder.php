<?php

namespace App\Support\LandingPages;

use App\Models\CatalogProduct;
use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\CmsTeamMember;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\LandingPageData;
use App\Models\SiteBanner;
use App\Models\ThemeTranslation;
use App\Support\FrontendLocalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LandingPageBuilder
{
    public function supportsTheme(?string $themeKey): bool
    {
        return in_array(strtoupper((string) $themeKey), ['XD0301', 'XD0302'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableBlocks(string $themeKey): array
    {
        if (! $this->supportsTheme($themeKey)) {
            return [];
        }

        return collect($this->defaultBlocksForTheme($themeKey))
            ->map(fn (array $block): array => [
                'block_type' => $block['block_type'],
                'label' => $block['label'],
                'description' => $block['description'],
                'default_anchor_id' => $block['anchor_id'],
                'preview_image' => $block['preview_image'] ?? null,
                'dynamic' => $block['dynamic'] ?? false,
                'settings_schema' => $block['settings_schema'] ?? [],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsSchemaFor(string $themeKey, string $blockType): array
    {
        if (! $this->supportsTheme($themeKey)) {
            return [];
        }

        $definition = collect($this->defaultBlocksForTheme($themeKey))->firstWhere('block_type', $blockType);

        return is_array($definition) ? (array) ($definition['settings_schema'] ?? []) : [];
    }

    public function resolveHome(string $websiteKey, string $themeKey, bool $createIfMissing = true): ?LandingPage
    {
        if (! $this->supportsTheme($themeKey) || ! $this->tablesReady()) {
            return null;
        }

        $query = LandingPage::query()
            ->with(['data', 'blocks.data'])
            ->where('website_key', $websiteKey)
            ->where('theme_key', strtoupper($themeKey))
            ->where('is_home', true);

        $page = $query->first();

        if ($page === null && $createIfMissing) {
            $page = $this->seedHome($websiteKey, $themeKey);
        }

        return $page?->load(['data', 'blocks.data']);
    }

    public function resolveBySlug(string $websiteKey, string $slug, string $themeKey, bool $publishedOnly = true): ?LandingPage
    {
        if (! $this->supportsTheme($themeKey) || ! $this->tablesReady()) {
            return null;
        }

        $query = LandingPage::query()
            ->with(['data', 'blocks.data'])
            ->where('website_key', $websiteKey)
            ->where('theme_key', strtoupper($themeKey))
            ->where('slug', $slug);

        if ($publishedOnly) {
            $query->where('status', 'published');
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(LandingPage $page, string $locale, string $fallbackLocale = 'vi'): array
    {
        $pageData = $this->localizedPageData($page, $locale, $fallbackLocale);

        $blocks = $page->blocks
            ->filter(fn (LandingPageBlock $block): bool => $block->is_visible)
            ->map(fn (LandingPageBlock $block): array => $this->serializeBlock($block, $locale, $fallbackLocale, true))
            ->values()
            ->all();

        return [
            'landingPage' => $this->serializePage($page, $pageData),
            'landingBlocks' => $blocks,
            'landingMenuItems' => $this->landingMenuItems($blocks, $page->is_home ? null : $page->slug),
            'landingEditorOptions' => $this->editorOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function editorOptions(): array
    {
        return [
            'categories_by_source' => [
                'custom' => [],
                'cms_posts' => $this->cmsCategoryOptions(),
                'latest_posts' => $this->cmsCategoryOptions(),
                'cms_products' => $this->catalogCategoryOptions(),
                'catalog_products' => $this->catalogCategoryOptions(),
                'featured_products' => $this->catalogCategoryOptions(),
                'cms_projects' => [],
                'cms_services' => $this->cmsServiceCategoryOptions(),
                'cms_team_members' => [],
                'cms_testimonials' => [],
                'catalog_categories' => [],
                'cms_categories' => [],
                'cms_service_categories' => [],
            ],
        ];
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    private function cmsCategoryOptions(): array
    {
        if (! Schema::hasTable('cms_categories')) {
            return [];
        }

        return CmsCategory::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CmsCategory $category): array => [
                'value' => (int) $category->id,
                'label' => (string) $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    private function cmsServiceCategoryOptions(): array
    {
        if (! Schema::hasTable('cms_service_categories')) {
            return [];
        }

        return CmsServiceCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name'])
            ->map(fn (CmsServiceCategory $category): array => [
                'value' => (int) $category->id,
                'label' => (string) $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    private function catalogCategoryOptions(): array
    {
        if (! Schema::hasTable('catalog_categories')) {
            return [];
        }

        return CatalogCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CatalogCategory $category): array => [
                'value' => (int) $category->id,
                'label' => (string) $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePage(LandingPage $page, ?LandingPageData $data = null): array
    {
        return [
            'id' => $page->id,
            'website_key' => $page->website_key,
            'theme_key' => $page->theme_key,
            'page_type' => $page->page_type,
            'slug' => $page->slug,
            'status' => $page->status,
            'template' => $page->template,
            'is_home' => $page->is_home,
            'settings' => $page->settings ?? [],
            'media' => $page->media ?? [],
            'title' => $data?->title,
            'excerpt' => $data?->excerpt,
            'meta_title' => $data?->meta_title,
            'meta_description' => $data?->meta_description,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeBlock(LandingPageBlock $block, string $locale, string $fallbackLocale = 'vi', bool $includeDynamic = false): array
    {
        $data = $this->localizedBlockData($block, $locale, $fallbackLocale);
        $fallbackData = $block->data->firstWhere('locale', $fallbackLocale) ?? $block->data->first();
        $content = $this->decodeContent($data?->content);
        $fallbackContent = $this->decodeContent($fallbackData?->content);

        if ($content === [] || (array_key_exists('items', $content) && ($content['items'] ?? []) === [])) {
            $content = $fallbackContent;
        }

        return [
            'id' => $block->id,
            'landing_page_id' => $block->landing_page_id,
            'theme_key' => $block->theme_key,
            'block_type' => $block->block_type,
            'sort_order' => $block->sort_order,
            'is_visible' => $block->is_visible,
            'anchor_id' => $block->anchor_id,
            'settings' => $block->settings ?? [],
            'settings_schema' => $this->settingsSchemaFor((string) $block->theme_key, (string) $block->block_type),
            'media' => $block->media ?? [],
            'data' => [
                'locale' => $data?->locale ?? $locale,
                'title' => $data?->title ?? $fallbackData?->title,
                'subtitle' => $data?->subtitle ?? $fallbackData?->subtitle,
                'description' => $data?->description ?? $fallbackData?->description,
                'button_label' => $data?->button_label ?? $fallbackData?->button_label,
                'content' => $content,
            ],
            'data_by_locale' => collect(FrontendLocalization::supportedLocales())
                ->mapWithKeys(function (string $supportedLocale) use ($block, $fallbackData, $fallbackContent): array {
                    $localeData = $block->data->firstWhere('locale', $supportedLocale);
                    $localeContent = $this->decodeContent($localeData?->content);

                    if ($localeContent === [] || (array_key_exists('items', $localeContent) && ($localeContent['items'] ?? []) === [])) {
                        $localeContent = $fallbackContent;
                    }

                    return [
                        $supportedLocale => [
                            'locale' => $supportedLocale,
                            'title' => $localeData?->title ?? $fallbackData?->title,
                            'subtitle' => $localeData?->subtitle ?? $fallbackData?->subtitle,
                            'description' => $localeData?->description ?? $fallbackData?->description,
                            'button_label' => $localeData?->button_label ?? $fallbackData?->button_label,
                            'content' => $localeContent,
                        ],
                    ];
                })
                ->all(),
            'dynamic_items' => $includeDynamic ? $this->dynamicItems($block, $locale) : [],
        ];
    }

    public function seedHome(string $websiteKey, string $themeKey): LandingPage
    {
        $page = LandingPage::query()->create([
            'website_key' => $websiteKey,
            'theme_key' => strtoupper($themeKey),
            'page_type' => 'home',
            'slug' => 'home',
            'status' => 'published',
            'template' => 'home',
            'is_home' => true,
            'sort_order' => 0,
            'settings' => ['menu_display_type' => 'landingpage'],
            'published_at' => now(),
        ]);

        foreach ($this->supportedLocales() as $locale) {
            LandingPageData::query()->create([
                'landing_page_id' => $page->id,
                'locale' => $locale,
                'title' => strtoupper($themeKey).' Landing',
                'excerpt' => 'Trang chủ landingpage.',
                'meta_title' => strtoupper($themeKey).' Landing',
                'meta_description' => 'Landing page được quản lý theo từng block.',
            ]);
        }

        foreach ($this->defaultBlocksForTheme($themeKey) as $index => $definition) {
            $block = LandingPageBlock::query()->create([
                'landing_page_id' => $page->id,
                'theme_key' => strtoupper($themeKey),
                'block_type' => $definition['block_type'],
                'sort_order' => ($index + 1) * 10,
                'is_visible' => true,
                'anchor_id' => $definition['anchor_id'],
                'settings' => $definition['settings'] ?? [],
                'media' => $definition['media'] ?? [],
            ]);

            foreach ($this->supportedLocales() as $locale) {
                $data = $definition['data'][$locale] ?? $definition['data']['vi'];
                LandingPageBlockData::query()->create([
                    'landing_page_block_id' => $block->id,
                    'locale' => $locale,
                    'title' => $data['title'] ?? null,
                    'subtitle' => $data['subtitle'] ?? null,
                    'description' => $data['description'] ?? null,
                    'button_label' => $data['button_label'] ?? null,
                    'content' => json_encode($data['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }

        return $page->load(['data', 'blocks.data']);
    }

    public function createBlock(LandingPage $page, string $blockType): LandingPageBlock
    {
        $definition = collect($this->defaultBlocksForTheme((string) $page->theme_key))->firstWhere('block_type', $blockType);
        abort_if($definition === null, 422, 'Unsupported landing block type.');

        $maxSort = (int) $page->blocks()->max('sort_order');
        $block = LandingPageBlock::query()->create([
            'landing_page_id' => $page->id,
            'theme_key' => $page->theme_key,
            'block_type' => $blockType,
            'sort_order' => $maxSort + 10,
            'is_visible' => true,
            'anchor_id' => $definition['anchor_id'].'-'.Str::lower(Str::random(4)),
            'settings' => $definition['settings'] ?? [],
            'media' => $definition['media'] ?? [],
        ]);

        foreach ($this->supportedLocales() as $locale) {
            $data = $definition['data'][$locale] ?? $definition['data']['vi'];
            LandingPageBlockData::query()->create([
                'landing_page_block_id' => $block->id,
                'locale' => $locale,
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? null,
                'button_label' => $data['button_label'] ?? null,
                'content' => json_encode($data['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        return $block->load('data');
    }

    private function tablesReady(): bool
    {
        return Schema::hasTable('landing_pages')
            && Schema::hasTable('landing_page_blocks')
            && Schema::hasTable('landing_page_data')
            && Schema::hasTable('landing_page_block_data');
    }

    /**
     * @return array<int, string>
     */
    private function supportedLocales(): array
    {
        return array_values(array_unique(FrontendLocalization::supportedLocales() ?: ['vi']));
    }

    private function localizedPageData(LandingPage $page, string $locale, string $fallbackLocale): ?LandingPageData
    {
        return $page->data->firstWhere('locale', $locale)
            ?? $page->data->firstWhere('locale', $fallbackLocale)
            ?? $page->data->first();
    }

    private function localizedBlockData(LandingPageBlock $block, string $locale, string $fallbackLocale): ?LandingPageBlockData
    {
        return $block->data->firstWhere('locale', $locale)
            ?? $block->data->firstWhere('locale', $fallbackLocale)
            ?? $block->data->first();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeContent(?string $content): array
    {
        if ($content === null || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array{label:string,url:string,children:array<int, array{label:string,url:string}>}>
     */
    private function landingMenuItems(array $blocks, ?string $slug): array
    {
        $base = $slug ? route('site.landing.show', ['locale' => app()->getLocale(), 'slug' => $slug]) : '';

        return collect($blocks)
            ->filter(fn (array $block): bool => filled($block['anchor_id'] ?? null))
            ->map(function (array $block) use ($base): array {
                $label = $block['data']['subtitle'] ?: $block['data']['title'] ?: Str::headline($block['block_type']);

                return [
                    'label' => (string) $label,
                    'url' => $base.'#'.$block['anchor_id'],
                    'children' => [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function previewDynamicItems(LandingPageBlock $block, string $locale, array $settings = []): array
    {
        $block->loadMissing('landingPage');
        $settings = array_merge($block->settings ?? [], $settings);
        $defaultLimit = match ($block->block_type) {
            'hero_slider' => 3,
            'featured_categories' => 6,
            'content_mosaic' => 5,
            'content_showcase' => 5,
            'project_gallery' => 4,
            'featured_services' => 3,
            'latest_posts' => 3,
            'team_members' => 4,
            'testimonials' => 2,
            'partner_logos' => 6,
            default => 3,
        };
        $limit = max(1, min(12, (int) ($settings['limit'] ?? $defaultLimit)));

        if ($block->block_type === 'hero_slider') {
            return $this->heroSlideItems([...$settings, 'theme_key' => $block->theme_key], $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'featured_categories') {
            return $this->featuredCategoryItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'latest_posts') {
            return $this->contentSourceItems($settings, 'cms_posts', $limit, $locale, $block->landingPage?->website_key);
        }

        if (in_array($block->block_type, ['featured_services', 'content_mosaic', 'content_showcase', 'project_gallery'], true)) {
            $defaultSource = match ($block->block_type) {
                'content_mosaic' => 'cms_posts',
                'content_showcase' => 'cms_projects',
                'project_gallery' => 'cms_projects',
                default => 'cms_services',
            };

            return $this->contentSourceItems($settings, $defaultSource, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'featured_services') {
            $source = (string) ($settings['source'] ?? 'cms_services');

            if ($source === 'latest_posts') {
                return $this->latestPostItems($settings, $limit, $locale, $block->landingPage?->website_key);
            }

            if ($source === 'featured_products') {
                return $this->featuredProductItems($settings, $limit, $locale, $block->landingPage?->website_key);
            }

            if (! Schema::hasTable('cms_services') || ! Schema::hasTable('cms_service_images')) {
                return [];
            }

            /** @var Builder $query */
            $query = CmsService::query()
                ->with('images')
                ->where('status', 'published')
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->latest('updated_at');

            if (($settings['featured_only'] ?? true) === true) {
                $query->where('is_featured', true);
            }

            return $query->take($limit)->get()->map(function (CmsService $service) use ($locale, $block): array {
                $featuredImage = $service->images->firstWhere('is_featured', true) ?? $service->images->first();
                $websiteKey = (string) ($block->landingPage?->website_key ?? 'website-main');

                return [
                    'title' => $this->contentText($websiteKey, $locale, sprintf('cms_service.%d.title', $service->id), $service->title),
                    'summary' => $this->contentText($websiteKey, $locale, sprintf('cms_service.%d.summary', $service->id), $service->summary),
                    'icon' => $service->icon ?: '▦',
                    'image' => $featuredImage?->image_url,
                    'alt' => $featuredImage?->alt_text ?: $service->title,
                    'url' => $service->slug !== '' ? route('site.services.show', ['slug' => $service->slug]) : ($service->link_url ?: '#lien-he'),
                ];
            })->all();
        }

        if ($block->block_type === 'project_gallery') {
            $source = (string) ($settings['source'] ?? 'cms_projects');
            $websiteKey = $block->landingPage?->website_key;

            if ($source === 'latest_posts') {
                return $this->latestPostItems($settings, $limit, $locale, $websiteKey);
            }

            if ($source === 'featured_products') {
                return $this->featuredProductItems($settings, $limit, $locale, $websiteKey);
            }

            if ($source === 'cms_services') {
                return $this->cmsServiceItems($settings, $limit, $locale, $websiteKey);
            }

            return $this->cmsProjectItems($settings, $limit, $locale, $websiteKey);
        }

        if ($block->block_type === 'team_members') {
            if (($settings['source'] ?? 'cms_team_members') === 'custom') {
                return [];
            }

            return $this->cmsTeamMemberItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'testimonials') {
            if (($settings['source'] ?? 'cms_testimonials') === 'custom') {
                return [];
            }

            return $this->cmsTestimonialItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'partner_logos') {
            return $this->cmsPartnerItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'featured_products') {
            return $this->featuredProductItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'menu_links') {
            $menu = CmsMenu::query()->where('location', (string) ($settings['location'] ?? 'primary'))->first();

            return $menu?->items ?? [];
        }

        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dynamicItems(LandingPageBlock $block, string $locale): array
    {
        return $this->previewDynamicItems($block, $locale, $block->settings ?? []);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function heroSlideItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        if (! Schema::hasTable('site_banners')) {
            return [];
        }

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');
        $placement = (string) ($settings['placement'] ?? 'xd0301-hero-slider');
        $themeKey = strtoupper((string) ($settings['theme_key'] ?? 'XD0301'));

        /** @var Builder $query */
        $query = SiteBanner::query()
            ->where('is_active', true)
            ->where('placement', $placement)
            ->where(function (Builder $builder) use ($themeKey): void {
                $builder->where('theme_key', $themeKey)->orWhereNull('theme_key');
            })
            ->orderBy('sort_order')
            ->orderBy('id');

        return $query->take($limit)->get()->map(function (SiteBanner $banner) use ($locale, $resolvedWebsiteKey): array {
            $title = $this->contentText($resolvedWebsiteKey, $locale, sprintf('site_banner.%d.title', $banner->id), $banner->title ?? '');
            $summary = $this->contentText($resolvedWebsiteKey, $locale, sprintf('site_banner.%d.metadata.summary', $banner->id), (string) data_get($banner->metadata, 'summary', $banner->subtitle ?? ''));
            $kicker = $this->contentText($resolvedWebsiteKey, $locale, sprintf('site_banner.%d.metadata.eyebrow', $banner->id), (string) data_get($banner->metadata, 'eyebrow', $banner->badge ?? ''));
            $buttonLabel = $this->contentText($resolvedWebsiteKey, $locale, sprintf('site_banner.%d.metadata.button_label', $banner->id), (string) data_get($banner->metadata, 'button_label', 'Xem dự án →'));

            return [
                'id' => $banner->id,
                'kicker' => $kicker,
                'title' => $title,
                'summary' => $summary,
                'image' => $banner->image_url,
                'alt' => $title ?: 'Banner',
                'button_label' => $buttonLabel,
                'link_url' => $banner->link_url ?: '#du-an',
                'source' => 'site_banners',
            ];
        })->all();
    }

    /**
     * Resolve a generic content-list source for landing blocks that render card/slide items.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function contentSourceItems(array $settings, string $defaultSource, int $limit, string $locale, ?string $websiteKey): array
    {
        $source = (string) ($settings['source'] ?? $defaultSource);

        return match ($source) {
            'custom' => [],
            'cms_posts', 'latest_posts' => $this->latestPostItems($settings, $limit, $locale, $websiteKey),
            'cms_products', 'catalog_products', 'featured_products' => $this->featuredProductItems($settings, $limit, $locale, $websiteKey),
            'cms_projects' => $this->cmsProjectItems($settings, $limit, $locale, $websiteKey),
            'cms_services' => $this->cmsServiceItems($settings, $limit, $locale, $websiteKey),
            default => $this->contentSourceItems([...$settings, 'source' => $defaultSource], $defaultSource, $limit, $locale, $websiteKey),
        };
    }

    private function fallbackContentImage(): string
    {
        return 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80';
    }

    private function fallbackCategoryImage(int $index = 0): string
    {
        $images = [
            'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=80',
            'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=900&q=80',
        ];

        return $images[$index % count($images)];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function featuredCategoryItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        $source = (string) ($settings['source'] ?? 'catalog_categories');
        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        if ($source === 'custom') {
            return [];
        }

        if ($source === 'cms_categories') {
            if (! Schema::hasTable('cms_categories')) {
                return [];
            }

            return CmsCategory::query()
                ->withCount(['posts' => fn (Builder $query) => $query->where('status', 'published')])
                ->orderByDesc('posts_count')
                ->orderBy('name')
                ->take($limit)
                ->get()
                ->map(function (CmsCategory $category, int $index) use ($resolvedWebsiteKey, $locale): array {
                    $title = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_category.%d.name', $category->id), $category->name);

                    return [
                        'title' => $title,
                        'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_category.%d.description', $category->id), $category->description),
                        'image' => $this->fallbackCategoryImage($index),
                        'alt' => $title,
                        'icon' => Str::upper(Str::substr((string) $title, 0, 1)),
                        'count_label' => (int) $category->posts_count > 0 ? $category->posts_count.' bài viết' : null,
                        'url' => route('site.blog.category', ['locale' => $locale, 'slug' => $category->slug]),
                    ];
                })
                ->all();
        }

        if ($source === 'cms_services') {
            return $this->cmsServiceItems(['featured_only' => $settings['featured_only'] ?? true], $limit, $locale, $websiteKey)
                ?: [];
        }

        if ($source === 'cms_service_categories') {
            if (! Schema::hasTable('cms_service_categories')) {
                return [];
            }

            return CmsServiceCategory::query()
                ->where('is_active', true)
                ->withCount(['services' => fn (Builder $query) => $query->where('status', 'published')])
                ->orderByDesc('services_count')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take($limit)
                ->get()
                ->map(function (CmsServiceCategory $category, int $index) use ($resolvedWebsiteKey, $locale): array {
                    $title = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_service_category.%d.name', $category->id), $category->name);
                    $summary = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_service_category.%d.description', $category->id), $category->description);

                    return [
                        'title' => $title,
                        'summary' => $summary,
                        'image' => $category->image_url ?: $this->fallbackCategoryImage($index),
                        'alt' => $title,
                        'icon' => Str::upper(Str::substr((string) $title, 0, 1)),
                        'count_label' => (int) $category->services_count > 0 ? $category->services_count.' dịch vụ' : null,
                        'url' => route('site.services.category', ['locale' => $locale, 'slug' => $category->slug]),
                    ];
                })
                ->all();
        }

        if (! Schema::hasTable('catalog_categories')) {
            return [];
        }

        return CatalogCategory::query()
            ->where('is_active', true)
            ->withCount(['products' => fn (Builder $query) => $query->where('is_active', true)])
            ->orderByDesc('products_count')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take($limit)
            ->get()
            ->map(function (CatalogCategory $category, int $index) use ($resolvedWebsiteKey, $locale): array {
                $title = $this->contentText($resolvedWebsiteKey, $locale, sprintf('catalog_category.%d.name', $category->id), $category->name);
                $summary = $this->contentText($resolvedWebsiteKey, $locale, sprintf('catalog_category.%d.description', $category->id), $category->description);

                return [
                    'title' => $title,
                    'summary' => $summary,
                    'image' => $category->image_url ?: $this->fallbackCategoryImage($index),
                    'alt' => $title,
                    'icon' => Str::upper(Str::substr((string) $title, 0, 1)),
                    'count_label' => (int) $category->products_count > 0 ? $category->products_count.' sản phẩm' : null,
                    'url' => route('site.catalog.category', ['locale' => $locale, 'slug' => $category->slug]),
                ];
            })
            ->all();
    }

    private function highlightColumn(string $table): ?string
    {
        if (Schema::hasColumn($table, 'is_highlight')) {
            return 'is_highlight';
        }

        return Schema::hasColumn($table, 'is_featured') ? 'is_featured' : null;
    }

    private function applyHighlightFilter(Builder $query, string $table, array $settings): void
    {
        if (($settings['featured_only'] ?? true) !== true) {
            return;
        }

        $column = $this->highlightColumn($table);

        if ($column !== null) {
            $query->where($column, true);
        }
    }

    private function orderByHighlight(Builder $query, string $table): void
    {
        $column = $this->highlightColumn($table);

        if ($column !== null) {
            $query->orderByDesc($column);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function latestPostItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        /** @var Builder $query */
        $query = CmsPost::query()->with('featuredMedia')->where('status', 'published')->latest('publish_at');

        if (filled($settings['category_id'] ?? null)) {
            $query->where('category_id', (int) $settings['category_id']);
        }
        $this->applyHighlightFilter($query, 'cms_posts', $settings);

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(fn (CmsPost $post): array => [
            'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_post.%d.title', $post->id), $post->title),
            'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_post.%d.excerpt', $post->id), $post->excerpt),
            'icon' => '▦',
            'image' => $post->featuredMedia?->file_url ?: $this->fallbackContentImage(),
            'alt' => $post->featuredMedia?->alt_text ?: $post->title,
            'url' => route('site.blog.show', ['slug' => $post->slug]),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function featuredProductItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        /** @var Builder $query */
        $query = CatalogProduct::query()->where('is_active', true);
        $this->orderByHighlight($query, 'catalog_products');
        $query->latest();

        if (filled($settings['category_id'] ?? null)) {
            $query->where('catalog_category_id', (int) $settings['category_id']);
        }
        $this->applyHighlightFilter($query, 'catalog_products', $settings);

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(fn (CatalogProduct $product): array => [
            'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('catalog_product.%d.name', $product->id), $product->name),
            'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('catalog_product.%d.short_description', $product->id), $product->short_description),
            'icon' => '▦',
            'image' => $product->image_url ?: $this->fallbackContentImage(),
            'alt' => $product->name,
            'url' => route('site.catalog.product', ['slug' => $product->slug]),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsProjectItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        if (! Schema::hasTable('cms_projects') || ! Schema::hasTable('cms_project_images')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsProject::query()
            ->with('images')
            ->where('status', 'published');
        $this->orderByHighlight($query, 'cms_projects');
        $query
            ->orderBy('sort_order')
            ->latest('updated_at');
        $this->applyHighlightFilter($query, 'cms_projects', $settings);

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(function (CmsProject $project) use ($locale, $resolvedWebsiteKey): array {
            $featuredImage = $project->images->firstWhere('is_featured', true) ?? $project->images->first();

            return [
                'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.title', $project->id), $project->title),
                'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.summary', $project->id), $project->summary),
                'tag' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.summary', $project->id), $project->summary),
                'image' => $featuredImage?->image_url ?: $this->fallbackContentImage(),
                'alt' => $featuredImage?->alt_text ?: $project->title,
                'url' => $project->link_url ?: '#lien-he',
                'button_label' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.button_label', $project->id), $project->button_label),
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsServiceItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        if (! Schema::hasTable('cms_services') || ! Schema::hasTable('cms_service_images')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsService::query()
            ->with('images')
            ->where('status', 'published');
        $this->orderByHighlight($query, 'cms_services');
        $query
            ->orderBy('sort_order')
            ->latest('updated_at');
        $this->applyHighlightFilter($query, 'cms_services', $settings);
        if (! empty($settings['category_id']) && Schema::hasColumn('cms_services', 'cms_service_category_id')) {
            $query->where('cms_service_category_id', (int) $settings['category_id']);
        }

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(function (CmsService $service) use ($locale, $resolvedWebsiteKey): array {
            $featuredImage = $service->images->firstWhere('is_featured', true) ?? $service->images->first();

            return [
                'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_service.%d.title', $service->id), $service->title),
                'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_service.%d.summary', $service->id), $service->summary),
                'tag' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_service.%d.summary', $service->id), $service->summary),
                'icon' => $service->icon ?: 'â–¦',
                'image' => $featuredImage?->image_url ?: $this->fallbackContentImage(),
                'alt' => $featuredImage?->alt_text ?: $service->title,
                'url' => $service->slug !== '' ? route('site.services.show', ['slug' => $service->slug]) : ($service->link_url ?: '#lien-he'),
            'button_label' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_service.%d.button_label', $service->id), $service->button_label),
        ];
    })->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsTestimonialItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        if (! Schema::hasTable('cms_testimonials')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsTestimonial::query()
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('updated_at');

        if (($settings['featured_only'] ?? true) === true) {
            $query->where('is_featured', true);
        }

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(fn (CmsTestimonial $testimonial): array => [
            'name' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.name', $testimonial->id), $testimonial->name),
            'role' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.role', $testimonial->id), $testimonial->role),
            'company' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.company', $testimonial->id), $testimonial->company),
            'quote' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.quote', $testimonial->id), $testimonial->quote),
            'image' => $testimonial->image_url,
            'alt' => $testimonial->image_alt ?: $testimonial->name,
            'url' => $testimonial->link_url ?: '#lien-he',
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsTeamMemberItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        if (! Schema::hasTable('cms_team_members') || ! Schema::hasTable('cms_team_member_images')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsTeamMember::query()
            ->with('images')
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('updated_at');

        if (($settings['featured_only'] ?? true) === true) {
            $query->where('is_featured', true);
        }

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(function (CmsTeamMember $member) use ($locale, $resolvedWebsiteKey): array {
            $featuredImage = $member->images->firstWhere('is_featured', true) ?? $member->images->first();

            return [
                'name' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_team_member.%d.name', $member->id), $member->name),
                'role' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_team_member.%d.role', $member->id), $member->role),
                'department' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_team_member.%d.department', $member->id), $member->department),
                'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_team_member.%d.summary', $member->id), $member->summary),
                'image' => $featuredImage?->image_url,
                'alt' => $featuredImage?->alt_text ?: $member->name,
                'url' => $member->link_url ?: '#lien-he',
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsPartnerItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
    {
        if (! Schema::hasTable('cms_partners')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsPartner::query()
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('updated_at');

        if (($settings['featured_only'] ?? true) === true) {
            $query->where('is_featured', true);
        }

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(fn (CmsPartner $partner): array => [
            'name' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_partner.%d.title', $partner->id), $partner->title),
            'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_partner.%d.title', $partner->id), $partner->title),
            'description' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_partner.%d.description', $partner->id), $partner->description),
            'image' => $partner->image_url,
            'alt' => $partner->image_alt ?: $partner->title,
            'href' => $partner->link_url ?: '#top',
            'url' => $partner->link_url ?: '#top',
        ])->all();
    }

    private function contentText(string $websiteKey, string $locale, string $key, ?string $fallback): ?string
    {
        if (! Schema::hasTable('theme_translations')) {
            return $fallback;
        }

        $value = ThemeTranslation::query()
            ->where('theme_key', 'site-content:'.strtolower(trim($websiteKey) !== '' ? $websiteKey : 'default'))
            ->where('locale', FrontendLocalization::resolveLocale($locale))
            ->where('group', 'content')
            ->where('translation_key', $key)
            ->value('value');

        return filled($value) ? (string) $value : $fallback;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultBlocksForTheme(string $themeKey): array
    {
        return strtoupper($themeKey) === 'XD0302'
            ? $this->xd0302DefaultBlocks()
            : $this->xd0301DefaultBlocks();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function xd0302DefaultBlocks(): array
    {
        $contentSources = ['custom', 'cms_posts', 'cms_products', 'cms_services', 'cms_projects'];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Header và banner',
                'description' => 'Header, menu và banner ảnh chạy.',
                'preview_image' => '/theme-previews/XD0302/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0302-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [
                    ['key' => 'placement', 'label' => 'Vị trí banner', 'type' => 'text', 'default' => 'xd0302-hero-slider'],
                    ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 3],
                    ['key' => 'autoplay_ms', 'label' => 'Tự chuyển (ms)', 'type' => 'number', 'default' => 6000],
                ],
                'data' => [
                    'vi' => ['title' => 'Giải pháp năng lượng bền vững cho doanh nghiệp', 'subtitle' => 'Soler Panel', 'description' => 'Tư vấn, thiết kế và triển khai hệ thống năng lượng phù hợp với nhu cầu vận hành.', 'button_label' => 'Xem dự án', 'content' => ['slides' => []]],
                    'en' => ['title' => 'Sustainable energy for business', 'subtitle' => 'Soler Panel', 'description' => 'Energy solutions designed for your operation.', 'button_label' => 'View projects', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu',
                'description' => 'Giới thiệu năng lực, kinh nghiệm và định hướng doanh nghiệp.',
                'preview_image' => '/theme-previews/XD0302/about-experience.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['years' => 29, 'cta_url' => '/gioi-thieu'],
                'media' => [],
                'data' => [
                    'vi' => ['title' => 'Chúng tôi đang phát triển các giải pháp năng lượng mặt trời', 'subtitle' => 'Giới thiệu của chúng tôi', 'description' => 'Giải pháp năng lượng xanh giúp doanh nghiệp chủ động chi phí và hướng tới tương lai bền vững.', 'button_label' => 'Về chúng tôi', 'content' => ['tabs' => ['Về chúng tôi', 'Tầm nhìn', 'Sứ mệnh'], 'image_secondary' => '']],
                    'en' => ['title' => 'We develop solar energy solutions', 'subtitle' => 'About us', 'description' => 'Clean energy solutions for long-term operations.', 'button_label' => 'About us', 'content' => ['tabs' => ['About', 'Vision', 'Mission'], 'image_secondary' => '']],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dịch vụ',
                'description' => 'Danh sách dịch vụ lấy từ CMS Services.',
                'preview_image' => '/theme-previews/XD0302/featured-services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Cung cấp giải pháp năng lượng mặt trời', 'subtitle' => 'Dịch vụ của chúng tôi', 'description' => '', 'button_label' => 'Đọc thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Solar energy solutions', 'subtitle' => 'Our services', 'description' => '', 'button_label' => 'Read more', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'Hỏi đáp',
                'description' => 'Câu hỏi thường gặp và các dự án nổi bật minh họa.',
                'preview_image' => '/theme-previews/XD0302/faq-showcase.png',
                'anchor_id' => 'hoi-dap',
                'settings' => [],
                'data' => ['vi' => ['title' => 'Muốn hỏi điều gì đó từ chúng tôi?', 'subtitle' => 'Câu hỏi thường gặp', 'description' => '', 'button_label' => '', 'content' => ['items' => [['question' => 'Năng lượng mặt trời là gì?', 'answer' => 'Đây là nguồn năng lượng tái tạo từ ánh sáng mặt trời, có thể chuyển đổi thành điện để phục vụ sinh hoạt và sản xuất.'], ['question' => 'Hệ thống hoạt động như thế nào?', 'answer' => 'Tấm pin hấp thụ ánh sáng, bộ biến tần chuyển đổi thành điện và đưa điện vào hệ thống sử dụng.'], ['question' => 'Lợi ích của việc sử dụng là gì?', 'answer' => 'Tiết kiệm chi phí vận hành, giảm phát thải và tăng giá trị bền vững cho công trình.']]]], 'en' => ['title' => 'What would you like to ask?', 'subtitle' => 'FAQ', 'description' => '', 'button_label' => '', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'landing_contact',
                'label' => 'Liên hệ',
                'description' => 'Khối lợi ích và biểu mẫu đặt lịch tư vấn.',
                'preview_image' => '/theme-previews/XD0302/landing-contact.png',
                'anchor_id' => 'lien-he',
                'settings' => [],
                'data' => ['vi' => ['title' => 'Giải pháp tốt nhất cho bạn', 'subtitle' => 'Tại sao chọn chúng tôi', 'description' => 'Chúng tôi tư vấn giải pháp phù hợp với nhu cầu, quy mô và mục tiêu vận hành của doanh nghiệp.', 'button_label' => 'Gửi đi', 'content' => ['form_title' => 'Đặt lịch hẹn', 'benefits' => ['Tiết kiệm chi phí tiền điện', 'Tuổi thọ cao và ít bảo trì', 'Tăng giá trị cho công trình'], 'phone' => '1900 9477']], 'en' => ['title' => 'The best solution for you', 'subtitle' => 'Why choose us', 'description' => 'Tell us about your requirements and timeline.', 'button_label' => 'Send request', 'content' => ['form_title' => 'Book an appointment', 'benefits' => [], 'phone' => '1900 9477']]],
            ],
            [
                'block_type' => 'content_showcase',
                'label' => 'Dự án / nội dung nổi bật',
                'description' => 'Lưới nội dung linh hoạt cho dự án, tin tức, sản phẩm hoặc dịch vụ.',
                'preview_image' => '/theme-previews/XD0302/project-gallery.png',
                'anchor_id' => 'du-an',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Dự án năng lượng của chúng tôi', 'subtitle' => 'Những dự án của chúng tôi', 'description' => '', 'button_label' => 'Tất cả dự án', 'content' => ['items' => []]], 'en' => ['title' => 'Our energy projects', 'subtitle' => 'Projects', 'description' => '', 'button_label' => 'All projects', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'latest_posts',
                'label' => 'Tin tức',
                'description' => 'Danh sách tin tức; có thể đổi nguồn sang dự án, sản phẩm hoặc dịch vụ.',
                'preview_image' => '/theme-previews/XD0302/content-mosaic.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Tin tức & bài viết mới nhất', 'subtitle' => 'Tin tức mới nhất', 'description' => '', 'button_label' => 'Xem tất cả', 'content' => ['items' => []]], 'en' => ['title' => 'Latest news and articles', 'subtitle' => 'Latest news', 'description' => '', 'button_label' => 'View all', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'footer_contact',
                'label' => 'Footer',
                'description' => 'Thông tin thương hiệu, menu dịch vụ và bản đồ.',
                'preview_image' => '/theme-previews/XD0302/footer-contact.png',
                'anchor_id' => 'footer',
                'settings' => [],
                'data' => ['vi' => ['title' => 'Bản đồ chỉ đường', 'subtitle' => 'Liên hệ', 'description' => '', 'button_label' => '', 'content' => ['map_url' => '', 'copyright' => '© Bản quyền nội dung thuộc về Soler Panel']], 'en' => ['title' => 'Directions', 'subtitle' => 'Contact', 'description' => '', 'button_label' => '', 'content' => ['map_url' => '', 'copyright' => '© Soler Panel']]],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function xd0301DefaultBlocks(): array
    {
        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero slider',
                'description' => 'Menu đầu trang và slide banner ảnh chạy.',
                'preview_image' => '/theme-previews/XD0301/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'media' => [],
                'settings' => ['autoplay_ms' => 5200, 'source' => 'site_banners', 'placement' => 'xd0301-hero-slider', 'limit' => 3],
                'settings_schema' => [
                    ['key' => 'placement', 'label' => 'Placement banner', 'type' => 'text', 'default' => 'xd0301-hero-slider'],
                    ['key' => 'limit', 'label' => 'So slide', 'type' => 'number', 'default' => 3],
                    ['key' => 'autoplay_ms', 'label' => 'Autoplay ms', 'type' => 'number', 'default' => 5200],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Xây dựng ngôi nhà mơ ước',
                        'subtitle' => 'Trang chủ',
                        'description' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.',
                        'button_label' => 'Xem dự án →',
                        'content' => ['slides' => [
                            ['kicker' => 'Residential', 'title' => 'Xây dựng ngôi nhà mơ ước', 'summary' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85'],
                            ['kicker' => 'Commercial', 'title' => 'Thi công không gian kinh doanh', 'summary' => 'Đội ngũ kỹ sư và kiến trúc sư phối hợp để bàn giao showroom, văn phòng, khách sạn đúng chuẩn vận hành.', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1920&q=85'],
                            ['kicker' => 'Planning', 'title' => 'Quản lý dự án minh bạch', 'summary' => 'Quy trình báo cáo theo mốc, nghiệm thu từng hạng mục và tối ưu chi phí ngay từ giai đoạn thiết kế.', 'image' => 'https://images.unsplash.com/photo-1485083269755-a7b559a4fe5e?auto=format&fit=crop&w=1920&q=85'],
                        ]],
                    ],
                    'en' => [
                        'title' => 'Build the home you imagined',
                        'subtitle' => 'Home',
                        'description' => 'From drawings and materials to handover, XD0301 keeps quality and schedule transparent.',
                        'button_label' => 'View projects →',
                        'content' => ['slides' => [
                            ['kicker' => 'Residential', 'title' => 'Build the home you imagined', 'summary' => 'From drawings and materials to handover, XD0301 keeps quality and schedule transparent.', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85'],
                            ['kicker' => 'Commercial', 'title' => 'Deliver commercial spaces', 'summary' => 'Architects and engineers coordinate to deliver showrooms, offices and hotels ready for operation.', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1920&q=85'],
                            ['kicker' => 'Planning', 'title' => 'Transparent project control', 'summary' => 'Milestone reporting, staged acceptance and early cost optimization.', 'image' => 'https://images.unsplash.com/photo-1485083269755-a7b559a4fe5e?auto=format&fit=crop&w=1920&q=85'],
                        ]],
                    ],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Danh mục nổi bật',
                'description' => 'Khối chiến lược hiển thị các nhóm sản phẩm, dịch vụ hoặc tin tức nổi bật.',
                'preview_image' => '/theme-previews/XD0301/featured-categories.png',
                'anchor_id' => 'danh-muc-noi-bat',
                'dynamic' => true,
                'settings' => [
                    'source' => 'catalog_categories',
                    'limit' => 6,
                    'featured_only' => false,
                ],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => ['custom', 'catalog_categories', 'cms_service_categories', 'cms_services', 'cms_categories']],
                    'limit' => ['type' => 'number', 'label' => 'Số danh mục hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung highlight'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Danh mục trọng tâm',
                        'subtitle' => 'Khám phá nhanh',
                        'description' => 'Các nhóm nội dung quan trọng nhất giúp khách hàng đi thẳng tới nhu cầu chính.',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Nhà ở dân dụng', 'summary' => 'Thiết kế, thi công và hoàn thiện nhà phố, biệt thự.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                            ['title' => 'Không gian thương mại', 'summary' => 'Showroom, văn phòng và khách sạn theo chuẩn vận hành.', 'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80', 'url' => '#du-an'],
                            ['title' => 'Cải tạo nội thất', 'summary' => 'Tối ưu công năng, vật liệu và trải nghiệm sử dụng.', 'image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                            ['title' => 'Tư vấn kỹ thuật', 'summary' => 'Kiểm soát tiến độ, chi phí và chất lượng công trình.', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80', 'url' => '#lien-he'],
                        ]],
                    ],
                    'en' => [
                        'title' => 'Key categories',
                        'subtitle' => 'Explore fast',
                        'description' => 'Priority content groups that help visitors reach the right offer quickly.',
                        'button_label' => 'View more',
                        'content' => ['items' => [
                            ['title' => 'Residential builds', 'summary' => 'Design, construction and finishing for houses and villas.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                            ['title' => 'Commercial spaces', 'summary' => 'Showrooms, offices and hotels ready for operation.', 'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=80', 'url' => '#du-an'],
                            ['title' => 'Interior upgrades', 'summary' => 'Function, material and living-experience optimization.', 'image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80', 'url' => '#dich-vu'],
                            ['title' => 'Technical consulting', 'summary' => 'Schedule, cost and quality control for each project.', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=80', 'url' => '#lien-he'],
                        ]],
                    ],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu và kinh nghiệm',
                'description' => 'Khối giới thiệu công ty kèm số năm kinh nghiệm.',
                'preview_image' => '/theme-previews/XD0301/about-experience.png',
                'anchor_id' => 'gioi-thieu',
                'media' => ['image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=1000&q=85'],
                'settings' => ['years' => 10, 'cta_url' => '/gioi-thieu'],
                'data' => [
                    'vi' => [
                        'title' => 'Thiết kế và thi công Nhà ở, Tòa nhà văn phòng.',
                        'subtitle' => 'Giới thiệu',
                        'description' => 'ARKIT là công ty chuyên về thiết kế và thi công. Được thành lập và phát triển bởi các kiến trúc sư, kỹ sư nhiều năm kinh nghiệm.',
                        'button_label' => 'Tìm hiểu thêm',
                        'content' => [],
                    ],
                    'en' => [
                        'title' => 'Design and construction for homes and office buildings.',
                        'subtitle' => 'About',
                        'description' => 'ARKIT is a design and construction company founded by experienced architects and engineers.',
                        'button_label' => 'Learn more',
                        'content' => [],
                    ],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dịch vụ nổi bật',
                'description' => 'Danh sách dịch vụ nổi bật dạng card.',
                'preview_image' => '/theme-previews/XD0301/featured-services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => [
                    'source' => 'cms_services',
                    'limit' => 3,
                    'featured_only' => true,
                ],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => ['custom', 'cms_services', 'cms_products', 'cms_posts', 'cms_projects']],
                    'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'ID danh mục khi dùng tin tức/sản phẩm'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung highlight'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Chuyên môn chính',
                        'subtitle' => 'Dịch vụ',
                        'button_label' => 'Tìm hiểu ngay',
                        'content' => ['items' => [
                            ['title' => 'Thiết kế căn hộ chung cư', 'icon' => '▦', 'image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80', 'summary' => 'Tối ưu mặt bằng, ánh sáng, công năng và vật liệu để tạo không gian sống gọn, bền và dễ bảo trì.'],
                            ['title' => 'Thiết kế căn phòng ngủ', 'icon' => '▤', 'image' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=900&q=80', 'summary' => 'Phối hợp màu sắc, hệ tủ, giường và chiếu sáng để phòng ngủ yên tĩnh nhưng vẫn giàu cá tính.'],
                            ['title' => 'Thiết kế nhà có tầng hầm', 'icon' => '⌂', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=80', 'summary' => 'Tư vấn kết cấu, thông gió, chống thấm và giao thông nội bộ cho các nhà phố diện tích hạn chế.'],
                        ]],
                    ],
                    'en' => [
                        'title' => 'Core expertise',
                        'subtitle' => 'Services',
                        'button_label' => 'Learn more',
                        'content' => ['items' => [
                            ['title' => 'Apartment design', 'icon' => '▦', 'image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=900&q=80', 'summary' => 'Optimized layouts, lighting, functionality and materials for compact, durable living spaces.'],
                            ['title' => 'Bedroom design', 'icon' => '▤', 'image' => 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=900&q=80', 'summary' => 'Color, storage, bed and lighting coordination for calm but expressive bedrooms.'],
                            ['title' => 'Basement house design', 'icon' => '⌂', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=80', 'summary' => 'Structural, ventilation, waterproofing and circulation consulting for urban townhouses.'],
                        ]],
                    ],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Lưới nội dung nổi bật',
                'description' => 'Khối dạng mosaic như dự án hoàn thành, có thể lấy dữ liệu từ tin tức, sản phẩm, dịch vụ hoặc dự án.',
                'preview_image' => '/theme-previews/XD0301/content-mosaic.png',
                'anchor_id' => 'noi-dung-noi-bat',
                'dynamic' => true,
                'settings' => [
                    'source' => 'cms_posts',
                    'limit' => 5,
                    'featured_only' => false,
                ],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => ['custom', 'cms_posts', 'cms_products', 'cms_services', 'cms_projects']],
                    'limit' => ['type' => 'number', 'label' => 'Số item hiển thị', 'default' => 5],
                    'category_id' => ['type' => 'number', 'label' => 'ID danh mục khi dùng tin tức/sản phẩm/dịch vụ'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung highlight'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Dự án hoàn thành',
                        'subtitle' => 'Những dự án nổi bật do ENVARCH thực hiện',
                        'description' => '',
                        'button_label' => 'Xem chi tiết',
                        'content' => ['items' => [
                            ['title' => 'Công trình trường mầm non xã Hồng Thái - Bắc', 'summary' => 'Trường mầm non Hồng Thái nằm trải dài ven dòng sông Hồng với 3 khu và 6 điểm...', 'image' => 'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1200&q=85', 'url' => '#lien-he'],
                            ['title' => 'Tòa nhà văn phòng IKON', 'summary' => 'Tòa nhà văn phòng tích hợp cho thuê, phù hợp cho không gian làm việc hiện đại...', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=85', 'url' => '#lien-he'],
                            ['title' => 'Quán cà phê: Làng Coffee', 'summary' => 'Không gian cải tạo mang tinh thần địa phương, chất liệu mộc và trải nghiệm gần gũi...', 'image' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1200&q=85', 'url' => '#lien-he'],
                        ]],
                    ],
                    'en' => [
                        'title' => 'Completed projects',
                        'subtitle' => 'Featured work delivered by ENVARCH',
                        'description' => '',
                        'button_label' => 'View detail',
                        'content' => ['items' => [
                            ['title' => 'Hong Thai kindergarten project', 'summary' => 'A kindergarten campus planned around clear circulation, safe play areas and practical classrooms.', 'image' => 'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1200&q=85', 'url' => '#lien-he'],
                            ['title' => 'IKON office building', 'summary' => 'A flexible office building designed for rental operation and modern workplace needs.', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=85', 'url' => '#lien-he'],
                            ['title' => 'Lang Coffee renovation', 'summary' => 'A local-inspired cafe renovation with warm materials and a memorable guest experience.', 'image' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1200&q=85', 'url' => '#lien-he'],
                        ]],
                    ],
                ],
            ],
            [
                'block_type' => 'project_gallery',
                'label' => 'Dự án hoàn thành',
                'description' => 'Gallery dự án đã hoàn thành.',
                'preview_image' => '/theme-previews/XD0301/project-gallery.png',
                'anchor_id' => 'du-an',
                'dynamic' => true,
                'settings' => [
                    'source' => 'cms_projects',
                    'limit' => 4,
                    'featured_only' => true,
                ],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => ['custom', 'cms_projects', 'cms_services', 'cms_products', 'cms_posts']],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'ID danh muc khi dung tin tuc/san pham'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay highlight'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Hoàn thành mới nhất',
                        'subtitle' => 'Dự án',
                        'content' => ['items' => [
                            ['title' => 'Công Trình Trường Mầm Non', 'tag' => 'Điểm nổi bật của thiết kế là những mái nhà xanh...', 'image' => 'https://images.unsplash.com/photo-1592595896616-c37162298647?auto=format&fit=crop&w=900&q=80'],
                            ['title' => 'Công Trình Nhà Văn Phòng', 'tag' => 'Được xây dựng dựa trên ý tưởng về một công trình mở...', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=80'],
                            ['title' => 'Công Trình Khách Sạn', 'tag' => 'Đội hiện có phối hợp điện, nước, kết cấu và hoàn thiện...', 'image' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=900&q=80'],
                            ['title' => 'Công trình biệt thự nhà vườn', 'tag' => 'Công trình tạo môi trường sống xanh và thoáng...', 'image' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=900&q=80'],
                        ]],
                    ],
                    'en' => ['title' => 'Latest completions', 'subtitle' => 'Projects', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Nhân sự',
                'description' => 'Giới thiệu nhân sự chủ chốt.',
                'preview_image' => '/theme-previews/XD0301/team-members.png',
                'anchor_id' => 'thu-vien',
                'dynamic' => true,
                'settings' => [
                    'source' => 'cms_team_members',
                    'limit' => 4,
                    'featured_only' => true,
                ],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => ['custom', 'cms_team_members']],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Nhân viên kiến trúc sư',
                        'subtitle' => 'Đội ngũ',
                        'content' => ['items' => [
                            ['name' => 'Jhon Castellon', 'role' => 'Giám sát', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=700&q=80'],
                            ['name' => 'José Carpio', 'role' => 'Quản lí', 'image' => 'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?auto=format&fit=crop&w=700&q=80'],
                            ['name' => 'Valentin Lacoste', 'role' => 'Kiến trúc sư', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=700&q=80'],
                            ['name' => 'Kyle Frederick', 'role' => 'Trưởng nhóm', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=700&q=80'],
                        ]],
                    ],
                    'en' => ['title' => 'Architect team', 'subtitle' => 'Team', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonials',
                'label' => 'Khách hàng nhận xét',
                'description' => 'Nhận xét của khách hàng.',
                'preview_image' => '/theme-previews/XD0301/testimonials.png',
                'anchor_id' => 'danh-gia',
                'dynamic' => true,
                'settings' => [
                    'source' => 'cms_testimonials',
                    'limit' => 2,
                    'featured_only' => true,
                ],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => ['custom', 'cms_testimonials']],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Khách hàng nhận xét',
                        'subtitle' => 'Đánh giá',
                        'content' => ['items' => [
                            ['name' => 'Sharah Albert', 'quote' => 'Là đơn vị thiết kế và thi công chuyên nghiệp nhất mà tôi từng hợp tác. Thiết kế nhà rất đẹp, thi công chuẩn từng chi tiết.', 'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=300&q=80'],
                            ['name' => 'Emily Blunt', 'quote' => 'Công ty luôn cung cấp mẫu thiết kế đa dạng theo yêu cầu. Ngôi nhà của chúng tôi đẹp và phù hợp nhu cầu sử dụng của gia đình.', 'image' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&w=300&q=80'],
                        ]],
                    ],
                    'en' => ['title' => 'What clients say', 'subtitle' => 'Reviews', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'partner_logos',
                'label' => 'Logo đối tác',
                'description' => 'Logo đối tác có link.',
                'preview_image' => '/theme-previews/XD0301/partner-logos.png',
                'anchor_id' => 'doi-tac',
                'dynamic' => true,
                'settings' => [
                    'source' => 'cms_partners',
                    'limit' => 6,
                    'featured_only' => true,
                ],
                'settings_schema' => [
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Đối tác',
                        'subtitle' => 'Đối tác',
                        'content' => ['items' => [
                            ['name' => 'HOABINH', 'href' => '#'],
                            ['name' => 'HUNG THINH LAND', 'href' => '#'],
                            ['name' => 'VIET THANG', 'href' => '#'],
                            ['name' => 'TLC', 'href' => '#'],
                            ['name' => 'HUNG PHUOC', 'href' => '#'],
                            ['name' => 'HOA SEN GROUP', 'href' => '#'],
                        ]],
                    ],
                    'en' => ['title' => 'Partners', 'subtitle' => 'Partners', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'landing_contact',
                'label' => 'Khối liên hệ',
                'description' => 'Thông tin liên hệ và form gửi yêu cầu trực tiếp trên landingpage.',
                'preview_image' => '/theme-previews/XD0301/landing-contact.png',
                'anchor_id' => 'lien-he',
                'settings' => [],
                'media' => [],
                'data' => [
                    'vi' => [
                        'title' => 'CÔNG TY CP PHỤ GIA VÀ HOÁ CHẤT DẦU KHÍ',
                        'subtitle' => 'Thông tin liên hệ',
                        'description' => 'Hãy cho chúng tôi biết nhu cầu, quy mô và thời gian dự kiến. Đội ngũ tư vấn sẽ kiểm tra và đề xuất hướng triển khai phù hợp.',
                        'button_label' => 'Gửi liên hệ',
                        'content' => [
                            'form_title' => 'Gửi yêu cầu liên hệ',
                            'note_title' => 'Chia sẻ nhu cầu, chúng tôi tư vấn đúng giải pháp.',
                            'note_text' => 'Hãy gửi thêm địa điểm, diện tích, tiến độ mong muốn hoặc yêu cầu kỹ thuật để đội ngũ chuẩn bị phương án phù hợp ngay từ lần phản hồi đầu tiên.',
                        ],
                    ],
                    'en' => [
                        'title' => 'PETROLEUM ADDITIVES AND CHEMICALS JSC',
                        'subtitle' => 'Contact info',
                        'description' => 'Tell us about your project, timeline and expected scope. We will review and advise the next practical step.',
                        'button_label' => 'Send request',
                        'content' => [
                            'form_title' => 'Send a request',
                            'note_title' => 'Share the essentials, we will shape the right solution.',
                            'note_text' => 'Add your site location, surface area, expected timeline or technical requirements so our team can prepare a practical recommendation.',
                        ],
                    ],
                ],
            ],
            [
                'block_type' => 'footer_contact',
                'label' => 'Footer',
                'description' => 'Footer thông tin, liên hệ và đăng ký nhận tin.',
                'preview_image' => '/theme-previews/XD0301/footer-contact.png',
                'anchor_id' => 'lien-he',
                'data' => [
                    'vi' => [
                        'title' => 'Đăng ký nhận tin',
                        'subtitle' => 'Liên hệ',
                        'description' => 'Arkit là công ty chuyên về thiết kế và thi công. Được thành lập và phát triển bởi kiến trúc sư, kỹ sư nhiều năm kinh nghiệm.',
                        'button_label' => 'Đăng ký',
                        'content' => ['address' => '196 Nguyễn Đình Chiểu, Phường Võ Thị Sáu, Quận 3, TP.HCM', 'email' => 'admin@htvietnam.vn', 'phone' => '0399162342'],
                    ],
                    'en' => [
                        'title' => 'Subscribe',
                        'subtitle' => 'Contact',
                        'description' => 'Arkit is a design and construction company developed by experienced architects and engineers.',
                        'button_label' => 'Subscribe',
                        'content' => ['address' => '196 Nguyen Dinh Chieu, District 3, HCMC', 'email' => 'admin@htvietnam.vn', 'phone' => '0399162342'],
                    ],
                ],
            ],
        ];
    }
}
