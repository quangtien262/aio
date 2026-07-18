<?php

namespace App\Support\LandingPages;

use App\Models\CatalogProduct;
use App\Models\CatalogCategory;
use App\Models\CmsCategory;
use App\Models\CmsMenu;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
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
        return in_array(strtoupper((string) $themeKey), ['XD0301', 'XD0302', 'XD0303', 'XD0304', 'XD0305', 'XD0306', 'XD0307', 'XD0308', 'XD0309', 'XD0310', 'XD0311', 'XD0312', 'XD0313', 'XD0314', 'XD0315', 'XD0318', 'FOOT401', 'XD0320', 'NT501', 'XD321', 'XD0322', 'XD0323', 'XD0324', 'BZ501'], true);
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
            ->filter(fn (LandingPageBlock $block): bool => $block->is_visible && $block->block_type !== 'footer_contact')
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
                'cms_project_categories' => [],
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
            'service_category_slider' => 4,
            'solutions_split_list' => 4,
            'collection_gallery' => 4,
            'business_service_grid' => 4,
            'bizmax_latest_posts' => 3,
            'bizmax_testimonial_carousel' => 3,
            'project_gallery' => 4,
            'featured_services', 'featured_service_list' => 3,
            'completed_projects_list' => 5,
            'testimonial_showcase' => 3,
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

        if (in_array($block->block_type, ['featured_services', 'featured_service_list', 'completed_projects_list', 'content_mosaic', 'content_showcase', 'project_gallery', 'service_category_slider', 'solutions_split_list', 'collection_gallery', 'business_service_grid', 'bizmax_latest_posts'], true)) {
            $defaultSource = match ($block->block_type) {
                'content_mosaic' => 'cms_posts',
                'content_showcase' => 'cms_projects',
                'project_gallery' => 'cms_projects',
                'bizmax_latest_posts' => 'cms_posts',
                'solutions_split_list', 'collection_gallery' => 'cms_services',
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

        if (in_array($block->block_type, ['testimonials', 'testimonial_showcase', 'bizmax_testimonial_carousel'], true)) {
            if ($block->block_type === 'testimonial_showcase') {
                return $this->cmsTestimonialItems($settings, $limit, $locale, $block->landingPage?->website_key);
            }

            if ($block->block_type === 'bizmax_testimonial_carousel') {
                return $this->cmsTestimonialItems($settings, $limit, $locale, $block->landingPage?->website_key);
            }

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
            'catalog_categories', 'cms_categories', 'cms_service_categories', 'cms_project_categories' => $this->featuredCategoryItems($settings, $limit, $locale, $websiteKey),
            'cms_menus' => $this->cmsMenuItems($settings, $limit),
            default => $this->contentSourceItems([...$settings, 'source' => $defaultSource], $defaultSource, $limit, $locale, $websiteKey),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsMenuItems(array $settings, int $limit): array
    {
        if (! Schema::hasTable('cms_menus')) {
            return [];
        }

        $location = trim((string) ($settings['menu_location'] ?? 'primary-navigation')) ?: 'primary-navigation';
        $menu = CmsMenu::query()->where('location', $location)->first();

        return collect($menu?->items ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
            ->take($limit)
            ->values()
            ->map(function (array $item, int $index): array {
                $title = trim((string) ($item['label'] ?? $item['title'] ?? ''));
                $children = collect($item['children'] ?? [])
                    ->filter(fn (mixed $child): bool => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))
                    ->map(fn (array $child): string => (string) ($child['label'] ?? $child['title'] ?? ''))
                    ->filter()
                    ->implode(', ');

                return [
                    'title' => $title,
                    'summary' => $children ?: (string) ($item['description'] ?? $item['summary'] ?? ''),
                    'image' => (string) ($item['image'] ?? $item['image_url'] ?? $this->fallbackContentImage()),
                    'alt' => (string) ($item['alt'] ?? $title),
                    'url' => (string) ($item['url'] ?? $item['href'] ?? '#'),
                    'button_label' => (string) ($item['button_label'] ?? ''),
                ];
            })
            ->all();
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

        if ($source === 'cms_project_categories') {
            if (! Schema::hasTable('cms_project_categories')) {
                return [];
            }

            return CmsProjectCategory::query()
                ->where('is_active', true)
                ->withCount(['projects' => fn (Builder $query) => $query->where('status', 'published')])
                ->orderByDesc('projects_count')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->take($limit)
                ->get()
                ->map(function (CmsProjectCategory $category, int $index) use ($resolvedWebsiteKey, $locale): array {
                    $title = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project_category.%d.name', $category->id), $category->name);
                    $summary = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project_category.%d.description', $category->id), $category->description);

                    return [
                        'title' => $title,
                        'summary' => $summary,
                        'image' => $category->image_url ?: $this->fallbackCategoryImage($index),
                        'alt' => $title,
                        'icon' => Str::upper(Str::substr((string) $title, 0, 1)),
                        'count_label' => (int) $category->projects_count > 0 ? $category->projects_count.' dự án' : null,
                        'url' => route('site.projects.category', ['locale' => $locale, 'slug' => $category->slug]),
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
            $projectImages = $project->images
                ->filter(fn ($image): bool => filled($image->image_url))
                ->map(fn ($image): array => [
                    'image' => $image->image_url,
                    'image_url' => $image->image_url,
                    'alt' => $image->alt_text ?: $project->title,
                    'caption' => $image->caption,
                    'is_featured' => (bool) $image->is_featured,
                ])
                ->values()
                ->all();

            return [
                'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.title', $project->id), $project->title),
                'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.summary', $project->id), $project->summary),
                'tag' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_project.%d.summary', $project->id), $project->summary),
                'image' => $featuredImage?->image_url ?: $this->fallbackContentImage(),
                'alt' => $featuredImage?->alt_text ?: $project->title,
                'images' => $projectImages,
                'gallery_images' => $projectImages,
                'url' => route('site.projects.show', ['slug' => $project->slug]),
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
        return match (strtoupper($themeKey)) {
            'XD0318' => $this->xd0318DefaultBlocks(),
            'XD0315' => $this->xd0315DefaultBlocks(),
            'XD0314' => $this->xd0314DefaultBlocks(),
            'XD0313' => $this->xd0313DefaultBlocks(),
            'FOOT401' => $this->foot401DefaultBlocks(),
            'XD0320' => $this->xd0320DefaultBlocks(),
            'NT501' => $this->nt501DefaultBlocks(),
            'XD321' => $this->xd321DefaultBlocks(),
            'XD0322' => $this->xd0322DefaultBlocks(),
            'XD0323' => $this->xd0323EuroFarmDefaultBlocks(),
            'XD0324' => $this->xd0324DefaultBlocks(),
            'BZ501' => $this->bz501DefaultBlocks(),
            'XD0312' => $this->xd0312DefaultBlocks(),
            'XD0311' => $this->xd0311DefaultBlocks(),
            'XD0310' => $this->xd0310DefaultBlocks(),
            'XD0309' => $this->xd0309DefaultBlocks(),
            'XD0308' => $this->xd0308DefaultBlocks(),
            'XD0307' => $this->xd0307DefaultBlocks(),
            'XD0306' => $this->xd0306DefaultBlocks(),
            'XD0305' => $this->xd0305DefaultBlocks(),
            'XD0304' => $this->xd0304DefaultBlocks(),
            'XD0303' => $this->xd0303DefaultBlocks(),
            'XD0302' => $this->xd0302DefaultBlocks(),
            default => $this->xd0301DefaultBlocks(),
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function bz501DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_categories', 'label' => 'Danh mục tin tức'],
            ['value' => 'cms_project_categories', 'label' => 'Danh mục dự án'],
        ];

        $sourceSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
            'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'ID danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero slider HaluFin',
                'description' => 'Header, slide ảnh lớn và lời giới thiệu chính ở đầu trang.',
                'preview_image' => '/theme-previews/BZ501/preview-bz501.png',
                'anchor_id' => 'trang-chu',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'bz501-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                    'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Xây nhà trọn gói',
                        'subtitle' => 'Thiết kế & thi công',
                        'description' => 'Cung cấp dịch vụ thi công trọn gói các công trình dân dụng với quy trình linh hoạt và đội ngũ chuyên nghiệp.',
                        'button_label' => 'Liên hệ báo giá',
                        'content' => ['slides' => [
                            ['title' => 'Xây nhà trọn gói', 'kicker' => 'Thiết kế & thi công', 'summary' => 'Cung cấp dịch vụ thi công trọn gói các công trình dân dụng với quy trình linh hoạt và đội ngũ chuyên nghiệp.', 'button_label' => 'Liên hệ báo giá', 'link_url' => '#lien-he', 'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=1920&q=85'],
                            ['title' => 'Tư vấn kinh doanh hiệu quả', 'kicker' => 'Giải pháp vận hành', 'summary' => 'Đồng hành từ chiến lược, thiết kế dịch vụ đến triển khai thực tế cho từng mô hình doanh nghiệp.', 'button_label' => 'Tìm hiểu thêm', 'link_url' => '#gioi-thieu', 'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1920&q=85'],
                        ]],
                    ],
                    'en' => [
                        'title' => 'Complete business solutions',
                        'subtitle' => 'Design & delivery',
                        'description' => 'End-to-end consulting and delivery services for ambitious teams.',
                        'button_label' => 'Request a quote',
                        'content' => ['slides' => []],
                    ],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Thẻ cam kết dịch vụ',
                'description' => 'Danh sách item chất lượng dịch vụ hoặc danh mục dịch vụ, hiển thị dạng trượt ngang.',
                'preview_image' => '/theme-previews/BZ501/preview-bz501.png',
                'anchor_id' => 'cam-ket',
                'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 6],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Cam kết dịch vụ',
                        'subtitle' => 'Năng lực',
                        'description' => '',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Tư vấn xây dựng', 'summary' => 'Đội ngũ chuyên gia đồng hành từ khảo sát, lập kế hoạch đến triển khai.', 'icon' => 'chart', 'color' => '#ff1748', 'url' => '#dich-vu'],
                            ['title' => 'Cam kết chất lượng', 'summary' => 'Quy trình kiểm soát rõ ràng, cập nhật thường xuyên theo nhu cầu thực tế.', 'icon' => 'piggy', 'color' => '#05bdb4', 'url' => '#dich-vu'],
                            ['title' => 'Chính sách ưu đãi', 'summary' => 'Linh hoạt cho khách hàng thân thiết, đối tác và các chương trình cộng đồng.', 'icon' => 'saving', 'color' => '#ffc400', 'url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Service commitments', 'subtitle' => 'Capabilities', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu công ty',
                'description' => 'Khối giới thiệu ngắn gọn, nội dung do người dùng nhập.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'gioi-thieu',
                'dynamic' => false,
                'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => [
                        'title' => 'Chúng tôi giúp khách hàng đạt được mục tiêu kinh doanh của họ',
                        'subtitle' => 'Giới thiệu',
                        'description' => 'Tài chính chỉ có thể vững mạnh khi đội ngũ của chúng tôi đều hành công việc kinh doanh của họ.',
                        'content' => [
                            'image' => 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?auto=format&fit=crop&w=1100&q=85',
                            'mini_image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=500&q=85',
                            'items' => ['Kế hoạch kinh doanh tốt', 'Cải thiện kinh doanh', 'Dịch vụ chúng tôi cung cấp'],
                            'body' => 'Đội ngũ chuyên gia nhiều năm kinh nghiệm trực tiếp tư vấn, tổ chức, điều hành và giám sát dự án, đảm bảo chuyên nghiệp, nhanh chóng, chính xác.',
                            'author_name' => 'Mr. Robert Smith',
                            'author_role' => 'CEO & Founder',
                            'author_image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=80',
                        ],
                    ],
                    'en' => ['title' => 'We help clients reach their business goals', 'subtitle' => 'About', 'description' => '', 'content' => []],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dịch vụ nổi bật',
                'description' => 'Card dịch vụ có thể lấy từ nhiều nguồn dữ liệu hoặc nhập thủ công.',
                'preview_image' => '/theme-previews/BZ501/preview-bz501.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Những dịch vụ chúng tôi cung cấp cho khách hàng của mình',
                        'subtitle' => 'Dịch vụ',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Tư vấn xây dựng', 'summary' => 'Khảo sát, thiết kế và tổ chức đấu thầu để mua sắm thiết bị, xây lắp công trình.', 'image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Thiết kế kiến trúc', 'summary' => 'Tối ưu công năng, thẩm mỹ và ngân sách cho từng loại công trình.', 'image' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Ký kết chung', 'summary' => 'Hợp tác minh bạch, bình đẳng, đảm bảo tiến độ theo thỏa thuận.', 'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                        ]],
                    ],
                    'en' => ['title' => 'Services we provide', 'subtitle' => 'Services', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'content_showcase',
                'label' => 'Chia sẻ mục tiêu',
                'description' => 'Khối giới thiệu/chia sẻ ngắn gọn do người dùng nhập.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'muc-tieu',
                'dynamic' => false,
                'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => [
                        'title' => 'Tạo một doanh nghiệp với sự liêm chính đích thực',
                        'subtitle' => 'Mục tiêu',
                        'description' => 'Không chỉ đem những điều tốt đẹp đến cho khách hàng, chúng tôi còn tạo ra nhiều cơ hội thành công cho đội ngũ nhân viên của mình.',
                        'content' => [
                            'image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1000&q=85',
                            'image_secondary' => 'https://images.unsplash.com/photo-1556761175-4b46a572b786?auto=format&fit=crop&w=600&q=85',
                            'items' => [
                                ['title' => 'Tầm nhìn chiến lược', 'summary' => 'Từng bước trở thành một trong những công ty được tôn trọng hàng đầu trong lĩnh vực của mình.', 'icon' => 'pie'],
                                ['title' => 'Sứ mệnh', 'summary' => 'Đem lại giải pháp tốt nhất nhằm đáp ứng kỳ vọng của khách hàng và mục tiêu dài hạn.', 'icon' => 'growth'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Build a company with integrity', 'subtitle' => 'Mission', 'content' => []],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Nghiên cứu / trường hợp',
                'description' => 'Slider ngang có thể lấy từ sản phẩm, tin tức, dịch vụ, dự án hoặc các danh mục.',
                'preview_image' => '/theme-previews/BZ501/preview-bz501.png',
                'anchor_id' => 'nghien-cuu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Chúng tôi là chuyên gia tư vấn cho nhiều trường hợp',
                        'subtitle' => 'Nghiên cứu',
                        'content' => ['items' => [
                            ['title' => 'Thiết kế nội thất hiện đại', 'tag' => 'Thiết kế nội thất', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Thi công nhà phố trọn gói', 'tag' => 'Thi công nhà phố', 'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Các dịch vụ giải trí', 'tag' => 'Thi công nhà hàng, cafe', 'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Căn hộ nội thất cao cấp', 'tag' => 'Thi công căn hộ', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Biệt thự là loại hình cao cấp', 'tag' => 'Thi công biệt thự', 'image' => 'https://images.unsplash.com/photo-1524758631624-e2822e304c36?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                        ]],
                    ],
                    'en' => ['title' => 'Case studies we consult for', 'subtitle' => 'Research', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'logistics_feature_panel',
                'label' => 'Lý do chọn chúng tôi',
                'description' => 'Khối chia sẻ nền tối, nội dung người dùng nhập.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'ly-do',
                'dynamic' => false,
                'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => [
                        'title' => 'Để có hiệu suất xuất sắc, chúng tôi tập trung vào quan trọng.',
                        'subtitle' => 'Lý do chọn chúng tôi',
                        'description' => 'Tính trung thực mang lại sự tin cậy trong mọi mối quan hệ, giao dịch, với đồng nghiệp, khách hàng, đối tác.',
                        'content' => [
                            'image' => 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=85',
                            'background_image' => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&w=1920&q=85',
                            'video_image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=600&q=85',
                            'support_label' => '12/7 Support Team',
                            'phone' => '+123-55-05800',
                            'items' => ['Năm 2021: Chứng nhận ISO 45001:2018', 'Kinh doanh là kế hoạch tốt nhất', 'Làm thế nào để cải thiện kinh doanh', 'Năm 2021: Chứng nhận ISO 14001:2015', 'Dịch vụ chúng tôi cung cấp', 'Bằng khen trong công tác An toàn'],
                        ],
                    ],
                    'en' => ['title' => 'We focus on what matters', 'subtitle' => 'Why choose us', 'content' => []],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Đội ngũ nhân sự',
                'description' => 'Danh sách nhân sự, có thể lấy từ CMS hoặc nhập thủ công.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'doi-ngu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_team_members', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_team_members', 'label' => 'Nhân sự CMS']]],
                    'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Luôn luôn tận tâm và chuyên nghiệp',
                        'subtitle' => 'Đội ngũ nhân viên',
                        'content' => ['items' => [
                            ['name' => 'Hoàng Văn Sơn', 'role' => 'Tổng giám đốc', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=800&q=85', 'facebook_url' => '#'],
                            ['name' => 'Lê Thị Thúy', 'role' => 'Trưởng phòng nhân sự', 'image' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=800&q=85', 'instagram_url' => '#'],
                            ['name' => 'Nguyễn Văn Anh', 'role' => 'Giám đốc thi công', 'image' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=800&q=85', 'facebook_url' => '#'],
                        ]],
                    ],
                    'en' => ['title' => 'Dedicated and professional', 'subtitle' => 'Team', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_products',
                'label' => 'Sản phẩm nổi bật',
                'description' => 'Slider sản phẩm hoặc nguồn dữ liệu tương đương.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'san-pham',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Nội thất đảm bảo chất lượng số 1 Việt Nam',
                        'subtitle' => 'Sản phẩm',
                        'content' => ['items' => [
                            ['title' => 'Bàn Trang Điểm Gỗ Đa Năng', 'price' => '3.690.000đ', 'rating' => 4, 'image' => 'https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Giường Ngủ Bọc Nệm', 'price' => '3.290.000đ', 'rating' => 0, 'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Giường Ngủ Gỗ VLINE 601', 'price' => '4.290.000đ', 'rating' => 4, 'image' => 'https://images.unsplash.com/photo-1617325247661-675ab4b64ae2?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                            ['title' => 'Bàn Sofa - Bàn Cafe - Bàn Trà Gỗ Thông', 'price' => '1.290.000đ', 'rating' => 0, 'image' => 'https://images.unsplash.com/photo-1532372320572-cda25653a26d?auto=format&fit=crop&w=900&q=85', 'url' => '#lien-he'],
                        ]],
                    ],
                    'en' => ['title' => 'Quality furniture products', 'subtitle' => 'Products', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'partner_logos',
                'label' => 'Đối tác',
                'description' => 'Dải logo đối tác.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'doi-tac',
                'dynamic' => true,
                'settings' => ['source' => 'cms_partners', 'limit' => 6],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_partners', 'label' => 'Đối tác CMS']]],
                    'limit' => ['type' => 'number', 'label' => 'Số logo hiển thị'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Đối tác',
                        'content' => ['items' => [
                            ['name' => 'Zikzac', 'logo' => ''],
                            ['name' => 'SinTech', 'logo' => ''],
                            ['name' => 'Recyclepro', 'logo' => ''],
                            ['name' => 'WOWAVE', 'logo' => ''],
                            ['name' => 'Octakle', 'logo' => ''],
                            ['name' => '7Tekart', 'logo' => ''],
                        ]],
                    ],
                    'en' => ['title' => 'Partners', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'latest_posts',
                'label' => 'Tin tức mới nhất',
                'description' => 'Danh sách bài viết mới hoặc nguồn dữ liệu khác theo cấu hình.',
                'preview_image' => '/theme-previews/BZ501/cover-bz501.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Những bài viết mới nhất',
                        'subtitle' => 'Tin tức',
                        'button_label' => 'Đọc thêm',
                        'content' => ['items' => [
                            ['title' => 'Bình Định tham gia chuỗi công viên phần mềm Quang Trung', 'summary' => 'Thủ tướng Chính phủ đã có quyết định kết nạp trung tâm công nghệ thông tin...', 'date' => '02/04/2023', 'image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                            ['title' => 'Thương mại điện tử là động lực thúc đẩy nền kinh tế số', 'summary' => 'Tại sự kiện, vai trò là cơ quan đồng hành phát triển và công bố báo cáo ngành...', 'date' => '02/04/2023', 'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                            ['title' => 'Cộng đồng doanh nghiệp Việt tại San Jose gặp khó', 'summary' => 'Đại dịch đã khiến nhiều doanh nghiệp nhỏ cần tái cấu trúc chiến lược vận hành...', 'date' => '02/04/2023', 'image' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                        ]],
                    ],
                    'en' => ['title' => 'Latest posts', 'subtitle' => 'News', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0324DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_categories', 'label' => 'Danh mục tin tức'],
        ];

        $sourceSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
            'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'ID danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero WolfArch',
                'description' => 'Hero toàn màn hình với header trong suốt và ảnh nền lớn.',
                'preview_image' => '/theme-previews/XD0324/preview-xd0324.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0324-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Thiết kế độc đáo',
                        'subtitle' => 'Sáng tạo không giới hạn',
                        'description' => 'Mỗi dự án là một câu chuyện, mỗi thiết kế là một tác phẩm nghệ thuật độc lập.',
                        'button_label' => 'Khám phá',
                        'content' => [
                            'slides' => [
                                ['title' => 'Thiết kế độc đáo', 'subtitle' => 'Sáng tạo không giới hạn', 'summary' => 'Mỗi dự án là một câu chuyện, mỗi thiết kế là một tác phẩm nghệ thuật độc lập.', 'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#gioi-thieu'],
                                ['title' => 'Không gian sống có chiều sâu', 'subtitle' => 'WolfArch Studio', 'summary' => 'Chúng tôi kết hợp kiến trúc, nội thất và ánh sáng để tạo nên nơi chốn giàu cảm xúc.', 'image' => 'https://images.unsplash.com/photo-1600210491892-03d54c0aaf87?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#du-an'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Distinctive design', 'subtitle' => 'Unlimited creativity', 'description' => 'Every project is a story and every space is a crafted work.', 'button_label' => 'Explore', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Về chúng tôi',
                'description' => 'Giới thiệu công ty với cụm ảnh collage và chỉ số nhanh.',
                'preview_image' => '/theme-previews/XD0324/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#du-an'],
                'media' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1493857671505-72967e2e2760?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85',
                    ],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Tạo ra những không gian đẹp',
                        'subtitle' => 'Về chúng tôi',
                        'description' => 'Với hơn 15 năm kinh nghiệm trong lĩnh vực kiến trúc, chúng tôi đã tạo ra hàng trăm dự án ấn tượng trên toàn quốc. Mỗi dự án là sự kết hợp hài hòa giữa thẩm mỹ, chức năng và bền vững.',
                        'button_label' => 'Tìm hiểu thêm',
                        'content' => ['items' => [
                            ['title' => '500+', 'summary' => 'Dự án hoàn thành'],
                            ['title' => '50+', 'summary' => 'Giải thưởng'],
                            ['title' => '100%', 'summary' => 'Hài lòng khách hàng'],
                        ]],
                    ],
                    'en' => ['title' => 'Creating beautiful spaces', 'subtitle' => 'About us', 'description' => 'More than 15 years crafting memorable architecture and interiors.', 'button_label' => 'Learn more', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Giá trị chúng tôi mang lại',
                'description' => 'Khối lý do chọn WolfArch với icon và mosaic ảnh.',
                'preview_image' => '/theme-previews/XD0324/values.png',
                'anchor_id' => 'gia-tri',
                'settings' => ['source' => 'custom', 'limit' => 4],
                'settings_schema' => $sourceSchema,
                'media' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600573472550-8090b5e0745e?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85',
                    ],
                ],
                'data' => [
                    'vi' => ['title' => 'Khám phá <em>giá trị chúng tôi mang lại</em> trong mỗi dự án', 'subtitle' => 'Tại sao chọn chúng tôi', 'description' => 'Chúng tôi kết hợp sự sáng tạo, quy trình tối ưu và kinh nghiệm chuyên môn để mang đến những dự án chất lượng cao, bền vững và đạt chuẩn quốc tế.', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Kiến tạo Dấu ấn Độc bản', 'summary' => 'Kiến trúc không chỉ là sự sắp đặt của gạch đá, mà là bản giao hưởng giữa không gian, ánh sáng và cảm xúc.', 'icon' => 'fa-regular fa-lightbulb'],
                        ['title' => 'Vẻ đẹp Thách thức Thời gian', 'summary' => 'Một công trình hoàn mỹ không chỉ lộng lẫy ở hiện tại mà còn là một di sản vững bền cho mai sau.', 'icon' => 'fa-regular fa-gem'],
                        ['title' => 'Tinh hoa từ Tâm huyết & Kinh nghiệm', 'summary' => 'Đằng sau sự hoàn hảo là những trái tim nóng và khối óc tinh anh của đội ngũ kiến trúc sư.', 'icon' => 'fa-solid fa-people-group'],
                        ['title' => 'Hành trình Đồng hành Trọn vẹn', 'summary' => 'Xây nhà là hành trình kiến tạo hạnh phúc, và chúng tôi vinh dự được trở thành người bạn tri kỷ.', 'icon' => 'fa-solid fa-handshake'],
                    ]]],
                    'en' => ['title' => 'Discover the values we bring to every project', 'subtitle' => 'Why choose us', 'description' => 'Creative thinking, refined workflow and deep practice in every project.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Dấu ấn độc bản',
                'description' => 'Biến thể khối giá trị để người dùng sửa nhanh nội dung và ảnh.',
                'preview_image' => '/theme-previews/XD0324/signature.png',
                'anchor_id' => 'dau-an',
                'settings' => ['source' => 'custom', 'limit' => 4],
                'settings_schema' => $sourceSchema,
                'media' => [
                    'images' => [
                        'https://images.unsplash.com/photo-1600566752355-35792bedcfea?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600607687644-c7171b42498f?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600573472591-ee6b68d14c68?auto=format&fit=crop&w=900&q=85',
                        'https://images.unsplash.com/photo-1600585154363-67eb9e2e2099?auto=format&fit=crop&w=900&q=85',
                    ],
                ],
                'data' => [
                    'vi' => ['title' => 'Khám phá <em>giá trị chúng tôi mang lại</em> trong mỗi dự án', 'subtitle' => 'Tại sao chọn chúng tôi', 'description' => 'Từ ý tưởng đến thi công, đội ngũ chuyên gia luôn đồng hành để mỗi công trình đều vượt trên mong đợi.', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Thiết kế có bản sắc', 'summary' => 'Mỗi không gian được phát triển theo cá tính và lối sống riêng của chủ nhân.', 'icon' => 'fa-regular fa-object-group'],
                        ['title' => 'Vật liệu được tuyển chọn', 'summary' => 'Ưu tiên chất liệu bền vững, tinh tế và phù hợp với nhịp sống thực tế.', 'icon' => 'fa-solid fa-layer-group'],
                        ['title' => 'Quản lý chặt chẽ', 'summary' => 'Quy trình minh bạch từ khảo sát, concept, bản vẽ đến bàn giao.', 'icon' => 'fa-solid fa-list-check'],
                        ['title' => 'Bàn giao trọn vẹn', 'summary' => 'Không gian hoàn thiện đồng bộ từ kiến trúc đến nội thất.', 'icon' => 'fa-solid fa-key'],
                    ]]],
                    'en' => ['title' => 'A signature in every project', 'subtitle' => 'Our values', 'description' => 'From concept to handover, every detail is intentional.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dịch vụ của chúng tôi',
                'description' => 'Slider ảnh ngang cho dịch vụ, hỗ trợ kéo chuột.',
                'preview_image' => '/theme-previews/XD0324/services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 8, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Kiến tạo <em>Sự khác biệt</em> - Gói trọn mọi Ý tưởng', 'subtitle' => 'Dịch vụ của chúng tôi', 'description' => 'Thổi hồn vào không gian với dịch vụ thiết kế nội thất độc bản, nơi mọi mong muốn của bạn được lắng nghe và hiện thực hóa.', 'button_label' => 'Xem thêm dịch vụ', 'content' => ['items' => []]],
                    'en' => ['title' => 'Create difference - complete every idea', 'subtitle' => 'Our services', 'description' => 'Bespoke design services from concept to final installation.', 'button_label' => 'More services', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'project_gallery',
                'label' => 'Dự án nổi bật',
                'description' => 'Slider ảnh ngang cho dự án, hỗ trợ kéo chuột.',
                'preview_image' => '/theme-previews/XD0324/projects.png',
                'anchor_id' => 'du-an',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Dấu ấn Sáng tạo - <em>Tuyên ngôn Phong cách</em>', 'subtitle' => 'Dự án nổi bật', 'description' => 'Khám phá bộ sưu tập đa sắc màu của chúng tôi: nơi hội tụ từ những tổ ấm bình yên đến không gian thương mại đẳng cấp.', 'button_label' => 'Xem thêm Dự án', 'content' => ['items' => []]],
                    'en' => ['title' => 'Creative marks - style statements', 'subtitle' => 'Featured projects', 'description' => 'A curated collection of homes, villas and commercial spaces.', 'button_label' => 'More projects', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'process_steps',
                'label' => 'Quy trình thực hiện',
                'description' => 'Khối nền tối mô tả quy trình 4 bước.',
                'preview_image' => '/theme-previews/XD0324/process.png',
                'anchor_id' => 'quy-trinh',
                'data' => [
                    'vi' => ['title' => 'Từ Bản vẽ Sơ phác đến <em>Công trình Hoàn thiện</em>', 'subtitle' => 'Quy trình thực hiện', 'description' => 'Quy trình thiết kế khép kín là kim chỉ nam dẫn lối cho bạn: từ bước phác thảo concept, phát triển ý tưởng cho đến hiện thực hóa không gian sống đẳng cấp.', 'button_label' => '', 'content' => ['items' => [
                        ['title' => '01. Khơi nguồn Cảm hứng & Thấu hiểu', 'summary' => 'Chúng tôi bắt đầu bằng việc lắng nghe mong muốn sâu kín và phong cách sống của bạn.', 'icon' => 'fa-regular fa-lightbulb'],
                        ['title' => '02. Phác họa Giấc mơ & Concept', 'summary' => 'Kiến trúc sư vẽ nên giấc mơ bằng ngôn ngữ hình khối và ánh sáng.', 'icon' => 'fa-regular fa-map'],
                        ['title' => '03. Tinh chỉnh Chi tiết & Kỹ thuật', 'summary' => 'Chuyển hóa ý tưởng thành hệ thống bản vẽ kỹ thuật chính xác.', 'icon' => 'fa-solid fa-compass-drafting'],
                        ['title' => '04. Hiện thực hóa & Trao gửi', 'summary' => 'Đội ngũ thi công biến bản vẽ thành công trình vững chãi và tinh tế.', 'icon' => 'fa-solid fa-house-lock'],
                    ]]],
                    'en' => ['title' => 'From sketch to complete work', 'subtitle' => 'Our process', 'description' => 'A closed design workflow from concept to finished space.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'latest_posts',
                'label' => 'Tin mới nhất',
                'description' => 'Slider ảnh ngang cho tin tức/xu hướng, hỗ trợ kéo chuột.',
                'preview_image' => '/theme-previews/XD0324/posts.png',
                'anchor_id' => 'xu-huong',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 8, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Góc nhìn & <em>Xu hướng</em>', 'subtitle' => 'Tin mới nhất', 'description' => 'Khởi đầu hành trình sáng tạo không gian sống. Khám phá kho kiến thức đa dạng, bao gồm bí quyết thiết kế thực tiễn và xu hướng nội thất dẫn đầu thị trường.', 'button_label' => '', 'content' => ['items' => []]],
                    'en' => ['title' => 'Insights & trends', 'subtitle' => 'Latest news', 'description' => 'Practical design knowledge and leading interior trends.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0323EuroFarmDefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_categories', 'label' => 'Danh mục tin tức'],
        ];

        $sourceSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
            'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'ID danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
        ];

        $farmHero = 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=2200&q=90';
        $vegetables = 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=85';
        $tractor = 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?auto=format&fit=crop&w=900&q=85';
        $farmer = 'https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?auto=format&fit=crop&w=900&q=85';

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero Euro Farm',
                'description' => 'Header, hero image slider và các item chất lượng dịch vụ chạy ngang.',
                'preview_image' => '/theme-previews/XD0323/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0323-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Thực phẩm hữu cơ tươi chất lượng cao',
                        'subtitle' => 'Sản phẩm nông nghiệp tự nhiên',
                        'description' => 'Nguồn nông sản sạch được tuyển chọn từ trang trại hữu cơ và giao đến khách hàng mỗi ngày.',
                        'button_label' => 'Xem ngay',
                        'content' => [
                            'slides' => [
                                ['title' => 'Thực phẩm hữu cơ tươi chất lượng cao', 'summary' => 'Sản phẩm nông nghiệp tự nhiên', 'button_label' => 'Xem ngay', 'image' => $farmHero, 'link_url' => '#san-pham'],
                                ['title' => 'Nông sản sạch cho bữa ăn xanh', 'summary' => 'Từ trang trại đến bàn ăn', 'button_label' => 'Khám phá', 'image' => 'https://images.unsplash.com/photo-1492496913980-501348b61469?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#danh-muc'],
                            ],
                            'items' => [
                                ['title' => 'Giao hàng nhanh', 'summary' => 'Giao hàng nhanh chóng trên toàn quốc', 'icon' => 'fa-solid fa-truck-fast'],
                                ['title' => 'Chính sách đổi trả', 'summary' => 'Đổi trả nhanh chóng, linh hoạt', 'icon' => 'fa-solid fa-rotate-left'],
                                ['title' => 'Hỗ trợ trực tuyến', 'summary' => 'Hỗ trợ nhanh chóng 24/7', 'icon' => 'fa-solid fa-headset'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Fresh organic food with premium quality', 'subtitle' => 'Natural farm produce', 'description' => 'Clean produce selected from organic farms and delivered daily.', 'button_label' => 'Shop now', 'content' => ['slides' => [], 'items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu công ty',
                'description' => 'Giới thiệu ngắn gọn về Euro Farm với ảnh lá và ảnh tròn.',
                'preview_image' => '/theme-previews/XD0323/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#san-pham'],
                'media' => ['image' => 'https://images.unsplash.com/photo-1492496913980-501348b61469?auto=format&fit=crop&w=1000&q=85', 'secondary_image' => $tractor],
                'data' => [
                    'vi' => [
                        'title' => 'Thực phẩm hữu cơ & tốt cho sức khỏe',
                        'subtitle' => 'Về Euro Farm',
                        'description' => 'Euro Farm là doanh nghiệp nông nghiệp tiên phong chuyên sản xuất và cung cấp thực phẩm hữu cơ, an toàn và tốt cho sức khỏe. Chúng tôi áp dụng quy trình canh tác hiện đại theo tiêu chuẩn châu Âu, nói không với hóa chất và chất bảo quản.',
                        'button_label' => 'Xem thêm',
                        'content' => ['phone' => '1900 6750', 'items' => [
                            ['title' => 'Nông nghiệp và Thực phẩm', 'summary' => '', 'icon' => 'fa-solid fa-seedling'],
                            ['title' => 'Rau củ và trái cây', 'summary' => '', 'icon' => 'fa-solid fa-carrot'],
                        ]],
                    ],
                    'en' => ['title' => 'Organic food for better health', 'subtitle' => 'About Euro Farm', 'description' => 'Euro Farm produces safe organic food using modern farming standards.', 'button_label' => 'Learn more', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'stats_strip',
                'label' => 'Con số thống kê',
                'description' => 'Khối số liệu ấn tượng về trang trại.',
                'preview_image' => '/theme-previews/XD0323/stats.png',
                'anchor_id' => 'thong-ke',
                'data' => [
                    'vi' => ['title' => 'Những con số ấn tượng về trang trại của chúng tôi', 'subtitle' => 'Hiểu rõ hơn về chúng tôi', 'description' => 'Mỗi con số đều thể hiện hành trình phát triển bền vững của chúng tôi, từ những cánh đồng hữu cơ đến hàng nghìn sản phẩm sạch trao tận tay người tiêu dùng.', 'button_label' => '', 'content' => ['items' => [
                        ['title' => '250+', 'summary' => 'Sản phẩm nông nghiệp', 'icon' => 'fa-solid fa-box-open'],
                        ['title' => '690+', 'summary' => 'Dự án đã hoàn thành', 'icon' => 'fa-brands fa-pagelines'],
                        ['title' => '460+', 'summary' => 'Khách hàng hài lòng', 'icon' => 'fa-regular fa-face-smile'],
                        ['title' => '1200+', 'summary' => 'Expert Farmers', 'icon' => 'fa-solid fa-user-tie'],
                    ]]],
                    'en' => ['title' => 'Impressive numbers from our farm', 'subtitle' => 'Know us better', 'description' => 'Numbers that reflect our sustainable farming journey.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Nông sản phân theo loại',
                'description' => 'Slider danh mục có thể lấy từ sản phẩm, tin tức, dịch vụ, dự án hoặc danh mục.',
                'preview_image' => '/theme-previews/XD0323/categories.png',
                'anchor_id' => 'danh-muc',
                'dynamic' => true,
                'settings' => ['source' => 'catalog_categories', 'limit' => 8, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Nông sản phân theo loại', 'subtitle' => 'Danh mục', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Trái Cây Việt', 'image' => 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?auto=format&fit=crop&w=500&q=85', 'url' => '#san-pham'],
                        ['title' => 'Rau lá hữu cơ', 'image' => 'https://images.unsplash.com/photo-1515543904379-3d757afe72e4?auto=format&fit=crop&w=500&q=85', 'url' => '#san-pham'],
                        ['title' => 'Củ Quả hữu cơ', 'image' => 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?auto=format&fit=crop&w=500&q=85', 'url' => '#san-pham'],
                    ]]],
                    'en' => ['title' => 'Farm produce by category', 'subtitle' => 'Categories', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'business_service_grid',
                'label' => 'Nông sản phẩm nổi bật',
                'description' => 'Grid sản phẩm hoặc nội dung động có thể đổi nguồn.',
                'preview_image' => '/theme-previews/XD0323/products.png',
                'anchor_id' => 'san-pham',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Nông sản phẩm nổi bật', 'subtitle' => 'Sản phẩm', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Bắp ngọt hữu cơ 500gr', 'summary' => '46.000đ', 'price' => '46.000đ', 'image' => 'https://images.unsplash.com/photo-1551754655-cd27e38d2076?auto=format&fit=crop&w=700&q=85', 'url' => '#'],
                        ['title' => 'Bí đao hữu cơ - 500g', 'summary' => '85.000đ', 'price' => '85.000đ', 'image' => 'https://images.unsplash.com/photo-1566385101042-1a0aa0c1268c?auto=format&fit=crop&w=700&q=85', 'url' => '#'],
                        ['title' => 'Cà chua bee ngọt hữu cơ - 300g', 'summary' => '59.400đ', 'price' => '59.400đ', 'image' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=700&q=85', 'url' => '#'],
                        ['title' => 'Cà rốt baby hữu cơ - 250g', 'summary' => '74.750đ', 'price' => '74.750đ', 'image' => 'https://images.unsplash.com/photo-1445282768818-728615cc910a?auto=format&fit=crop&w=700&q=85', 'url' => '#'],
                    ]]],
                    'en' => ['title' => 'Featured farm products', 'subtitle' => 'Products', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dịch vụ của chúng tôi',
                'description' => 'Slider dịch vụ có thể đổi nguồn dữ liệu.',
                'preview_image' => '/theme-previews/XD0323/services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 5, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Chúng tôi mang đến những gì', 'subtitle' => 'Dịch vụ của chúng tôi', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Giải pháp trồng rau củ sạch', 'summary' => '', 'image' => $vegetables, 'icon' => 'fa-solid fa-basket-shopping', 'url' => '#'],
                        ['title' => 'Giải pháp thu hoạch', 'summary' => '', 'image' => 'https://images.unsplash.com/photo-1523741543316-beb7fc7023d8?auto=format&fit=crop&w=900&q=85', 'icon' => 'fa-solid fa-tractor', 'url' => '#'],
                        ['title' => 'Giải pháp dinh dưỡng', 'summary' => '', 'image' => 'https://images.unsplash.com/photo-1592841200221-a6898f307baa?auto=format&fit=crop&w=900&q=85', 'icon' => 'fa-solid fa-apple-whole', 'url' => '#'],
                    ]]],
                    'en' => ['title' => 'What we provide', 'subtitle' => 'Our services', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'process_steps',
                'label' => 'Quy trình làm việc',
                'description' => 'Khối user tự nhập cho quy trình sản xuất thực phẩm sạch.',
                'preview_image' => '/theme-previews/XD0323/process.png',
                'anchor_id' => 'quy-trinh',
                'data' => [
                    'vi' => ['title' => 'Quy trình sản xuất thực phẩm sạch', 'subtitle' => 'Quy trình làm việc', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Trải nghiệm quy trình sản xuất', 'image' => 'https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&w=700&q=85'],
                        ['title' => 'Được chuyên gia hướng dẫn tận tình', 'image' => 'https://images.unsplash.com/photo-1523741543316-beb7fc7023d8?auto=format&fit=crop&w=700&q=85'],
                        ['title' => 'Tìm hiểu đội ngũ chuyên nghiệp của chúng tôi', 'image' => $farmer],
                        ['title' => 'Nhận ngay những sản phẩm chất lượng nhất', 'image' => 'https://images.unsplash.com/photo-1595475207225-428b62bda831?auto=format&fit=crop&w=700&q=85'],
                    ]]],
                    'en' => ['title' => 'Clean food production process', 'subtitle' => 'Working process', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'Hỏi đáp',
                'description' => 'FAQ cùng ảnh minh họa.',
                'preview_image' => '/theme-previews/XD0323/faq.png',
                'anchor_id' => 'hoi-dap',
                'media' => ['image' => $farmer],
                'data' => [
                    'vi' => ['title' => 'Chúng tôi sẵn sàng trả lời mọi câu hỏi của bạn.', 'subtitle' => 'Những câu hỏi thường gặp', 'description' => 'Chúng tôi luôn sẵn lòng hỗ trợ và giải đáp mọi thắc mắc của bạn. Hãy gửi câu hỏi cho chúng tôi để được tư vấn nhanh chóng và chính xác nhất!', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Làm thế nào để chúng ta bảo vệ nền nông nghiệp hữu cơ?', 'summary' => 'Chúng tôi ưu tiên canh tác tự nhiên, quản lý nguồn nước, đất và quy trình thu hoạch theo tiêu chuẩn an toàn.'],
                        ['title' => 'Chúng tôi mang đến giải pháp gì cho nông nghiệp xanh cho tương lai?', 'summary' => 'Euro Farm cung cấp quy trình, sản phẩm và tư vấn giúp khách hàng xây dựng chuỗi cung ứng nông sản bền vững.'],
                        ['title' => 'Làm thế nào để đạt được sự phát triển bền vững trong nông nghiệp?', 'summary' => 'Kết hợp công nghệ, kiểm soát chất lượng và đào tạo nông dân là nền tảng phát triển dài hạn.'],
                        ['title' => 'Làm thế nào để cân bằng giữa năng suất và sự bền vững trong nông nghiệp?', 'summary' => 'Chúng tôi chọn giống phù hợp, tối ưu đất và giảm phụ thuộc hóa chất để giữ năng suất ổn định.'],
                    ]]],
                    'en' => ['title' => 'We are ready to answer your questions.', 'subtitle' => 'FAQ', 'description' => 'Send us your questions for fast and accurate advice.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonials',
                'label' => 'Phản hồi khách hàng',
                'description' => 'Đánh giá khách hàng, lấy từ CMS Testimonials hoặc nhập tay.',
                'preview_image' => '/theme-previews/XD0323/testimonials.png',
                'anchor_id' => 'danh-gia',
                'dynamic' => true,
                'settings' => ['source' => 'cms_testimonials', 'limit' => 2, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_testimonials', 'label' => 'Đánh giá']]],
                    'limit' => ['type' => 'number', 'label' => 'Số đánh giá hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Khách hàng nói gì về Euro Farm', 'subtitle' => 'Đánh giá', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['name' => 'Nguyễn Văn Huy', 'company' => 'Khách hàng thân thiết', 'quote' => 'Tôi rất hài lòng với sản phẩm của farm. Rau củ luôn tươi, sạch và có mùi vị tự nhiên.', 'image' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=500&q=85'],
                        ['name' => 'Trần Quốc Anh', 'company' => 'Nhân viên văn phòng', 'quote' => 'Mình đặt hàng vài lần và lần nào cũng ưng ý. Rau quả tươi lâu, đóng gói gọn gàng.', 'image' => 'https://images.unsplash.com/photo-1527980965255-d3b416303d12?auto=format&fit=crop&w=500&q=85'],
                    ]]],
                    'en' => ['title' => 'What customers say about Euro Farm', 'subtitle' => 'Reviews', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Đội ngũ nhân sự',
                'description' => 'Đội ngũ chuyên gia, lấy từ CMS Team hoặc nhập tay.',
                'preview_image' => '/theme-previews/XD0323/team.png',
                'anchor_id' => 'doi-ngu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_team_members', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_team_members', 'label' => 'Đội ngũ']]],
                    'limit' => ['type' => 'number', 'label' => 'Số thành viên hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Chuyên gia hàng đầu từ nước ngoài', 'subtitle' => 'Đội ngũ của chúng tôi', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['name' => 'Mike Brown', 'role' => 'Founder & owner', 'image' => 'https://images.unsplash.com/photo-1530268729831-4b0b9e170218?auto=format&fit=crop&w=700&q=85'],
                        ['name' => 'Alees Hardson', 'role' => 'Winery Master', 'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=700&q=85'],
                        ['name' => 'Hailey Simpson', 'role' => 'Agricultural Development Specialist', 'image' => 'https://images.unsplash.com/photo-1527980965255-d3b416303d12?auto=format&fit=crop&w=700&q=85'],
                        ['name' => 'Jassica Andrew', 'role' => 'Agricultural Systems Technician', 'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=700&q=85'],
                    ]]],
                    'en' => ['title' => 'Leading experts from abroad', 'subtitle' => 'Our team', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'latest_posts',
                'label' => 'Tin tức',
                'description' => 'Tin cập nhật, có thể đổi nguồn sang sản phẩm/dịch vụ/dự án/danh mục hoặc nhập tay.',
                'preview_image' => '/theme-previews/XD0323/posts.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Tin được cập nhật thường xuyên', 'subtitle' => 'Tin tức', 'description' => '', 'button_label' => 'Đọc tiếp', 'content' => ['items' => []]],
                    'en' => ['title' => 'News updated regularly', 'subtitle' => 'News', 'description' => '', 'button_label' => 'Read more', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0323DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_categories', 'label' => 'Danh mục tin tức'],
        ];

        $sourceSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
            'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'ID danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero Avex',
                'description' => 'Header ảnh lớn, CTA và slide ưu điểm chạy ngang.',
                'preview_image' => '/theme-previews/XD0323/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0323-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Giải pháp Hiệu quả & Chất lượng',
                        'subtitle' => 'Avex Construction',
                        'description' => 'Chúng tôi tự hào mang đến cho bạn những giải pháp thiết kế hiệu quả nhất.',
                        'button_label' => 'Xem chi tiết',
                        'content' => [
                            'slides' => [
                                ['title' => 'Giải pháp Hiệu quả & Chất lượng', 'summary' => 'Chúng tôi tự hào mang đến cho bạn những giải pháp thiết kế hiệu quả nhất', 'button_label' => 'Xem chi tiết', 'secondary_button_label' => 'Liên hệ ngay', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#gioi-thieu', 'secondary_link_url' => '#lien-he'],
                                ['title' => 'Thi công trọn gói, đúng tiến độ', 'summary' => 'Đội ngũ kỹ sư và kiến trúc sư đồng hành từ tư vấn đến bàn giao', 'button_label' => 'Dự án', 'secondary_button_label' => 'Nhận tư vấn', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#du-an', 'secondary_link_url' => '#lien-he'],
                            ],
                            'items' => [
                                ['title' => 'Bền vững', 'summary' => 'Chúng tôi chọn vật liệu tốt nhất và thiết kế luôn được cập nhật liên tục.', 'icon' => 'fa-solid fa-gear'],
                                ['title' => 'Thẩm mỹ', 'summary' => 'Đội ngũ kỹ sư, kiến trúc sư trẻ, táo bạo và đầy sáng tạo.', 'icon' => 'fa-solid fa-drafting-compass'],
                                ['title' => 'Tiết kiệm', 'summary' => 'Khách hàng được tư vấn giải pháp phù hợp với ngân sách và nhu cầu.', 'icon' => 'fa-solid fa-hand-holding-dollar'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Efficient & Quality Solutions', 'subtitle' => 'Avex Construction', 'description' => 'Reliable design and construction solutions.', 'button_label' => 'View details', 'content' => ['slides' => [], 'items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu công ty',
                'description' => 'Giới thiệu ngắn gọn kèm ảnh và chỉ số phụ.',
                'preview_image' => '/theme-previews/XD0323/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#du-an'],
                'media' => ['image' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=1200&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Chúng tôi là nhà xây dựng dân dụng chuyên nghiệp xây nhà trọn gói, chìa khóa trao tay.',
                        'subtitle' => 'Về chúng tôi',
                        'description' => 'Với hơn 10 năm trong nghề, Avex Construct luôn tự hào là đơn vị thiết kế thi công chuyên nghiệp, luôn mang lại giá trị thiết thực nhất tới khách hàng.',
                        'button_label' => 'Xem chi tiết',
                        'content' => ['items' => [
                            ['title' => '1000+', 'summary' => 'Dự án ấn tượng đã hoàn thiện và được khách hàng đánh giá cao.', 'icon' => 'fa-regular fa-building'],
                            ['title' => '300+', 'summary' => 'Chuyên gia tư vấn, kiến trúc sư được đào tạo chuyên sâu.', 'icon' => 'fa-solid fa-helmet-safety'],
                        ]],
                    ],
                    'en' => ['title' => 'Professional residential design and build contractor.', 'subtitle' => 'About us', 'description' => 'More than 10 years of practical construction experience.', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'stats_strip',
                'label' => 'Con số thống kê',
                'description' => 'Dải thống kê nền cam.',
                'preview_image' => '/theme-previews/XD0323/stats.png',
                'anchor_id' => 'thong-ke',
                'data' => [
                    'vi' => ['title' => 'Thống kê', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['title' => '13+', 'summary' => 'Giải thưởng', 'icon' => 'fa-solid fa-award'],
                        ['title' => '100+', 'summary' => 'Kỹ sư xuất sắc', 'icon' => 'fa-solid fa-users-gear'],
                        ['title' => '1500+', 'summary' => 'Đánh giá chất lượng tốt', 'icon' => 'fa-regular fa-thumbs-up'],
                        ['title' => '1000+', 'summary' => 'Dự án hoàn thành', 'icon' => 'fa-regular fa-building'],
                    ]]],
                    'en' => ['title' => 'Stats', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'project_gallery',
                'label' => 'Dự án của chúng tôi',
                'description' => 'Grid nội dung động, hỗ trợ mọi nguồn dữ liệu chính.',
                'preview_image' => '/theme-previews/XD0323/projects.png',
                'anchor_id' => 'du-an',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Dự án của chúng tôi', 'subtitle' => '', 'description' => 'Avex Construction tự hào là đơn vị thi công các công trình nhà phố, biệt thự, căn hộ cao cấp, khách sạn - resort.', 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]],
                    'en' => ['title' => 'Our projects', 'subtitle' => '', 'description' => 'Selected construction and interior projects.', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'process_steps',
                'label' => 'Tư vấn khách hàng',
                'description' => 'Khối sửa nhanh cho quy trình tư vấn.',
                'preview_image' => '/theme-previews/XD0323/process.png',
                'anchor_id' => 'tu-van',
                'media' => ['background' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=2200&q=85'],
                'data' => [
                    'vi' => ['title' => 'Tư vấn khách hàng', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Tư vấn khách hàng', 'summary' => 'Ngay sau khi tiếp nhận thông báo và nhận được lịch gặp mặt của khách hàng.', 'icon' => 'fa-regular fa-comments'],
                        ['title' => 'Thiết kế 3D miễn phí', 'summary' => 'Lên bản vẽ bối cảnh 3D mô phỏng yêu cầu của khách.', 'icon' => 'fa-solid fa-ruler-combined'],
                        ['title' => 'Tiến hành thi công', 'summary' => 'Lên kế hoạch và thi công, đảm bảo bàn giao đúng tiến độ.', 'icon' => 'fa-solid fa-trowel-bricks'],
                    ]]],
                    'en' => ['title' => 'Customer consulting', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'pricing_cards',
                'label' => 'Báo giá thi công',
                'description' => 'Khối sửa nhanh cho các gói báo giá.',
                'preview_image' => '/theme-previews/XD0323/pricing.png',
                'anchor_id' => 'bao-gia',
                'data' => [
                    'vi' => ['title' => 'Báo giá thi công', 'subtitle' => '', 'description' => 'Các gói thi công được phân tích theo từng hạng mục riêng, phù hợp với nhu cầu ngân sách của mỗi gia đình.', 'button_label' => 'Đăng ký ngay', 'content' => ['items' => [
                        ['title' => 'Gói thi công tiết kiệm', 'summary' => 'Kinh phí dự kiến: 80 - 120 triệu', 'bullets' => ['Phong cách hiện đại, Bắc Âu, Vintage', 'Chất liệu MDF chống ẩm phủ Melamine', 'Thi công đúng tiến độ', 'Giám sát hoàn thiện 24/7']],
                        ['title' => 'Gói thi công tiêu chuẩn', 'summary' => 'Kinh phí dự kiến: 300 - 500 triệu', 'bullets' => ['Phong cách Luxury, tân cổ điển', 'Chất liệu cao cấp', 'Hồ sơ cấp phép thi công', 'Kỹ sư giám sát kinh nghiệm trên 5 năm']],
                        ['title' => 'Gói thi công cao cấp', 'summary' => 'Kinh phí dự kiến: 500 triệu - 1 tỷ', 'bullets' => ['Phong cách Modern Luxury', 'Gỗ tự nhiên nhập khẩu', 'Giám sát hoàn thiện 24/7', 'Kỹ sư giám sát kinh nghiệm trên 7 năm']],
                    ]]],
                    'en' => ['title' => 'Construction pricing', 'subtitle' => '', 'description' => 'Flexible construction packages.', 'button_label' => 'Register now', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'business_service_grid',
                'label' => 'Sản phẩm mới nhất',
                'description' => 'Grid sản phẩm hoặc nội dung động.',
                'preview_image' => '/theme-previews/XD0323/products.png',
                'anchor_id' => 'san-pham',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 10, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Sản phẩm mới nhất', 'subtitle' => '', 'description' => 'Chúng tôi luôn cam kết đem đến những sản phẩm chất lượng với giá thành ưu đãi lớn.', 'button_label' => '', 'content' => ['items' => []]],
                    'en' => ['title' => 'Latest products', 'subtitle' => '', 'description' => 'Quality products and practical construction solutions.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'content_showcase',
                'label' => 'Đội ngũ kỹ sư',
                'description' => 'Slider nhân sự hoặc nội dung động.',
                'preview_image' => '/theme-previews/XD0323/team.png',
                'anchor_id' => 'doi-ngu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 4, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Đội ngũ kỹ sư Avex', 'subtitle' => '', 'description' => 'Đội ngũ chuyên gia tư vấn, kiến trúc sư, giám sát công trình được đào tạo chuyên sâu.', 'button_label' => '', 'content' => ['items' => [
                        ['title' => 'Huỳnh Ngọc Thanh', 'summary' => 'Kỹ sư công trình', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=900&q=85'],
                        ['title' => 'Hanah Phạm', 'summary' => 'Kiến trúc sư', 'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=900&q=85'],
                        ['title' => 'Trần Trung Anh', 'summary' => 'Giám sát thi công', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=900&q=85'],
                        ['title' => 'Tuấn Dino', 'summary' => 'Chuyên viên kỹ thuật', 'image' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=900&q=85'],
                    ]]],
                    'en' => ['title' => 'Avex engineers', 'subtitle' => '', 'description' => 'Experienced consultants, architects and supervisors.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'latest_posts',
                'label' => 'Tạp chí xây dựng',
                'description' => 'Slider bài viết, không hiển thị ngày đăng và người đăng.',
                'preview_image' => '/theme-previews/XD0323/posts.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 6, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => ['title' => 'Tạp chí xây dựng', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                    'en' => ['title' => 'Construction journal', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'Hỏi đáp',
                'description' => 'FAQ và CTA liên hệ.',
                'preview_image' => '/theme-previews/XD0323/faq.png',
                'anchor_id' => 'hoi-dap',
                'data' => [
                    'vi' => ['title' => 'Bạn hãy để lại câu hỏi hoặc liên hệ ngay với chúng tôi', 'subtitle' => 'Hãy để chúng tôi giải đáp những thắc mắc của bạn.', 'description' => '', 'button_label' => 'Liên hệ ngay', 'content' => ['items' => [
                        ['title' => 'Tôi cần thiết kế thi công nhà ở thì cần bao nhiêu tiền?', 'summary' => 'Avex thường có các hợp đồng thiết kế và thi công riêng biệt. Khi khách hàng đồng ý thi công, Avex sẽ đưa ra bảng báo giá và hợp đồng để tham khảo và ký kết.'],
                        ['title' => 'Tôi chỉ có nhu cầu thiết kế, không có thi công có được không?', 'summary' => 'Có. Chúng tôi hỗ trợ riêng từng nhu cầu thiết kế hoặc thi công.'],
                        ['title' => 'Bản thiết kế căn hộ của tôi có bị copy hay trùng lặp không?', 'summary' => 'Mỗi bản thiết kế được phát triển theo hiện trạng, nhu cầu và phong cách riêng của khách hàng.'],
                        ['title' => 'Sẽ làm sao nếu chúng tôi không hài lòng với bản thiết kế?', 'summary' => 'Đội ngũ sẽ tiếp nhận phản hồi và điều chỉnh theo phạm vi đã thống nhất.'],
                    ]]],
                    'en' => ['title' => 'Leave your questions or contact us now', 'subtitle' => 'Let us answer your questions.', 'description' => '', 'button_label' => 'Contact now', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0313DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'custom', 'label' => 'Nhap thu cong'],
            ['value' => 'cms_posts', 'label' => 'Tin tuc'],
            ['value' => 'cms_products', 'label' => 'San pham'],
            ['value' => 'cms_services', 'label' => 'Dich vu'],
            ['value' => 'cms_projects', 'label' => 'Du an'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero RouteX',
                'description' => 'Banner bo tron nen xanh, CTA va video.',
                'preview_image' => '/theme-previews/XD0313/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0313-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vi tri banner'],
                    'limit' => ['type' => 'number', 'label' => 'So slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Visa De Dang, Giac Mo Thanh Hien Thuc',
                        'subtitle' => '',
                        'description' => 'Visa chi la phuong tien, con giac mo du hoc, du lich, dinh cu moi la muc tieu cuoi cung.',
                        'button_label' => 'Doc Them',
                        'content' => ['slides' => [
                            ['title' => 'Visa De Dang, Giac Mo Thanh Hien Thuc', 'summary' => 'Visa chi la phuong tien, con giac mo du hoc, du lich, dinh cu moi la muc tieu cuoi cung. Su de dang trong viec co visa se giup ban tap trung hon vao viec thuc hien giac mo cua minh.', 'button_label' => 'Doc Them', 'image' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=900&q=85', 'link_url' => '#gioi-thieu', 'video_url' => '#video'],
                            ['title' => 'Dong Hanh Tren Moi Hanh Trinh Quoc Te', 'summary' => 'Tu van ho so, dat lich hen va ho tro visa nhanh chong cho tung muc tieu cua ban.', 'button_label' => 'Dich Vu Visa', 'image' => 'https://images.unsplash.com/photo-1521791055366-0d553872125f?auto=format&fit=crop&w=900&q=85', 'link_url' => '#dich-vu', 'video_url' => '#video'],
                        ]],
                    ],
                    'en' => ['title' => 'Easy visa, real dreams', 'subtitle' => '', 'description' => 'RouteX helps make your travel, study and work dreams easier.', 'button_label' => 'Read more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Uu diem RouteX',
                'description' => 'Bon card loi ich dau trang.',
                'preview_image' => '/theme-previews/XD0313/benefits.png',
                'anchor_id' => 'uu-diem',
                'settings' => ['limit' => 4],
                'data' => [
                    'vi' => [
                        'title' => 'Uu diem',
                        'subtitle' => '',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Ho So Don Gian', 'summary' => 'Co cong viec chinh thuc voi thu nhap tot, co the chung minh qua hop dong lao dong va bang luong.', 'icon' => '01'],
                            ['title' => 'Nhanh Chong Tuc Thi', 'summary' => 'Hay lien he voi chung toi de nhan duoc su tu van mien phi va chuyen sau nhat.', 'icon' => '02'],
                            ['title' => 'Tu Van Tan Tam', 'summary' => 'RouteX tu hao la doi tac tin cay, chuyen cung cap dich vu tu van va ho tro visa chuyen nghiep.', 'icon' => '03'],
                            ['title' => 'Bao Mat Tuyet Doi', 'summary' => 'Moi du lieu khach hang deu duoc bao mat tuyet doi, dam bao su an tam va tin tuong.', 'icon' => '04'],
                        ]],
                    ],
                    'en' => ['title' => 'Benefits', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Ve RouteX',
                'description' => 'Gioi thieu cong ty voi hinh anh va goi dich vu.',
                'preview_image' => '/theme-previews/XD0313/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#dich-vu'],
                'media' => [
                    'image_one' => 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=900&q=85',
                    'image_two' => 'https://images.unsplash.com/photo-1521791055366-0d553872125f?auto=format&fit=crop&w=900&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Noi Niem Dam Me Nhung Diem Den Trong Mo',
                        'subtitle' => 'Ve chung toi',
                        'description' => 'RouteX Visa tu hao la doi tac tin cay cua ban tren moi hanh trinh kham pha the gioi. Chung toi cung cap cac giai phap visa toan dien va hieu qua nhat.',
                        'button_label' => 'Doc Them',
                        'content' => [
                            'years' => '25',
                            'items' => [
                                ['title' => 'Ho Chieu Plus', 'icon' => 'P', 'bullets' => ['Di tru vuot bien gioi', 'Ho tro thi thuc toan cau']],
                                ['title' => 'Nhap Canh Toan Cau', 'icon' => 'G', 'bullets' => ['Dich vu Visa GlobeTrot', 'Giai phap Visa Infinity']],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Passion for dream destinations', 'subtitle' => 'About us', 'description' => 'RouteX provides complete visa consulting solutions.', 'button_label' => 'Read more', 'content' => []],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Danh muc visa noi bat',
                'description' => 'Slider anh danh muc visa tren nen xanh dam.',
                'preview_image' => '/theme-previews/XD0313/featured-visa.png',
                'anchor_id' => 'visa-noi-bat',
                'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 6],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Danh Muc Visa Noi Bat',
                        'subtitle' => 'Visa noi bat',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Visa Chau My', 'image' => 'https://images.unsplash.com/photo-1508433957232-3107f5fd5995?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa Chau Au', 'image' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa Du Hoc', 'image' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Featured visa categories', 'subtitle' => 'Featured visas', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Cac loai visa pho bien',
                'description' => 'Grid dich vu visa lay tu service/post/product/project hoac custom.',
                'preview_image' => '/theme-previews/XD0313/services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Cac Loai Visa Pho Bien',
                        'subtitle' => 'Visa noi bat',
                        'description' => '',
                        'button_label' => 'Xem Chi Tiet',
                        'content' => ['items' => [
                            ['title' => 'Dich Vu Xin Visa Brunei Nhanh Chong - Chuyen Nghiep Tai', 'summary' => 'Doi voi cong dan Viet Nam, viec xin visa di Brunei can duoc thuc hien neu muon luu tru qua 14 ngay.', 'image' => 'https://images.unsplash.com/photo-1560264280-88b68371db39?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa Du Hoc Chau Au', 'summary' => 'Thu tuc xin visa du hoc Chau Au co the phuc tap vi moi quoc gia co nhung quy dinh rieng.', 'image' => 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Dich Vu Gia Han Visa My', 'summary' => 'Ho tro gia han thi thuc My cho ca nhan da co visa du dieu kien.', 'image' => 'https://images.unsplash.com/photo-1527853787696-f7be74f2e39a?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa Tham Nguoi Than', 'summary' => 'Loai visa danh cho muc dich tham than, du lich ngan han hoac bao lanh dac biet.', 'image' => 'https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa Du Lich', 'summary' => 'Thi thuc cho phep nhap canh voi muc dich tham quan, nghi duong va kham pha.', 'image' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa Cong Tac', 'summary' => 'Ho tro ho so cong tac, thuong mai, ky ket hop dong va tham du su kien.', 'image' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Popular visa services', 'subtitle' => 'Featured visas', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'logistics_feature_panel',
                'label' => 'Uu dai va thong ke',
                'description' => 'Khoi CTA kem thong ke RouteX.',
                'preview_image' => '/theme-previews/XD0313/promo.png',
                'anchor_id' => 'uu-dai',
                'settings' => ['cta_url' => '#footer'],
                'media' => [
                    'image' => 'https://images.unsplash.com/photo-1502685104226-ee32379fefbe?auto=format&fit=crop&w=900&q=85',
                    'card_image' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=900&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Nhan uu dai tot nhat cua chung toi mot cach nhanh chong',
                        'subtitle' => '',
                        'description' => 'Dung ngan ngai lien he truc tiep voi cac cong ty visa qua hotline hoac email. Ban co the hoi thang ve cac chuong trinh khuyen mai hien tai.',
                        'button_label' => 'Lien He Ngay',
                        'content' => ['stats' => [
                            ['value' => '17+', 'label' => 'Nam kinh nghiem'],
                            ['value' => '99,8%', 'label' => 'Khach hang hai long'],
                            ['value' => '24/7', 'label' => 'Tu van mien phi'],
                            ['value' => '98,6%', 'label' => 'Ti le dau visa'],
                        ]],
                    ],
                    'en' => ['title' => 'Get our best offer quickly', 'subtitle' => '', 'description' => 'Contact RouteX for the latest visa consulting offers.', 'button_label' => 'Contact now', 'content' => []],
                ],
            ],
            [
                'block_type' => 'process_steps',
                'label' => 'Quy trinh visa',
                'description' => 'Cac buoc lam visa tai RouteX.',
                'preview_image' => '/theme-previews/XD0313/process.png',
                'anchor_id' => 'quy-trinh',
                'settings' => [],
                'media' => ['image' => 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=900&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Cac Buoc Lam Visa Tai RouteX',
                        'subtitle' => 'Quy trinh tu van',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Dang Ky (1 Phut)', 'summary' => 'Form dien thong tin don gian, nhanh chong. Thong tin duoc bao mat an toan.'],
                            ['title' => 'Tu Van', 'summary' => 'Nhan vien se lien he lai voi ban trong vong 4h. Hoac lien he qua Hotline 1900 9477.'],
                            ['title' => 'Hoan Thien Ho So (2 - 3 Ngay)', 'summary' => 'Mot nhan vien giau kinh nghiem se dong hanh ho tro ban suot qua trinh.'],
                            ['title' => 'Nhan Visa', 'summary' => 'Khach hang nhan Visa se den lay truc tiep hoac chuyen phat tan tay.'],
                        ]],
                    ],
                    'en' => ['title' => 'RouteX visa process', 'subtitle' => 'Consulting process', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonials',
                'label' => 'Danh gia khach hang',
                'description' => 'Khoi testimonial xanh.',
                'preview_image' => '/theme-previews/XD0313/testimonials.png',
                'anchor_id' => 'danh-gia',
                'settings' => ['source' => 'custom', 'limit' => 3],
                'media' => ['image' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=900&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Danh gia',
                        'subtitle' => '',
                        'description' => 'Toi la chu doanh nghiep, khong co thoi gian tim hieu thu tuc phuc tap. Cong ty dich vu da lam moi thu tu A den Z, toi chi can den va nop ho so.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Tran Minh Hoang', 'role' => 'SEO cong ty ABC', 'quote' => 'Toi la chu doanh nghiep, khong co thoi gian tim hieu thu tuc phuc tap. Cong ty dich vu da lam moi thu tu A den Z, tu dich thuat cong chung den dat lich hen, toi chi can den va nop ho so. Rat tien loi va chuyen nghiep.', 'avatar' => 'https://images.unsplash.com/photo-1502685104226-ee32379fefbe?auto=format&fit=crop&w=300&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Testimonials', 'subtitle' => '', 'description' => 'RouteX made the process simple and professional.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'bizmax_latest_posts',
                'label' => 'Blog gan day',
                'description' => 'Bai viet RouteX dang card.',
                'preview_image' => '/theme-previews/XD0313/blog.png',
                'anchor_id' => 'blog',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Mot So Bai Viet Cua Chung Toi',
                        'subtitle' => 'Blog gan day',
                        'description' => '',
                        'button_label' => 'Xem Chi Tiet',
                        'content' => ['items' => [
                            ['title' => 'Hoc Bong Du Hoc Duc 2021 Len Toi 100% Hoc Phi - Co Hoi', 'summary' => 'Dai hoc Jacobs xep hang 1 trong bang xep hang cac truong dai hoc tu cua Duc.', 'image' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 238],
                            ['title' => 'Vuong Quoc Anh: Cac Truong Dai Hoc Giu Cho Cho Sinh', 'summary' => 'Theo cac ben lien quan trong nganh, cac du hoc sinh Anh se khong mat di co hoi ghi danh.', 'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 138],
                            ['title' => 'Thong Tin Cap Nhat Ve Cac Chinh Sach Lien Quan Den Tinh', 'summary' => 'Thong tin cap nhat ve cac chinh sach lien quan den tinh hinh moi.', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 192],
                            ['title' => 'Soi Dong Hoi Thao Du Hoc: Cuoc Song Noi Xu Nguoi', 'summary' => 'Buoi hoi thao ve chu de du hoc va cuoc song noi xu nguoi thu hut nhieu ban tre.', 'image' => 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 920],
                        ]],
                    ],
                    'en' => ['title' => 'Some of our articles', 'subtitle' => 'Recent blog', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0318DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'custom', 'label' => 'Nhap thu cong'],
            ['value' => 'cms_posts', 'label' => 'Tin tuc'],
            ['value' => 'cms_products', 'label' => 'San pham'],
            ['value' => 'cms_services', 'label' => 'Dich vu'],
            ['value' => 'cms_projects', 'label' => 'Du an'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero Fast Gear',
                'description' => 'Header sang co dang nhap/dang ky va banner xe tai.',
                'preview_image' => '/theme-previews/XD0318/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0318-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vi tri banner'],
                    'limit' => ['type' => 'number', 'label' => 'So slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Van chuyen moi luc moi noi',
                        'subtitle' => '',
                        'description' => 'Ban lo ngai ve chat luong hang hoa se ra sao duoi thoi tiet nang mua nong lanh that thuong cua Viet Nam',
                        'button_label' => 'Xem them',
                        'content' => ['slides' => [
                            ['title' => 'Van chuyen moi luc moi noi', 'summary' => 'Ban lo ngai ve chat luong hang hoa se ra sao duoi thoi tiet nang mua nong lanh that thuong cua Viet Nam', 'button_label' => 'Xem them', 'image' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#gioi-thieu'],
                            ['title' => 'Giao nhan nhanh chong an toan', 'summary' => 'Mang luoi van tai linh hoat cho hang hoa noi dia va quoc te.', 'button_label' => 'Dich vu', 'image' => 'https://images.unsplash.com/photo-1519003722824-194d4455a60c?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Shipping anywhere, anytime', 'subtitle' => '', 'description' => 'Reliable logistics services for every shipment.', 'button_label' => 'Learn more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'bizmax_about',
                'label' => 'Gioi thieu Fast Gear',
                'description' => 'Khoi gioi thieu 2 cot voi anh tai xe giao hang.',
                'preview_image' => '/theme-previews/XD0318/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#dich-vu'],
                'media' => ['image' => 'https://images.unsplash.com/photo-1580674285054-bed31e145f59?auto=format&fit=crop&w=1200&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Giai phap logistics toan cau tot nhat',
                        'subtitle' => 'Ve chung toi',
                        'description' => "Cong ty Fast Gear duoc thanh lap boi doi ngu nhan vien co hon 20 nam kinh nghiem trong linh vuc giao nhan quoc te, chuyen cung cap cac giai phap logistics cho khach hang.\nMang luoi hoat dong cua cong ty tren toan the gioi - 100 doi tac o 50 quoc gia, tru so chinh tai Viet Nam va van phong chi nhanh tai Hoa Ky.",
                        'button_label' => 'Xem them',
                        'content' => [],
                    ],
                    'en' => ['title' => 'Best global logistics solutions', 'subtitle' => 'About us', 'description' => 'Fast Gear provides reliable international forwarding and logistics solutions.', 'button_label' => 'More', 'content' => []],
                ],
            ],
            [
                'block_type' => 'logistics_feature_panel',
                'label' => 'Video logistics',
                'description' => 'Khoi video nen cang container.',
                'preview_image' => '/theme-previews/XD0318/video.png',
                'anchor_id' => 'video',
                'settings' => ['video_url' => '#video'],
                'media' => ['background' => 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => ['title' => 'Doi tac Logistics Toan Cau Doi Voi The Gioi', 'subtitle' => 'Ve chung toi', 'description' => '', 'button_label' => '', 'content' => []],
                    'en' => ['title' => 'Global logistics partner for the world', 'subtitle' => 'About us', 'description' => '', 'button_label' => '', 'content' => []],
                ],
            ],
            [
                'block_type' => 'business_service_grid',
                'label' => 'Dich vu Fast Gear',
                'description' => 'Grid dich vu logistics va card nhan bao gia.',
                'preview_image' => '/theme-previews/XD0318/services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'media' => ['quote_background' => 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1000&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Giai phap thuc te, nhanh chong thuc su',
                        'subtitle' => 'Dich vu cua chung toi',
                        'description' => '',
                        'button_label' => 'Xem them',
                        'content' => ['items' => [
                            ['title' => 'Van chuyen hang khong', 'summary' => 'Dai ly ban cuoc va hop dong van chuyen voi nhieu hang hang khong lon tren the gioi voi tan suat bay cao.', 'image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Van chuyen vat lieu xay dung', 'summary' => 'Mo rong dich vu van chuyen vat lieu xay dung cho doanh nghiep va cong trinh.', 'image' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Van chuyen nha', 'summary' => 'Dich vu chuyen do dac sang nha moi nhanh gon va dam bao an toan.', 'image' => 'https://images.unsplash.com/photo-1600518464441-9154a4dea21b?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Van chuyen thu cung', 'summary' => 'Giai phap dac biet cho khach hang co nhu cau gui dong vat canh.', 'image' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Van chuyen hang khong', 'summary' => 'Air cargo la hang hoa van chuyen bang may bay, phu hop don hang can toc do cao.', 'image' => 'https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Practical and fast solutions', 'subtitle' => 'Our services', 'description' => '', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'FAQ logistics',
                'description' => 'Giai dap thac mac va hinh anh kho van.',
                'preview_image' => '/theme-previews/XD0318/faq.png',
                'anchor_id' => 'faq',
                'settings' => [],
                'media' => [
                    'image_one' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=900&q=85',
                    'image_two' => 'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?auto=format&fit=crop&w=900&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Giai dap cac thac mac ve dich vu cua ban',
                        'subtitle' => 'Cau hoi thuong gap',
                        'description' => 'Cac cau hoi se phan nao cho ban cai nhin tong quat nhat ve chung toi, giup ban hieu ro hon dich vu dang cung cap.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['question' => 'Cac tieu chi danh gia do uy tin va chat luong cua cong ty van tai chuan nhat?', 'answer' => 'Do uy tin duoc danh gia qua kinh nghiem, mang luoi doi tac, quy trinh giao nhan va kha nang xu ly su co.'],
                            ['question' => 'Tieu chuan chat luong dich vu van tai hanh khach bang xe o to the nao?', 'answer' => 'Dich vu can dam bao an toan, dung lich trinh, xe dat chuan va thong tin minh bach.'],
                            ['question' => 'The nao la cac dich vu ho tro van tai duong bo?', 'answer' => 'Bao gom dong goi, kho bai, boc xep, theo doi don hang va giao nhan chang cuoi.'],
                        ]],
                    ],
                    'en' => ['title' => 'Answers to your service questions', 'subtitle' => 'FAQ', 'description' => 'Helpful answers about our logistics services.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'landing_contact',
                'label' => 'Yeu cau goi lai',
                'description' => 'Form lien he nen cang container.',
                'preview_image' => '/theme-previews/XD0318/contact.png',
                'anchor_id' => 'lien-he',
                'settings' => [],
                'media' => ['background' => 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Yeu cau mot cuoc goi lai',
                        'subtitle' => 'Lien he',
                        'description' => 'Chi mat 30 giay va sau do chung toi se goi cho ban tro lai, tu Thu Hai den Thu Sau, 8 gio sang - 5 gio chieu. De dang.',
                        'button_label' => 'Gui tin nhan',
                        'content' => [],
                    ],
                    'en' => ['title' => 'Request a call back', 'subtitle' => 'Contact', 'description' => 'It takes 30 seconds and we will call you back during business hours.', 'button_label' => 'Send message', 'content' => []],
                ],
            ],
            [
                'block_type' => 'bizmax_latest_posts',
                'label' => 'Tin tuc moi',
                'description' => 'Tin tuc moi nhat dang 3 cot.',
                'preview_image' => '/theme-previews/XD0318/news.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Doc tin tuc moi nhat cua chung toi',
                        'subtitle' => 'Tin tuc moi',
                        'description' => '',
                        'button_label' => 'Xem them',
                        'content' => ['items' => [
                            ['title' => 'Cong thong tin huong dan xuat nhap khau hang hoa chinh thuc', 'summary' => 'Ngay 10/12, tai Ha Noi, Cong thong tin huong dan xuat nhap khau hang hoa duoc gioi thieu den doanh nghiep.', 'image' => 'https://images.unsplash.com/photo-1586528116493-a029325540fa?auto=format&fit=crop&w=900&q=85', 'date' => '24/03/2022', 'views' => 359],
                            ['title' => 'Ky vong gi tu viec ket thuc thoa thuan FTA giua Viet Nam va Anh?', 'summary' => 'Bien ban ket thuc dam phan thuong mai tu do se mo ra nhieu co hoi cho logistics.', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85', 'date' => '24/03/2022', 'views' => 320],
                            ['title' => 'Dang sau con so xuat sieu hon 20 ty USD', 'summary' => 'Con so xuat sieu la tin vui trong boi canh hien nay va tao dong luc cho chuoi cung ung.', 'image' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?auto=format&fit=crop&w=900&q=85', 'date' => '24/03/2022', 'views' => 273],
                        ]],
                    ],
                    'en' => ['title' => 'Read our latest news', 'subtitle' => 'Latest news', 'description' => '', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0315DefaultBlocks(): array
    {
        $multiSources = [
            ['value' => 'custom', 'label' => 'Nhap thu cong'],
            ['value' => 'cms_posts', 'label' => 'Tin tuc'],
            ['value' => 'cms_products', 'label' => 'San pham'],
            ['value' => 'cms_services', 'label' => 'Dich vu'],
            ['value' => 'cms_projects', 'label' => 'Du an'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Header va hero Athletic',
                'description' => 'Header den, dang nhap/dang ky va hero slider full man hinh.',
                'preview_image' => '/theme-previews/XD0315/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0315-hero-slider', 'limit' => 3, 'autoplay_ms' => 6200],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vi tri banner'],
                    'limit' => ['type' => 'number', 'label' => 'So slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Hang dau Viet Nam',
                        'subtitle' => 'Tap luyen cung cac chuyen gia the hinh',
                        'description' => 'Khong gian tap luyen hien dai danh cho nguoi yeu the thao.',
                        'button_label' => 'Dang ky tap thu',
                        'content' => ['slides' => [
                            ['kicker' => 'Tap luyen cung cac chuyen gia the hinh', 'title' => 'Hang dau Viet Nam', 'button_label' => 'Dang ky tap thu', 'image' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#lop-tap'],
                            ['kicker' => 'Suc manh, ky luat va nang luong moi ngay', 'title' => 'Athletic Fitness', 'button_label' => 'Kham pha lop tap', 'image' => 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#lop-tap'],
                        ]],
                    ],
                    'en' => ['title' => 'Vietnam leading fitness center', 'subtitle' => 'Train with professional coaches', 'description' => 'Modern fitness experiences for active members.', 'button_label' => 'Start trial', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Lop tap va dich vu',
                'description' => 'Mosaic lop tap, co the lay tu news/product/services/project hoac custom.',
                'preview_image' => '/theme-previews/XD0315/classes.png',
                'anchor_id' => 'lop-tap',
                'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 6, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $multiSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Trung tam the duc Athletic !',
                        'subtitle' => '',
                        'description' => 'Cac dich vu luyen tap va nhung cach giam can hieu qua. Chung toi se giup ban.',
                        'button_label' => 'Xem them',
                        'content' => ['items' => [
                            ['title' => 'Huan luyen ca nhan', 'summary' => 'Huan luyen vien ca nhan danh gia chi so co the va xay dung dinh huong tap luyen rieng.', 'image' => 'https://images.unsplash.com/photo-1534367610401-9f5ed68180aa?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Yoga', 'summary' => 'Hon 50 lop yoga tu co ban den nang cao cho nhieu muc tieu tap luyen.', 'image' => 'https://images.unsplash.com/photo-1599901860904-17e6ed7083a0?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Giam can', 'summary' => 'He thong bai tap chuyen sau giup giam mo va cai thien suc ben.', 'image' => 'https://images.unsplash.com/photo-1605296867304-46d5465a13f1?auto=format&fit=crop&w=1200&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Dance', 'summary' => 'Giai phong nang luong, tang su linh hoat va giup lop tap luon day hung khoi.', 'image' => 'https://images.unsplash.com/photo-1524594152303-9fd13543fe6e?auto=format&fit=crop&w=1200&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Giam cang co', 'summary' => 'Lieu phap gian co thu gian sau khi tap luyen cuong do cao.', 'image' => 'https://images.unsplash.com/photo-1599058917212-d750089bc07e?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Kickfit', 'summary' => 'Ket hop vo thuat va cardio voi cac dong tac manh me.', 'image' => 'https://images.unsplash.com/photo-1517438322307-e67111335449?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Athletic fitness center', 'subtitle' => '', 'description' => 'Training services and effective weight control programs.', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Gioi thieu va video',
                'description' => 'Khoi gioi thieu nen den voi hinh anh lon va video.',
                'preview_image' => '/theme-previews/XD0315/about-video.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#lop-tap', 'video_url' => '#video'],
                'media' => [
                    'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?auto=format&fit=crop&w=1200&q=85',
                    'video_image' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1200&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Chung toi tao ra su khac biet',
                        'subtitle' => 'But I must explain to you how all this mistaken idea denouncing pleasure and praising pain was born.',
                        'description' => 'Chung toi muon chung minh rang de co duoc cuoc song tot va lanh manh hon, ban khong nhat thiet phai hy sinh qua nhieu. Chi can dua vao loi song cua minh nhung thoi quen giup nang cao chat luong song.',
                        'button_label' => 'Xem them',
                        'content' => [],
                    ],
                    'en' => ['title' => 'We make the difference', 'subtitle' => 'Train smarter every day.', 'description' => 'Healthy habits and practical coaching for a stronger lifestyle.', 'button_label' => 'More', 'content' => []],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Gap go chuyen gia',
                'description' => 'Danh sach huan luyen vien voi ten va chuc danh.',
                'preview_image' => '/theme-previews/XD0315/trainers.png',
                'anchor_id' => 'chuyen-gia',
                'dynamic' => true,
                'settings' => ['source' => 'cms_team_members', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => [['value' => 'custom', 'label' => 'Nhap thu cong'], ['value' => 'cms_team_members', 'label' => 'Doi ngu']]],
                    'limit' => ['type' => 'number', 'label' => 'So nhan su'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Gap go chuyen gia',
                        'subtitle' => '',
                        'description' => 'Cac chuyen gia hang dau cua Athletic da san sang de cung ban tap luyen, vuon toi than hinh san chac va loi song khoe manh.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Brad Tran', 'role' => 'Huan luyen vien ta', 'image' => 'https://images.unsplash.com/photo-1571731956672-f2b94d7dd0cb?auto=format&fit=crop&w=800&q=85'],
                            ['name' => 'Raymond L. Brown', 'role' => 'Huan luyen vien quyen anh', 'image' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&w=800&q=85'],
                            ['name' => 'Tieu Phuong', 'role' => 'Chuyen gia the hinh', 'image' => 'https://images.unsplash.com/photo-1603988363607-e1e4a66962c6?auto=format&fit=crop&w=800&q=85'],
                            ['name' => 'Solomon K. Sawyers', 'role' => 'Huan luyen vien lam dep', 'image' => 'https://images.unsplash.com/photo-1532384748853-8f54a8f476e2?auto=format&fit=crop&w=800&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Meet the experts', 'subtitle' => '', 'description' => 'Professional coaches ready to guide your fitness journey.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'project_gallery',
                'label' => 'Cau lac bo',
                'description' => 'Thu vien co so/cau lac bo voi nhan cam tren anh.',
                'preview_image' => '/theme-previews/XD0315/clubs.png',
                'anchor_id' => 'cau-lac-bo',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $multiSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Cau lac bo',
                        'subtitle' => '',
                        'description' => 'He thong phong tap tieu chuan 5 Sao voi trang thiet bi, may moc tap luyen nhap khau tu cac thuong hieu hang dau the gioi.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Athletic Fitness Center 3 Thang 2 Quan 10', 'image' => 'https://images.unsplash.com/photo-1558611848-73f7eb4001a1?auto=format&fit=crop&w=1200&q=85'],
                            ['title' => 'Athletic Fitness Center Thien Son Plaza Quan 7', 'image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1200&q=85'],
                            ['title' => 'Athletic Fitness Center Ho Xuan Huong Quan 3', 'image' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Athletic Fitness Center Hoang Sa Quan 3', 'image' => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Athletic Fitness Center Pham Van Hai Quan Tan', 'image' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Clubs', 'subtitle' => '', 'description' => 'Premium fitness clubs with modern imported equipment.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Tin tuc su kien',
                'description' => 'Danh sach tin tuc/su kien 3 cot.',
                'preview_image' => '/theme-previews/XD0315/news.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $multiSources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Tin tuc su kien',
                        'subtitle' => '',
                        'description' => '',
                        'button_label' => 'Xem them',
                        'content' => ['items' => [
                            ['title' => 'So huu body chuan khong kho neu nam duoc bi quyet dinh duong khi tap gym nay', 'summary' => 'Neu chi tap gym ma khong an uong hop ly thi co the se khong co nhung thay doi dang ke.', 'image' => 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Khong an kieng van co body san chac nho che do an tang co giam mo nay!', 'summary' => 'An uong theo che do tang co giam mo giup nam gioi co than hinh quyen ru hon.', 'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Thuc don cho nguoi moi tap gym co nen su dung nhieu trung?', 'summary' => 'Thuc don cho nguoi moi tap gym nen bat dau voi khau phan an hop ly va da dang.', 'image' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'News and events', 'subtitle' => '', 'description' => '', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonials',
                'label' => 'Cau chuyen thanh cong',
                'description' => 'Success stories dang truoc/sau voi chi so co the.',
                'preview_image' => '/theme-previews/XD0315/success-stories.png',
                'anchor_id' => 'cau-chuyen',
                'settings' => ['source' => 'custom', 'limit' => 4],
                'media' => ['background' => 'https://images.unsplash.com/photo-1534258936925-c58bed479fcb?auto=format&fit=crop&w=1800&q=80'],
                'data' => [
                    'vi' => [
                        'title' => 'Cau chuyen thanh cong',
                        'subtitle' => '',
                        'description' => 'Kham pha phuong phap da giup thay doi cuoc song cua hang tram ngan nguoi tai Viet Nam.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Bui Quoc Thai', 'image' => 'https://images.unsplash.com/photo-1532384748853-8f54a8f476e2?auto=format&fit=crop&w=700&q=85', 'before_weight' => '100kg', 'after_weight' => '77kg', 'before_muscle' => '40.5kg', 'after_muscle' => '47.2kg', 'before_fat' => '25%', 'after_fat' => '14.3%'],
                            ['name' => 'Nguyen Huu Trong', 'image' => 'https://images.unsplash.com/photo-1571731956672-f2b94d7dd0cb?auto=format&fit=crop&w=700&q=85', 'before_weight' => '62kg', 'after_weight' => '61.5kg', 'before_muscle' => '29.3kg', 'after_muscle' => '31.2kg', 'before_fat' => '25%', 'after_fat' => '20.3%'],
                        ]],
                    ],
                    'en' => ['title' => 'Success stories', 'subtitle' => '', 'description' => 'Real transformations from Athletic members.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0320DefaultBlocks(): array
    {
        $industrial = collect($this->xd0314DefaultBlocks())->keyBy('block_type');
        $legacy = collect($this->xd0313DefaultBlocks())->keyBy('block_type');
        $logistics = collect($this->xd0312DefaultBlocks())->keyBy('block_type');

        $hero = $industrial->get('hero_slider');
        $hero['label'] = 'Header và hero công nghiệp';
        $hero['description'] = 'Header không có ô tìm kiếm, có đăng nhập/đăng ký và hero ảnh công nghiệp.';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'xd0320-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Giải pháp công nghiệp cho vận hành bền vững', 'subtitle' => 'XD0320 Industrial', 'description' => 'Tư vấn kỹ thuật, thi công và bảo trì cho doanh nghiệp.', 'button_label' => 'Nhận báo giá', 'content' => ['slides' => [['title' => 'Giải pháp công nghiệp cho vận hành bền vững', 'summary' => 'Đồng hành cùng doanh nghiệp từ tư vấn kỹ thuật đến thi công và bảo trì.', 'button_label' => 'Nhận báo giá', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#lien-he']]]];

        $quality = $industrial->get('featured_categories');
        $quality['label'] = 'Cam kết chất lượng';
        $quality['description'] = 'Các tiêu chí chất lượng, dùng icon Font Awesome.';
        $quality['anchor_id'] = 'dich-vu';
        $quality['data']['vi'] = ['title' => 'Cam kết chất lượng', 'subtitle' => 'Năng lực dịch vụ', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['title' => 'Hài lòng 100%', 'summary' => 'Lấy khách hàng làm trung tâm.', 'icon' => 'fa-regular fa-face-smile'],
            ['title' => 'Kiểm tra chính xác', 'summary' => 'Quy trình kiểm định rõ ràng.', 'icon' => 'fa-solid fa-ruler-combined'],
            ['title' => 'Nhân sự chuyên nghiệp', 'summary' => 'Đội ngũ kỹ thuật giàu kinh nghiệm.', 'icon' => 'fa-solid fa-users'],
            ['title' => 'Điều kiện tiêu chuẩn', 'summary' => 'Thiết bị và quy chuẩn đầy đủ.', 'icon' => 'fa-solid fa-gear'],
            ['title' => 'Giá cả minh bạch', 'summary' => 'Báo giá minh bạch và kịp thời.', 'icon' => 'fa-regular fa-money-bill-1'],
        ]]];

        $about = $industrial->get('about_experience');
        $about['label'] = 'Giới thiệu doanh nghiệp';
        $about['description'] = 'Khối giới thiệu nhanh dùng dữ liệu tùy chỉnh.';
        $about['media'] = ['image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1100&q=85'];
        $about['data']['vi'] = ['title' => 'Giúp khách hàng đạt được những thành tựu', 'subtitle' => 'Chào mừng bạn đến với XD0320', 'description' => 'XD0320 là doanh nghiệp cung cấp dịch vụ sản xuất và công nghệ chuyên nghiệp. Với nền tảng kỹ thuật vững chắc, chúng tôi cùng khách hàng tạo ra các giải pháp vận hành hiệu quả và bền vững.', 'button_label' => '', 'content' => ['years' => '20+', 'years_label' => 'Năm kinh nghiệm', 'items' => [['title' => 'Năng lực kỹ thuật đã được kiểm chứng'], ['title' => 'Đội ngũ chuyên nghiệp'], ['title' => 'Lắng nghe để đồng hành cùng khách hàng'], ['title' => 'Vật liệu và tiêu chuẩn chất lượng cao']]]];

        $feature = $legacy->get('logistics_feature_panel');
        $feature['label'] = 'Năng lực và dịch vụ';
        $feature['description'] = 'Khối giới thiệu nhanh thứ hai, dùng dữ liệu tùy chỉnh.';
        $feature['anchor_id'] = 'nang-luc';
        $feature['media'] = ['image' => 'https://images.unsplash.com/photo-1516939884455-1445c8652f83?auto=format&fit=crop&w=1500&q=85'];
        $feature['data']['vi'] = ['title' => 'Dịch vụ tốt nhất để tiến bộ bền vững', 'subtitle' => 'Năng lực kỹ thuật', 'description' => 'Giải pháp thực tế giúp doanh nghiệp gia tăng hiệu quả vận hành và tạo lợi thế cạnh tranh dài hạn.', 'button_label' => '', 'content' => ['items' => [['title' => 'Hỗ trợ kỹ thuật tận tâm'], ['title' => 'Công nghệ phù hợp'], ['title' => 'Đội ngũ giàu kinh nghiệm'], ['title' => 'Vật liệu chất lượng'], ['title' => 'Quy trình thông minh']]]];

        $projects = $industrial->get('content_mosaic');
        $projects['label'] = 'Dự án đa nguồn';
        $projects['description'] = 'Carousel ngang lấy từ tin tức, sản phẩm, dịch vụ, dự án hoặc nhập tay.';
        $projects['anchor_id'] = 'du-an';
        $projects['settings'] = ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => true];
        $projects['data']['vi'] = ['title' => 'Một số dự án đã thực hiện cho khách hàng', 'subtitle' => 'Dự án tiêu biểu', 'description' => 'Các dự án chuyên ngành tiêu biểu đã giúp XD0320 khẳng định vị thế và năng lực triển khai.', 'button_label' => 'Tất cả dự án', 'content' => ['items' => []]];

        $team = $industrial->get('team_members');
        $team['label'] = 'Đội ngũ kỹ sư';
        $team['description'] = 'Đội ngũ đồng nhất layout, gồm hình, tên và chức danh.';
        $team['data']['vi'] = ['title' => 'Đội ngũ kỹ sư có năng lực', 'subtitle' => 'Thành viên chuyên gia', 'description' => 'Những con người tâm huyết, kinh nghiệm và cùng hướng về kết quả cho khách hàng.', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'Anwar Ramadan', 'role' => 'Giám đốc nhân sự', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Osama Bakri', 'role' => 'Giám đốc điều hành', 'image' => 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Sana El-Shamy', 'role' => 'Kỹ sư xây dựng', 'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Jackson Ckumanni', 'role' => 'Quản lý dự án', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=700&q=85'],
        ]]];

        $partners = $logistics->get('partner_logos');
        $partners['label'] = 'Đối tác';
        $partners['description'] = 'Danh sách logo đối tác từ CMS Partners.';
        $partners['data']['vi'] = ['title' => 'Đối tác của chúng tôi', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        return [$hero, $quality, $about, $feature, $projects, $team, $partners];
    }

    private function xd0322DefaultBlocks(): array
    {
        $base = collect($this->xd321DefaultBlocks())->keyBy('block_type');
        $industrial = collect($this->xd0320DefaultBlocks())->keyBy('block_type');

        $hero = $base->get('hero_slider');
        $hero['label'] = 'Header va hero XD0322';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'xd0322-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Cung cap giai phap xay dung tot nhat', 'subtitle' => 'Chat luong - An toan - Hieu qua - Chuyen nghiep', 'description' => 'Thiet ke va thi cong tron goi cho cac cong trinh dan dung va thuong mai.', 'button_label' => 'Lien he bao gia', 'content' => ['slides' => [['title' => 'Cung cap giai phap xay dung tot nhat', 'summary' => 'Dich vu thi cong tron goi voi chien luoc linh hoat trong qua trinh van hanh va phat trien.', 'button_label' => 'Lien he bao gia', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#lien-he']]]];

        $quality = $base->get('featured_categories');
        $quality['label'] = 'Cam ket XD0322';
        $quality['data']['vi'] = ['title' => 'Cam ket chat luong', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['title' => 'Cam ket chat luong', 'summary' => 'Kiem soat ky tung hang muc.', 'icon' => 'fa-regular fa-lightbulb'], ['title' => 'Dung tien do', 'summary' => 'Bao dam ke hoach thi cong.', 'icon' => 'fa-solid fa-hand-holding-heart'], ['title' => 'Tan tam voi khach hang', 'summary' => 'Dong hanh trong tung giai doan.', 'icon' => 'fa-solid fa-trowel-bricks'], ['title' => 'Bao hanh dai han', 'summary' => 'Chinh sach sau ban giao ro rang.', 'icon' => 'fa-solid fa-helmet-safety']]]];

        $about = $base->get('about_experience');
        $about['data']['vi'] = ['title' => 'Chung toi dan dau trong linh vuc xay dung', 'subtitle' => 'Ve chung toi', 'description' => 'XD0322 la don vi thiet ke va thi cong tron goi. Chung toi giu vung cam ket ve chat luong, an toan, hieu qua va tinh chuyen nghiep trong moi cong trinh.', 'button_label' => 'Xem them', 'content' => ['years' => '18+', 'years_label' => 'Nam trong nghe', 'items' => []]];

        $services = $base->get('content_mosaic');
        $services['data']['vi'] = ['title' => 'Dich vu tot nhat cho ban', 'subtitle' => 'Dich vu cua chung toi', 'description' => 'Giai phap thiet ke, thi cong va hoan thien phu hop voi moi nhu cau.', 'button_label' => '', 'content' => ['items' => []]];

        $projects = $base->get('project_gallery');
        $projects['data']['vi'] = ['title' => 'Du an cua chung toi', 'subtitle' => 'Cong trinh tieu bieu', 'description' => 'Nhung cong trinh chung cu, biet thu, nha pho va van phong da hoan thien.', 'button_label' => '', 'content' => ['items' => []]];

        $products = $base->get('featured_products');
        $products['data']['vi']['title'] = 'San pham cua chung toi';
        $products['data']['vi']['subtitle'] = 'Noi that va vat lieu hoan thien';

        $team = $industrial->get('team_members');
        $team['data']['vi'] = ['title' => 'Doi ngu cua chung toi', 'subtitle' => 'Tan tam, chuyen nghiep', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $testimonials = $base->get('testimonials');
        $testimonials['data']['vi'] = ['title' => 'Nhan xet cua khach hang', 'subtitle' => 'Phan hoi sau thi cong', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $pricing = $base->get('process_steps');
        $pricing['label'] = 'Bao gia thi cong';
        $pricing['data']['vi'] = ['title' => 'Bao gia thi cong', 'subtitle' => 'Goi dich vu', 'description' => 'Cac goi thi cong linh hoat phu hop voi quy mo va ngan sach cong trinh.', 'button_label' => 'Dang ky ngay', 'content' => ['items' => [['title' => 'Goi co ban', 'summary' => '280.000d/m2'], ['title' => 'Goi nang cao', 'summary' => '350.000d/m2'], ['title' => 'Goi cao cap', 'summary' => '500.000d/m2']]]];

        $partners = $base->get('partner_logos');
        $news = $base->get('bizmax_latest_posts');
        $news['data']['vi'] = ['title' => 'Bai viet moi nhat', 'subtitle' => 'Tin tuc va cap nhat', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        return [$hero, $quality, $about, $services, $products, $team, $projects, $pricing, $testimonials, $news, $partners];
    }

    private function xd321DefaultBlocks(): array
    {
        $industrial = collect($this->xd0320DefaultBlocks())->keyBy('block_type');
        $interior = collect($this->xd0314DefaultBlocks())->keyBy('block_type');
        $foot = collect($this->foot401DefaultBlocks())->keyBy('block_type');
        $logistics = collect($this->xd0312DefaultBlocks())->keyBy('block_type');

        $hero = $industrial->get('hero_slider');
        $hero['label'] = 'Header va hero logistics XD321';
        $hero['description'] = 'Hero slider cho dich vu van chuyen va logistics.';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'xd321-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Nhanh chong, an toan cung XD321 Cargo', 'subtitle' => 'XD321 Cargo', 'description' => 'Toi uu moi hanh trinh van chuyen tu noi dia den quoc te.', 'button_label' => 'Xem them', 'content' => ['slides' => [['title' => 'Nhanh chong, an toan cung XD321 Cargo', 'summary' => 'Toi uu moi hanh trinh van chuyen tu noi dia den quoc te. An toan va tiet kiem.', 'button_label' => 'Xem them', 'image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#dich-vu']]]];

        $quality = $industrial->get('featured_categories');
        $quality['label'] = 'Cam ket dich vu';
        $quality['data']['vi'] = ['title' => 'Cam ket XD321 Cargo', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['title' => 'Dung tien do', 'summary' => 'Theo doi don hang lien tuc.', 'icon' => 'fa-solid fa-clock'], ['title' => 'An toan hang hoa', 'summary' => 'Quy trinh minh bach.', 'icon' => 'fa-solid fa-shield-halved'], ['title' => 'Ket noi toan cau', 'summary' => 'Mang luoi quoc te.', 'icon' => 'fa-solid fa-globe'], ['title' => 'Chi phi toi uu', 'summary' => 'Bao gia ro rang.', 'icon' => 'fa-solid fa-tags']]]];

        $about = $industrial->get('about_experience');
        $about['label'] = 'Gioi thieu XD321 Cargo';
        $about['media'] = ['image' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1100&q=85'];
        $about['data']['vi'] = ['title' => 'Doi tac logistics tin cay cho doanh nghiep', 'subtitle' => 'Gioi thieu ve XD321 Cargo', 'description' => 'Chung toi ket noi thuong mai toan cau bang giai phap van chuyen dang tin cay, minh bach va linh hoat. Doi ngu chuyen nghiep ho tro tu kho bai den giao nhan cuoi cung.', 'button_label' => 'Tu van ngay', 'content' => ['years' => '15+', 'years_label' => 'Nam kinh nghiem', 'items' => [['title' => 'Van tai da phuong thuc'], ['title' => 'Quy trinh kiem soat chat che'], ['title' => 'Dich vu linh hoat cho tung lo hang'], ['title' => 'Mang luoi doi tac toan cau']]]];

        $services = $interior->get('content_mosaic');
        $services['label'] = 'Dich vu van chuyen'; $services['anchor_id'] = 'dich-vu';
        $services['description'] = 'Lay tu tin tuc, san pham, dich vu, du an hoac nhap tay.';
        $services['settings'] = ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true];
        $services['data']['vi'] = ['title' => 'Dich vu van chuyen', 'subtitle' => 'Ket noi van tai toan cau', 'description' => 'Giai phap linh hoat cho hang hoa duong bien, hang khong va duong bo.', 'button_label' => '', 'content' => ['items' => []]];

        $solutions = $interior->get('content_mosaic');
        $solutions['block_type'] = 'project_gallery'; $solutions['label'] = 'Giai phap logistics'; $solutions['anchor_id'] = 'giai-phap';
        $solutions['settings'] = ['source' => 'cms_projects', 'limit' => 3, 'featured_only' => true];
        $solutions['data']['vi'] = ['title' => 'Giai phap logistics hien dai', 'subtitle' => 'Giai phap', 'description' => 'Tu luu kho, xu ly don hang den dieu phoi chuoi cung ung.', 'button_label' => '', 'content' => ['items' => []]];

        $process = $logistics->get('process_steps');
        $process['label'] = 'Quy trinh van hanh'; $process['anchor_id'] = 'quy-trinh';
        $process['data']['vi'] = ['title' => 'Quy trinh dam bao van hanh logistics xuyen suot', 'subtitle' => 'Quy trinh lam viec', 'description' => 'Moi buoc deu duoc kiem soat chat che de dam bao tien do va tinh minh bach.', 'button_label' => 'Lien he ngay', 'content' => ['items' => [['title' => 'Tiep nhan va len ke hoach', 'summary' => 'Phan tich chi tiet lo hang va xay dung phuong an toi uu.'], ['title' => 'Dieu phoi va trien khai', 'summary' => 'To chuc van chuyen, xu ly chung tu va toi uu lo trinh.'], ['title' => 'Theo doi va cap nhat', 'summary' => 'Cap nhat trang thai lien tuc trong thoi gian thuc.'], ['title' => 'Giao hang va toi uu', 'summary' => 'Danh gia ket qua de nang cao chat luong dich vu.']]]];

        $products = $foot->get('featured_products');
        $products['label'] = 'San pham logistics'; $products['anchor_id'] = 'san-pham';
        $products['data']['vi']['title'] = 'San pham ho tro logistics'; $products['data']['vi']['subtitle'] = 'Vat tu dong goi';

        $testimonials = $industrial->get('team_members');
        $testimonials['block_type'] = 'testimonials'; $testimonials['label'] = 'Phan hoi khach hang';
        $testimonials['data']['vi'] = ['title' => 'Khach hang noi gi ve XD321 Cargo', 'subtitle' => 'Phan hoi tu doi tac', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $partners = $industrial->get('partner_logos'); $partners['label'] = 'Mang luoi doi tac';
        $news = $foot->get('bizmax_latest_posts'); $news['label'] = 'Tin tuc logistics'; $news['anchor_id'] = 'tin-tuc';
        $news['settings'] = ['source' => 'cms_posts', 'limit' => 6, 'featured_only' => true];
        $news['data']['vi'] = ['title' => 'Cap nhat tin tuc logistics', 'subtitle' => 'Kien thuc va thi truong', 'description' => '', 'button_label' => 'Xem them', 'content' => ['items' => []]];
        return [$hero, $quality, $about, $services, $solutions, $process, $products, $testimonials, $partners, $news];
    }

    private function nt501DefaultBlocks(): array
    {
        $interior = collect($this->xd0314DefaultBlocks())->keyBy('block_type');
        $legacy = collect($this->xd0313DefaultBlocks())->keyBy('block_type');
        $foot = collect($this->foot401DefaultBlocks())->keyBy('block_type');
        $partners = collect($this->xd0312DefaultBlocks())->keyBy('block_type');

        $hero = $interior->get('hero_slider');
        $hero['label'] = 'Header va hero NT501';
        $hero['description'] = 'Hero slider cho studio thiet ke noi that.';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'nt501-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Khong gian dep, chat luong ben vung', 'subtitle' => 'NT501 Interior Studio', 'description' => 'Thiet ke va thi cong noi that cho nhung ngoi nha mang dau an rieng.', 'button_label' => 'Kham pha du an', 'content' => ['slides' => [['title' => 'Khong gian dep, chat luong ben vung', 'summary' => 'Dong hanh cung ban kien tao mot khong gian song tinh te va ben vung.', 'button_label' => 'Kham pha du an', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#du-an']]]];

        $about = $interior->get('about_experience');
        $about['label'] = 'Gioi thieu studio';
        $about['description'] = 'Khoi gioi thieu nhanh dung du lieu tuy chinh.';
        $about['media'] = ['image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=1200&q=90'];
        $about['data']['vi'] = ['title' => 'Kien tao khong gian song day cam hung', 'subtitle' => 'Gioi thieu ve NT501', 'description' => 'NT501 ket hop tu duy thiet ke tinh te, vat lieu chat luong va quy trinh thi cong ky luong de bien moi y tuong thanh mot khong gian dang song.', 'button_label' => 'Xem dich vu', 'content' => ['items' => []]];

        $showcase = $interior->get('content_mosaic');
        $showcase['label'] = 'Khong gian tieu bieu';
        $showcase['description'] = 'Lay tu tin tuc, san pham, dich vu, du an hoac nhap tay.';
        $showcase['anchor_id'] = 'khong-gian';
        $showcase['settings'] = ['source' => 'cms_projects', 'limit' => 2, 'featured_only' => true];
        $showcase['data']['vi'] = ['title' => 'Giai phap cho moi phong cach song', 'subtitle' => 'Khong gian tieu bieu', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $projects = $interior->get('content_mosaic');
        $projects['block_type'] = 'project_gallery';
        $projects['label'] = 'Du an noi bat';
        $projects['description'] = 'Du an hien thi thanh thanh truot ngang.';
        $projects['anchor_id'] = 'du-an';
        $projects['settings'] = ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => true];
        $projects['data']['vi'] = ['title' => 'Nhung cong trinh noi bat', 'subtitle' => 'Du an da thuc hien', 'description' => '', 'button_label' => 'Xem tat ca', 'content' => ['items' => []]];

        $services = $interior->get('featured_services');
        $services['label'] = 'Dich vu noi that';
        $services['description'] = 'Lay tu nhieu nguon du lieu hoac nhap tay.';
        $services['anchor_id'] = 'dich-vu';
        $services['settings'] = ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true];
        $services['data']['vi'] = ['title' => 'Dong hanh tu y tuong den hoan thien', 'subtitle' => 'Dich vu cua chung toi', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $testimonials = $legacy->get('testimonials');
        $testimonials['label'] = 'Cam nhan khach hang';
        $testimonials['description'] = 'Phan hoi khach hang va hinh anh dai dien.';
        $testimonials['data']['vi'] = ['title' => 'Dieu khach hang noi ve chung toi', 'subtitle' => 'Cam nhan khach hang', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $posts = $foot->get('bizmax_latest_posts');
        $posts['label'] = 'Bai viet noi that';
        $posts['description'] = 'Lay tu tin tuc, san pham, dich vu, du an hoac nhap tay.';
        $posts['settings'] = ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => true];
        $posts['data']['vi'] = ['title' => 'Bai viet gan day', 'subtitle' => 'Tin tuc va cap nhat', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $partnerLogos = $partners->get('partner_logos');
        $partnerLogos['label'] = 'Doi tac NT501';

        $stats = $interior->get('featured_categories');
        $stats['label'] = 'Chi so nang luc';
        $stats['description'] = 'Ba chi so nang luc dung du lieu tuy chinh.';
        $stats['data']['vi'] = ['title' => 'Nang luc NT501', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['title' => '10+', 'summary' => 'Nam lam viec'], ['title' => '20', 'summary' => 'Chuyen gia noi that'], ['title' => '1000', 'summary' => 'Du an tiem nang']]]];

        return [$hero, $about, $showcase, $projects, $services, $testimonials, $posts, $partnerLogos, $stats];
    }

    private function foot401DefaultBlocks(): array
    {
        $blocks = collect($this->xd0314DefaultBlocks())->keyBy('block_type');

        $hero = $blocks->get('hero_slider');
        $hero['label'] = 'Header và hero FOOT401';
        $hero['description'] = 'Header không có ô tìm kiếm, có đăng nhập/đăng ký và hero slider ảnh ẩm thực.';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'foot401-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = [
            'title' => 'Ẩm thực được kể bằng ký ức',
            'subtitle' => 'FOOT401 Restaurant',
            'description' => 'Những nguyên liệu theo mùa, căn bếp mở và các cuộc gặp gỡ được chuẩn bị thật riêng.',
            'button_label' => 'Khám phá thực đơn',
            'content' => ['slides' => [
                ['title' => 'Ẩm thực được kể bằng ký ức', 'summary' => 'Một bàn ăn ấm áp, những nguyên liệu theo mùa và trải nghiệm được chuẩn bị dành riêng cho từng vị khách.', 'button_label' => 'Khám phá thực đơn', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#thuc-don'],
                ['title' => 'Một buổi tối thật đáng nhớ', 'summary' => 'Không gian riêng, hương vị tinh tế và nhịp phục vụ vừa đủ để bạn ở lại lâu hơn.', 'button_label' => 'Xem dịch vụ', 'image' => 'https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#dich-vu'],
            ]],
        ];

        $services = $blocks->get('content_mosaic');
        $services['label'] = 'Dịch vụ nổi bật';
        $services['description'] = 'Carousel có tiêu đề nằm trong ảnh; lấy từ tin tức, sản phẩm, dịch vụ, dự án hoặc nhập tay.';
        $services['anchor_id'] = 'dich-vu';
        $services['settings'] = ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true];
        $services['data']['vi'] = ['title' => 'Dịch vụ nổi bật', 'subtitle' => 'Trải nghiệm FOOT401', 'description' => '', 'button_label' => 'Khám phá', 'content' => ['items' => [
            ['title' => 'Bữa tối riêng', 'summary' => 'Không gian bàn tiệc được thiết kế theo câu chuyện của riêng bạn.', 'image' => 'https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Bếp tại bàn', 'summary' => 'Đầu bếp chia sẻ hành trình của nguyên liệu ngay trước mắt thực khách.', 'image' => 'https://images.unsplash.com/photo-1551218808-94e220e084d2?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Sự kiện ẩm thực', 'summary' => 'Một buổi gặp gỡ chỉn chu cho doanh nghiệp và những dịp đặc biệt.', 'image' => 'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?auto=format&fit=crop&w=900&q=85'],
        ]]];

        $about = $blocks->get('about_experience');
        $about['label'] = 'Giới thiệu nhà hàng';
        $about['description'] = 'Khối giới thiệu nhanh, dùng dữ liệu do người dùng nhập.';
        $about['media'] = ['image' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=1100&q=85', 'background' => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=2200&q=90'];
        $about['data']['vi'] = ['title' => 'Một căn bếp mở cho những cuộc gặp gỡ', 'subtitle' => 'Câu chuyện FOOT401', 'description' => 'FOOT401 tin rằng một bữa ăn đẹp bắt đầu từ sự tôn trọng nguyên liệu. Chúng tôi chọn món theo mùa, lắng nghe từng vị khách và dành thời gian để mỗi cuộc gặp gỡ trở nên ấm áp hơn.', 'button_label' => 'Tìm hiểu thêm', 'content' => ['items' => [], 'stats' => []]];

        $products = [
            'block_type' => 'featured_products',
            'label' => 'Thực đơn sản phẩm',
            'description' => 'Carousel món ăn lấy trực tiếp từ bảng sản phẩm.',
            'preview_image' => '/theme-previews/FOOT401/preview-foot401.png',
            'anchor_id' => 'thuc-don',
            'dynamic' => true,
            'settings' => ['limit' => 8, 'featured_only' => true],
            'settings_schema' => [
                'limit' => ['type' => 'number', 'label' => 'Số món hiển thị'],
                'category_id' => ['type' => 'number', 'label' => 'Danh mục sản phẩm'],
                'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy món nổi bật'],
            ],
            'data' => ['vi' => ['title' => 'Thực đơn theo mùa', 'subtitle' => 'Từ bếp', 'description' => '', 'button_label' => 'Xem món', 'content' => ['items' => []]], 'en' => ['title' => 'Seasonal menu', 'subtitle' => 'From the kitchen', 'description' => '', 'button_label' => 'View dish', 'content' => ['items' => []]]],
        ];

        $stories = $blocks->get('featured_services');
        $stories['block_type'] = 'bizmax_latest_posts';
        $stories['label'] = 'Tin tức và sự kiện';
        $stories['description'] = 'Carousel ngang lấy từ tin tức, sản phẩm, dịch vụ, dự án hoặc nhập tay.';
        $stories['anchor_id'] = 'tin-tuc';
        $stories['settings'] = ['source' => 'cms_posts', 'limit' => 6, 'featured_only' => true];
        $stories['data']['vi'] = ['title' => 'Tin tức và sự kiện', 'subtitle' => 'Nhật ký bàn ăn', 'description' => '', 'button_label' => 'Đọc thêm', 'content' => ['items' => [
            ['title' => 'Những nguyên liệu làm nên một mùa mới', 'summary' => 'Câu chuyện về những chuyến đi tìm hương vị cho thực đơn FOOT401.', 'image' => 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Một buổi tối cùng những người bạn', 'summary' => 'Gợi ý cho buổi gặp gỡ thật chậm rãi và nhiều dư vị.', 'image' => 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Bếp mở và hành trình của món ăn', 'summary' => 'Chúng tôi chuẩn bị món ăn như thế nào trước giờ phục vụ.', 'image' => 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=900&q=85'],
        ]]];

        $team = $blocks->get('team_members');
        $team['label'] = 'Đội ngũ FOOT401';
        $team['description'] = 'Giới thiệu đội ngũ theo một layout thống nhất, có tên và chức danh.';
        $team['data']['vi'] = ['title' => 'Đội ngũ của chúng tôi', 'subtitle' => 'Những con người tạo nên trải nghiệm', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'Linh Nguyễn', 'role' => 'Bếp trưởng', 'image' => 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Minh Trần', 'role' => 'Bếp phó', 'image' => 'https://images.unsplash.com/photo-1583394293214-28ded15ee548?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'An Phạm', 'role' => 'Quản lý nhà hàng', 'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Quang Lê', 'role' => 'Chuyên gia đồ uống', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=700&q=85'],
        ]]];

        return [$hero, $services, $about, $products, $stories, $team];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0314DefaultBlocks(): array
    {
        $sources = [
            ['value' => 'custom', 'label' => 'Nhap thu cong'],
            ['value' => 'cms_posts', 'label' => 'Tin tuc'],
            ['value' => 'cms_products', 'label' => 'San pham'],
            ['value' => 'cms_services', 'label' => 'Dich vu'],
            ['value' => 'cms_projects', 'label' => 'Du an'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Header va banner',
                'description' => 'Topbar, header, login/register va hero slider hinh anh.',
                'preview_image' => '/theme-previews/XD0314/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0314-hero-slider', 'limit' => 3, 'autoplay_ms' => 6200],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vi tri banner'],
                    'limit' => ['type' => 'number', 'label' => 'So slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Think different - do different',
                        'subtitle' => 'Build Bench',
                        'description' => 'Hien thuc hoa uoc mo so huu ngoi nha hoan hao cua khach hang bang kinh nghiem va su chuyen nghiep.',
                        'button_label' => 'Xem them',
                        'content' => ['slides' => [
                            ['title' => 'Think different - do different', 'summary' => 'Hien thuc hoa uoc mo so huu ngoi nha hoan hao cua khach hang, thoi hon vao tung cong trinh bang kinh nghiem, su chuyen nghiep cua chung toi.', 'button_label' => 'Xem them', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#gioi-thieu'],
                            ['title' => 'Thiet ke va thi cong tron goi', 'summary' => 'Tu ban ve, vat lieu den thi cong hoan thien, moi buoc deu duoc quan ly ro rang.', 'button_label' => 'Dich vu', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Think different - do different', 'subtitle' => 'Build Bench', 'description' => 'Professional construction solutions for homes and commercial spaces.', 'button_label' => 'Learn more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Slide danh muc dich vu',
                'description' => 'Danh muc dich vu chay ngang.',
                'preview_image' => '/theme-previews/XD0314/service-category-slider.png',
                'anchor_id' => 'danh-muc-dich-vu',
                'settings' => [],
                'data' => [
                    'vi' => [
                        'title' => 'Danh muc dich vu',
                        'subtitle' => 'Dich vu',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Thi cong ong nuoc', 'summary' => 'Sua chua va thi cong ong nuoc ngam cho cong trinh.', 'icon' => '🚰', 'url' => '#dich-vu'],
                            ['title' => 'Son sua cong trinh', 'summary' => 'Son sua cong trinh lon nho dung tien do.', 'icon' => '🎨', 'url' => '#dich-vu'],
                            ['title' => 'Thi cong noi that', 'summary' => 'Thiet ke noi that da dang phong cach.', 'icon' => '🪑', 'url' => '#dich-vu'],
                            ['title' => 'Thi cong xay dung', 'summary' => 'Kien truc su va tho lanh nghe giau kinh nghiem.', 'icon' => '🏠', 'url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Service categories', 'subtitle' => 'Services', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Gioi thieu cong ty',
                'description' => 'Khoi gioi thieu nhanh, card nang luc va thong ke nhap tuy chinh.',
                'preview_image' => '/theme-previews/XD0314/about-experience.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => [],
                'media' => ['image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1400&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Ve cong ty',
                        'subtitle' => 'Gioi thieu',
                        'description' => 'Voi kinh nghiem cung doi ngu cong nhan hang dau, chung toi da dat duoc nhieu thanh cong voi cac cong trinh tren khap dat nuoc.',
                        'button_label' => '',
                        'content' => [
                            'items' => [
                                ['title' => 'Thi cong ong nuoc', 'summary' => 'Thuc hien sua chua va thi cong ong nuoc ngam cho cong trinh va nha o.', 'icon' => '🚰'],
                                ['title' => 'Son sua cong trinh', 'summary' => 'Nhan va thuc hien cac yeu cau son sua cong trinh lon nho dung tien do.', 'icon' => '🎨'],
                                ['title' => 'Thi cong noi that', 'summary' => 'Nhan luc thiet ke noi that da dang voi nhieu phong cach.', 'icon' => '🪑'],
                                ['title' => 'Thi cong xay dung', 'summary' => 'Doi ngu kien truc su, tho lanh nghe se giup uoc mo cua ban thanh hien thuc.', 'icon' => '🏡'],
                            ],
                            'stats' => [
                                ['value' => '316', 'label' => 'Du an da hoan thanh', 'icon' => '▥'],
                                ['value' => '761', 'label' => 'Khach hang hai long', 'icon' => '●'],
                                ['value' => '1245', 'label' => 'Cong nhan lam viec', 'icon' => '☏'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'About company', 'subtitle' => 'About', 'description' => 'Experienced teams delivering construction projects nationwide.', 'button_label' => '', 'content' => []],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Du an moi nhat',
                'description' => 'Gallery anh co tieu de trong anh, lay du lieu tu tin tuc/san pham/dich vu/du an hoac custom.',
                'preview_image' => '/theme-previews/XD0314/project-gallery.png',
                'anchor_id' => 'du-an',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $sources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'media' => ['background' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Du an moi nhat',
                        'subtitle' => '',
                        'description' => '',
                        'button_label' => 'Xem chi tiet',
                        'content' => ['tabs' => [
                            ['label' => 'Mau nha dang hot'], ['label' => 'Mau nha don gian dep 2019'], ['label' => 'Mau nha cao cap dep 2019'], ['label' => 'Mau nha cap 4 dep 2019'],
                        ], 'items' => [
                            ['title' => 'Mau nha pho hien dai', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Toa nha van phong', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'San vuon tren cao', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Chung cu cao tang', 'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Latest projects', 'subtitle' => '', 'description' => '', 'button_label' => 'View more', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'Tai sao chon chung toi',
                'description' => 'Khoi ly do chon nhap lieu tuy chinh.',
                'preview_image' => '/theme-previews/XD0314/why-choose.png',
                'anchor_id' => 'tai-sao-chon',
                'settings' => [],
                'media' => ['background' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Tai sao chon chung toi !',
                        'subtitle' => '',
                        'description' => 'Tieu chi kinh doanh hang dau cua chung toi la tao ra san pham doc dao, toi uu cho khach hang va dam bao hoan thanh du an dung y tuong, chat luong va tien do.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Chat luong tot nhat', 'summary' => 'Chat luong cong trinh luon dam bao tot nhat va phu hop cac tieu chuan chung.', 'icon' => '◎'],
                            ['title' => 'Chinh truc', 'summary' => 'Dam bao tinh trung thuc va chinh truc trong qua trinh thiet ke va xay dung.', 'icon' => '🏆'],
                            ['title' => 'Chien luoc', 'summary' => 'Cung cap phuong an va chien luoc xay dung day du cho khach hang.', 'icon' => '☝'],
                            ['title' => 'Su an toan', 'summary' => 'Dat tinh an toan len hang dau trong qua trinh xay dung.', 'icon' => '●'],
                            ['title' => 'Cong dong', 'summary' => 'Cong trinh phu hop tieu chuan cong dong va boi canh xung quanh.', 'icon' => '▰'],
                            ['title' => 'Su ben vung', 'summary' => 'Cong trinh duoc xay dung chat luong va ben vung theo thoi gian.', 'icon' => '⚙'],
                        ]],
                    ],
                    'en' => ['title' => 'Why choose us', 'subtitle' => '', 'description' => 'Practical construction values for quality, safety and progress.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dich vu cua chung toi',
                'description' => 'Carousel dich vu lay tu nhieu nguon hoac custom.',
                'preview_image' => '/theme-previews/XD0314/featured-services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => $sources],
                    'limit' => ['type' => 'number', 'label' => 'So item hien thi'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh muc'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Dich vu cua chung toi',
                        'subtitle' => 'Dich vu',
                        'description' => '',
                        'button_label' => 'Xem them',
                        'content' => ['items' => [
                            ['title' => 'Xay dung nha pho theo nhu cau su dung', 'summary' => 'Thiet ke va xay dung nha thanh pho hien dai.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Xay dung nha pho 2 tang mai Thai', 'summary' => 'Kien truc tinh te, toi uu cong nang su dung.', 'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Xay dung biet thu vuon', 'summary' => 'Khong gian song xanh, tien nghi va thoai mai.', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Our services', 'subtitle' => 'Services', 'description' => '', 'button_label' => 'Read more', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Doi ngu',
                'description' => 'Gioi thieu doi ngu nhan su, moi item cung layout voi chuc danh va ten.',
                'preview_image' => '/theme-previews/XD0314/team-members.png',
                'anchor_id' => 'doi-ngu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_team_members', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguon du lieu', 'options' => [['value' => 'custom', 'label' => 'Nhap thu cong'], ['value' => 'cms_team_members', 'label' => 'Doi ngu']]],
                    'limit' => ['type' => 'number', 'label' => 'So nhan su'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chi lay noi bat'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Doi cua chung toi',
                        'subtitle' => 'Doi ngu',
                        'description' => 'Voi doi ngu cong nhan lau nam va kinh nghiem, chung toi dam bao mang den cac cong trinh dat tieu chuan trong ca thiet ke va xay dung.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Danny Johnny', 'role' => 'Building Worker', 'image' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?auto=format&fit=crop&w=700&q=85'],
                            ['name' => 'Anna Smith', 'role' => 'Site Engineer', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=700&q=85'],
                            ['name' => 'John Carter', 'role' => 'Construction Lead', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=700&q=85'],
                            ['name' => 'Maria Hill', 'role' => 'Safety Manager', 'image' => 'https://images.unsplash.com/photo-1581092795360-fd1ca04f0952?auto=format&fit=crop&w=700&q=85'],
                            ['name' => 'Peter Brown', 'role' => 'Architect', 'image' => 'https://images.unsplash.com/photo-1568602471122-7832951cc4c5?auto=format&fit=crop&w=700&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Our team', 'subtitle' => 'Team', 'description' => 'Experienced construction people delivering quality projects.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0312DefaultBlocks(): array
    {
        $blocks = collect($this->xd0305DefaultBlocks())->keyBy('block_type');
        $process = collect($this->xd0303DefaultBlocks())->firstWhere('block_type', 'process_steps');

        $hero = $blocks->get('hero_slider');
        $hero['settings']['placement'] = 'xd0312-hero-slider';
        $hero['settings_schema'][0]['default'] = 'xd0312-hero-slider';
        $hero['data']['vi'] = ['title' => 'Dich vu kho bai va luu tru', 'subtitle' => 'Bizgrow logistics', 'description' => 'Giai phap kho bai, van chuyen va chuoi cung ung toi uu cho doanh nghiep.', 'button_label' => 'Xem dich vu cua chung toi', 'content' => ['slides' => []]];

        $about = $blocks->get('bizmax_about');
        $about['data']['vi'] = ['title' => 'Logistics tren toan the gioi', 'subtitle' => 'Ve chung toi', 'description' => 'Bizgrow ket noi kho bai, van chuyen va giao nhan bang quy trinh ro rang, linh hoat va tiet kiem chi phi.', 'button_label' => 'Kham pha chung toi', 'content' => ['image_primary' => '', 'image_secondary' => '', 'years' => '15+', 'years_label' => 'Nam kinh nghiem', 'progress_label' => 'Khach hang hai long', 'progress_value' => 98]];

        $services = $blocks->get('business_service_grid');
        $services['data']['vi'] = ['title' => 'Giai phap kinh doanh sang tao', 'subtitle' => 'Dich vu pho bien', 'description' => 'Dich vu hau can dong bo tu kho bai den giao nhan quoc te.', 'button_label' => 'Xem chi tiet', 'content' => ['items' => []]];

        $process['data']['vi'] = ['title' => 'Quy trinh lam viec', 'subtitle' => 'Quy trinh lam viec', 'description' => 'Minh bach trong tung buoc de don hang duoc xu ly chinh xac va dung tien do.', 'button_label' => '', 'content' => ['items' => [
            ['title' => 'Yeu cau bao gia', 'description' => 'Tiep nhan nhu cau va phan hoi phuong an phu hop.'],
            ['title' => 'Tiep nhan don hang', 'description' => 'Xac nhan thong tin, lich trinh va chung tu can thiet.'],
            ['title' => 'Dat kho bai va luu tru', 'description' => 'Sap xep hang hoa khoa hoc va theo doi bang du lieu so.'],
            ['title' => 'Van chuyen san pham', 'description' => 'Giao hang an toan, dung thoi gian va cap nhat lien tuc.'],
        ]]];

        $benefits = $blocks->get('bizmax_benefit_panel');
        $benefits['data']['vi'] = ['title' => 'Nang luc logistics san sang dong hanh', 'subtitle' => 'Quy mo Bizgrow', 'description' => 'He thong van hanh duoc xay dung de mo rong cung doanh nghiep.', 'button_label' => '', 'content' => ['image' => '', 'items' => [
            ['title' => '50 cum kho tren toan quoc'], ['title' => '500 can bo nhan vien'], ['title' => '1000 xe tai chuyen dung'], ['title' => '5000 khach hang tin tuong'],
        ]]];

        $team = $blocks->get('team_members');
        $team['data']['vi']['title'] = 'Doi ngu chuyen gia cua chung toi';
        $team['data']['vi']['subtitle'] = 'Thanh vien chuyen gia';
        $team['data']['vi']['description'] = 'Nhung con nguoi mang den su toi uu va hieu qua cho chuoi cung ung cua ban.';

        $partners = $blocks->get('partner_logos');
        $partners['data']['vi']['title'] = 'Doi tac tin cay';
        $partners['data']['vi']['subtitle'] = 'Ket noi toan cau';

        $posts = $blocks->get('bizmax_latest_posts');
        $posts['data']['vi']['title'] = 'Tin tuc cua chung toi';
        $posts['data']['vi']['subtitle'] = 'Tu tap chi';
        $posts['data']['vi']['button_label'] = 'Xem them';

        return [$hero, $about, $services, $process, $benefits, $team, $partners, $posts];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0311DefaultBlocks(): array
    {
        $blocks = $this->xd0305DefaultBlocks();
        $processBlock = collect($this->xd0303DefaultBlocks())->firstWhere('block_type', 'process_steps');
        array_splice($blocks, 4, 0, [$processBlock]);

        $blocks[0]['settings']['placement'] = 'xd0311-hero-slider';
        $blocks[0]['settings_schema'][0]['default'] = 'xd0311-hero-slider';
        $blocks[0]['data']['vi'] = [
            'title' => 'Dich vu ke toan - thue cho doanh nghiep',
            'subtitle' => 'Tu van tai chinh chuyen nghiep',
            'description' => 'Dong hanh cung doanh nghiep tu ke toan, ke khai thue den quan tri tai chinh minh bach.',
            'button_label' => 'Tim hieu them',
            'content' => ['slides' => []],
        ];
        $blocks[1]['data']['vi']['title'] = 'Dich vu noi bat cua chung toi';
        $blocks[1]['data']['vi']['subtitle'] = 'Dich vu ke toan va tu van';
        $blocks[2]['data']['vi']['title'] = 'Tin tuong voi cac ke toan vien gioi nhat cua chung toi';
        $blocks[2]['data']['vi']['subtitle'] = 'Ve chung toi';
        $blocks[2]['data']['vi']['description'] = 'Doi ngu InVess cung cap giai phap ke toan, thue va tai chinh thuc te, ro rang cho doanh nghiep.';
        $blocks[2]['data']['vi']['content']['years'] = '25+';
        $blocks[2]['data']['vi']['content']['years_label'] = 'Nam kinh nghiem';
        $blocks[3]['data']['vi']['title'] = 'Noi giac mo chap canh';
        $blocks[3]['data']['vi']['subtitle'] = 'Chung toi lam gi';
        $blocks[4]['data']['vi'] = [
            'title' => 'Cach chung toi hoat dong',
            'subtitle' => 'Quy trinh lam viec',
            'description' => 'Quy trinh tu van ro rang giup doanh nghiep chu dong trong tung quyet dinh tai chinh.',
            'button_label' => '',
            'content' => ['items' => [
                ['title' => 'Tiep nhan nhu cau', 'description' => 'Lang nghe muc tieu va thu thap thong tin can thiet.'],
                ['title' => 'Danh gia ho so', 'description' => 'Phan tich du lieu va xac dinh phuong an phu hop.'],
                ['title' => 'Tu van giai phap', 'description' => 'Trinh bay ke hoach minh bach ve chi phi va tien do.'],
                ['title' => 'Dong hanh trien khai', 'description' => 'Theo doi ket qua va ho tro doanh nghiep kip thoi.'],
            ]],
        ];
        $blocks[5]['data']['vi']['title'] = 'Cam nhan tu khach hang';
        $blocks[5]['data']['vi']['subtitle'] = 'Loi chung thuc';
        $blocks[6]['data']['vi']['title'] = 'Tin moi nhat';
        $blocks[6]['data']['vi']['subtitle'] = 'Kien thuc cho doanh nghiep';
        $blocks[7]['data']['vi']['title'] = 'Doi tac dong hanh';

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0310DefaultBlocks(): array
    {
        $blocks = $this->xd0305DefaultBlocks();
        $projectBlock = collect($this->xd0301DefaultBlocks())->firstWhere('block_type', 'project_gallery');
        array_splice($blocks, 4, 0, [$projectBlock]);

        $blocks[0]['settings']['placement'] = 'xd0310-hero-slider';
        $blocks[0]['settings_schema'][0]['default'] = 'xd0310-hero-slider';
        $blocks[0]['data']['vi'] = ['title' => 'Chung toi biet ve cac loai thuc vat tot hon', 'subtitle' => 'Mot khu vuon hon bao gio het', 'description' => 'Thiet ke, thi cong va cham soc canh quan xanh cho khong gian song cua ban.', 'button_label' => '1900 9477', 'content' => ['slides' => []]];
        $blocks[1]['data']['vi']['title'] = 'Dich vu chinh cua chung toi';
        $blocks[1]['data']['vi']['subtitle'] = 'Dich vu';
        $blocks[2]['data']['vi']['title'] = 'Gioi thieu cong ty';
        $blocks[2]['data']['vi']['subtitle'] = 'Ve chung toi';
        $blocks[3]['data']['vi']['title'] = 'Hoan thanh cong viec theo dung cach';
        $blocks[4]['data']['vi']['title'] = 'Mot so du an tieu bieu';
        $blocks[4]['data']['vi']['subtitle'] = 'Du an canh quan';
        $blocks[5]['data']['vi']['title'] = 'Cam nhan tu khach hang';
        $blocks[6]['data']['vi']['title'] = 'Doi ngu kien truc su va ky su';
        $blocks[7]['data']['vi']['title'] = 'Yeu cau tu van va bao gia';
        $blocks[8]['data']['vi']['title'] = 'Tin tuc moi';
        $blocks[9]['data']['vi']['title'] = 'Doi tac dong hanh';

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0309DefaultBlocks(): array
    {
        $blocks = $this->xd0305DefaultBlocks();
        $blocks[0]['settings']['placement'] = 'xd0309-hero-slider';
        $blocks[0]['settings_schema'][0]['default'] = 'xd0309-hero-slider';
        $blocks[0]['data']['vi'] = ['title' => 'Toi uu chat luong chi phi cho doi tac doanh nghiep', 'subtitle' => 'Thiet bi va giai phap an toan', 'description' => 'Cung cap thiet bi bao ho lao dong va giai phap an toan chuyen nghiep cho doanh nghiep.', 'button_label' => 'Nhan bao gia mien phi', 'content' => ['slides' => []]];
        $blocks[1]['data']['vi']['title'] = 'Dich vu cua chung toi';
        $blocks[1]['data']['vi']['subtitle'] = 'Dich vu';
        $blocks[2]['data']['vi']['title'] = 'Gioi thieu cong ty';
        $blocks[2]['data']['vi']['subtitle'] = 'Ve chung toi';
        $blocks[3]['data']['vi']['title'] = 'Tu van hop ly va cam ket chat luong';
        $blocks[4]['data']['vi']['title'] = 'Doi tac noi gi ve Antek';
        $blocks[5]['data']['vi']['title'] = 'Doi ngu ky thuat cua chung toi';
        $blocks[6]['data']['vi']['title'] = 'Yeu cau tu van va bao gia';
        $blocks[7]['data']['vi']['title'] = 'Tin tuc moi';

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0307DefaultBlocks(): array
    {
        $blocks = $this->xd0305DefaultBlocks();
        $blocks[0]['settings']['placement'] = 'xd0307-hero-slider';
        $blocks[0]['settings_schema'][0]['default'] = 'xd0307-hero-slider';
        $blocks[0]['data']['vi'] = ['title' => 'Chúng tôi là lựa chọn tốt nhất cho bạn', 'subtitle' => 'Dịch vụ dọn dẹp', 'description' => 'Đội ngũ vệ sinh chuyên nghiệp, tận tâm cho gia đình và doanh nghiệp.', 'button_label' => 'Nhận báo giá', 'content' => ['slides' => []]];
        $blocks[1]['data']['vi']['title'] = 'Dịch vụ làm sạch tuyệt vời dành cho bạn';
        $blocks[1]['data']['vi']['subtitle'] = 'Dịch vụ của chúng tôi';
        $blocks[2]['data']['vi']['title'] = 'Chúng tôi cung cấp các dịch vụ vệ sinh tốt nhất';
        $blocks[2]['data']['vi']['subtitle'] = 'Tìm hiểu về chúng tôi';
        $blocks[3]['data']['vi']['title'] = '25 năm kinh nghiệm trong ngành làm sạch';
        $blocks[4]['data']['vi']['title'] = 'Cảm nhận của khách hàng';
        $blocks[5]['data']['vi']['title'] = 'Gặp gỡ đội ngũ kinh nghiệm cao của chúng tôi';
        $blocks[6]['data']['vi']['title'] = 'Yêu cầu báo giá dịch vụ';
        $blocks[7]['data']['vi']['title'] = 'Các bài viết mới nhất từ chúng tôi';

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0308DefaultBlocks(): array
    {
        $studyBlocks = collect($this->xd0303DefaultBlocks())->keyBy('block_type');
        $coreBlocks = collect($this->xd0301DefaultBlocks())->keyBy('block_type');
        $blocks = [
            $studyBlocks->get('hero_slider'),
            $coreBlocks->get('about_experience'),
            $studyBlocks->get('process_steps'),
            $coreBlocks->get('featured_services'),
            $coreBlocks->get('content_mosaic'),
            $coreBlocks->get('testimonials'),
            $coreBlocks->get('landing_contact'),
        ];

        $blocks[0]['settings']['placement'] = 'xd0308-hero-slider';
        $blocks[0]['settings_schema'][0]['default'] = 'xd0308-hero-slider';
        $blocks[0]['data']['vi'] = ['title' => 'Du học và định hướng tương lai', 'subtitle' => 'Tư vấn du học Comgo', 'description' => 'Lộ trình học tập, hồ sơ và visa được đội ngũ chuyên môn đồng hành từ đầu đến cuối.', 'button_label' => 'Đăng ký tư vấn', 'content' => ['slides' => []]];
        $blocks[1]['data']['vi'] = ['title' => 'Chúng tôi cung cấp giải pháp du học tốt nhất', 'subtitle' => 'Về chúng tôi', 'description' => 'Đồng hành cùng học viên lựa chọn quốc gia, trường học và lộ trình phù hợp với mục tiêu phát triển cá nhân.', 'button_label' => 'Xem thêm', 'content' => []];
        $blocks[2]['data']['vi'] = ['title' => 'Quy trình đăng ký du học', 'subtitle' => 'Chúng tôi làm việc như thế nào', 'description' => 'Sáu bước rõ ràng để chuẩn bị một hành trình học tập quốc tế vững vàng.', 'button_label' => '', 'content' => ['steps' => []]];
        $blocks[3]['data']['vi']['title'] = 'Cung cấp các dịch vụ mới nhất';
        $blocks[3]['data']['vi']['subtitle'] = 'Dịch vụ';
        $blocks[4]['anchor_id'] = 'quoc-gia';
        $blocks[4]['settings']['source'] = 'cms_services';
        $blocks[4]['data']['vi']['title'] = 'Quốc gia được yêu thích nhất cho người nhập cư';
        $blocks[4]['data']['vi']['subtitle'] = 'Tư vấn du học';
        $blocks[5]['data']['vi']['title'] = 'Nhận xét từ khách hàng';
        $blocks[5]['data']['vi']['subtitle'] = 'Lời chứng thực';
        $blocks[6]['data']['vi']['title'] = 'Yêu cầu một cuộc gọi lại';
        $blocks[6]['data']['vi']['subtitle'] = 'Tư vấn du học';

        return $blocks;
    }

    private function xd0306DefaultBlocks(): array
    {
        $sources = [['value'=>'cms_services','label'=>'Dịch vụ'],['value'=>'cms_posts','label'=>'Tin tức'],['value'=>'cms_products','label'=>'Sản phẩm'],['value'=>'cms_projects','label'=>'Dự án'],['value'=>'custom','label'=>'Nhập thủ công']];
        return [
            ['block_type'=>'hero_slider','label'=>'Header và banner','description'=>'Header hai tầng và banner slider.','preview_image'=>'/theme-previews/XD0306/hero-slider.png','anchor_id'=>'top','dynamic'=>true,'settings'=>['source'=>'site_banners','placement'=>'xd0306-hero-slider','limit'=>3,'autoplay_ms'=>6000],'settings_schema'=>[['key'=>'placement','label'=>'Vị trí banner','type'=>'text','default'=>'xd0306-hero-slider'],['key'=>'limit','label'=>'Số slide','type'=>'number','default'=>3]],'data'=>['vi'=>['title'=>'Thiết kế website chuyên nghiệp','subtitle'=>'Digital agency','description'=>'Chúng tôi xây dựng website, thương hiệu và chiến dịch số có hiệu quả.','button_label'=>'Liên hệ ngay','content'=>['slides'=>[]]],'en'=>['title'=>'Professional website design','subtitle'=>'Digital agency','description'=>'Website, branding and digital campaigns.','button_label'=>'Contact us','content'=>['slides'=>[]]]]],
            ['block_type'=>'business_service_grid','label'=>'Dịch vụ nổi bật','description'=>'Nguồn Dịch vụ, Tin tức, Sản phẩm, Dự án hoặc nhập thủ công.','preview_image'=>'/theme-previews/XD0306/business-service-grid.png','anchor_id'=>'dich-vu','dynamic'=>true,'settings'=>['source'=>'cms_services','limit'=>3,'featured_only'=>true],'settings_schema'=>['source'=>['type'=>'select','label'=>'Nguồn dữ liệu','options'=>$sources],'limit'=>['type'=>'number','label'=>'Số mục hiển thị']],'data'=>['vi'=>['title'=>'Công ty cổ phần Black','subtitle'=>'Về chúng tôi','description'=>'Dịch vụ marketing tổng thể giúp doanh nghiệp phát triển trong môi trường số.','button_label'=>'Xem thêm','content'=>['items'=>[]]],'en'=>['title'=>'Black agency','subtitle'=>'About us','description'=>'Integrated digital marketing services.','button_label'=>'Learn more','content'=>['items'=>[]]]]],
            ['block_type'=>'bizmax_about','label'=>'Giới thiệu sáng tạo','description'=>'Nội dung giới thiệu và hotline tùy chỉnh.','preview_image'=>'/theme-previews/XD0306/bizmax-about.png','anchor_id'=>'gioi-thieu','settings'=>[],'data'=>['vi'=>['title'=>'Chúng tôi sáng tạo studio xây dựng thương hiệu','subtitle'=>'Chúng tôi là ai','description'=>'Đồng hành và phát triển cùng doanh nghiệp bằng giải pháp marketing online, truyền thông và quảng cáo.','button_label'=>'1900 9477','content'=>['years'=>'20+','years_label'=>'Năm kinh nghiệm','progress_label'=>'Khách hàng hài lòng','progress_value'=>90]],'en'=>['title'=>'We build creative brands','subtitle'=>'Who we are','description'=>'We help businesses grow through digital marketing.','button_label'=>'1900 9477','content'=>[]]]],
            ['block_type'=>'collection_gallery','label'=>'Album hình ảnh','description'=>'Nguồn Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.','preview_image'=>'/theme-previews/XD0306/collection-gallery.png','anchor_id'=>'thu-vien','dynamic'=>true,'settings'=>['source'=>'cms_projects','limit'=>6],'settings_schema'=>['source'=>['type'=>'select','label'=>'Nguồn dữ liệu','options'=>$sources],'limit'=>['type'=>'number','label'=>'Số ảnh']],'data'=>['vi'=>['title'=>'Một số album của chúng tôi','subtitle'=>'Hình ảnh','description'=>'','button_label'=>'Xem thêm','content'=>['items'=>[]]],'en'=>['title'=>'Selected albums','subtitle'=>'Gallery','description'=>'','button_label'=>'View more','content'=>['items'=>[]]]]],
            ['block_type'=>'faq_showcase','label'=>'Câu hỏi thường gặp','description'=>'FAQ tùy chỉnh.','preview_image'=>'/theme-previews/XD0306/faq-showcase.png','anchor_id'=>'faq','settings'=>[],'data'=>['vi'=>['title'=>'Faq\'s','subtitle'=>'','description'=>'','button_label'=>'','content'=>['items'=>[['question'=>'Tôi cần chuẩn bị gì? Tiến hành mất bao lâu?','answer'=>'Đội ngũ sẽ khảo sát và đề xuất lộ trình phù hợp.'],['question'=>'Dịch vụ marketing trọn gói bao gồm những gì?','answer'=>'Bao gồm chiến lược, nội dung, quảng cáo và đo lường hiệu quả.'],['question'=>'Tại sao nên chọn dịch vụ marketing trọn gói?','answer'=>'Giúp các kênh truyền thông vận hành thống nhất.']]]],'en'=>['title'=>'FAQ','subtitle'=>'','description'=>'','button_label'=>'','content'=>['items'=>[]]]]],
            ['block_type'=>'bizmax_latest_posts','label'=>'Blog của chúng tôi','description'=>'Nguồn Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.','preview_image'=>'/theme-previews/XD0306/bizmax-latest-posts.png','anchor_id'=>'tin-tuc','dynamic'=>true,'settings'=>['source'=>'cms_posts','limit'=>5],'settings_schema'=>['source'=>['type'=>'select','label'=>'Nguồn dữ liệu','options'=>$sources],'limit'=>['type'=>'number','label'=>'Số bài hiển thị']],'data'=>['vi'=>['title'=>'Blog của chúng tôi','subtitle'=>'','description'=>'','button_label'=>'Đọc thêm','content'=>['items'=>[]]],'en'=>['title'=>'Our blog','subtitle'=>'','description'=>'','button_label'=>'Read more','content'=>['items'=>[]]]]],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function xd0305DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Header và banner', 'description' => 'Header doanh nghiệp và banner ảnh chạy.', 'preview_image' => '/theme-previews/XD0305/hero-slider.png', 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'xd0305-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000], 'settings_schema' => [['key' => 'placement', 'label' => 'Vị trí banner', 'type' => 'text', 'default' => 'xd0305-hero-slider'], ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 3], ['key' => 'autoplay_ms', 'label' => 'Tự chuyển (ms)', 'type' => 'number', 'default' => 6000]], 'data' => ['vi' => ['title' => 'Nâng cao hiệu quả dành cho bạn', 'subtitle' => 'Tư vấn doanh nghiệp', 'description' => 'Giải pháp tư vấn chuyên nghiệp, linh hoạt cho tổ chức và cá nhân.', 'button_label' => 'Nhận báo giá', 'content' => ['slides' => []]], 'en' => ['title' => 'Better business results for you', 'subtitle' => 'Business consulting', 'description' => 'Professional consulting for organisations and individuals.', 'button_label' => 'Get a quote', 'content' => ['slides' => []]]]],
            ['block_type' => 'business_service_grid', 'label' => 'Danh sách dịch vụ', 'description' => 'Dịch vụ dạng thẻ; chọn Dịch vụ, Tin tức, Sản phẩm, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0305/business-service-grid.png', 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 4, 'featured_only' => true], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']], 'data' => ['vi' => ['title' => 'Chúng tôi cung cấp các giải pháp', 'subtitle' => 'Danh sách dịch vụ', 'description' => '', 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]], 'en' => ['title' => 'Solutions we provide', 'subtitle' => 'Services', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]]]],
            ['block_type' => 'bizmax_about', 'label' => 'Giới thiệu doanh nghiệp', 'description' => 'Khối giới thiệu với hình ảnh, kinh nghiệm và chỉ số tiến độ.', 'preview_image' => '/theme-previews/XD0305/bizmax-about.png', 'anchor_id' => 'gioi-thieu', 'settings' => [], 'data' => ['vi' => ['title' => 'Chuẩn bị cho thành công với Bizmax', 'subtitle' => 'Về chúng tôi', 'description' => 'Cung cấp tư vấn pháp lý, hỗ trợ tuân thủ và các giải pháp phát triển bền vững cho doanh nghiệp.', 'button_label' => 'Nhận báo giá', 'content' => ['image_primary' => '', 'image_secondary' => '', 'years' => '40+', 'years_label' => 'Năm kinh nghiệm', 'progress_label' => 'Phát triển website', 'progress_value' => 90]], 'en' => ['title' => 'Prepare for success with Bizmax', 'subtitle' => 'About us', 'description' => 'Professional business consulting for sustainable growth.', 'button_label' => 'Get a quote', 'content' => ['image_primary' => '', 'image_secondary' => '', 'years' => '40+', 'years_label' => 'Years of experience', 'progress_label' => 'Website growth', 'progress_value' => 90]]]],
            ['block_type' => 'bizmax_benefit_panel', 'label' => 'Lý do chọn chúng tôi', 'description' => 'Khối điểm mạnh tùy chỉnh với bốn lợi ích.', 'preview_image' => '/theme-previews/XD0305/bizmax-benefit-panel.png', 'anchor_id' => 'loi-ich', 'settings' => [], 'data' => ['vi' => ['title' => 'Phát triển một thiết kế để định hướng và sử dụng', 'subtitle' => 'Vì sao chọn chúng tôi', 'description' => 'Thiết kế được tối ưu hóa để người dùng dễ dàng điều hướng và tìm hiểu thông tin.', 'button_label' => '', 'content' => ['image' => '', 'items' => [['title' => 'Cam kết chất lượng'], ['title' => 'Tăng trưởng hiệu quả'], ['title' => 'Tiết kiệm chi phí'], ['title' => 'Am hiểu thị trường']]]], 'en' => ['title' => 'Designed to guide and perform', 'subtitle' => 'Why choose us', 'description' => 'An experience designed to make information clear and useful.', 'button_label' => '', 'content' => ['image' => '', 'items' => []]]]],
            ['block_type' => 'bizmax_testimonial_carousel', 'label' => 'Cảm nhận khách hàng', 'description' => 'Đánh giá khách hàng lấy từ CMS Testimonials.', 'preview_image' => '/theme-previews/XD0305/testimonials.png', 'anchor_id' => 'cam-nhan', 'dynamic' => true, 'settings' => ['limit' => 3], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số đánh giá hiển thị']], 'data' => ['vi' => ['title' => 'Phát triển một thiết kế để định hướng và sử dụng', 'subtitle' => 'Lời chứng thực', 'description' => 'Trải nghiệm thực tế từ khách hàng đồng hành cùng chúng tôi.', 'button_label' => '', 'content' => ['items' => []]], 'en' => ['title' => 'Designed to guide and perform', 'subtitle' => 'Testimonials', 'description' => 'Experiences from our customers.', 'button_label' => '', 'content' => ['items' => []]]]],
            ['block_type' => 'team_members', 'label' => 'Đội ngũ chuyên gia', 'description' => 'Danh sách thành viên lấy từ CMS Team.', 'preview_image' => '/theme-previews/XD0305/team-members.png', 'anchor_id' => 'doi-ngu', 'dynamic' => true, 'settings' => ['source' => 'cms_team_members', 'limit' => 3, 'featured_only' => true], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số thành viên hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy thành viên nổi bật']], 'data' => ['vi' => ['title' => 'Gặp gỡ đội ngũ chuyên gia', 'subtitle' => 'Gặp gỡ thành viên của chúng tôi', 'description' => '', 'button_label' => '', 'content' => ['items' => []]], 'en' => ['title' => 'Meet our experts', 'subtitle' => 'Meet our team', 'description' => '', 'button_label' => '', 'content' => ['items' => []]]]],
            ['block_type' => 'bizmax_contact', 'label' => 'Liên hệ', 'description' => 'Thông tin liên hệ và biểu mẫu tư vấn.', 'preview_image' => '/theme-previews/XD0305/bizmax-contact.png', 'anchor_id' => 'lien-he', 'settings' => [], 'data' => ['vi' => ['title' => 'Làm việc cùng nhau', 'subtitle' => 'Liên hệ với chúng tôi', 'description' => 'Đội ngũ sẵn sàng lắng nghe nhu cầu và xây dựng phương án phù hợp.', 'button_label' => 'Gửi ngay', 'content' => []], 'en' => ['title' => 'Let us work together', 'subtitle' => 'Contact us', 'description' => 'Our team is ready to discuss your needs.', 'button_label' => 'Send', 'content' => []]]],
            ['block_type' => 'bizmax_latest_posts', 'label' => 'Blog và bài viết', 'description' => 'Bài viết dạng thẻ; chọn Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0305/bizmax-latest-posts.png', 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số bài hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']], 'data' => ['vi' => ['title' => 'Các blog và bài viết gần nhất', 'subtitle' => 'Các blog và bài viết', 'description' => '', 'button_label' => 'Đọc thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Latest blogs and articles', 'subtitle' => 'Blog and articles', 'description' => '', 'button_label' => 'Read more', 'content' => ['items' => []]]]],
            ['block_type' => 'partner_logos', 'label' => 'Logo đối tác', 'description' => 'Danh sách logo đối tác lấy từ CMS Partners.', 'preview_image' => '/theme-previews/XD0305/partner-logos.png', 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 5], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số logo hiển thị']], 'data' => ['vi' => ['title' => 'Đối tác đồng hành', 'subtitle' => 'Đối tác', 'description' => '', 'button_label' => '', 'content' => ['items' => []]], 'en' => ['title' => 'Trusted partners', 'subtitle' => 'Partners', 'description' => '', 'button_label' => '', 'content' => ['items' => []]]]],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function xd0304DefaultBlocks(): array
    {
        $contentSources = [
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
        ];

        return [
            [
                'block_type' => 'hero_slider', 'label' => 'Header và banner', 'description' => 'Header, menu và banner hình ảnh chạy.', 'preview_image' => '/theme-previews/XD0304/hero-slider.png', 'anchor_id' => 'top', 'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0304-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [['key' => 'placement', 'label' => 'Vị trí banner', 'type' => 'text', 'default' => 'xd0304-hero-slider'], ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 3], ['key' => 'autoplay_ms', 'label' => 'Tự chuyển (ms)', 'type' => 'number', 'default' => 6000]],
                'data' => ['vi' => ['title' => 'Giải pháp logistics cho mọi hành trình', 'subtitle' => 'Vận tải và hậu cần', 'description' => 'Kết nối vận tải, kho bãi và giao nhận với quy trình rõ ràng, chủ động.', 'button_label' => 'Nhận báo giá', 'content' => ['slides' => []]], 'en' => ['title' => 'Logistics solutions for every journey', 'subtitle' => 'Transport and logistics', 'description' => 'Reliable transport, warehousing and delivery services.', 'button_label' => 'Get a quote', 'content' => ['slides' => []]]],
            ],
            [
                'block_type' => 'service_category_slider', 'label' => 'Danh mục dịch vụ chạy ngang', 'description' => 'Dịch vụ nổi bật hiển thị dạng thanh trượt dưới banner.', 'preview_image' => '/theme-previews/XD0304/service-category-slider.png', 'anchor_id' => 'dich-vu', 'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Dịch vụ vận tải nổi bật', 'subtitle' => 'Giải pháp vận chuyển', 'description' => '', 'button_label' => 'Xem thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Featured transport services', 'subtitle' => 'Transport solutions', 'description' => '', 'button_label' => 'Learn more', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'solutions_split_list', 'label' => 'Giải pháp logistics', 'description' => 'Danh sách nội dung xen kẽ ảnh và mô tả; có thể lấy từ Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0304/solutions-split-list.png', 'anchor_id' => 'giai-phap', 'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Giải pháp logistics toàn cầu tốt nhất', 'subtitle' => 'Giải pháp thực tế, nhanh chóng thực sự!', 'description' => '', 'button_label' => 'Xem thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Global logistics solutions', 'subtitle' => 'Practical solutions, delivered fast', 'description' => '', 'button_label' => 'Learn more', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'logistics_feature_panel', 'label' => 'Điểm mạnh logistics', 'description' => 'Khối giới thiệu tùy chỉnh với ảnh, số liệu và hai điểm mạnh.', 'preview_image' => '/theme-previews/XD0304/logistics-feature-panel.png', 'anchor_id' => 'gioi-thieu',
                'settings' => [],
                'data' => ['vi' => ['title' => 'Đối tác logistics toàn cầu cho chuỗi cung ứng của bạn', 'subtitle' => 'Giải pháp thực tế, nhanh chóng thực sự!', 'description' => 'Chuyên môn về hậu cần toàn cầu cùng quy trình linh hoạt giúp doanh nghiệp chủ động từng chặng vận chuyển.', 'button_label' => 'Liên hệ tư vấn', 'content' => ['image' => '', 'feature_one_title' => 'Tối ưu hóa chi phí', 'feature_one_text' => 'Phương án giao nhận và vận tải phù hợp, rõ ràng về chi phí.', 'feature_two_title' => 'Giảm thời gian vận chuyển', 'feature_two_text' => 'Điều phối linh hoạt để hàng hóa đến đúng kế hoạch.', 'statistic' => '99,9% khách hàng hài lòng trên hơn 750 đánh giá.']], 'en' => ['title' => 'A global logistics partner for your supply chain', 'subtitle' => 'Practical solutions, delivered fast', 'description' => 'Flexible global logistics services designed around your business.', 'button_label' => 'Contact us', 'content' => ['image' => '', 'feature_one_title' => 'Cost efficiency', 'feature_one_text' => 'Transparent transport and delivery plans.', 'feature_two_title' => 'Faster delivery', 'feature_two_text' => 'Flexible operations that keep your schedule on track.', 'statistic' => '99.9% customer satisfaction across 750+ reviews.']]],
            ],
            [
                'block_type' => 'collection_gallery', 'label' => 'Bộ sưu tập hình ảnh', 'description' => 'Thư viện nội dung có thể lấy từ Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0304/collection-gallery.png', 'anchor_id' => 'thu-vien', 'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Một số hình ảnh tiêu biểu', 'subtitle' => 'Khám phá bộ sưu tập', 'description' => 'Những lát cắt về hành trình vận tải, kho bãi và giao nhận của chúng tôi.', 'button_label' => 'Tất cả bộ sưu tập', 'content' => ['items' => []]], 'en' => ['title' => 'Featured collection', 'subtitle' => 'Explore our collection', 'description' => 'A closer look at our transport and logistics operations.', 'button_label' => 'View all', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'partner_logos', 'label' => 'Logo đối tác', 'description' => 'Danh sách logo đối tác lấy từ CMS Partners.', 'preview_image' => '/theme-previews/XD0304/partner-logos.png', 'anchor_id' => 'doi-tac', 'dynamic' => true,
                'settings' => ['source' => 'cms_partners', 'limit' => 6], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số logo hiển thị']],
                'data' => ['vi' => ['title' => 'Đối tác đồng hành', 'subtitle' => 'Đối tác', 'description' => '', 'button_label' => '', 'content' => ['items' => []]], 'en' => ['title' => 'Trusted partners', 'subtitle' => 'Partners', 'description' => '', 'button_label' => '', 'content' => ['items' => []]]],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function xd0303DefaultBlocks(): array
    {
        $contentSources = ['custom', 'cms_posts', 'cms_products', 'cms_services', 'cms_projects'];

        return [
            [
                'block_type' => 'hero_slider', 'label' => 'Header và banner', 'description' => 'Header dùng logo hệ thống, menu và banner ảnh chạy.', 'preview_image' => '/theme-previews/XD0303/hero-slider.png', 'anchor_id' => 'top', 'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0303-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [['key' => 'placement', 'label' => 'Vị trí banner', 'type' => 'text', 'default' => 'xd0303-hero-slider'], ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 3], ['key' => 'autoplay_ms', 'label' => 'Tự chuyển (ms)', 'type' => 'number', 'default' => 6000]],
                'data' => ['vi' => ['title' => 'Dịch vụ chuyên nghiệp, chất lượng nhanh gọn', 'subtitle' => 'Vận hành linh hoạt', 'description' => 'Đội ngũ chuyên nghiệp, quy trình minh bạch và giải pháp phù hợp cho từng nhu cầu.', 'button_label' => 'Liên hệ ngay', 'content' => ['slides' => []]], 'en' => ['title' => 'Professional service, delivered quickly', 'subtitle' => 'Flexible operations', 'description' => 'Clear processes and solutions for every need.', 'button_label' => 'Contact us', 'content' => ['slides' => []]]],
            ],
            [
                'block_type' => 'hotline_callout', 'label' => 'Hotline', 'description' => 'Khối quảng cáo ngắn với số hotline lấy từ hồ sơ thương hiệu.', 'preview_image' => '/theme-previews/XD0303/hotline-callout.png', 'anchor_id' => 'hotline',
                'settings' => [], 'data' => ['vi' => ['title' => 'Một cuộc gọi có thể giải quyết tất cả các vấn đề trong nhà của bạn', 'subtitle' => 'Hotline', 'description' => 'Và, chúng tôi có nhiều tùy chọn hơn để liên hệ với chúng tôi.', 'button_label' => '', 'content' => ['phone' => '1900 9477']], 'en' => ['title' => 'One call can solve your service needs', 'subtitle' => 'Hotline', 'description' => 'There are several ways to reach our team.', 'button_label' => '', 'content' => ['phone' => '1900 9477']]],
            ],
            [
                'block_type' => 'content_showcase', 'label' => 'Nội dung nổi bật', 'description' => 'Hiển thị Dịch vụ mặc định; có thể đổi sang Tin tức, Sản phẩm hoặc Dự án.', 'preview_image' => '/theme-previews/XD0303/content-showcase.png', 'anchor_id' => 'dich-vu', 'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 3, 'featured_only' => true], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật']],
                'data' => ['vi' => ['title' => 'Giải pháp cho mọi nhu cầu vận hành', 'subtitle' => 'Dịch vụ nổi bật', 'description' => '', 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]], 'en' => ['title' => 'Solutions for every operational need', 'subtitle' => 'Featured services', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'process_steps', 'label' => 'Quy trình dịch vụ', 'description' => 'Quy trình 4 bước có thể chỉnh sửa trực tiếp.', 'preview_image' => '/theme-previews/XD0303/process-steps.png', 'anchor_id' => 'quy-trinh', 'settings' => [],
                'data' => ['vi' => ['title' => 'Quy trình dịch vụ', 'subtitle' => 'Những gì chúng tôi đã làm', 'description' => 'Tính toán chi phí hợp lý, cung cấp phương án rõ ràng và triển khai đúng hẹn để mỗi lần hợp tác đều dễ theo dõi.', 'button_label' => '', 'content' => ['items' => [['title' => 'Tiếp nhận thông tin', 'description' => 'Ghi nhận nhu cầu, thời gian và phạm vi công việc cần thực hiện.'], ['title' => 'Khảo sát - báo giá', 'description' => 'Khảo sát thực tế, thống nhất phương án và báo giá minh bạch.'], ['title' => 'Triển khai dịch vụ', 'description' => 'Điều phối nhân sự, thiết bị và cập nhật tiến độ trong quá trình làm việc.'], ['title' => 'Nghiệm thu - thanh toán', 'description' => 'Kiểm tra kết quả, bàn giao và tiếp nhận phản hồi từ khách hàng.']]]], 'en' => ['title' => 'Service process', 'subtitle' => 'How we work', 'description' => 'A clear process from initial request to completion.', 'button_label' => '', 'content' => ['items' => []]]],
            ],
            [
                'block_type' => 'partner_logos', 'label' => 'Logo đối tác', 'description' => 'Danh sách logo đối tác lấy từ CMS Partners.', 'preview_image' => '/theme-previews/XD0303/partner-logos.png', 'anchor_id' => 'doi-tac', 'dynamic' => true,
                'settings' => ['source' => 'cms_partners', 'limit' => 5], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số logo hiển thị']],
                'data' => ['vi' => ['title' => 'Đối tác đồng hành', 'subtitle' => 'Đối tác', 'description' => '', 'button_label' => '', 'content' => ['items' => []]], 'en' => ['title' => 'Trusted partners', 'subtitle' => 'Partners', 'description' => '', 'button_label' => '', 'content' => ['items' => []]]],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function xd0302DefaultBlocks(): array
    {
        $contentSources = ['custom', 'cms_posts', 'cms_products', 'cms_services', 'cms_projects'];
        $featuredListSources = [
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_menus', 'label' => 'Menu website'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
        ];

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
                'block_type' => 'featured_service_list',
                'label' => 'Danh sách dịch vụ nổi bật',
                'description' => 'Danh sách card có thể lấy từ Dịch vụ, Menu, Tin tức, Sản phẩm, Dự án hoặc nhập thủ công.',
                'preview_image' => '/theme-previews/XD0302/featured-services.png',
                'anchor_id' => 'dich-vu-noi-bat',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 3, 'featured_only' => true, 'menu_location' => 'primary-navigation'],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $featuredListSources],
                    'menu_location' => ['type' => 'text', 'label' => 'Vị trí menu'],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Dịch vụ nổi bật', 'subtitle' => 'Giải pháp của chúng tôi', 'description' => '', 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]],
                    'en' => ['title' => 'Featured services', 'subtitle' => 'Our solutions', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'completed_projects_list',
                'label' => 'Danh sách dự án đã thực hiện',
                'description' => 'Danh sách dự án dạng card có thể lấy từ Dịch vụ, Menu, Tin tức, Sản phẩm, Dự án hoặc nhập thủ công.',
                'preview_image' => '/theme-previews/XD0302/project-gallery.png',
                'anchor_id' => 'du-an-da-thuc-hien',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 5, 'featured_only' => true, 'menu_location' => 'primary-navigation'],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $featuredListSources],
                    'menu_location' => ['type' => 'text', 'label' => 'Vị trí menu'],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Dự án đã thực hiện', 'subtitle' => 'Dấu ấn triển khai', 'description' => '', 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]],
                    'en' => ['title' => 'Completed projects', 'subtitle' => 'Our work', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonial_showcase',
                'label' => 'Cảm nhận khách hàng',
                'description' => 'Danh sách cảm nhận khách hàng lấy từ CMS Đánh giá.',
                'preview_image' => '/theme-previews/XD0302/testimonials.png',
                'anchor_id' => 'cam-nhan-khach-hang',
                'dynamic' => true,
                'settings' => ['limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'limit' => ['type' => 'number', 'label' => 'Số đánh giá hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy đánh giá nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Phát triển một thiết kế dễ định hướng và sử dụng', 'subtitle' => 'Lời chứng thực', 'description' => 'Thiết kế được tối ưu để người dùng có thể dễ dàng điều hướng, tìm hiểu và sử dụng một cách thuận tiện, hiệu quả.', 'button_label' => '', 'content' => ['items' => []]],
                    'en' => ['title' => 'Designing experiences that are easy to navigate and use', 'subtitle' => 'Testimonials', 'description' => 'Feedback from customers who have worked with our team.', 'button_label' => '', 'content' => ['items' => []]],
                ],
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
        ];
    }
}
