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
use App\Models\RealEstateListing;
use App\Models\RealEstatePropertyType;
use App\Models\SiteBanner;
use App\Models\ThemeTranslation;
use App\Support\FrontendLocalization;
use App\Support\FrontendRouteUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LandingPageBuilder
{
    public function supportsTheme(?string $themeKey): bool
    {
        return in_array(strtoupper((string) $themeKey), ['TH0001', 'TH0002', 'TH0003', 'TH0020', 'TH0050', 'TH0201', 'SER0100', 'SER0101', 'SER102', 'XD0301', 'XD0302', 'XD0303', 'XD0304', 'XD0305', 'XD0306', 'XD0307', 'XD0308', 'XD0309', 'XD0310', 'XD0311', 'XD0312', 'XD0313', 'XD0314', 'XD0315', 'XD0318', 'FOOT401', 'FOOT403', 'XD0320', 'NT501', 'NT502', 'NT503', 'XD321', 'XD0322', 'XD0323', 'XD0324', 'XD0325', 'DN202', 'DN302', 'BZ501', 'SPA502', 'SHOP601', 'SHOP602', 'SHOP603', 'SHOP604', 'SHOP605', 'CA0050', 'BDS701'], true);
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
                'real_estate_listings' => $this->realEstatePropertyTypeOptions(),
                'real_estate_property_types' => [],
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
    private function realEstatePropertyTypeOptions(): array
    {
        if (! Schema::hasTable('real_estate_property_types')) {
            return [];
        }

        return RealEstatePropertyType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (RealEstatePropertyType $type): array => [
                'value' => (int) $type->id,
                'label' => (string) $type->name,
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
            'shop601_collection_cards', 'shop601_latest_content' => 4,
            'shop601_flash_sale', 'shop601_product_grid' => 10,
            'shop601_feature_collection' => 4,
            'shop601_product_carousel' => 5,
            'shop603_hot_products', 'shop603_sale_slider' => 5,
            'shop603_new_arrivals' => 7,
            'shop604_flash_sale', 'shop604_new_arrivals' => 4,
            'shop604_collection_tabs' => 8,
            'ca0050_fish_products' => 8,
            'ca0050_accessories' => 4,
            'shop605_sale', 'shop605_new' => 4,
            'shop605_best' => 10,
            'nt502_categories' => 9,
            'nt502_promotion' => 3,
            'nt502_living_room', 'nt502_bedroom' => 6,
            'nt502_latest_news' => 7,
            'nt503_categories' => 10,
            'nt503_mattresses', 'nt503_kids_collection' => 4,
            'nt503_flash_sale' => 5,
            'nt503_advice' => 4,
            'bds701_hero_search' => 5,
            'bds701_latest_listings' => 6,
            'bds701_property_types' => 5,
            'bds701_rental_listings' => 3,
            'bds701_market_news' => 5,
            'bds701_latest_news' => 3,
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

        if (in_array($block->block_type, ['bds701_hero_search', 'bds701_property_types'], true)) {
            return $this->realEstatePropertyTypeItems($limit, $locale);
        }

        if (in_array($block->block_type, ['bds701_latest_listings', 'bds701_rental_listings'], true)) {
            if ($block->block_type === 'bds701_rental_listings') {
                $settings['transaction_type'] = 'rent';
            }

            return $this->realEstateListingItems($settings, $limit, $locale);
        }

        if (in_array($block->block_type, ['bds701_market_news', 'bds701_latest_news'], true)) {
            return $this->latestPostItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if (in_array($block->block_type, ['featured_services', 'featured_service_list', 'completed_projects_list', 'content_mosaic', 'content_showcase', 'project_gallery', 'service_category_slider', 'solutions_split_list', 'collection_gallery', 'business_service_grid', 'bizmax_latest_posts', 'shop601_collection_cards', 'shop601_flash_sale', 'shop601_product_grid', 'shop601_feature_collection', 'shop601_product_carousel', 'shop601_latest_content', 'shop603_hot_products', 'shop603_new_arrivals', 'shop603_sale_slider', 'shop604_flash_sale', 'shop604_new_arrivals', 'shop604_collection_tabs', 'shop605_sale', 'shop605_new', 'shop605_best', 'ca0050_fish_products', 'ca0050_accessories', 'nt502_categories', 'nt502_promotion', 'nt502_living_room', 'nt502_bedroom', 'nt502_latest_news', 'nt503_categories', 'nt503_mattresses', 'nt503_flash_sale', 'nt503_kids_collection', 'nt503_advice'], true)) {
            $defaultSource = match ($block->block_type) {
                'content_mosaic' => 'cms_posts',
                'content_showcase' => 'cms_projects',
                'project_gallery' => 'cms_projects',
                'bizmax_latest_posts', 'shop601_latest_content' => 'cms_posts',
                'shop601_collection_cards' => 'custom',
                'shop601_flash_sale', 'shop601_product_grid', 'shop601_feature_collection', 'shop601_product_carousel' => 'cms_products',
                'shop603_hot_products', 'shop603_new_arrivals', 'shop603_sale_slider', 'shop604_flash_sale', 'shop604_new_arrivals', 'shop604_collection_tabs', 'shop605_sale', 'shop605_new', 'shop605_best', 'ca0050_fish_products', 'ca0050_accessories' => 'cms_products',
                'nt502_categories' => 'catalog_categories',
                'nt502_promotion', 'nt502_living_room', 'nt502_bedroom' => 'cms_products',
                'nt502_latest_news' => 'cms_posts',
                'nt503_categories' => 'catalog_categories',
                'nt503_mattresses', 'nt503_flash_sale', 'nt503_kids_collection' => 'cms_products',
                'nt503_advice' => 'cms_posts',
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
                if (($settings['source'] ?? 'cms_testimonials') === 'custom') {
                    return [];
                }

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
            if (($settings['source'] ?? 'cms_partners') === 'custom') {
                return [];
            }

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
            'real_estate_listings' => $this->realEstateListingItems($settings, $limit, $locale),
            'real_estate_property_types' => $this->realEstatePropertyTypeItems($limit, $locale),
            default => $this->contentSourceItems([...$settings, 'source' => $defaultSource], $defaultSource, $limit, $locale, $websiteKey),
        };
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<int, array<string, mixed>>
     */
    private function realEstateListingItems(array $settings, int $limit, string $locale): array
    {
        if (! Schema::hasTable('real_estate_listings') || ! Schema::hasTable('real_estate_listing_media')) {
            return [];
        }

        $query = RealEstateListing::query()
            ->with(['propertyType', 'media'])
            ->where('publication_status', 'published')
            ->where('availability_status', 'available')
            ->when(filled($settings['transaction_type'] ?? null), fn (Builder $builder) => $builder->where('transaction_type', $settings['transaction_type']))
            ->when(filled($settings['category_id'] ?? null), fn (Builder $builder) => $builder->where('property_type_id', (int) $settings['category_id']))
            ->when(($settings['featured_only'] ?? false) === true, fn (Builder $builder) => $builder->where('is_featured', true))
            ->orderByDesc('is_featured')
            ->orderByDesc('is_hot')
            ->orderBy('sort_order')
            ->latest('published_at');

        return $query->take($limit)->get()->map(function (RealEstateListing $listing) use ($locale): array {
            $image = $listing->media->firstWhere('is_featured', true) ?? $listing->media->first();
            $location = collect([$listing->ward, $listing->district, $listing->province])->filter()->implode(', ');
            $area = $listing->floor_area ?: $listing->land_area;

            return [
                'id' => $listing->id,
                'title' => $listing->title,
                'summary' => $listing->summary,
                'image' => $image?->media_url ?: $this->fallbackCategoryImage($listing->id),
                'alt' => $image?->alt_text ?: $listing->title,
                'url' => FrontendRouteUrl::realEstateListing($listing->slug, $locale),
                'transaction_type' => $listing->transaction_type,
                'transaction_label' => $listing->transaction_type === 'rent' ? 'Cho thuê' : 'Bán',
                'price' => $listing->price !== null ? (float) $listing->price : null,
                'price_unit' => $listing->price_unit,
                'currency' => $listing->currency,
                'location' => $location,
                'bedrooms' => $listing->bedrooms,
                'bathrooms' => $listing->bathrooms,
                'area' => $area !== null ? (float) $area : null,
                'is_hot' => (bool) $listing->is_hot,
                'is_featured' => (bool) $listing->is_featured,
                'virtual_tour_url' => $listing->virtual_tour_url,
                'property_type' => $listing->propertyType?->name,
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function realEstatePropertyTypeItems(int $limit, string $locale): array
    {
        if (! Schema::hasTable('real_estate_property_types')) {
            return [];
        }

        return RealEstatePropertyType::query()
            ->where('is_active', true)
            ->withCount(['listings' => fn (Builder $builder) => $builder->where('publication_status', 'published')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->take($limit)
            ->get()
            ->map(fn (RealEstatePropertyType $type, int $index): array => [
                'id' => $type->id,
                'title' => $type->name,
                'summary' => $type->description,
                'image' => $type->image_url ?: $this->fallbackCategoryImage($index),
                'icon' => $type->icon ?: 'fa-solid fa-building',
                'count_label' => $type->listings_count.' dự án',
                'url' => FrontendRouteUrl::realEstate($locale).'?property_type='.rawurlencode($type->slug),
            ])
            ->all();
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

        if (filled($settings['search'] ?? null)) {
            $search = '%'.trim((string) $settings['search']).'%';
            $query->where(fn (Builder $nested) => $nested
                ->where('title', 'like', $search)
                ->orWhere('excerpt', 'like', $search));
        }

        if (filled($settings['category_id'] ?? null)) {
            $query->where('category_id', (int) $settings['category_id']);
        }
        $this->applyHighlightFilter($query, 'cms_posts', $settings);

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(fn (CmsPost $post): array => [
            'title' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_post.%d.title', $post->id), $post->title),
            'summary' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_post.%d.excerpt', $post->id), $post->excerpt),
            'icon' => '▦',
            'image' => $post->featuredMedia?->file_url ?: (string) ($settings['fallback_image'] ?? $this->fallbackContentImage()),
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

        if (filled($settings['search'] ?? null)) {
            $search = '%'.trim((string) $settings['search']).'%';
            $query->where(fn (Builder $nested) => $nested
                ->where('name', 'like', $search)
                ->orWhere('short_description', 'like', $search)
                ->orWhere('sku', 'like', $search));
        }

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
            'price' => (float) $product->price,
            'original_price' => filled($product->original_price) ? (float) $product->original_price : null,
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
                'date' => $project->publish_at?->format('d/m/Y'),
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
            'SHOP605' => $this->shop605DefaultBlocks(),
            'CA0050' => $this->ca0050DefaultBlocks(),
            'SHOP604' => $this->shop604DefaultBlocks(),
            'SHOP603' => $this->shop603DefaultBlocks(),
            'SHOP602' => $this->shop602DefaultBlocks(),
            'SHOP601' => $this->shop601DefaultBlocks(),
            'TH0050' => $this->th0050DefaultBlocks(),
            'TH0001' => $this->th0001DefaultBlocks(),
            'TH0002', 'TH0003', 'TH0020' => $this->legacyCommerceDefaultBlocks($themeKey),
            'TH0201' => $this->projectLandingDefaultBlocks($themeKey),
            'SER0100', 'SER0101' => $this->legacyServiceDefaultBlocks($themeKey),
            'SER102' => $this->ser102DefaultBlocks(),
            'XD0318' => $this->xd0318DefaultBlocks(),
            'XD0315' => $this->xd0315DefaultBlocks(),
            'XD0314' => $this->xd0314DefaultBlocks(),
            'XD0313' => $this->xd0313DefaultBlocks(),
            'FOOT401' => $this->foot401DefaultBlocks(),
            'FOOT403' => $this->foot403DefaultBlocks(),
            'XD0320' => $this->xd0320DefaultBlocks(),
            'NT501' => $this->nt501DefaultBlocks(),
            'NT502' => $this->nt502DefaultBlocks(),
            'NT503' => $this->nt503DefaultBlocks(),
            'XD321' => $this->xd321DefaultBlocks(),
            'XD0322' => $this->xd0322DefaultBlocks(),
            'XD0323' => $this->xd0323EuroFarmDefaultBlocks(),
            'XD0324' => $this->xd0324DefaultBlocks(),
            'XD0325' => $this->xd0325DefaultBlocks(),
            'DN202' => $this->dn202DefaultBlocks(),
            'DN302' => $this->dn302DefaultBlocks(),
            'BZ501' => $this->bz501DefaultBlocks(),
            'SPA502' => $this->spa502DefaultBlocks(),
            'BDS701' => $this->bds701DefaultBlocks(),
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
    private function dn202DefaultBlocks(): array
    {
        $preview = '/theme-previews/DN202/preview-dn202.png';
        $hero = '/theme-demo/dn202/hero-studio.png';
        $villa = '/theme-demo/dn202/villa-01.jpg';
        $interior = '/theme-demo/dn202/interior-01.jpg';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $sourceSchema = fn (string $source, string $label, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => $source, 'label' => $label], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số lượng', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật', 'default' => true],
        ];
        $serviceItems = [
            ['title' => 'Nội thất biệt thự', 'icon' => 'fa-solid fa-house-chimney-window', 'url' => '#thiet-ke-biet-thu'],
            ['title' => 'Nội thất chung cư', 'icon' => 'fa-solid fa-building', 'url' => '#san-pham'],
            ['title' => 'Nội thất khách sạn', 'icon' => 'fa-solid fa-hotel', 'url' => '#du-an'],
            ['title' => 'Nội thất nhà phố', 'icon' => 'fa-solid fa-house', 'url' => '#thiet-ke-biet-thu'],
            ['title' => 'Nội thất showroom', 'icon' => 'fa-solid fa-shop', 'url' => '#san-pham'],
            ['title' => 'Nội thất văn phòng', 'icon' => 'fa-solid fa-chair', 'url' => '#du-an'],
        ];
        $villaItems = [
            ['title' => 'Thiết kế biệt thự sân vườn', 'summary' => 'Đẳng cấp mới', 'image' => '/theme-demo/dn202/villa-01.jpg', 'url' => '#lien-he'],
            ['title' => 'Mẫu thiết kế biệt thự song lập', 'summary' => 'Không gian cân bằng', 'image' => '/theme-demo/dn202/villa-02.jpg', 'url' => '#lien-he'],
            ['title' => 'Biệt thự hiện đại 2 tầng', 'summary' => 'Tối ưu công năng', 'image' => '/theme-demo/dn202/villa-03.jpg', 'url' => '#lien-he'],
            ['title' => 'Biệt thự phố thanh lịch', 'summary' => 'Dấu ấn riêng', 'image' => '/theme-demo/dn202/villa-04.jpg', 'url' => '#lien-he'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero kiến trúc DN202', 'description' => 'Slider ảnh toàn chiều rộng ngay dưới header.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'dn202-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Vị trí banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => $hero], 'data' => ['vi' => array_merge($heading(null, null), ['content' => ['slides' => [['title' => 'Không gian sống được thiết kế cho riêng bạn', 'summary' => 'Thiết kế và thi công nội thất trọn gói.', 'image' => $hero]]]]), 'en' => array_merge($heading(null, null), ['content' => ['slides' => [['title' => 'Spaces designed around you', 'summary' => 'Complete interior design and build.', 'image' => $hero]]]])]],
            ['block_type' => 'featured_services', 'label' => 'Bạn đang cần tìm?', 'description' => 'Sáu nhóm dịch vụ nội thất từ CMS hoặc nhập thủ công.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true], 'settings_schema' => $sourceSchema('cms_services', 'Dịch vụ CMS', 6), 'data' => ['vi' => $withItems($heading('Bạn đang cần tìm?', 'Những sản phẩm, dịch vụ DN202 cung cấp cho bạn'), $serviceItems), 'en' => $withItems($heading('What are you looking for?', 'Interior solutions made for your space'), $serviceItems)]],
            ['block_type' => 'project_gallery', 'label' => 'Thiết kế biệt thự', 'description' => 'Bộ sưu tập mẫu biệt thự nhập trực tiếp trong block.', 'preview_image' => $preview, 'anchor_id' => 'thiet-ke-biet-thu', 'settings' => ['source' => 'custom', 'limit' => 4], 'settings_schema' => $sourceSchema('cms_projects', 'Dự án CMS', 4), 'data' => ['vi' => $withItems($heading('Thiết kế biệt thự', 'Những mẫu biệt thự đẹp của DN202'), $villaItems), 'en' => $withItems($heading('Villa design', 'Selected villa concepts by DN202'), $villaItems)]],
            ['block_type' => 'featured_products', 'label' => 'Sản phẩm nội thất', 'description' => 'Sản phẩm nổi bật lấy động từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $sourceSchema('cms_products', 'Sản phẩm Catalog', 4), 'media' => ['image' => $interior], 'data' => ['vi' => $heading('Sản phẩm nội thất'), 'en' => $heading('Interior products')]],
            ['block_type' => 'content_showcase', 'label' => 'Dự án hoàn thành', 'description' => 'Các dự án đã bàn giao lấy động từ CMS Projects.', 'preview_image' => $preview, 'anchor_id' => 'du-an', 'dynamic' => true, 'settings' => ['source' => 'cms_projects', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $sourceSchema('cms_projects', 'Dự án CMS', 4), 'data' => ['vi' => $heading('Dự án hoàn thành', 'Những mẫu dự án đã bàn giao'), 'en' => $heading('Completed projects', 'A selection of delivered spaces')]],
            ['block_type' => 'partner_logos', 'label' => 'Đối tác tiêu biểu', 'description' => 'Logo đối tác lấy động từ CMS Partners.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 6, 'featured_only' => true], 'settings_schema' => $sourceSchema('cms_partners', 'Đối tác CMS', 6), 'data' => ['vi' => $heading('Đối tác tiêu biểu', 'Những đối tác lâu năm tại DN202'), 'en' => $heading('Featured partners', 'Long-term partners of DN202')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function shop605DefaultBlocks(): array
    {
        $preview='/theme-previews/SHOP605/preview-shop605.svg';$hero='/theme-demo/shop605/hero-fashion.png';$a='/theme-demo/shop605/product-women-knit.png';$b='/theme-demo/shop605/product-women-rose.png';$c='/theme-demo/shop605/product-men-green.png';$d='/theme-demo/shop605/ad-lac-quan.png';
        $heading=fn(?string $title=null,?string $subtitle=null,?string $description=null):array=>['title'=>$title,'subtitle'=>$subtitle,'description'=>$description];
        $with=fn(array $base,array $items):array=>array_merge($base,['content'=>['items'=>$items]]);
        $schema=fn(int $limit):array=>['source'=>['type'=>'select','label'=>'Nguồn dữ liệu','options'=>[['value'=>'cms_products','label'=>'Sản phẩm Catalog']]],'limit'=>['type'=>'number','label'=>'Số sản phẩm','default'=>$limit],'search'=>['type'=>'text','label'=>'Từ khóa'],'category_id'=>['type'=>'select','label'=>'Danh mục'],'featured_only'=>['type'=>'boolean','label'=>'Chỉ sản phẩm nổi bật','default'=>false]];
        $products=[['title'=>'Áo ngực su không gọng FA05072292','price'=>359000,'original_price'=>429000,'image'=>$a,'url'=>'#'],['title'=>'Đồ mặc nhà cotton DMN02083646','price'=>399000,'original_price'=>429000,'image'=>$b,'url'=>'#'],['title'=>'Bộ mặc nhà lụa DH04013279','price'=>559000,'image'=>$c,'url'=>'#'],['title'=>'Áo ngực ren không gọng','price'=>329000,'image'=>$d,'url'=>'#']];
        $benefits=[['title'=>'Freeship toàn quốc đơn > 499k','icon'=>'fa-solid fa-truck'],['title'=>'Kiểm hàng trước khi thanh toán','icon'=>'fa-solid fa-list-check'],['title'=>'Hỗ trợ đóng gói miễn phí','icon'=>'fa-regular fa-bookmark']];
        $collections=[['title'=>'BST Đến bên em','image'=>$a],['title'=>'BST Mùa yêu dấu','image'=>$b],['title'=>'Giải thưởng Top 100','image'=>$d],['title'=>'BST Em Xinh','image'=>$c],['title'=>'BST Thiết yếu','image'=>$a],['title'=>'BST Tơ vương','image'=>$b]];
        return [
            ['block_type'=>'hero_slider','label'=>'Hero gallery OH!Under','description'=>'Ba ảnh lifestyle trên nền hồng.','preview_image'=>$preview,'anchor_id'=>'top','dynamic'=>true,'settings'=>['source'=>'site_banners','placement'=>'shop605-hero-slider','limit'=>3],'settings_schema'=>['placement'=>['type'=>'text','label'=>'Placement'],'limit'=>['type'=>'number','label'=>'Số ảnh']],'media'=>['image'=>$hero],'data'=>['vi'=>array_merge($heading('OH!Under'),['content'=>['slides'=>$products]]),'en'=>$heading('OH!Under')]],
            ['block_type'=>'shop605_benefits','label'=>'Quyền lợi mua hàng','description'=>'Ba quyền lợi ngay dưới hero.','preview_image'=>$preview,'anchor_id'=>'quyen-loi','settings'=>[],'settings_schema'=>[],'data'=>['vi'=>$with($heading(),$benefits),'en'=>$with($heading(),$benefits)]],
            ['block_type'=>'shop605_sale','label'=>'End of season sale','description'=>'Bốn sản phẩm sale và bộ đếm.','preview_image'=>$preview,'anchor_id'=>'sale','dynamic'=>true,'settings'=>['source'=>'cms_products','limit'=>4,'featured_only'=>false],'settings_schema'=>$schema(4),'data'=>['vi'=>$with($heading('END OF SEASON SALE - MUA 1 TẶNG 1'),$products),'en'=>$with($heading('END OF SEASON SALE'),$products)]],
            ['block_type'=>'shop605_new','label'=>'Sản phẩm mới','description'=>'Danh mục bên trái và ba sản phẩm dọc.','preview_image'=>$preview,'anchor_id'=>'san-pham','dynamic'=>true,'settings'=>['source'=>'cms_products','limit'=>3,'featured_only'=>false],'settings_schema'=>$schema(3),'data'=>['vi'=>$with($heading('Sản phẩm mới'),$products),'en'=>$with($heading('New products'),$products)]],
            ['block_type'=>'shop605_best','label'=>'Sản phẩm bán chạy','description'=>'Lưới mười sản phẩm.','preview_image'=>$preview,'anchor_id'=>'ban-chay','dynamic'=>true,'settings'=>['source'=>'cms_products','limit'=>10,'featured_only'=>false],'settings_schema'=>$schema(10),'data'=>['vi'=>$with($heading('Sản phẩm bán chạy'),$products),'en'=>$with($heading('Best sellers'),$products)]],
            ['block_type'=>'shop605_editorial','label'=>'BST Mùa yêu dấu','description'=>'Banner editorial toàn chiều rộng.','preview_image'=>$preview,'anchor_id'=>'gioi-thieu','media'=>['image'=>$hero],'settings'=>[],'settings_schema'=>[],'data'=>['vi'=>$heading('Mùa yêu dấu',null,'Hãy thả mình vào tiết trời xuân hè với những mẫu sản phẩm không thể xinh yêu hơn tại OH!Under.'),'en'=>$heading('Beloved season')]],
            ['block_type'=>'shop605_collections','label'=>'OH!Under Collection','description'=>'Mosaic sáu bộ sưu tập.','preview_image'=>$preview,'anchor_id'=>'bo-suu-tap','settings'=>[],'settings_schema'=>[],'data'=>['vi'=>$with($heading('OH!Under Collection'),$collections),'en'=>$with($heading('OH!Under Collection'),$collections)]],
            ['block_type'=>'shop605_story','label'=>'Khách hàng cảm nhận','description'=>'Câu chuyện thương hiệu và khách hàng.','preview_image'=>$preview,'anchor_id'=>'cam-nhan','settings'=>[],'settings_schema'=>[],'data'=>['vi'=>$heading('Khách hàng cảm nhận gì về OH! Under',null,'Với OH!Under, sự thành công đến từ đam mê, sáng tạo và nỗ lực thấu hiểu, trân trọng phái đẹp.'),'en'=>$heading('Customer stories')]],
            ['block_type'=>'latest_posts','label'=>'Blog & Chia sẻ','description'=>'Ba bài viết mới nhất.','preview_image'=>$preview,'anchor_id'=>'blog','dynamic'=>true,'settings'=>['source'=>'cms_posts','limit'=>3],'settings_schema'=>['limit'=>['type'=>'number','label'=>'Số bài']],'data'=>['vi'=>$heading('Blog & Chia sẻ'),'en'=>$heading('Blog & Stories')]],
            ['block_type'=>'shop605_footer','label'=>'Footer và nhận tin','description'=>'Liên hệ, hướng dẫn và newsletter.','preview_image'=>$preview,'anchor_id'=>'lien-he','settings'=>[],'settings_schema'=>[],'data'=>['vi'=>$heading('NHẬN TIN KHUYẾN MÃI'),'en'=>$heading('NEWSLETTER')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ca0050DefaultBlocks(): array
    {
        $preview = '/theme-previews/CA0050/preview-ca0050.svg';
        $hero = '/theme-demo/ca0050/hero-goldfish.png';
        $aquascape = '/theme-demo/ca0050/aquascape.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = fn (array $base, array $values): array => array_merge($base, ['content' => ['items' => $values]]);
        $productSchema = fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm sản phẩm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $products = [
            ['title' => 'Cá Ba Đuôi Oranda Đuôi Lụa', 'price' => 999000, 'original_price' => 1200000, 'image' => $hero, 'url' => '#'],
            ['title' => 'Cá Ba Đuôi Lưu Kim Calico', 'price' => 50000, 'original_price' => 69000, 'image' => $aquascape, 'url' => '#'],
            ['title' => 'Cá Bảy Màu Koi Đỏ', 'price' => 12000, 'original_price' => 15000, 'image' => $hero, 'url' => '#'],
            ['title' => 'Cá Bảy Màu Red Albino', 'price' => 8000, 'original_price' => 9000, 'image' => $aquascape, 'url' => '#'],
            ['title' => 'Cá Hạc Đỉnh Hồng', 'price' => 200000, 'image' => $hero, 'url' => '#'],
            ['title' => 'Cá Chép Sư Tử Trắng', 'price' => 800000, 'image' => $aquascape, 'url' => '#'],
            ['title' => 'Cá Bảy Màu Blue Topaz', 'price' => 20000, 'original_price' => 22000, 'image' => $hero, 'url' => '#'],
            ['title' => 'Betta trống cá xiêm trống', 'price' => 49000, 'original_price' => 55000, 'image' => $aquascape, 'url' => '#'],
        ];
        $categories = [
            ['title' => 'Cá', 'summary' => '9 sản phẩm', 'image' => $hero, 'url' => '#the-gioi-ca-canh'],
            ['title' => 'Cây', 'summary' => '8 sản phẩm', 'image' => $aquascape, 'url' => '#the-gioi-ca-canh'],
            ['title' => 'Đèn', 'summary' => '4 sản phẩm', 'icon' => 'fa-regular fa-lightbulb', 'url' => '#phu-kien'],
            ['title' => 'Thức ăn', 'summary' => '8 sản phẩm', 'icon' => 'fa-solid fa-jar', 'url' => '#phu-kien'],
            ['title' => 'Hồ', 'summary' => '8 sản phẩm', 'image' => $aquascape, 'url' => '#phu-kien'],
            ['title' => 'Thuốc', 'summary' => '0 sản phẩm', 'icon' => 'fa-solid fa-flask', 'url' => '#phu-kien'],
        ];
        $stats = [
            ['title' => '199+', 'value' => '199+', 'summary' => 'Loại cá cảnh thủy sinh', 'icon' => 'fa-solid fa-fish'],
            ['title' => '99+', 'value' => '99+', 'summary' => 'Layout bể cá thủy sinh', 'icon' => 'fa-solid fa-water'],
            ['title' => '99+', 'value' => '99+', 'summary' => 'Loại thức ăn thủy sinh', 'icon' => 'fa-solid fa-jar'],
            ['title' => '59+', 'value' => '59+', 'summary' => 'Mẫu hồ cá cảnh', 'icon' => 'fa-solid fa-fish-fins'],
        ];
        $setup = [
            ['title' => 'Thiết kế đẹp – hợp mọi không gian', 'summary' => 'Bể cá được thiết kế theo phong cách riêng, hài hòa với mọi không gian.', 'icon' => 'fa-solid fa-pen-ruler'],
            ['title' => 'Tư vấn chuẩn – giải pháp tối ưu', 'summary' => 'Kỹ thuật viên tư vấn hệ cá, cây, ánh sáng và lọc nước phù hợp nhất.', 'icon' => 'fa-solid fa-comments'],
            ['title' => 'Thi công trọn gói – nhanh gọn', 'summary' => 'Sudes đảm nhận toàn bộ quy trình từ thiết kế đến bàn giao.', 'icon' => 'fa-solid fa-screwdriver-wrench'],
            ['title' => 'Bảo hành & bảo dưỡng định kỳ', 'summary' => 'Bảo dưỡng thường xuyên giúp duy trì chất lượng nước và sức khỏe cá.', 'icon' => 'fa-solid fa-shield-heart'],
            ['title' => 'Tùy chỉnh theo nhu cầu & ngân sách', 'summary' => 'Dịch vụ linh hoạt theo mục đích, không gian và ngân sách.', 'icon' => 'fa-solid fa-gears'],
            ['title' => 'Cá nhân hóa – chăm sóc tận tâm', 'summary' => 'Mỗi công trình được theo dõi và chăm sóc tận tâm.', 'icon' => 'fa-solid fa-hand-holding-heart'],
        ];
        $faq = [
            ['title' => 'Mình mới bắt đầu chơi thủy sinh, nên chọn loại cá nào dễ nuôi nhất?', 'summary' => 'Nên bắt đầu với các loại cá khỏe, dễ chăm như cá bảy màu, cá neon, cá molly hoặc cá platy.'],
            ['title' => 'Nước bể của mình bị đục, làm sao để xử lý?', 'summary' => 'Kiểm tra hệ lọc, giảm lượng thức ăn và thay từng phần nước để ổn định hệ vi sinh.'],
            ['title' => 'Bao lâu thì nên thay nước cho bể cá thủy sinh?', 'summary' => 'Nên thay 20–30% nước mỗi tuần, tùy mật độ cá và hệ lọc.'],
            ['title' => 'Mình muốn set up bể cá trọn gói, Sudes có đến tận nhà không?', 'summary' => 'Có. Sudes khảo sát, thiết kế, thi công và bàn giao tận nơi.'],
            ['title' => 'Mua cá về có được bảo hành không?', 'summary' => 'Sudes có chính sách hỗ trợ riêng theo từng dòng cá và điều kiện vận chuyển.'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Sudes Aquarium', 'description' => 'Slider cá cảnh toàn chiều rộng lấy từ banner CA0050.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ca0050-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => $hero], 'data' => ['vi' => array_merge($heading('Sudes Aquarium'), ['content' => ['slides' => [['title' => 'Sudes Aquarium', 'image' => $hero, 'link_url' => '#the-gioi-ca-canh']]]]), 'en' => $heading('Sudes Aquarium')]],
            ['block_type' => 'featured_categories', 'label' => 'Khám phá danh mục', 'description' => 'Sáu danh mục cá, cây, đèn, thức ăn, hồ và thuốc.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'settings' => ['source' => 'custom', 'limit' => 6], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục']], 'data' => ['vi' => $withItems($heading('KHÁM PHÁ SUDES AQUARIUM'), $categories), 'en' => $withItems($heading('EXPLORE SUDES AQUARIUM'), $categories)]],
            ['block_type' => 'ca0050_about', 'label' => 'Giới thiệu Sudes', 'description' => 'Giới thiệu, thống kê và ảnh hồ thủy sinh.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'media' => ['image' => $aquascape], 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading('GIỚI THIỆU VỀ SUDES AQUARIUM', 'Với niềm đam mê và kinh nghiệm, Sudes cam kết sản phẩm chất lượng, dịch vụ tận tâm và tư vấn chuyên nghiệp.', 'Sudes Aquarium là thế giới thu nhỏ dành cho những ai yêu thích vẻ đẹp của thủy sinh và cá cảnh.'), $stats), 'en' => $withItems($heading('ABOUT SUDES AQUARIUM'), $stats)]],
            ['block_type' => 'ca0050_fish_products', 'label' => 'Thế giới cá cảnh', 'description' => 'Lưới tám sản phẩm cá cảnh từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'the-gioi-ca-canh', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $withItems($heading('THẾ GIỚI CÁ CẢNH'), $products), 'en' => $withItems($heading('ORNAMENTAL FISH'), $products)]],
            ['block_type' => 'ca0050_tiktok', 'label' => 'Sudes trên TikTok', 'description' => 'Khối social media nền xanh đại dương.', 'preview_image' => $preview, 'anchor_id' => 'tiktok', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('SUDES AQUARIUM ON TIKTOK', null, 'Nếu bạn yêu thích thế giới thủy sinh, TikTok của Sudes Aquarium chính là nơi dành cho bạn!'), 'en' => $heading('SUDES AQUARIUM ON TIKTOK')]],
            ['block_type' => 'ca0050_setup', 'label' => 'Dịch vụ setup bể cá', 'description' => 'Sáu lợi ích setup bể cá bao quanh ảnh trung tâm.', 'preview_image' => $preview, 'anchor_id' => 'setup', 'media' => ['image' => $aquascape], 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading('SETUP BỂ CÁ THỦY SINH'), $setup), 'en' => $withItems($heading('AQUARIUM SETUP'), $setup)]],
            ['block_type' => 'ca0050_accessories', 'label' => 'Hồ và phụ kiện', 'description' => 'Bốn sản phẩm hồ và phụ kiện từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'phu-kien', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'CA0050-HO', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('HỒ VÀ PHỤ KIỆN'), $products), 'en' => $withItems($heading('TANKS & ACCESSORIES'), $products)]],
            ['block_type' => 'testimonials', 'label' => 'Review cóng tâm', 'description' => 'Đánh giá khách hàng với ảnh hồ thủy sinh.', 'preview_image' => $preview, 'anchor_id' => 'danh-gia', 'dynamic' => true, 'settings' => ['source' => 'cms_testimonials', 'limit' => 1], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số đánh giá']], 'data' => ['vi' => $withItems($heading('REVIEW CÓNG TÂM'), [['name' => 'Ngọc Vy', 'role' => 'Kế toán', 'quote' => 'Cá khỏe, màu đẹp và thích nghi rất nhanh. Shop tư vấn kỹ cách nuôi nên mình mới chơi mà vẫn nuôi ổn.']]), 'en' => $heading('CUSTOMER REVIEW')]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức Sudes', 'description' => 'Bốn bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài']], 'data' => ['vi' => $heading('TIN TỨC MỚI NHẤT TỪ SUDES CRAFT'), 'en' => $heading('LATEST NEWS FROM SUDES')]],
            ['block_type' => 'ca0050_faq', 'label' => 'Giải đáp thắc mắc', 'description' => 'Accordion năm câu hỏi thường gặp.', 'preview_image' => $preview, 'anchor_id' => 'faq', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading('GIẢI ĐÁP THẮC MẮC', null, 'Có thắc mắc về cách nuôi cá, chăm bể hay chọn phụ kiện thủy sinh?'), $faq), 'en' => $withItems($heading('FREQUENTLY ASKED QUESTIONS'), $faq)]],
            ['block_type' => 'partner_logos', 'label' => 'Đối tác Sudes', 'description' => 'Logo đối tác lấy từ CMS Partners.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 6], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => $heading('ĐỐI TÁC CỦA SUDES AQUARIUM'), 'en' => $heading('SUDES AQUARIUM PARTNERS')]],
            ['block_type' => 'ca0050_footer', 'label' => 'Footer và đăng ký nhận tin', 'description' => 'Thông tin liên hệ, chính sách và newsletter.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('ĐĂNG KÝ NHẬN TIN KHUYẾN MÃI', null, 'Đăng ký nhận tin khuyến mãi từ Sudes Aquarium để không bỏ lỡ các ưu đãi hấp dẫn!'), 'en' => $heading('PROMOTION NEWSLETTER')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function shop604DefaultBlocks(): array
    {
        $preview = '/theme-previews/SHOP604/preview-shop604.svg';
        $hero = '/theme-demo/shop604/hero-fashion.png';
        $rose = '/theme-demo/shop604/product-women-rose.png';
        $knit = '/theme-demo/shop604/product-women-knit.png';
        $green = '/theme-demo/shop604/product-men-green.png';
        $wide = '/theme-demo/shop604/ad-lac-quan.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = fn (array $base, array $values): array => array_merge($base, ['content' => ['items' => $values]]);
        $productSchema = fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm sản phẩm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $products = [
            ['title' => 'Áo ngực nữ thanh lịch', 'summary' => 'BEAN LINGERIE', 'price' => 235000, 'original_price' => 289000, 'image' => $knit, 'url' => '#'],
            ['title' => 'Bộ nội y ren dịu dàng', 'summary' => 'BRA LIVE', 'price' => 279000, 'original_price' => 399000, 'image' => $rose, 'url' => '#'],
            ['title' => 'Bralette mềm mại', 'summary' => 'BEAN PUSH', 'price' => 169000, 'original_price' => 199000, 'image' => $green, 'url' => '#'],
            ['title' => 'Bikini hiện đại', 'summary' => 'BEAN BIKINI', 'price' => 439000, 'original_price' => 559000, 'image' => $wide, 'url' => '#'],
            ['title' => 'Áo ren không gọng', 'summary' => 'LING BRALETTE', 'price' => 149000, 'original_price' => 188000, 'image' => $green, 'url' => '#'],
            ['title' => 'Bộ bikini ba mảnh', 'summary' => 'BEAN BIKINI', 'price' => 440000, 'original_price' => 599000, 'image' => $wide, 'url' => '#'],
            ['title' => 'Đồ bơi nữ thanh lịch', 'summary' => 'BRA LIVE', 'price' => 360000, 'original_price' => 410000, 'image' => $rose, 'url' => '#'],
            ['title' => 'Bikini đi biển dáng quấn', 'summary' => 'BEAN BIKINI', 'price' => 480000, 'original_price' => 535000, 'image' => $knit, 'url' => '#'],
        ];
        $categories = [
            ['title' => 'Áo ngực', 'summary' => 'Thiết kế tôn dáng, mềm mại và nâng đỡ tự nhiên.', 'image' => $knit, 'url' => '#san-pham-moi'],
            ['title' => 'Quần lót', 'summary' => 'Thoải mái trong từng khoảnh khắc thường ngày.', 'image' => $rose, 'url' => '#san-pham-moi'],
            ['title' => 'Bikini', 'summary' => 'Tự tin khoe trọn vẻ đẹp quyến rũ và năng động.', 'image' => $wide, 'url' => '#bo-suu-tap'],
        ];
        $benefits = [
            ['title' => 'Miễn phí vận chuyển', 'summary' => 'Miễn phí vận chuyển cho khu vực TP.HCM', 'icon' => 'fa-solid fa-box'],
            ['title' => 'Thanh toán bảo mật', 'summary' => 'Thanh toán được bảo mật hoàn toàn', 'icon' => 'fa-solid fa-shield-halved'],
            ['title' => 'Đổi trả dễ dàng', 'summary' => 'Hỗ trợ đổi trả nếu sản phẩm bị lỗi', 'icon' => 'fa-solid fa-arrows-rotate'],
            ['title' => 'Hotline 24/7', 'summary' => 'Luôn hỗ trợ mọi lúc khi có yêu cầu', 'icon' => 'fa-solid fa-headset'],
        ];
        $testimonials = [
            ['name' => 'Nguyễn Thảo', 'role' => 'Nhân viên văn phòng', 'quote' => 'Form ôm người đẹp, nâng ngực vừa phải, không lộ hay khó chịu.', 'image' => $knit],
            ['name' => 'Hoàng Duyên', 'role' => 'Kỹ sư', 'quote' => 'Thiết kế thanh mảnh, nữ tính và tạo cảm giác nhẹ nhàng.', 'image' => $rose],
            ['name' => 'Thu Hà', 'role' => 'Kinh doanh', 'quote' => 'Chất vải mềm, co giãn tốt, mặc không bí hay cọ rát da.', 'image' => $wide],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Bean Lingerie', 'description' => 'Slider editorial toàn màn hình lấy từ banner SHOP604.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'shop604-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => $hero], 'data' => ['vi' => array_merge($heading('Lingerie Bras', 'Bộ Sưu Tập Mới', 'Thiết kế hoàn hảo tôn lên vẻ đẹp nữ tính đầy tinh tế.', 'Mua Ngay'), ['content' => ['slides' => [['title' => 'Lingerie Bras', 'kicker' => 'Bộ Sưu Tập Mới', 'summary' => 'Thiết kế hoàn hảo tôn lên vẻ đẹp nữ tính đầy tinh tế.', 'button_label' => 'Mua Ngay', 'image' => $hero, 'link_url' => '#san-pham-moi']]]]), 'en' => $heading('Lingerie Bras', 'New Collection', 'Refined designs made to celebrate your natural beauty.', 'Shop now')]],
            ['block_type' => 'featured_categories', 'label' => 'Danh mục sản phẩm', 'description' => 'Ba danh mục nổi bật với ảnh dọc và mô tả.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'settings' => ['source' => 'custom', 'limit' => 3], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục']], 'data' => ['vi' => $withItems($heading('Danh Mục Sản Phẩm'), $categories), 'en' => $withItems($heading('Product Categories'), $categories)]],
            ['block_type' => 'shop604_flash_sale', 'label' => 'Flash sale', 'description' => 'Sản phẩm khuyến mãi và đồng hồ đếm ngược.', 'preview_image' => $preview, 'anchor_id' => 'flash-sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Flash Sale', 'ƯU ĐÃI CHỚP NHOÁNG'), $products), 'en' => $withItems($heading('Flash Sale', 'LIMITED OFFERS'), $products)]],
            ['block_type' => 'shop604_editorial_one', 'label' => 'Banner editorial 1', 'description' => 'Thông điệp bên trái, ảnh bên phải.', 'preview_image' => $preview, 'anchor_id' => 'phong-cach', 'media' => ['image' => $rose], 'settings' => ['cta_url' => '#san-pham-moi'], 'settings_schema' => ['cta_url' => ['type' => 'text', 'label' => 'Liên kết nút']], 'data' => ['vi' => $heading('Nét Quyến Rũ Dịu Dàng', 'Gợi Cảm Cuốn Hút', 'Thiết kế hoàn hảo với chất liệu cao cấp, tôn lên vẻ đẹp nữ tính đầy quyến rũ và thanh lịch.', 'Mua Ngay'), 'en' => $heading('Softly Alluring', 'Captivating Style', 'Premium details that feel refined and feminine.', 'Shop now')]],
            ['block_type' => 'shop604_new_arrivals', 'label' => 'Sản phẩm mới', 'description' => 'Bốn sản phẩm mới nhất từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Sản Phẩm Mới'), $products), 'en' => $withItems($heading('New Arrivals'), $products)]],
            ['block_type' => 'shop604_editorial_two', 'label' => 'Banner editorial 2', 'description' => 'Ảnh bên trái, thông điệp bên phải.', 'preview_image' => $preview, 'anchor_id' => 'net-dep-tu-nhien', 'media' => ['image' => $green], 'settings' => ['cta_url' => '#bo-suu-tap'], 'settings_schema' => ['cta_url' => ['type' => 'text', 'label' => 'Liên kết nút']], 'data' => ['vi' => $heading('Nét Đẹp Tự Nhiên', 'Thanh Lịch Hiện Đại', 'Chất liệu cao cấp mang lại cảm giác êm ái và vừa vặn, giúp bạn tự tin suốt cả ngày.', 'Mua Ngay'), 'en' => $heading('Natural Beauty', 'Modern Elegance', 'Premium fabrics designed for effortless comfort.', 'Shop now')]],
            ['block_type' => 'shop604_collection_tabs', 'label' => 'Bộ sưu tập dạng tab', 'description' => 'Lưới tám sản phẩm có tab bộ sưu tập.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $withItems($heading('Đồ bơi sexy · Đồ ngủ xinh · Phụ kiện'), $products), 'en' => $withItems($heading('Swim · Sleep · Accessories'), $products)]],
            ['block_type' => 'shop604_lookbook', 'label' => 'Lookbook hotspot', 'description' => 'Ảnh toàn chiều rộng với các điểm khám phá sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'lookbook', 'media' => ['image' => $hero], 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('Lookbook Bean'), 'en' => $heading('Bean Lookbook')]],
            ['block_type' => 'testimonials', 'label' => 'Đánh giá khách hàng', 'description' => 'Đánh giá từ CMS Testimonials hoặc nhập thủ công.', 'preview_image' => $preview, 'anchor_id' => 'danh-gia', 'dynamic' => true, 'settings' => ['source' => 'cms_testimonials', 'limit' => 3], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số đánh giá']], 'data' => ['vi' => $withItems($heading('Đánh Giá Khách Hàng'), $testimonials), 'en' => $withItems($heading('Customer Reviews'), $testimonials)]],
            ['block_type' => 'partner_logos', 'label' => 'Thương hiệu đồng hành', 'description' => 'Logo đối tác lấy từ CMS Partners.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 8], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số thương hiệu']], 'data' => ['vi' => $heading('Thương hiệu đồng hành'), 'en' => $heading('Featured brands')]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức', 'description' => 'Bốn bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài']], 'data' => ['vi' => $heading('Tin Tức'), 'en' => $heading('News')]],
            ['block_type' => 'shop604_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn chính sách dịch vụ nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading(), $benefits), 'en' => $withItems($heading(), $benefits)]],
            ['block_type' => 'shop604_gallery', 'label' => 'Gallery cuối trang', 'description' => 'Bốn ảnh lifestyle liền nhau.', 'preview_image' => $preview, 'anchor_id' => 'gallery', 'settings' => ['source' => 'custom', 'limit' => 4], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số ảnh']], 'data' => ['vi' => $withItems($heading('Bean Gallery'), $products), 'en' => $withItems($heading('Bean Gallery'), $products)]],
            ['block_type' => 'shop604_newsletter', 'label' => 'Đăng ký nhận tin', 'description' => 'Nội dung được render trong footer dùng chung.', 'preview_image' => $preview, 'anchor_id' => 'dang-ky', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('Đăng Ký Nhận Tin', null, 'Nhận thông tin về các chương trình khuyến mãi.', 'Đăng ký'), 'en' => $heading('Newsletter', null, 'Receive our latest promotions.', 'Subscribe')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function shop603DefaultBlocks(): array
    {
        $preview = '/theme-previews/SHOP603/preview-shop603.svg';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $items = fn (array $values): array => ['content' => ['items' => $values]];
        $productSchema = fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm sản phẩm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy sản phẩm nổi bật', 'default' => false],
        ];
        $newsSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 3],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm tin tức'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin tức'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy tin nổi bật', 'default' => false],
            'fallback_image' => ['type' => 'text', 'label' => 'Ảnh mặc định khi tin chưa có ảnh'],
        ];
        $products = [
            ['title' => 'Áo len nữ thanh lịch', 'image' => '/theme-demo/shop603/product-women-knit.png', 'price' => 110000, 'original_price' => 130000, 'url' => '#'],
            ['title' => 'Chân váy nữ', 'image' => '/theme-demo/shop603/product-women-knit.png', 'price' => 70000, 'url' => '#'],
            ['title' => 'Áo khoác nữ', 'image' => '/theme-demo/shop603/product-women-rose.png', 'price' => 300000, 'original_price' => 500000, 'url' => '#'],
            ['title' => 'Đồ thể thao nữ', 'image' => '/theme-demo/shop603/product-women-rose.png', 'price' => 0, 'url' => '#'],
            ['title' => 'Sơ mi trẻ em', 'image' => '/theme-demo/shop603/product-women-knit.png', 'price' => 0, 'url' => '#'],
            ['title' => 'Đồ ngủ nam', 'image' => '/theme-demo/shop603/product-men-green.png', 'price' => 130000, 'url' => '#'],
            ['title' => 'Bộ đồ hè nam', 'image' => '/theme-demo/shop603/product-men-green.png', 'price' => 200000, 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Header và slider Alena', 'description' => 'Slider ảnh lớn đầu trang lấy từ banner SHOP603.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'shop603-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Mùa lễ hội', 'Tuần lễ mặc đẹp', 'Mua 2 sản phẩm tặng 20%', 'Khám phá ngay'), ['content' => ['slides' => [['title' => 'Mùa lễ hội', 'summary' => 'Mua 2 sản phẩm tặng 20%', 'button_label' => 'Khám phá ngay', 'image' => '/theme-demo/shop603/hero-fashion.png', 'link_url' => '#san-pham-hot']]]]), 'en' => $heading('Holiday season', 'Style week', 'Buy two items and save 20%', 'Explore now')]],
            ['block_type' => 'shop603_quality_slider', 'label' => 'Cam kết / danh mục dịch vụ', 'description' => 'Dải item ảnh chạy ngang; nội dung do người dùng tự nhập.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'settings' => ['autoplay_ms' => 3400], 'settings_schema' => ['autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading(), $items([['title' => 'Miễn phí giao hàng', 'summary' => 'Miễn phí ship với đơn hàng từ 498K', 'icon' => 'truck'], ['title' => 'Thanh toán COD', 'summary' => 'Thanh toán khi nhận hàng', 'icon' => 'handshake'], ['title' => 'Khách hàng VIP', 'summary' => 'Ưu đãi dành cho khách hàng VIP', 'icon' => 'crown'], ['title' => 'Hỗ trợ bảo hành', 'summary' => 'Đổi, sửa đồ tại tất cả cửa hàng', 'icon' => 'shirt'], ['title' => 'Tư vấn tận tâm', 'summary' => 'Hỗ trợ lựa chọn sản phẩm phù hợp', 'icon' => 'headset']])), 'en' => $heading()]],
            ['block_type' => 'shop603_hot_products', 'label' => 'Sản phẩm hot', 'description' => 'Danh sách sản phẩm theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-hot', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => true], 'settings_schema' => $productSchema(5), 'data' => ['vi' => array_merge($heading('Sản phẩm hot'), $items(array_slice($products, 0, 5))), 'en' => $heading('Hot products')]],
            ['block_type' => 'shop603_new_arrivals', 'label' => 'Hàng mới về', 'description' => 'Sản phẩm đầu tiên dạng ảnh lớn, các sản phẩm sau dạng lưới nhỏ.', 'preview_image' => $preview, 'anchor_id' => 'hang-moi-ve', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 7, 'featured_only' => false], 'settings_schema' => $productSchema(7), 'data' => ['vi' => array_merge($heading('Hàng mới về', null, 'Sản phẩm mới cập nhật theo bộ lọc.'), $items($products)), 'en' => $heading('New arrivals')]],
            ['block_type' => 'shop603_single_ad', 'label' => 'Banner quảng cáo', 'description' => 'Một banner ảnh quảng cáo toàn chiều rộng.', 'preview_image' => $preview, 'anchor_id' => 'banner-quang-cao', 'settings' => [], 'data' => ['vi' => array_merge($heading(), $items([['title' => 'Mini collection - Lạc quan mang về', 'image' => '/theme-demo/shop603/ad-lac-quan.png', 'url' => '#sale-dong-gia']])), 'en' => $heading()]],
            ['block_type' => 'shop603_sale_slider', 'label' => 'Sale đồng giá', 'description' => 'Sản phẩm theo bộ lọc hiển thị dạng carousel ngang.', 'preview_image' => $preview, 'anchor_id' => 'sale-dong-gia', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'featured_only' => false, 'autoplay_ms' => 3600], 'settings_schema' => array_merge($productSchema(10), ['autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']]), 'data' => ['vi' => array_merge($heading('Sale đồng giá - Đừng lo về giá'), $items($products)), 'en' => $heading('One-price sale')]],
            ['block_type' => 'shop603_newsletter', 'label' => 'Đăng ký nhận khuyến mãi', 'description' => 'Form đăng ký email nhận tin khuyến mãi.', 'preview_image' => $preview, 'anchor_id' => 'dang-ky-email', 'settings' => [], 'data' => ['vi' => $heading('Nhập thông tin khuyến mãi từ chúng tôi', null, null, 'Gửi'), 'en' => $heading('Get our latest promotions', null, null, 'Subscribe')]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức thời trang', 'description' => 'Tin tức theo điều kiện tìm kiếm và danh mục.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false, 'fallback_image' => '/theme-demo/shop603/product-women-rose.png'], 'settings_schema' => $newsSchema, 'data' => ['vi' => $heading('Tin tức thời trang'), 'en' => $heading('Fashion news')]],
            ['block_type' => 'partner_logos', 'label' => 'Logo đối tác', 'description' => 'Logo lấy từ CMS Partners hoặc nội dung nhập tay.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 6], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_partners', 'label' => 'Đối tác CMS'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số logo', 'default' => 6]], 'data' => ['vi' => array_merge($heading(), $items([['title' => 'CHANEL'], ['title' => 'LOUIS VUITTON'], ['title' => 'GIVENCHY'], ['title' => 'BALENCIAGA'], ['title' => 'HERMÈS'], ['title' => 'YSL']])), 'en' => $heading()]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function shop602DefaultBlocks(): array
    {
        $preview = '/theme-previews/SHOP602/preview-shop602.svg';
        $productOptions = [['value' => 'cms_products', 'label' => 'Sản phẩm']];
        $productSchema = fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $productOptions],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy sản phẩm nổi bật', 'default' => false],
        ];
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $items = fn (array $values): array => ['content' => ['items' => $values]];
        $products = [
            ['title' => 'Set đồ tập yoga nữ Flow', 'summary' => 'WOLF ACTIVE', 'image' => 'https://images.unsplash.com/photo-1506629082955-511b1aa562c8?auto=format&fit=crop&w=900&q=85', 'price' => 650000, 'original_price' => 900000, 'url' => '#'],
            ['title' => 'Áo bra thể thao nữ Cushion', 'summary' => 'WOLF YOGA', 'image' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=900&q=85', 'price' => 349000, 'original_price' => 500000, 'url' => '#'],
            ['title' => 'Quần legging yoga nâng dáng', 'summary' => 'WOLF STUDIO', 'image' => 'https://images.unsplash.com/photo-1599447421416-3414500d18a5?auto=format&fit=crop&w=900&q=85', 'price' => 475000, 'original_price' => 500000, 'url' => '#'],
            ['title' => 'Túi trống thể thao Origin', 'summary' => 'WOLF GEAR', 'image' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=85', 'price' => 790000, 'original_price' => 950000, 'url' => '#'],
            ['title' => 'Thảm tập yoga cân bằng', 'summary' => 'WOLF BALANCE', 'image' => 'https://images.unsplash.com/photo-1592432678016-e910b452f9a2?auto=format&fit=crop&w=900&q=85', 'price' => 420000, 'original_price' => 550000, 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Slider Wolf Yoga', 'description' => 'Slider ảnh lớn đầu trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'shop602-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy (ms)']], 'data' => ['vi' => array_merge($heading('Sống khỏe mạnh, sống an yên', 'WOLF YOGA', 'Đồng hành cùng bạn trên hành trình cân bằng thân - tâm - trí.', 'Mua sắm ngay'), ['content' => ['slides' => [['title' => 'Sống khỏe mạnh<br>Sống an yên', 'summary' => 'Wolf Yoga đồng hành cùng bạn trên hành trình cân bằng thân - tâm - trí.', 'button_label' => 'Mua sắm ngay', 'image' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#san-pham-moi']]]]), 'en' => $heading('Live healthy, live peacefully', 'WOLF YOGA', 'Balance your body, mind and spirit.', 'Shop now')]],
            ['block_type' => 'shop602_quality_slider', 'label' => 'Cam kết / danh mục dịch vụ', 'description' => 'Carousel ảnh chạy ngang, toàn bộ nội dung do người dùng tự nhập.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'settings' => ['autoplay_ms' => 3600], 'settings_schema' => ['autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy (ms)']], 'data' => ['vi' => array_merge($heading(), $items([['title' => 'Sản phẩm chất lượng', 'summary' => 'Được lựa chọn kỹ lưỡng, an toàn và bền bỉ.', 'image' => 'https://images.unsplash.com/photo-1599447421416-3414500d18a5?auto=format&fit=crop&w=300&q=85'], ['title' => 'Hỗ trợ sức khỏe toàn diện', 'summary' => 'Nâng cao sức khỏe thể chất và tinh thần.', 'image' => 'https://images.unsplash.com/photo-1506126613408-eca07ce68773?auto=format&fit=crop&w=300&q=85'], ['title' => 'Trải nghiệm an yên', 'summary' => 'Thiết kế tối giản, tinh tế và thoải mái.', 'image' => 'https://images.unsplash.com/photo-1599447292180-45fd84092ef4?auto=format&fit=crop&w=300&q=85'], ['title' => 'Đồng hành tận tâm', 'summary' => 'Đội ngũ Wolf Yoga luôn lắng nghe bạn.', 'image' => 'https://images.unsplash.com/photo-1545389336-cf090694435e?auto=format&fit=crop&w=300&q=85']])), 'en' => $heading()]],
            ['block_type' => 'shop602_flash_sale', 'label' => 'Flash sale', 'description' => 'Sản phẩm theo điều kiện lọc và đồng hồ đếm ngược.', 'preview_image' => $preview, 'anchor_id' => 'flash-sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => false, 'ends_at' => now()->addDays(7)->toIso8601String()], 'settings_schema' => array_merge($productSchema(5), ['ends_at' => ['type' => 'text', 'label' => 'Thời điểm kết thúc']]), 'data' => ['vi' => array_merge($heading('Flash Sale', 'Ưu đãi chớp nhoáng', 'Số lượng có hạn'), $items($products)), 'en' => $heading('Flash Sale')]],
            ['block_type' => 'shop602_dual_ads', 'label' => 'Hai banner quảng cáo', 'description' => 'Hai ảnh quảng cáo do người dùng chọn và chèn link.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai', 'settings' => [], 'data' => ['vi' => array_merge($heading(), $items([['title' => 'New Arrival', 'image' => 'https://images.unsplash.com/photo-1538805060514-97d9cc17730c?auto=format&fit=crop&w=1400&q=85', 'url' => '#san-pham-moi'], ['title' => 'Ưu đãi Combo', 'image' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1400&q=85', 'url' => '#flash-sale']])), 'en' => $heading()]],
            ['block_type' => 'shop602_new_arrivals', 'label' => 'Sản phẩm mới về', 'description' => 'Sản phẩm theo điều kiện lọc, kèm ảnh giới thiệu lớn.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => true, 'feature_image' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1600&q=85'], 'settings_schema' => array_merge($productSchema(5), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh giới thiệu']]), 'data' => ['vi' => array_merge($heading('Sản phẩm Mới về', 'Bộ sưu tập mới 2026', 'Thiết kế mới, chất liệu cao cấp cho trải nghiệm tập luyện thoải mái.', 'Xem thêm'), $items($products)), 'en' => $heading('New arrivals')]],
            ['block_type' => 'shop602_product_explorer', 'label' => 'Khám phá sản phẩm', 'description' => 'Sản phẩm theo bộ lọc, trình bày dạng thẻ khám phá.', 'preview_image' => $preview, 'anchor_id' => 'kham-pha', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => array_merge($heading('Khám phá Sản phẩm', 'Danh mục sản phẩm', 'Đa dạng sản phẩm chất lượng, thiết kế tối ưu cho từng bài tập.'), $items(array_slice($products, 0, 4))), 'en' => $heading('Explore products')]],
            ['block_type' => 'shop602_accessories', 'label' => 'Phụ kiện tập Yoga', 'description' => 'Sản phẩm phụ kiện theo điều kiện lọc, kèm ảnh giới thiệu.', 'preview_image' => $preview, 'anchor_id' => 'phu-kien', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => false, 'feature_image' => 'https://images.unsplash.com/photo-1592432678016-e910b452f9a2?auto=format&fit=crop&w=1600&q=85'], 'settings_schema' => array_merge($productSchema(5), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh giới thiệu']]), 'data' => ['vi' => array_merge($heading('Phụ kiện Tập Yoga', 'Bộ sưu tập', 'Phụ kiện chất lượng cao, thiết kế tiện dụng cho hành trình tập luyện.', 'Xem thêm'), $items($products)), 'en' => $heading('Yoga accessories')]],
            ['block_type' => 'shop602_single_ad', 'label' => 'Banner đơn', 'description' => 'Một ảnh quảng cáo toàn chiều rộng, cho phép gắn link.', 'preview_image' => $preview, 'anchor_id' => 'banner-bo-suu-tap', 'settings' => [], 'data' => ['vi' => array_merge($heading(), $items([['title' => 'Bộ sưu tập tôn dáng', 'image' => 'https://images.unsplash.com/photo-1506629082955-511b1aa562c8?auto=format&fit=crop&w=2200&q=88', 'url' => '#san-pham-moi']])), 'en' => $heading()]],
            ['block_type' => 'shop602_latest_news', 'label' => 'Tin mới nhất', 'description' => 'Tin tức mới nhất từ hệ thống.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức']]], 'limit' => ['type' => 'number', 'label' => 'Số bài viết'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin tức'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy tin nổi bật']], 'data' => ['vi' => $heading('Cập nhật Tin tức mới', 'Tin mới nhất', 'Khám phá sản phẩm mới, xu hướng nổi bật và thông tin hữu ích.', 'Xem tất cả tin tức'), 'en' => $heading('Latest news')]],
            ['block_type' => 'shop602_contact_form', 'label' => 'Form gửi liên hệ', 'description' => 'Form liên hệ ở cuối trang, nội dung tiêu đề có thể chỉnh sửa.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => [], 'data' => ['vi' => $heading('Bắt đầu hành trình an yên', 'Liên hệ Wolf Yoga', 'Để lại thông tin, đội ngũ của chúng tôi sẽ tư vấn sản phẩm phù hợp cho bạn.', 'Gửi liên hệ'), 'en' => $heading('Start your wellness journey', 'Contact Wolf Yoga', 'Leave your details and our team will get back to you.', 'Send message')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function shop601DefaultBlocks(): array
    {
        $preview = '/theme-previews/SHOP601/preview-shop601.svg';
        $source = fn (array $options, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $options],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục lọc'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật', 'default' => false],
        ];
        $productSources = [
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
        ];
        $collectionSources = [
            ['value' => 'custom', 'label' => 'Người dùng tự nhập'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_categories', 'label' => 'Danh mục tin tức'],
        ];
        $contentSources = [
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
        ];
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $buttonLabel = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $buttonLabel];
        $custom = fn (array $items): array => ['content' => ['items' => $items]];
        $products = [
            ['title' => 'Đầm xòe đính nơ', 'summary' => 'BABYDOLL', 'image' => 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=900&q=85', 'price' => 460000, 'original_price' => 530000, 'url' => '#'],
            ['title' => 'Đầm cổ tròn cánh tiên', 'summary' => 'MANGO', 'image' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=900&q=85', 'price' => 515000, 'original_price' => 535000, 'url' => '#'],
            ['title' => 'Áo khoác blazer', 'summary' => 'GRACE', 'image' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=900&q=85', 'price' => 705000, 'original_price' => 850000, 'url' => '#'],
            ['title' => 'Váy Babydoll viền ren', 'summary' => 'LADIES', 'image' => 'https://images.unsplash.com/photo-1572804013309-59a88b7e92f1?auto=format&fit=crop&w=900&q=85', 'price' => 590000, 'original_price' => 640000, 'url' => '#'],
            ['title' => 'Đầm suông cổ lệch', 'summary' => 'BESTY', 'image' => 'https://images.unsplash.com/photo-1539008835657-9e8e9680c956?auto=format&fit=crop&w=900&q=85', 'price' => 720000, 'original_price' => 750000, 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Slider trang chủ', 'description' => 'Slider ảnh đầu trang lấy từ banner SHOP601.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['placement' => 'shop601-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy (ms)']], 'data' => ['vi' => array_merge($heading('Săn deal chào thu', 'BEAN Style', 'Phong cách chuẩn gu, giá chỉ từ 99K.', 'Mua ngay'), ['content' => ['slides' => [['title' => 'Săn deal chào thu', 'summary' => 'Phong cách chuẩn gu · Chỉ từ 99K', 'button_label' => 'Khám phá ngay', 'image' => 'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#san-pham']]]]), 'en' => $heading('Autumn style deals', 'BEAN Style', 'Signature looks from only 99K.', 'Shop now')]],
            ['block_type' => 'shop601_benefits', 'label' => 'Cam kết mua hàng', 'description' => 'Các lợi ích do người dùng tự nhập.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'settings' => [], 'data' => ['vi' => array_merge($heading(), $custom([['icon' => 'fa-solid fa-truck-fast', 'title' => 'Miễn phí vận chuyển', 'summary' => 'Đơn từ 399K'], ['icon' => 'fa-solid fa-rotate-left', 'title' => 'Đổi hàng tận nhà', 'summary' => 'Trong vòng 15 ngày'], ['icon' => 'fa-solid fa-money-bill-wave', 'title' => 'Thanh toán COD', 'summary' => 'Yên tâm mua sắm'], ['icon' => 'fa-solid fa-headset', 'title' => 'Hotline: 1800 6750', 'summary' => 'Hỗ trợ từ 8h00-22h00']])), 'en' => $heading()]],
            ['block_type' => 'shop601_collection_cards', 'label' => 'Bộ sưu tập / danh mục', 'description' => 'Lấy từ sản phẩm, tin tức, các loại danh mục hoặc tự nhập.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $source($collectionSources, 4), 'data' => ['vi' => array_merge($heading('Bộ sưu tập mới', null, null, 'Xem chi tiết'), $custom([['title' => 'Mini BST Xuân - Hè', 'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85', 'url' => '#'], ['title' => 'Mini BST Thu - Đông', 'image' => 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=85', 'url' => '#'], ['title' => 'Baby BST dạ hội', 'image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=900&q=85', 'url' => '#'], ['title' => 'Mini BST ngoài trời', 'image' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=900&q=85', 'url' => '#']])), 'en' => $heading('New collections')]],
            ['block_type' => 'shop601_flash_sale', 'label' => 'Flash sale', 'description' => 'Danh sách sản phẩm khuyến mại.', 'preview_image' => $preview, 'anchor_id' => 'flash-sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => false, 'ends_at' => now()->addDays(7)->toIso8601String()], 'settings_schema' => array_merge($source($productSources, 5), ['ends_at' => ['type' => 'text', 'label' => 'Thời điểm kết thúc']]), 'data' => ['vi' => array_merge($heading('Flash Sale', null, 'Giảm ngay 120K cho đơn hàng trên 500K'), $custom($products)), 'en' => $heading('Flash Sale')]],
            ['block_type' => 'shop601_ads', 'label' => 'Banner quảng cáo', 'description' => 'Ảnh quảng cáo do người dùng tự nhập và gắn link.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'settings' => [], 'data' => ['vi' => array_merge($heading(), $custom([['title' => 'Khám phá BST mới', 'image' => 'https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1400&q=85', 'url' => '#san-pham'], ['title' => 'Ưu đãi online', 'image' => 'https://images.unsplash.com/photo-1551488831-00ddcb6c6bd3?auto=format&fit=crop&w=1400&q=85', 'url' => '#flash-sale']])), 'en' => $heading()]],
            ['block_type' => 'shop601_product_grid', 'label' => 'Lưới sản phẩm', 'description' => 'Sản phẩm theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'featured_only' => true], 'settings_schema' => $source($productSources, 10), 'data' => ['vi' => array_merge($heading('Sản Phẩm Nổi Bật', null, null, 'Xem tất cả'), $custom(array_merge($products, $products))), 'en' => $heading('Featured Products', null, null, 'View all')]],
            ['block_type' => 'shop601_feature_collection', 'label' => 'Bộ sưu tập nổi bật', 'description' => 'Sản phẩm đầu tiên hiển thị ảnh lớn, các sản phẩm sau hiển thị nhỏ bên cạnh.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap-noi-bat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $source($productSources, 4), 'data' => ['vi' => array_merge($heading('Bộ Sưu Tập Trang Phục Leo Núi', 'Chính thức ra mắt', null, 'Khám phá ngay'), $custom($products)), 'en' => $heading('Outdoor Collection', 'Now available', null, 'Explore')]],
            ['block_type' => 'shop601_product_carousel', 'label' => 'Sản phẩm phổ biến', 'description' => 'Carousel sản phẩm theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'pho-bien', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => false], 'settings_schema' => $source($productSources, 5), 'data' => ['vi' => array_merge($heading('Sản phẩm phổ biến', null, 'Một trải nghiệm đặc biệt'), $custom($products)), 'en' => $heading('Popular products', null, 'A special experience')]],
            ['block_type' => 'testimonials', 'label' => 'Đánh giá khách hàng', 'description' => 'Đánh giá thật từ khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'danh-gia', 'dynamic' => true, 'settings' => ['source' => 'cms_testimonials', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $source([['value' => 'cms_testimonials', 'label' => 'Đánh giá khách hàng'], ['value' => 'custom', 'label' => 'Người dùng tự nhập']], 3), 'data' => ['vi' => array_merge($heading('Hơn 5K Khách Hàng Tin Tưởng', null, 'Những lời đánh giá chân thật của khách hàng'), $custom([['name' => 'Trang Trang', 'role' => 'Nhân viên văn phòng', 'quote' => 'Sản phẩm giao nhanh, chất lượng tốt và đóng gói rất chỉn chu.', 'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=300&q=85'], ['name' => 'Gia Kỳ', 'role' => 'Tiktoker', 'quote' => 'Sản phẩm tốt, ổn áp trong tầm giá. Mình rất hài lòng.', 'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=85'], ['name' => 'Thúy Hằng', 'role' => 'Người mẫu ảnh', 'quote' => 'Chất liệu cực kỳ mát mẻ và dễ phối đồ trong nhiều dịp.', 'image' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=300&q=85']])), 'en' => $heading('Trusted by 5K+ Customers')]],
            ['block_type' => 'shop601_tiktok', 'label' => 'TikTok embed', 'description' => 'Mã nhúng TikTok và phần mô tả.', 'preview_image' => $preview, 'anchor_id' => 'tiktok', 'settings' => ['cta_url' => 'https://www.tiktok.com/'], 'data' => ['vi' => array_merge($heading('Theo dõi chúng tôi trên TikTok', '@BEANSTYLETIKTOK', 'Cập nhật nhanh nhất về sản phẩm và xu hướng thời trang.', 'Theo dõi ngay'), ['content' => ['embed_html' => '<blockquote class="tiktok-embed" cite="https://www.tiktok.com/" data-unique-id="tiktok"><section><a href="https://www.tiktok.com/">TikTok</a></section></blockquote>']]), 'en' => $heading('Follow us on TikTok', '@BEANSTYLETIKTOK', 'Fresh product and fashion updates.', 'Follow now')]],
            ['block_type' => 'shop601_latest_content', 'label' => 'Tin tức / dịch vụ', 'description' => 'Lấy dữ liệu từ tin tức hoặc dịch vụ.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $source($contentSources, 4), 'data' => ['vi' => array_merge($heading('Tin Tức Mới Nhất', null, 'Cập nhật những tin tức thời trang mới nhất', 'Xem tất cả'), $custom([['title' => 'Áo khoác công sở nữ ghi điểm phong cách', 'summary' => 'Gợi ý những item tiện lợi cho môi trường công sở.', 'image' => 'https://images.unsplash.com/photo-1525507119028-ed4c629a60a3?auto=format&fit=crop&w=900&q=85', 'url' => '#'], ['title' => 'Các kiểu váy đầm đi đám cưới đẹp nhất', 'summary' => 'Lựa chọn nổi bật cho những cô nàng hiện đại.', 'image' => 'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=900&q=85', 'url' => '#'], ['title' => 'Tips phối đồ với quần jean ống rộng', 'summary' => 'Bí quyết trẻ trung cuốn hút mọi dịp.', 'image' => 'https://images.unsplash.com/photo-1541099649105-f69ad21f3246?auto=format&fit=crop&w=900&q=85', 'url' => '#'], ['title' => 'Phụ nữ hiện đại hãy mặc vest', 'summary' => 'Tuyên ngôn phong cách và sự tự tin.', 'image' => 'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=900&q=85', 'url' => '#']])), 'en' => $heading('Latest News')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function th0050DefaultBlocks(): array
    {
        $hero = '/theme-previews/TH0050/hero-th0050.png';
        $about = '/theme-previews/TH0050/about-th0050.png';
        $quality = '/theme-previews/TH0050/quality-th0050.png';
        $flexSources = [
            ['value' => 'custom', 'label' => 'Người dùng tự nhập'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'catalog_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
        ];
        $newsSources = [
            ['value' => 'custom', 'label' => 'Người dùng tự nhập'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
        ];
        $sourceSchema = ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $flexSources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị']];
        $heading = static fn (string $title, string $subtitle, string $description, string $buttonLabel = ''): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $buttonLabel,
        ];

        $blocks = [];
        $blocks[] = [
            'block_type' => 'hero_slider', 'label' => 'Hero và cam kết dịch vụ', 'description' => 'Slider ảnh và dải cam kết chạy ngang.', 'preview_image' => $hero, 'anchor_id' => 'top', 'dynamic' => true,
            'settings' => ['source' => 'site_banners', 'placement' => 'th0050-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600],
            'settings_schema' => [['key' => 'placement', 'label' => 'Placement banner', 'type' => 'text', 'default' => 'th0050-hero-slider'], ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 3], ['key' => 'autoplay_ms', 'label' => 'Autoplay ms', 'type' => 'number', 'default' => 5600]],
            'data' => ['vi' => array_merge($heading('Tinh hoa chăm sóc sức khỏe', 'Quà tặng cao cấp', 'Sản phẩm wellness tuyển chọn, nguồn gốc minh bạch và trình bày tinh tế.', 'Khám phá ngay'), ['content' => ['slides' => [['kicker' => 'An Nhiên Wellness', 'title' => 'Tinh hoa chăm sóc sức khỏe', 'summary' => 'Quà tặng cao cấp được chăm chút trong từng chi tiết.', 'button_label' => 'Khám phá ngay', 'link_url' => '#bo-suu-tap', 'image' => $hero]], 'benefits' => [['title' => 'Giao hàng nhanh', 'summary' => 'Đóng gói cẩn thận', 'icon' => 'fa-solid fa-truck-fast'], ['title' => 'Tư vấn tận tâm', 'summary' => 'Đồng hành cùng bạn', 'icon' => 'fa-solid fa-headset'], ['title' => 'Thanh toán an toàn', 'summary' => 'Nhiều phương thức', 'icon' => 'fa-solid fa-credit-card'], ['title' => 'Quà tặng doanh nghiệp', 'summary' => 'Thiết kế theo nhu cầu', 'icon' => 'fa-solid fa-gift']]]]), 'en' => array_merge($heading('A refined wellness experience', 'Premium gifting', 'Thoughtfully selected wellness products.', 'Explore now'), ['content' => ['slides' => [], 'benefits' => []]])],
        ];

        $collectionItems = [['title' => 'Bộ quà Bốn Mùa', 'summary' => 'Thanh lịch và trang nhã', 'image' => $quality, 'url' => '#san-pham'], ['title' => 'Bộ quà Lộc Phát', 'summary' => 'Gửi trao lời chúc thịnh vượng', 'image' => $hero, 'url' => '#san-pham'], ['title' => 'Bộ quà Tinh Hoa', 'summary' => 'Tuyển chọn cho dịp đặc biệt', 'image' => $about, 'url' => '#san-pham'], ['title' => 'Bộ quà Doanh Nghiệp', 'summary' => 'Cá nhân hóa theo nhu cầu', 'image' => $quality, 'url' => '#lien-he']];
        $blocks[] = ['block_type' => 'content_showcase', 'label' => 'Bộ sưu tập cao cấp', 'description' => 'Nguồn dữ liệu linh hoạt, mặc định tự nhập.', 'preview_image' => $quality, 'anchor_id' => 'bo-suu-tap', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 4], 'settings_schema' => $sourceSchema, 'data' => ['vi' => array_merge($heading('Bộ sưu tập quà tặng cao cấp', 'An Nhiên Wellness', 'Giải pháp quà tặng tinh tế cho những mối quan hệ bền chặt.', 'Xem ngay'), ['content' => ['items' => $collectionItems]]), 'en' => array_merge($heading('Premium gift collection', 'An Nhien Wellness', 'Elegant gifts for meaningful relationships.', 'View now'), ['content' => ['items' => []]])]];

        $offerItems = [['title' => 'Set quà chăm sóc sức khỏe', 'summary' => 'Ưu đãi mùa lễ hội', 'price' => '1.250.000đ', 'badge' => '-20%', 'image' => $hero, 'url' => '#'], ['title' => 'Hộp quà thượng hạng', 'summary' => 'Thiết kế sang trọng', 'price' => '1.690.000đ', 'badge' => '-15%', 'image' => $quality, 'url' => '#'], ['title' => 'Tinh chất wellness', 'summary' => 'Bổ sung năng lượng mỗi ngày', 'price' => '890.000đ', 'badge' => 'Mới', 'image' => $about, 'url' => '#'], ['title' => 'Bộ quà tri ân', 'summary' => 'Dành cho đối tác và khách hàng', 'price' => '2.100.000đ', 'badge' => '-10%', 'image' => $hero, 'url' => '#']];
        $blocks[] = ['block_type' => 'collection_gallery', 'label' => 'Ưu đãi đặc biệt', 'description' => 'Nguồn dữ liệu linh hoạt, mặc định tự nhập.', 'preview_image' => $hero, 'anchor_id' => 'uu-dai', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 4], 'settings_schema' => $sourceSchema, 'data' => ['vi' => array_merge($heading('Khuyến mãi đặc biệt', 'An Nhiên Wellness', 'Những lựa chọn được yêu thích với ưu đãi giới hạn.', 'Xem tất cả'), ['content' => ['items' => $offerItems]]), 'en' => array_merge($heading('Special offers', 'An Nhien Wellness', 'Limited seasonal favorites.', 'View all'), ['content' => ['items' => []]])]];

        $blocks[] = ['block_type' => 'about_experience', 'label' => 'Giới thiệu công ty', 'description' => 'Giới thiệu ngắn gọn, có thể đổi ảnh nền.', 'preview_image' => $about, 'anchor_id' => 'gioi-thieu', 'dynamic' => false, 'media' => ['image' => $about], 'settings' => ['cta_url' => '#lien-he'], 'settings_schema' => ['cta_url' => ['type' => 'text', 'label' => 'Liên kết nút']], 'data' => ['vi' => array_merge($heading('Câu chuyện về An Nhiên', 'Tinh hoa wellness', 'Chúng tôi tin món quà sức khỏe có giá trị nhất khi hội tụ chất lượng, sự chân thành và vẻ đẹp tinh tế.', 'Xem chi tiết'), ['content' => ['background_image' => $about]]), 'en' => array_merge($heading('Our story', 'Wellness refined', 'Quality, sincerity and refined presentation.', 'Learn more'), ['content' => ['background_image' => $about]])]];

        $productItems = [['title' => 'Tổ yến tinh chế cao cấp', 'summary' => 'Sợi nguyên vẹn, màu sắc tự nhiên', 'price' => 2900000, 'image' => $quality, 'url' => '#'], ['title' => 'Hộp quà wellness thượng hạng', 'summary' => 'Món quà trọn vẹn và tinh tế', 'price' => 3200000, 'image' => $hero, 'url' => '#'], ['title' => 'Yến chưng dinh dưỡng', 'summary' => 'Tiện lợi cho ngày bận rộn', 'price' => 890000, 'image' => $about, 'url' => '#']];
        $blocks[] = ['block_type' => 'featured_products', 'label' => 'Sản phẩm nổi bật', 'description' => 'Dữ liệu lấy từ sản phẩm.', 'preview_image' => $quality, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'catalog_products', 'limit' => 8], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số sản phẩm']], 'data' => ['vi' => array_merge($heading('Sản phẩm tuyển chọn', 'An Nhiên Wellness', 'Sản phẩm chất lượng cao cho chăm sóc sức khỏe và quà tặng.', 'Xem tất cả'), ['content' => ['tabs' => ['Tất cả', 'Quà tặng', 'Tinh chế', 'Cao cấp'], 'items' => $productItems]]), 'en' => array_merge($heading('Selected products', 'An Nhien Wellness', 'Premium wellness products.', 'View all'), ['content' => ['tabs' => ['All'], 'items' => []]])]];

        $reasonItems = [['title' => 'Nguyên liệu cao cấp', 'summary' => 'Tuyển chọn kỹ lưỡng', 'icon' => 'fa-solid fa-gem'], ['title' => 'Chất lượng tuyệt đối', 'summary' => 'Quy trình kiểm định', 'icon' => 'fa-solid fa-shield-heart'], ['title' => 'Sản phẩm đạt chuẩn', 'summary' => 'Thông tin minh bạch', 'icon' => 'fa-solid fa-award'], ['title' => 'Giá cả hợp lý', 'summary' => 'Giá trị tương xứng', 'icon' => 'fa-solid fa-hand-holding-dollar'], ['title' => 'Giao hàng nhanh', 'summary' => 'Đóng gói cẩn thận', 'icon' => 'fa-solid fa-truck-fast'], ['title' => 'Thanh toán an toàn', 'summary' => 'Linh hoạt và bảo mật', 'icon' => 'fa-solid fa-credit-card']];
        $blocks[] = ['block_type' => 'content_mosaic', 'label' => 'Lý do lựa chọn', 'description' => 'Nội dung tự nhập, đổi được ảnh trung tâm.', 'preview_image' => $quality, 'anchor_id' => 'tai-sao-chon', 'dynamic' => true, 'media' => ['image' => $quality], 'settings' => ['source' => 'custom', 'limit' => 6], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số lý do']], 'data' => ['vi' => array_merge($heading('Vì sao chọn chúng tôi', 'An Nhiên Wellness', 'Cam kết chất lượng xuyên suốt từ sản phẩm đến dịch vụ.'), ['content' => ['background_image' => $quality, 'items' => $reasonItems]]), 'en' => array_merge($heading('Why choose us', 'An Nhien Wellness', 'Quality in every interaction.'), ['content' => ['background_image' => $quality, 'items' => []]])]];

        $postItems = [['title' => 'Cách lựa chọn quà tặng sức khỏe phù hợp', 'summary' => 'Tiêu chí quan trọng cho từng người nhận.', 'image' => $about, 'url' => '#'], ['title' => 'Dùng yến vào thời điểm nào tốt nhất?', 'summary' => 'Gợi ý từ chuyên gia dinh dưỡng.', 'image' => $quality, 'url' => '#'], ['title' => 'Bảo quản sản phẩm wellness đúng cách', 'summary' => 'Giữ nguyên chất lượng và hương vị.', 'image' => $hero, 'url' => '#']];
        $blocks[] = ['block_type' => 'latest_posts', 'label' => 'Tin tức và tư vấn', 'description' => 'Nguồn tin tức, dịch vụ, dự án hoặc tự nhập.', 'preview_image' => $about, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 7], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $newsSources], 'limit' => ['type' => 'number', 'label' => 'Số bài hiển thị']], 'data' => ['vi' => array_merge($heading('Tin tức và tư vấn', 'An Nhiên Wellness', 'Kiến thức hữu ích giúp lựa chọn và sử dụng sản phẩm đúng cách.', 'Xem thêm'), ['content' => ['items' => $postItems]]), 'en' => array_merge($heading('News and advice', 'An Nhien Wellness', 'Helpful knowledge for better wellness choices.', 'View more'), ['content' => ['items' => []]])]];

        $blocks[] = ['block_type' => 'landing_contact', 'label' => 'Liên hệ tư vấn', 'description' => 'Khối liên hệ cao cấp.', 'preview_image' => $hero, 'anchor_id' => 'lien-he', 'dynamic' => false, 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => array_merge($heading('Cần một món quà thật sự ý nghĩa?', 'Liên hệ tư vấn', 'Đội ngũ của chúng tôi sẽ giúp bạn lựa chọn sản phẩm phù hợp.', 'Gửi yêu cầu'), ['content' => []]), 'en' => array_merge($heading('Looking for a meaningful gift?', 'Get in touch', 'Our team will help you choose.', 'Send request'), ['content' => []])]];
        $partners = [['name' => 'Green Bank'], ['name' => 'Fresh Market'], ['name' => 'Golden Care'], ['name' => 'Lotus Group'], ['name' => 'Viet Wellness'], ['name' => 'An Tâm']];
        $blocks[] = ['block_type' => 'partner_logos', 'label' => 'Đối tác', 'description' => 'Logo các đối tác đồng hành.', 'preview_image' => $quality, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 8], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => array_merge($heading('Đối tác của chúng tôi', 'An Nhiên Wellness', 'Đồng hành cùng những đơn vị uy tín.'), ['content' => ['items' => $partners]]), 'en' => array_merge($heading('Our partners', 'An Nhien Wellness', 'Trusted organizations growing with us.'), ['content' => ['items' => []]])]];

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyCommerceDefaultBlocks(string $themeKey): array
    {
        $labels = match (strtoupper($themeKey)) {
            'TH0002' => ['hero' => 'Bộ sưu tập xưởng may', 'categories' => 'Dòng sản phẩm', 'products' => 'Sản phẩm may mặc', 'content' => 'Lookbook và câu chuyện xưởng'],
            'TH0003' => ['hero' => 'Lookbook thời trang', 'categories' => 'Bộ sưu tập', 'products' => 'Sản phẩm nổi bật', 'content' => 'Fashion journal'],
            default => ['hero' => 'Không gian sống nổi bật', 'categories' => 'Bộ sưu tập theo phòng', 'products' => 'Nội thất nổi bật', 'content' => 'Câu chuyện vật liệu'],
        };
        $blocks = $this->th0001DefaultBlocks();

        foreach ($blocks as &$block) {
            $block['preview_image'] = '/theme-previews/'.strtoupper($themeKey).'/preview-'.strtolower($themeKey).'.svg';

            if ($block['block_type'] === 'hero_slider') {
                $block['label'] = $labels['hero'];
                $block['settings']['placement'] = 'hero-slider';
                $block['settings_schema'][0]['default'] = 'hero-slider';
            } elseif ($block['block_type'] === 'featured_categories') {
                $block['label'] = $labels['categories'];
            } elseif ($block['block_type'] === 'featured_products') {
                $block['label'] = $labels['products'];
            } elseif ($block['block_type'] === 'content_mosaic') {
                $block['label'] = $labels['content'];
            }
        }
        unset($block);

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function legacyServiceDefaultBlocks(string $themeKey): array
    {
        $blocks = $this->ser102DefaultBlocks();

        foreach ($blocks as &$block) {
            $block['preview_image'] = '/theme-previews/'.strtoupper($themeKey).'/preview-'.strtolower($themeKey).'.svg';

            if ($block['block_type'] === 'hero_slider') {
                $block['settings']['placement'] = 'hero-slider';
                $block['settings']['theme_key'] = strtoupper($themeKey);
                $block['label'] = strtoupper($themeKey).' hero và báo giá';
            }
        }
        unset($block);

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function projectLandingDefaultBlocks(string $themeKey): array
    {
        $blocks = $this->th0001DefaultBlocks();

        foreach ($blocks as &$block) {
            $block['preview_image'] = '/theme-previews/'.strtoupper($themeKey).'/preview-'.strtolower($themeKey).'.svg';

            if ($block['block_type'] === 'hero_slider') {
                $block['label'] = 'Hero dự án mở bán';
                $block['description'] = 'Banner dự án, thông điệp mở bán và CTA nhận bảng giá.';
                $block['settings']['placement'] = 'hero-slider';
                $block['settings_schema'][0]['default'] = 'hero-slider';
            } elseif ($block['block_type'] === 'featured_categories') {
                $block['label'] = 'Phân khu nổi bật';
            } elseif ($block['block_type'] === 'featured_products') {
                $block['label'] = 'Bảng hàng mở bán';
            } elseif ($block['block_type'] === 'content_mosaic') {
                $block['label'] = 'Thông tin dự án';
                $block['settings']['source'] = 'cms_posts';
            }
        }
        unset($block);

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function th0001DefaultBlocks(): array
    {
        $productSourceOptions = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero thương mại',
                'description' => 'Danh mục sản phẩm, banner chính và banner khuyến mãi của TH0001.',
                'preview_image' => '/theme-previews/TH0001/preview-th0001.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'hero-main', 'limit' => 4, 'autoplay_ms' => 5200],
                'settings_schema' => [
                    ['key' => 'placement', 'label' => 'Vị trí banner', 'type' => 'text', 'default' => 'hero-main'],
                    ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 4],
                    ['key' => 'autoplay_ms', 'label' => 'Thời gian tự chuyển (ms)', 'type' => 'number', 'default' => 5200],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Ưu đãi nổi bật',
                        'subtitle' => 'Khám phá ngay',
                        'description' => 'Khám phá sản phẩm và chương trình ưu đãi mới nhất.',
                        'button_label' => 'Xem ngay',
                        'content' => ['slides' => []],
                    ],
                    'en' => [
                        'title' => 'Featured deals',
                        'subtitle' => 'Discover now',
                        'description' => 'Explore the latest products and promotions.',
                        'button_label' => 'Shop now',
                        'content' => ['slides' => []],
                    ],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Danh mục nổi bật',
                'description' => 'Các nhóm sản phẩm nổi bật dạng nhãn tròn của TH0001.',
                'preview_image' => '/theme-previews/TH0001/preview-th0001.png',
                'anchor_id' => 'danh-muc-noi-bat',
                'dynamic' => true,
                'settings' => ['source' => 'catalog_categories', 'limit' => 5, 'featured_only' => false],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [
                        ['value' => 'custom', 'label' => 'Nhập thủ công'],
                        ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
                        ['value' => 'cms_categories', 'label' => 'Danh mục bài viết'],
                        ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
                    ]],
                    'limit' => ['type' => 'number', 'label' => 'Số danh mục hiển thị', 'default' => 5],
                ],
                'data' => [
                    'vi' => ['title' => 'Danh mục nổi bật', 'subtitle' => 'Khám phá nhanh', 'description' => '', 'button_label' => 'Xem danh mục', 'content' => ['items' => []]],
                    'en' => ['title' => 'Featured categories', 'subtitle' => 'Quick access', 'description' => '', 'button_label' => 'View category', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_products',
                'label' => 'Sản phẩm nổi bật',
                'description' => 'Lưới sản phẩm ưu tiên của storefront.',
                'preview_image' => '/theme-previews/TH0001/preview-th0001.png',
                'anchor_id' => 'featured',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 8, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $productSourceOptions],
                    'limit' => ['type' => 'number', 'label' => 'Số sản phẩm hiển thị', 'default' => 8],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Sản phẩm nổi bật', 'subtitle' => 'Đề xuất hôm nay', 'description' => '', 'button_label' => 'Xem tất cả', 'content' => ['items' => []]],
                    'en' => ['title' => 'Featured products', 'subtitle' => 'Today picks', 'description' => '', 'button_label' => 'View all', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Khối sản phẩm theo chủ đề',
                'description' => 'Khối card có thể đổi nguồn sang sản phẩm, tin tức, dịch vụ hoặc dự án.',
                'preview_image' => '/theme-previews/TH0001/preview-th0001.png',
                'anchor_id' => 'chu-de-noi-bat',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 8, 'featured_only' => false],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $productSourceOptions],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => 8],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
                ],
                'data' => [
                    'vi' => ['title' => 'Khám phá thêm', 'subtitle' => 'Chủ đề nổi bật', 'description' => 'Các lựa chọn mới dành cho bạn.', 'button_label' => 'Xem thêm', 'content' => ['items' => []]],
                    'en' => ['title' => 'Explore more', 'subtitle' => 'Featured topic', 'description' => 'More picks curated for you.', 'button_label' => 'View more', 'content' => ['items' => []]],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ser102DefaultBlocks(): array
    {
        $categorySources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
        ];
        $productSources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
        ];
        $insightSources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
        ];
        $sourceSchema = fn (array $options): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $options],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'ID danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
        ];
        $hero = '/theme-previews/SER102/cover-ser102.png';
        $appointment = '/theme-previews/SER102/appointment.png';
        $product = '/theme-previews/SER102/avatar.png';

        return [
            [
                'block_type' => 'hero_slider', 'label' => 'Header và slide hình ảnh',
                'description' => 'Hero toàn màn hình lấy banner SER102, có CTA và tự chuyển slide.',
                'preview_image' => $hero, 'anchor_id' => 'trang-chu', 'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'ser102-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner', 'default' => 'ser102-hero-slider'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide', 'default' => 3],
                    'autoplay_ms' => ['type' => 'number', 'label' => 'Thời gian tự chuyển (ms)', 'default' => 6000],
                ],
                'data' => [
                    'vi' => ['title' => 'Chăm sóc xe chuẩn chuyên gia', 'subtitle' => 'SER102 Auto Detailing', 'description' => 'Sản phẩm chính hãng, quy trình an toàn và hiệu quả vượt trội cho từng chi tiết.', 'button_label' => 'Khám phá ngay', 'content' => ['slides' => [
                        ['kicker' => 'SER102 Auto Detailing', 'title' => 'Chăm sóc xe chuẩn chuyên gia', 'summary' => 'Bảo vệ toàn diện, hoàn thiện từng chi tiết bằng quy trình chuyên nghiệp.', 'button_label' => 'Khám phá ngay', 'link_url' => '#dich-vu', 'image' => $hero],
                    ]]],
                    'en' => ['title' => 'Professional auto detailing', 'subtitle' => 'SER102 Auto Detailing', 'description' => 'Premium products, safe process and meticulous results.', 'button_label' => 'Explore', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories', 'label' => 'Dịch vụ nổi bật',
                'description' => 'Mặc định nhập thủ công; có thể đổi sang danh mục dịch vụ hoặc danh mục sản phẩm.',
                'preview_image' => $appointment, 'anchor_id' => 'dich-vu', 'dynamic' => true,
                'settings' => ['source' => 'custom'], 'settings_schema' => $sourceSchema($categorySources),
                'data' => [
                    'vi' => ['title' => 'Chăm sóc toàn diện - hoàn hảo từng chi tiết', 'subtitle' => 'Dịch vụ nổi bật', 'description' => 'Đội ngũ kỹ thuật chuyên nghiệp cùng sản phẩm cao cấp mang đến diện mạo hoàn hảo cho xe yêu.', 'content' => ['items' => [
                        ['title' => 'Ceramic coating', 'summary' => 'Bảo vệ sơn vượt trội, chống trầy xước và giữ màu bóng lâu dài.', 'image' => $appointment, 'url' => '#bang-gia'],
                        ['title' => 'Hiệu chỉnh sơn', 'summary' => 'Loại bỏ vết xoáy và xước nhẹ, phục hồi độ bóng sâu.', 'image' => $hero, 'url' => '#bang-gia'],
                        ['title' => 'Dán phim bảo vệ', 'summary' => 'Bảo vệ toàn diện bề mặt sơn khỏi đá văng và tác nhân bên ngoài.', 'image' => $product, 'url' => '#bang-gia'],
                        ['title' => 'Vệ sinh nội thất', 'summary' => 'Làm sạch sâu, khử khuẩn và khử mùi toàn bộ khoang xe.', 'image' => $appointment, 'url' => '#bang-gia'],
                        ['title' => 'Chăm sóc khoang máy', 'summary' => 'Làm sạch chi tiết, bảo vệ và duy trì hiệu suất vận hành.', 'image' => $product, 'url' => '#bang-gia'],
                        ['title' => 'Rửa xe cao cấp', 'summary' => 'Công nghệ rửa hiện đại, an toàn tuyệt đối cho bề mặt sơn.', 'image' => $hero, 'url' => '#bang-gia'],
                    ]]],
                    'en' => ['title' => 'Complete care for every detail', 'subtitle' => 'Featured services', 'description' => 'Professional technicians and premium products for a flawless vehicle.', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'process_steps', 'label' => 'Quy trình chăm sóc xe',
                'description' => 'Năm bước do người dùng tự nhập và sắp xếp.', 'preview_image' => $appointment,
                'anchor_id' => 'quy-trinh', 'dynamic' => false, 'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => ['title' => 'Chăm sóc xe chuyên nghiệp', 'subtitle' => 'Quy trình', 'description' => 'Đúng quy trình – Đúng kỹ thuật – Đúng chất lượng', 'content' => ['items' => [
                        ['title' => 'Kiểm tra xe', 'description' => 'Kiểm tra tổng thể tình trạng xe và tư vấn dịch vụ phù hợp.', 'icon' => 'fa-solid fa-clipboard-check'],
                        ['title' => 'Tư vấn dịch vụ', 'description' => 'Tư vấn chi tiết gói dịch vụ và báo giá rõ ràng.', 'icon' => 'fa-regular fa-comments'],
                        ['title' => 'Thực hiện', 'description' => 'Chăm sóc xe theo quy trình chuẩn xác với sản phẩm chuyên dụng.', 'icon' => 'fa-solid fa-car-on'],
                        ['title' => 'Kiểm tra chất lượng', 'description' => 'Kiểm tra kỹ lưỡng trước khi bàn giao.', 'icon' => 'fa-solid fa-shield-halved'],
                        ['title' => 'Bàn giao xe', 'description' => 'Hướng dẫn bảo quản để duy trì hiệu quả lâu dài.', 'icon' => 'fa-solid fa-key'],
                    ]]],
                    'en' => ['title' => 'Professional car care', 'subtitle' => 'Process', 'description' => 'The right process, technique and quality.', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'collection_gallery', 'label' => 'Banner quảng cáo',
                'description' => 'Ảnh, tiêu đề, mô tả và liên kết do người dùng tự nhập.', 'preview_image' => $hero,
                'anchor_id' => 'uu-dai', 'dynamic' => false, 'settings' => ['source' => 'custom'],
                'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công']]]],
                'data' => [
                    'vi' => ['title' => 'Ưu đãi', 'subtitle' => 'Chương trình nổi bật', 'content' => ['items' => [
                        ['title' => 'Bảo vệ sơn toàn diện', 'summary' => 'Ưu đãi đến 10%', 'badge' => 'Dán phim bảo vệ', 'image' => $hero, 'url' => '#bang-gia'],
                        ['title' => 'Nâng tầm diện mạo xe', 'summary' => 'Ưu đãi đến 20%', 'badge' => 'Chăm sóc chuyên sâu', 'image' => $product, 'url' => '#bang-gia'],
                    ]]],
                    'en' => ['title' => 'Offers', 'subtitle' => 'Featured promotions', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'service_pricing', 'label' => 'Bảng giá dịch vụ',
                'description' => 'Gói giá và quyền lợi do người dùng tự nhập; nút đặt lịch mở modal.', 'preview_image' => $appointment,
                'anchor_id' => 'bang-gia', 'dynamic' => false, 'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => ['title' => 'Dịch vụ chăm sóc xe', 'subtitle' => 'Bảng giá', 'description' => 'Chăm sóc toàn diện – Bảo vệ tối ưu – Nâng tầm đẳng cấp xế yêu', 'content' => ['items' => [
                        ['title' => 'Rửa xe tiêu chuẩn', 'price' => '200.000đ', 'features' => "Rửa xe ngoại thất|Hút bụi nội thất|Lau chùi cơ bản|Dưỡng lốp", 'icon' => 'fa-solid fa-car-side'],
                        ['title' => 'Rửa xe cao cấp', 'price' => '400.000đ', 'features' => "Rửa xe chi tiết|Vệ sinh nội thất|Dưỡng lốp, phủ bóng|Khử mùi", 'icon' => 'fa-solid fa-spray-can-sparkles'],
                        ['title' => 'Phủ ceramic', 'price' => '4.500.000đ', 'features' => "Phủ ceramic cao cấp|Bảo vệ sơn xe|Kéo dài độ bóng|Hiệu chỉnh bề mặt", 'icon' => 'fa-solid fa-shield-halved', 'featured' => true],
                        ['title' => 'Vệ sinh nội thất', 'price' => '800.000đ', 'features' => "Vệ sinh chi tiết|Khử mùi, diệt khuẩn|Dưỡng da và nhựa|Vệ sinh trần xe", 'icon' => 'fa-solid fa-couch'],
                    ]]],
                    'en' => ['title' => 'Vehicle care services', 'subtitle' => 'Pricing', 'description' => 'Complete care and lasting protection.', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'business_service_grid', 'label' => 'Sản phẩm và dịch vụ',
                'description' => 'Nguồn Sản phẩm, Dịch vụ hoặc nhập thủ công; dịch vụ không hiện giá và mở modal đặt lịch.',
                'preview_image' => $product, 'anchor_id' => 'san-pham', 'dynamic' => true,
                'settings' => ['source' => 'custom'], 'settings_schema' => $sourceSchema($productSources),
                'data' => [
                    'vi' => ['title' => 'Sản phẩm chăm sóc xe', 'subtitle' => 'Sản phẩm nổi bật', 'description' => 'Giải pháp chăm sóc chuyên dụng, an toàn và hiệu quả cho từng bề mặt.', 'content' => ['items' => [
                        ['title' => 'Bộ chăm sóc ngoại thất', 'summary' => 'Dung dịch và phụ kiện làm sạch chuyên sâu.', 'price' => '680.000đ', 'image' => $product, 'url' => '#'],
                        ['title' => 'Dung dịch vệ sinh kính', 'summary' => 'Làm sạch nhanh, hạn chế bám nước.', 'price' => '180.000đ', 'image' => $product, 'url' => '#'],
                        ['title' => 'Bộ khăn microfiber', 'summary' => 'Mềm mịn, thấm hút tốt, an toàn cho sơn.', 'price' => '250.000đ', 'image' => $product, 'url' => '#'],
                        ['title' => 'Ceramic coating', 'summary' => 'Dịch vụ phủ bảo vệ sơn chuyên nghiệp.', 'type' => 'service', 'image' => $appointment, 'url' => '#bang-gia'],
                        ['title' => 'Bàn chải detailing', 'summary' => 'Vệ sinh các khe nhỏ và chi tiết khó tiếp cận.', 'price' => '120.000đ', 'image' => $product, 'url' => '#'],
                        ['title' => 'Vệ sinh nội thất', 'summary' => 'Dịch vụ làm sạch và khử khuẩn toàn diện.', 'type' => 'service', 'image' => $appointment, 'url' => '#bang-gia'],
                        ['title' => 'Pad đánh bóng', 'summary' => 'Hoàn thiện bề mặt sơn với độ bóng cao.', 'price' => '190.000đ', 'image' => $product, 'url' => '#'],
                        ['title' => 'Hiệu chỉnh sơn', 'summary' => 'Loại bỏ xoáy xước và phục hồi độ sâu màu.', 'type' => 'service', 'image' => $hero, 'url' => '#bang-gia'],
                    ]]],
                    'en' => ['title' => 'Car care products', 'subtitle' => 'Featured products', 'description' => 'Purpose-built products and services for every surface.', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'latest_posts', 'label' => 'Kiến thức và kinh nghiệm',
                'description' => 'Nguồn Tin tức, Dịch vụ, Dự án hoặc nhập thủ công.', 'preview_image' => $appointment,
                'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'custom'],
                'settings_schema' => $sourceSchema($insightSources),
                'data' => [
                    'vi' => ['title' => 'Kiến thức và kinh nghiệm chăm sóc xe', 'subtitle' => 'Tin mới nhất', 'description' => 'Thông tin hữu ích giúp bạn chăm sóc và bảo vệ xe luôn bền đẹp.', 'content' => ['items' => [
                        ['title' => 'Bảo dưỡng ô tô thế nào sau hành trình dài?', 'summary' => 'Những hạng mục nên kiểm tra để chiếc xe luôn an toàn và vận hành ổn định.', 'image' => $appointment, 'url' => route('site.blog.index')],
                        ['title' => 'Khi nào nên hiệu chỉnh bề mặt sơn?', 'summary' => 'Nhận biết các dấu hiệu xoáy xước và xuống màu.', 'image' => $hero, 'url' => route('site.blog.index')],
                        ['title' => 'Phủ ceramic có thực sự cần thiết?', 'summary' => 'Ưu điểm và cách chăm sóc lớp phủ đúng cách.', 'image' => $product, 'url' => route('site.blog.index')],
                        ['title' => 'Mẹo giữ nội thất xe luôn sạch', 'summary' => 'Những thói quen nhỏ giúp hạn chế mùi và bụi bẩn.', 'image' => $appointment, 'url' => route('site.blog.index')],
                    ]]],
                    'en' => ['title' => 'Car care knowledge and experience', 'subtitle' => 'Latest insights', 'description' => 'Useful guidance to keep your vehicle looking its best.', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'landing_contact', 'label' => 'Liên hệ tư vấn',
                'description' => 'Khối liên hệ nền tối với form gửi yêu cầu trực tiếp.', 'preview_image' => $hero,
                'anchor_id' => 'lien-he', 'dynamic' => false, 'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => ['title' => 'Sẵn sàng nâng tầm chiếc xe của bạn?', 'subtitle' => 'Liên hệ với chúng tôi', 'description' => 'Để lại thông tin, đội ngũ SER102 sẽ tư vấn gói chăm sóc phù hợp với tình trạng và nhu cầu thực tế.', 'button_label' => 'Gửi yêu cầu', 'content' => ['form_title' => 'Nhận tư vấn miễn phí']],
                    'en' => ['title' => 'Ready to elevate your vehicle?', 'subtitle' => 'Contact us', 'description' => 'Leave your details and our team will recommend the right care package.', 'button_label' => 'Send request', 'content' => []],
                ],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function spa502DefaultBlocks(): array
    {
        $categorySources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_categories', 'label' => 'Danh mục tin tức'],
        ];

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

        $categorySchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $categorySources],
            'limit' => ['type' => 'number', 'label' => 'Số item hiển thị'],
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
                'label' => 'Header và hero slider SPA502',
                'description' => 'Header, menu và banner hình ảnh chạy ở đầu trang.',
                'preview_image' => '/theme-previews/SPA502/preview-spa502.png',
                'anchor_id' => 'trang-chu',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'spa502-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                    'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Viện thẩm mỹ Cosmetics',
                        'subtitle' => 'Bùng nổ khai trương cơ sở mới',
                        'description' => 'Giảm giá 50% tất cả các dịch vụ',
                        'button_label' => 'Xem thêm',
                        'content' => ['slides' => [
                            ['title' => 'Viện thẩm mỹ Cosmetics', 'kicker' => 'Bùng nổ khai trương cơ sở mới', 'summary' => 'Giảm giá 50% tất cả các dịch vụ', 'button_label' => 'Xem thêm', 'link_url' => '#dich-vu', 'image' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?auto=format&fit=crop&w=2200&q=88'],
                            ['title' => 'Spa thư giãn chuyên sâu', 'kicker' => 'Chăm sóc sắc đẹp', 'summary' => 'Trải nghiệm liệu trình chăm sóc da và cơ thể chuẩn spa.', 'button_label' => 'Đặt lịch ngay', 'link_url' => '#lien-he', 'image' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=2200&q=88'],
                        ]],
                    ],
                    'en' => ['title' => 'Cosmetics Beauty Spa', 'subtitle' => 'Grand opening offer', 'description' => 'Up to 50% off selected services', 'button_label' => 'View more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Dịch vụ đa dạng',
                'description' => 'Danh mục dịch vụ/sản phẩm/tin tức hoặc nhập thủ công, hiển thị dạng ảnh tròn.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_service_categories', 'limit' => 5],
                'settings_schema' => $categorySchema,
                'data' => [
                    'vi' => [
                        'title' => 'Dịch Vụ Đa Dạng',
                        'subtitle' => 'Toàn bộ dịch vụ sản phẩm',
                        'content' => ['items' => [
                            ['title' => 'Trị liệu da', 'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=700&q=85', 'url' => '#san-pham'],
                            ['title' => 'Tóc & Làm đẹp', 'image' => 'https://images.unsplash.com/photo-1522337660859-02fbefca4702?auto=format&fit=crop&w=700&q=85', 'url' => '#san-pham'],
                            ['title' => 'Tẩy lông', 'badge' => '50% Off', 'image' => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?auto=format&fit=crop&w=700&q=85', 'url' => '#uu-dai'],
                            ['title' => 'Thư giãn cơ thể', 'image' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=700&q=85', 'url' => '#san-pham'],
                            ['title' => 'Tắm thảo dược', 'image' => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=700&q=85', 'url' => '#san-pham'],
                        ]],
                    ],
                    'en' => ['title' => 'Diverse Services', 'subtitle' => 'Spa and beauty categories', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'collection_gallery',
                'label' => 'Gói ưu đãi đặc biệt',
                'description' => 'Người dùng tự nhập ảnh khuyến mại và link đích.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
                'anchor_id' => 'uu-dai',
                'dynamic' => false,
                'settings' => ['source' => 'custom', 'limit' => 4],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công']]],
                    'limit' => ['type' => 'number', 'label' => 'Số banner'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Gói Ưu Đãi Đặc Biệt',
                        'subtitle' => 'Hãy đến và trải nghiệm ngay hôm nay!',
                        'content' => ['items' => [
                            ['title' => 'Trị hôi nách', 'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=800&q=85', 'url' => '#lien-he'],
                            ['title' => 'Điều trị mụn', 'image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=800&q=85', 'url' => '#lien-he'],
                            ['title' => 'Sinh nhật vui khui quà', 'image' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?auto=format&fit=crop&w=800&q=85', 'url' => '#lien-he'],
                            ['title' => 'Đón sinh nhật sang', 'image' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?auto=format&fit=crop&w=800&q=85', 'url' => '#lien-he'],
                        ]],
                    ],
                    'en' => ['title' => 'Special Offers', 'subtitle' => 'Visit us and experience today', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_products',
                'label' => 'Sản phẩm',
                'description' => 'Lưới sản phẩm hoặc dữ liệu lấy từ nhiều nguồn.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
                'anchor_id' => 'san-pham',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 8, 'featured_only' => true],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Sản Phẩm',
                        'subtitle' => 'Đảm bảo chất lượng số 1 Việt Nam',
                        'content' => ['items' => [
                            ['title' => 'Gel Phục Hồi Hàng Rào Bảo Vệ Da', 'price' => '2.300.000đ', 'rating' => 0, 'tags' => ['Mới', 'Bán chạy'], 'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'SEPTONA Care Cotton Pads', 'price' => '50.000đ', 'old_price' => '100.000đ', 'sale_label' => '- 50%', 'rating' => 0, 'tags' => ['Bán chạy'], 'image' => 'https://images.unsplash.com/photo-1608248597279-f99d160bfcbc?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Priori The Recovery Kit', 'price' => '1.500.000đ', 'rating' => 5, 'tags' => [], 'image' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Balance Active Formula Hyaluronic', 'price' => '210.000đ', 'rating' => 0, 'tags' => ['Mới', 'Bán chạy'], 'image' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'RevitaBrow Advanced Serum', 'price' => '2.700.000đ', 'rating' => 0, 'tags' => ['Bán chạy'], 'image' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Combo ASAP Cải Thiện Da Dầu Mụn', 'price' => '5.500.000đ', 'rating' => 0, 'tags' => ['Mới'], 'image' => 'https://images.unsplash.com/photo-1612817288484-6f916006741a?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Endorecare Expert Drops', 'price' => '1.700.000đ', 'rating' => 0, 'tags' => ['Mới'], 'image' => 'https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Tinh Chất Dưỡng Ẩm Kích Thích Collagen', 'price' => '2.600.000đ', 'rating' => 0, 'tags' => [], 'image' => 'https://images.unsplash.com/photo-1620916297397-a4a5402a3c6c?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                        ]],
                    ],
                    'en' => ['title' => 'Products', 'subtitle' => 'Premium cosmetic products', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Deal cực hấp dẫn',
                'description' => 'Khu sản phẩm/deal nền tím, nguồn dữ liệu có thể đổi linh hoạt.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
                'anchor_id' => 'deal',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => $sourceSchema,
                'media' => ['background_image' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?auto=format&fit=crop&w=1800&q=80'],
                'data' => [
                    'vi' => [
                        'title' => 'Deal Cực Hấp Dẫn',
                        'subtitle' => 'Chương trình giảm giá:',
                        'description' => 'Expired',
                        'content' => ['items' => [
                            ['title' => 'Minerals Fx350 Uber Finishing', 'price' => '1.300.000đ', 'old_price' => '1.600.000đ', 'sale_label' => '- 19%', 'sold_label' => '10 sản phẩm', 'rating' => 0, 'tags' => ['Bán chạy'], 'image' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Priori Q+SOD Brightening Serum', 'price' => '2.250.000đ', 'old_price' => '3.000.000đ', 'sale_label' => '- 25%', 'sold_label' => '46 sản phẩm', 'rating' => 0, 'tags' => ['Mới'], 'image' => 'https://images.unsplash.com/photo-1598440947619-2c35fc9aa908?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Dermedic HYDRAIN3 Hialuro Creamy', 'price' => '530.000đ', 'old_price' => '800.000đ', 'sale_label' => '- 34%', 'sold_label' => '5 sản phẩm', 'rating' => 0, 'tags' => ['Mới'], 'image' => 'https://images.unsplash.com/photo-1601049541289-9b1b7bbbfe19?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                            ['title' => 'Balance Active Formula Vitamin C', 'price' => '250.000đ', 'old_price' => '360.000đ', 'sale_label' => '- 31%', 'sold_label' => '10 sản phẩm', 'rating' => 0, 'tags' => ['Mới', 'Bán chạy'], 'image' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=800&q=85', 'url' => '#'],
                        ]],
                    ],
                    'en' => ['title' => 'Hot Deals', 'subtitle' => 'Promotion program', 'description' => 'Expired', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'content_showcase',
                'label' => 'Giới thiệu sản phẩm',
                'description' => 'Khối giới thiệu/chia sẻ ngắn, người dùng tự nhập.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
                'anchor_id' => 'gioi-thieu',
                'dynamic' => false,
                'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => [
                        'title' => 'Kem Dưỡng Da Dịu Nhẹ',
                        'subtitle' => 'Biến đổi tình trạng da',
                        'description' => 'Nhắc đến top kem dưỡng ẩm tốt và được yêu thích hiện nay, sản phẩm cấp ẩm dịu nhẹ luôn góp mặt trong chu trình chăm sóc da sau liệu trình thẩm mỹ.',
                        'content' => [
                            'heading' => 'Kem dưỡng da - Có tác dụng rất nhanh',
                            'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=1000&q=85',
                            'items' => [
                                ['title' => 'Được tạo từ thảo mộc tự nhiên', 'icon' => 'fa-solid fa-leaf'],
                                ['title' => 'An toàn 100% cho làn da của bạn', 'icon' => 'fa-solid fa-heart'],
                                ['title' => 'Không chứa paraben và cồn', 'icon' => 'fa-solid fa-shield-halved'],
                                ['title' => 'Quà tặng và ưu đãi đặc biệt dành cho bạn', 'icon' => 'fa-solid fa-gift'],
                                ['title' => 'Được tạo bởi các chuyên gia y tế của Halu Cosmetics', 'icon' => 'fa-solid fa-user-doctor'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Gentle Face Cream', 'subtitle' => 'Transform your skin', 'description' => 'A gentle moisturizer for healthier, calmer skin.', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'latest_posts',
                'label' => 'Tin tức',
                'description' => 'Tin tức hoặc dữ liệu động từ nhiều nguồn theo cấu hình.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false],
                'settings_schema' => $sourceSchema,
                'data' => [
                    'vi' => [
                        'title' => 'Tin Tức',
                        'subtitle' => 'Những bài viết mới nhất',
                        'content' => ['items' => [
                            ['title' => 'Review GoodnDoc Hydra B5 Serum - Có thực sự gây sốt?', 'summary' => 'Sản phẩm được quan tâm và bàn luận sôi nổi trên các diễn đàn làm đẹp.', 'date' => '30/10/2023', 'image' => 'https://images.unsplash.com/photo-1608248543803-ba4f8c70ae0b?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                            ['title' => 'Top 5 kem chống nắng tốt nhất dành cho da', 'summary' => 'Bảo vệ da khỏi tia UV và ánh sáng xanh là một điều cực kỳ quan trọng.', 'date' => '30/10/2023', 'image' => 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                            ['title' => 'Điểm mặt các thành phần dưỡng da mà ai cũng nên có', 'summary' => 'Lựa chọn đúng thành phần giúp quá trình chăm sóc da hiệu quả hơn.', 'date' => '30/10/2023', 'image' => 'https://images.unsplash.com/photo-1556228578-8c89e6adf883?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                            ['title' => 'Gallinée - Chìa khóa đánh thức nét đẹp tươi sáng', 'summary' => 'Nguồn lợi khuẩn khỏe mạnh là bí quyết cho một làn da căng tràn sức sống.', 'date' => '30/10/2023', 'image' => 'https://images.unsplash.com/photo-1620916566398-39f1143ab7be?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
                        ]],
                    ],
                    'en' => ['title' => 'News', 'subtitle' => 'Latest articles', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'partner_logos',
                'label' => 'Đối tác',
                'description' => 'Logo đối tác lấy từ CMS Partners hoặc nhập thủ công.',
                'preview_image' => '/theme-previews/SPA502/cover-spa502.png',
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
                            ['name' => 'BEAUTY'],
                            ['name' => 'INFINITY WELLNESS'],
                            ['name' => 'MANDALA HOTEL SPA'],
                            ['name' => 'VAIJACH'],
                            ['name' => 'Beauty Salon'],
                            ['name' => 'BEAUTY'],
                        ]],
                    ],
                    'en' => ['title' => 'Partners', 'content' => ['items' => []]],
                ],
            ],
        ];
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
    private function dn302DefaultBlocks(): array
    {
        $preview = '/theme-previews/DN302/preview-dn302.png';
        $living = '/theme-demo/dn302/dn302-living-room.png';
        $villa = '/theme-demo/dn302/dn302-villa.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $sourceSchema = fn (string $source, string $label): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => $source, 'label' => $label], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số lượng', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật', 'default' => false],
        ];
        $serviceSourceSchema = $sourceSchema('cms_services', 'Dịch vụ');
        $serviceSourceSchema['source']['options'] = [
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_service_categories', 'label' => 'Danh mục dịch vụ'],
            ['value' => 'custom', 'label' => 'Nội dung tùy chỉnh'],
        ];

        $services = [
            ['title' => 'Cửa trượt quay', 'summary' => 'Hệ cửa nhôm có công năng ưu việt, vận hành linh hoạt và phù hợp nhiều loại công trình.', 'image' => $living, 'url' => '#lien-he'],
            ['title' => 'Cửa nhôm lùa 1 cánh', 'summary' => 'Giải pháp tối ưu cho không gian hẹp, cần tiết kiệm diện tích và đón sáng tự nhiên.', 'image' => $villa, 'url' => '#lien-he'],
            ['title' => 'Cửa nhôm lùa 2 cánh đẹp', 'summary' => 'Vận hành êm, thẩm mỹ tối giản và bền bỉ trong điều kiện thời tiết Việt Nam.', 'image' => $living, 'url' => '#lien-he'],
        ];
        $features = [
            ['title' => 'Cách nhiệt', 'icon' => 'fa-solid fa-temperature-half'],
            ['title' => 'Chịu lực tốt', 'icon' => 'fa-regular fa-window-restore'],
            ['title' => 'Không phai mờ', 'icon' => 'fa-solid fa-sun'],
            ['title' => 'Nhẹ', 'icon' => 'fa-solid fa-feather-pointed'],
            ['title' => 'Dễ lắp đặt', 'icon' => 'fa-solid fa-screwdriver-wrench'],
            ['title' => 'Nhiều màu sắc', 'icon' => 'fa-solid fa-palette'],
        ];
        $projects = [
            ['title' => 'Biệt thự Mojec', 'summary' => 'Hệ cửa kính mở rộng tầm nhìn và kết nối sân vườn.', 'image' => $villa, 'url' => '#lien-he'],
            ['title' => 'Tropical Green House', 'summary' => 'Cửa nhôm kính đồng bộ cho không gian nghỉ dưỡng.', 'image' => $living, 'url' => '#lien-he'],
            ['title' => 'Nhà phố hiện đại', 'summary' => 'Thi công hệ mặt dựng kính và cửa ra vào cao cấp.', 'image' => $villa, 'url' => '#lien-he'],
            ['title' => 'Ban công xanh', 'summary' => 'Giải pháp mở linh hoạt cho không gian sống.', 'image' => $living, 'url' => '#lien-he'],
        ];
        $doorStyles = [
            ['title' => 'Kiểu mái vòm', 'summary' => 'Đường nét mềm mại tạo điểm nhấn sang trọng cho mặt tiền và không gian đón khách.', 'icon' => 'fa-solid fa-archway', 'url' => '#lien-he'],
            ['title' => 'Kiểu lưới', 'summary' => 'Thiết kế thông thoáng, bền chắc và phù hợp với nhiều phong cách kiến trúc hiện đại.', 'icon' => 'fa-solid fa-border-all', 'url' => '#lien-he'],
            ['title' => 'Kiểu 1 cánh', 'summary' => 'Giải pháp gọn gàng cho cửa phòng, cửa phụ và những khu vực có diện tích vừa phải.', 'icon' => 'fa-solid fa-door-closed', 'url' => '#lien-he'],
            ['title' => 'Kiểu 2 cánh', 'summary' => 'Tăng độ mở, lấy sáng tốt và tạo cảm giác rộng rãi cho lối vào chính của công trình.', 'icon' => 'fa-solid fa-door-open', 'url' => '#lien-he'],
        ];
        $testimonials = [
            ['name' => 'Lê Hoàng Bảo', 'role' => 'Chủ nhà', 'quote' => 'Sản phẩm sử dụng tuyệt vời, gia đình và bạn bè đều đánh giá cao sự lựa chọn của tôi.', 'image' => $villa],
            ['name' => 'Hứa Thị Quỳnh', 'role' => 'Khách hàng', 'quote' => 'Đội ngũ phục vụ tận tâm, tư vấn rõ ràng và hoàn thiện đúng tiến độ đã cam kết.', 'image' => $living],
        ];
        $posts = [
            ['title' => 'Ứng dụng đa dạng của cửa nhôm Xingfa trong cuộc sống', 'summary' => 'Tìm hiểu những ứng dụng phổ biến và cách chọn hệ cửa phù hợp.', 'date' => '15/04/2022', 'image' => $living, 'url' => '#'],
            ['title' => 'Top những mẫu cửa sổ nhôm kính đẹp mới nhất', 'summary' => 'Các xu hướng thiết kế giúp công trình thông thoáng và hiện đại.', 'date' => '15/04/2022', 'image' => $villa, 'url' => '#'],
            ['title' => 'Kinh nghiệm lựa chọn cửa nhôm kính cho ngôi nhà', 'summary' => 'Những tiêu chí về profile, kính, phụ kiện và kỹ thuật lắp đặt.', 'date' => '15/04/2022', 'image' => $living, 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Janelas', 'description' => 'Header và slider ảnh lớn đầu trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'dn302-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Vị trí banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Thi công lắp đặt các loại cửa dân dụng', 'Cung cấp giải pháp trọn gói', 'Cung cấp cửa sổ, cửa ra vào bằng nhôm kính an toàn, tiện nghi và thân thiện với môi trường.', 'Tìm hiểu ngay'), ['content' => ['slides' => [['title' => 'Thi công lắp đặt các loại cửa dân dụng', 'summary' => 'Giải pháp cửa nhôm kính đồng bộ cho nhà ở hiện đại.', 'image' => $living], ['title' => 'Kiến tạo không gian mở', 'summary' => 'Thẩm mỹ, bền vững và tối ưu ánh sáng tự nhiên.', 'image' => $villa]]]]), 'en' => $heading('Premium windows and doors', 'Complete solutions')]],
            ['block_type' => 'about_experience', 'label' => 'Giới thiệu Janelas', 'description' => 'Giới thiệu và bốn giá trị thương hiệu.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => [], 'media' => ['image' => $living], 'data' => ['vi' => $withItems($heading('Tạo nên không gian hiện đại cho ngôi nhà của bạn', 'Giới thiệu', 'Với nhiều năm kinh nghiệm, chúng tôi luôn tôn trọng khách hàng, giữ vững uy tín và mang đến các giải pháp nhôm kính bền vững.'), array_map(fn ($title, $url) => ['title' => $title, 'url' => $url], ['Chất lượng', 'Tiến bộ', 'Uy tín', 'Chuyên nghiệp'], ['#dich-vu', '#san-pham', '#du-an', '#lien-he'])), 'en' => $withItems($heading('Modern spaces for your home', 'About us'), array_map(fn ($title, $url) => ['title' => $title, 'url' => $url], ['Quality', 'Progress', 'Prestige', 'Professional'], ['#dich-vu', '#san-pham', '#du-an', '#lien-he']))]],
            ['block_type' => 'featured_services', 'label' => 'Dịch vụ cửa nhôm kính', 'description' => 'Dịch vụ động từ CMS hoặc nhập thủ công.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $serviceSourceSchema, 'data' => ['vi' => $withItems($heading('Dịch vụ của chúng tôi', 'Nổi bật', 'Chất lượng là nền tảng thành công. Chúng tôi cam kết sản phẩm chuẩn xác, dịch vụ chuyên nghiệp và bảo hành dài hạn.'), $services), 'en' => $heading('Our services', 'Featured')]],
            ['block_type' => 'featured_categories', 'label' => 'Ưu điểm cửa kính', 'description' => 'Sáu ưu điểm của hệ cửa kính cao cấp.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'settings' => ['source' => 'custom', 'limit' => 6], 'media' => ['image' => $living], 'data' => ['vi' => $withItems($heading('Cửa kính cao cấp', 'Khám phá', 'Sản xuất từ nhôm nhập khẩu, kính cường lực, gioăng cao su và bộ phụ kiện kim khí đồng bộ.'), $features), 'en' => $heading('Premium glass doors', 'Explore')]],
            ['block_type' => 'project_gallery', 'label' => 'Dự án hoàn thành', 'description' => 'Dự án động theo điều kiện lọc hoặc nhập thủ công.', 'preview_image' => $preview, 'anchor_id' => 'du-an', 'dynamic' => true, 'settings' => ['source' => 'cms_projects', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_projects', 'Dự án CMS'), 'data' => ['vi' => $withItems($heading('Hoàn thành', 'Dự án'), $projects), 'en' => $heading('Completed projects', 'Projects')]],
            ['block_type' => 'content_showcase', 'label' => 'Các kiểu cửa chính', 'description' => 'Bốn kiểu cửa và ảnh giới thiệu.', 'preview_image' => $preview, 'anchor_id' => 'kieu-cua', 'settings' => ['source' => 'custom', 'limit' => 4], 'media' => ['image' => $living], 'data' => ['vi' => $withItems($heading('Các kiểu cửa chính', 'Lắp đặt', 'Cửa ra vào, cửa sổ, cửa cuốn, cửa kéo, mái tôn, mái che và mái hiên di động'), $doorStyles), 'en' => $heading('Main door styles', 'Installation')]],
            ['block_type' => 'newsletter_signup', 'label' => 'Đăng ký nhận tin', 'description' => 'Form đăng ký email.', 'preview_image' => $preview, 'anchor_id' => 'dang-ky', 'settings' => [], 'data' => ['vi' => $heading('Đăng ký nhận bản tin và tin tức cập nhật mới nhất', 'Đăng ký'), 'en' => $heading('Subscribe for our latest updates', 'Newsletter')]],
            ['block_type' => 'testimonials', 'label' => 'Khách hàng nhận xét', 'description' => 'Đánh giá khách hàng và chỉ số kinh nghiệm.', 'preview_image' => $preview, 'anchor_id' => 'khach-hang', 'settings' => ['source' => 'custom', 'limit' => 2], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_testimonials', 'label' => 'Đánh giá CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đánh giá']], 'data' => ['vi' => $withItems($heading('Khách hàng', 'Nhận xét từ', 'Cửa nhôm kính đẹp cần vật liệu đạt chuẩn, phụ kiện đồng bộ chính xác và nhân viên lắp đặt có quy trình.'), $testimonials), 'en' => $heading('Clients', 'Testimonials')]],
            ['block_type' => 'latest_posts', 'label' => 'Kiến thức & Kinh nghiệm', 'description' => 'Bài viết động theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_posts', 'Tin tức CMS'), 'data' => ['vi' => $withItems($heading('Kiến thức & Kinh nghiệm', 'Tin tức cập nhật'), $posts), 'en' => $heading('Knowledge & Experience', 'Latest news')]],
            ['block_type' => 'landing_contact', 'label' => 'Đăng ký tư vấn', 'description' => 'Form tư vấn và thông tin liên hệ.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => [], 'media' => ['image' => $living], 'data' => ['vi' => $heading('Đăng ký tư vấn dịch vụ', 'Liên hệ'), 'en' => $heading('Book a consultation', 'Contact')]],
            ['block_type' => 'partner_logos', 'label' => 'Logo đối tác', 'description' => 'Đối tác CMS hoặc nhập thủ công.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 10], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_partners', 'label' => 'Đối tác CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => $withItems($heading('Đối tác của chúng tôi'), array_map(fn ($name) => ['title' => $name], ['SMALLHOUSE', 'GARDENHOUSE', 'STARTUP HAUS', 'REALHOUSE', 'HOUSESMART'])), 'en' => $heading('Our partners')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0325DefaultBlocks(): array
    {
        $preview = '/theme-previews/XD0325/preview-xd0325.png';
        $hero = '/theme-demo/xd0325/hero-construction.png';
        $interior = '/theme-demo/curated/home/bathroom/bathroom-02.jpg';
        $product = '/theme-demo/xd0325/safety-products.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $items = fn (array $values): array => ['content' => ['items' => $values]];
        $sourceSchema = fn (string $source, string $label): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => $source, 'label' => $label], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số lượng', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật', 'default' => false],
        ];

        $services = [
            ['title' => 'Kiến trúc xây dựng', 'summary' => 'Thiết kế sáng tạo và tính toán toàn vẹn kết cấu, tạo ra không gian chức năng và đẹp mắt.', 'image' => $hero, 'icon' => 'fa-solid fa-building', 'url' => '#lien-he'],
            ['title' => 'Lắp đặt sàn gỗ', 'summary' => 'Vật liệu chất lượng cao và tay nghề chính xác mang lại vẻ đẹp bền vững.', 'image' => $interior, 'icon' => 'fa-solid fa-layer-group', 'url' => '#lien-he'],
            ['title' => 'Thiết kế nội thất', 'summary' => 'Biến đổi không gian bằng khái niệm sáng tạo, thẩm mỹ và bố cục công năng.', 'image' => $interior, 'icon' => 'fa-solid fa-pen-ruler', 'url' => '#lien-he'],
            ['title' => 'Bảo trì công trình', 'summary' => 'Làm mới công trình bằng tư duy hiện đại, nâng cao sự thoải mái và giá trị.', 'image' => $hero, 'icon' => 'fa-solid fa-hammer', 'url' => '#lien-he'],
        ];
        $projects = [
            ['title' => 'Thiết kế nội thất biệt thự Mùa Xuân', 'summary' => 'Chủ đầu tư: Bean Company · Quy mô: 1000m² · Đắk Lắk', 'image' => $interior, 'url' => '#lien-he'],
            ['title' => 'Tòa nhà văn phòng Bean Center', 'summary' => 'Thiết kế và thi công trọn gói theo tiêu chuẩn hiện đại.', 'image' => $hero, 'url' => '#lien-he'],
            ['title' => 'Căn hộ cao cấp Bình Dương', 'summary' => 'Nội thất tối giản, tinh tế và tối ưu công năng.', 'image' => '/theme-demo/curated/home/bathroom/bathroom-03.jpg', 'url' => '#lien-he'],
        ];
        $why = [
            ['title' => 'Giải pháp hiện đại', 'summary' => 'Công nghệ và phương pháp thi công tiên tiến, tối ưu chi phí và thời gian.'],
            ['title' => 'Đội ngũ chuyên nghiệp', 'summary' => 'Kiến trúc sư, kỹ sư và công nhân lành nghề, tận tâm với nghề.'],
            ['title' => 'Kinh nghiệm lâu năm', 'summary' => 'Mang đến những công trình chất lượng, bền vững và đúng tiến độ.'],
            ['title' => 'Cam kết lâu dài', 'summary' => 'Mỗi công trình là lời hứa về sự an toàn và giá trị sử dụng lâu dài.'],
        ];
        $team = [
            ['name' => 'Hoàng Quân', 'title' => 'Hoàng Quân', 'role' => 'Kiến trúc sư', 'image' => $hero],
            ['name' => 'Quang Hiệp', 'title' => 'Quang Hiệp', 'role' => 'Kỹ sư xây dựng', 'image' => $hero],
            ['name' => 'Văn Mạnh', 'title' => 'Văn Mạnh', 'role' => 'Kỹ sư công trình', 'image' => $hero],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Bean Construction', 'description' => 'Slider ảnh lớn đầu trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'settings' => ['autoplay_ms' => 6000], 'settings_schema' => ['autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Kiến Tạo Tương Lai Cùng Bean Construction', 'Bean Construction', 'Chúng tôi đồng hành cùng bạn trong mọi công trình, mang đến giải pháp xây dựng bền vững và hiện đại.', 'Xem thêm'), ['content' => ['slides' => [['title' => 'Kiến Tạo Tương Lai Cùng Bean Construction', 'summary' => 'Giải pháp xây dựng bền vững và hiện đại.', 'image' => $hero]]]]), 'en' => $heading('Building the future with Bean Construction')]],
            ['block_type' => 'about_experience', 'label' => 'Về Bean Construction', 'description' => 'Giới thiệu, ảnh ghép và cam kết.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => [], 'data' => ['vi' => array_merge($heading('Về Bean Construction', 'Chúng tôi là ai?', 'Chuyên thi công và phát triển công trình uy tín, cung cấp giải pháp xây dựng hiện đại, bền vững và tối ưu chi phí.', 'Xem chi tiết'), $items([['title' => 'Phân tích và đánh giá tính khả thi của dự án.'], ['title' => 'Cam kết chất lượng hài lòng 100% từ khách hàng.'], ['title' => 'Thiết kế phù hợp nhu cầu và mục tiêu công trình.'], ['title' => 'Giải pháp bền vững, tiết kiệm và thân thiện.']])), 'en' => $heading('About Bean Construction')], 'media' => ['images' => [$hero, $interior, $hero]]],
            ['block_type' => 'project_gallery', 'label' => 'Các dự án mới nhất', 'description' => 'Dự án động hoặc nội dung nhập tay, hiển thị dạng carousel ngang.', 'preview_image' => $preview, 'anchor_id' => 'du-an', 'dynamic' => true, 'settings' => ['source' => 'cms_projects', 'limit' => 6, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_projects', 'Dự án CMS'), 'data' => ['vi' => array_merge($heading('Các Dự Án Mới Nhất', 'Dự án của chúng tôi', 'Khám phá những công trình tiêu biểu Bean Construction đã và đang thực hiện.'), $items($projects)), 'en' => $heading('Latest projects')]],
            ['block_type' => 'featured_services', 'label' => 'Dịch vụ xây dựng', 'description' => 'Dịch vụ động hoặc nhập tay.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_services', 'Dịch vụ CMS'), 'data' => ['vi' => array_merge($heading('Chúng Tôi Cung Cấp Các Dịch Vụ', 'Dịch vụ của chúng tôi', 'Giải pháp xây dựng hiện đại, bền vững và tối ưu chi phí.'), $items($services)), 'en' => $heading('Our services')]],
            ['block_type' => 'featured_products', 'label' => 'Sản phẩm nổi bật', 'description' => 'Sản phẩm công trình theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false, 'fallback_image' => $product], 'settings_schema' => $sourceSchema('cms_products', 'Sản phẩm'), 'data' => ['vi' => array_merge($heading('✦ Sản Phẩm Nổi Bật ✦', null, 'Đồ bảo hộ lao động, dụng cụ cầm tay và thiết bị an toàn công trình.'), $items([['title' => 'Găng tay chống dầu Safety', 'image' => $product, 'price' => 170000, 'original_price' => 175000], ['title' => 'Găng tay da hàn TIG', 'image' => $product, 'price' => 100000, 'original_price' => 120000], ['title' => 'Áo gile phản quang', 'image' => $product, 'price' => 150000, 'original_price' => 177000], ['title' => 'Áo vest an toàn công trường', 'image' => $product, 'price' => 225000, 'original_price' => 385000]])), 'en' => $heading('Featured products')]],
            ['block_type' => 'featured_categories', 'label' => 'Tại sao chọn chúng tôi', 'description' => 'Bốn lợi thế nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'ly-do', 'settings' => [], 'data' => ['vi' => array_merge($heading('Từ Ý Tưởng Đến Công Trình Hoàn Thiện', 'Tại sao chọn chúng tôi', 'Sự hợp tác chặt chẽ, kiến thức ngành và kinh nghiệm lâu năm mang lại sự hoàn hảo cho khách hàng.'), $items($why)), 'en' => $heading('Why choose us')], 'media' => ['image' => $hero]],
            ['block_type' => 'testimonials', 'label' => 'Phản hồi khách hàng', 'description' => 'Đánh giá khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'phan-hoi', 'settings' => ['source' => 'custom', 'limit' => 3], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_testimonials', 'label' => 'Đánh giá CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đánh giá']], 'data' => ['vi' => array_merge($heading('Khách Hàng Nói Gì?', 'Phản hồi từ khách hàng'), $items([['name' => 'Nguyễn Thảo – Hà Nội', 'role' => 'Chủ đầu tư', 'quote' => 'Bean Construction mang đến trải nghiệm chuyên nghiệp từ tư vấn đến thi công. Tiến độ và chất lượng công trình luôn được đảm bảo.']])), 'en' => $heading('What clients say')]],
            ['block_type' => 'team_members', 'label' => 'Đội ngũ chuyên nghiệp', 'description' => 'Nhân sự động hoặc nhập tay.', 'preview_image' => $preview, 'anchor_id' => 'doi-ngu', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 3], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_team_members', 'label' => 'Nhân sự CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số nhân sự']], 'data' => ['vi' => array_merge($heading('Đội Ngũ Nhân Viên Chuyên Nghiệp', 'Đội ngũ nhân viên', 'Đội ngũ có kiến thức chuyên môn, kinh nghiệm dày dạn và khả năng thích ứng linh hoạt.'), $items($team)), 'en' => $heading('Professional team')]],
            ['block_type' => 'faq_showcase', 'label' => 'Câu hỏi thường gặp', 'description' => 'Danh sách câu hỏi accordion.', 'preview_image' => $preview, 'anchor_id' => 'faq', 'settings' => [], 'data' => ['vi' => array_merge($heading('Giải Đáp Thắc Mắc Cùng Bean Construction', 'Câu hỏi thường gặp', 'Nếu chưa tìm thấy câu trả lời phù hợp, hãy liên hệ với chúng tôi.'), $items([['title' => 'Bean Construction có đảm bảo tiến độ thi công không?', 'summary' => 'Chúng tôi cam kết thi công đúng tiến độ đã thống nhất trong hợp đồng với quy trình kiểm soát chặt chẽ.'], ['title' => 'Công trình có được bảo hành sau khi hoàn thành không?', 'summary' => 'Mọi công trình đều có chính sách bảo hành minh bạch theo từng hạng mục.'], ['title' => 'Có hỗ trợ tư vấn thiết kế và lựa chọn vật liệu không?', 'summary' => 'Đội ngũ kiến trúc sư hỗ trợ trọn gói từ ý tưởng đến vật liệu hoàn thiện.']])), 'en' => $heading('Frequently asked questions')]],
            ['block_type' => 'process_steps', 'label' => 'Quy trình thi công', 'description' => 'Bốn bước từ yêu cầu đến thi công.', 'preview_image' => $preview, 'anchor_id' => 'quy-trinh', 'settings' => [], 'data' => ['vi' => array_merge($heading('Quy trình làm việc'), $items([['title' => 'Lấy yêu cầu', 'summary' => 'Tổng hợp yêu cầu và tư vấn giải pháp từ đội ngũ chuyên nghiệp.'], ['title' => 'Lên ý tưởng', 'summary' => 'Đưa ra ý tưởng và thiết kế tối ưu nhất.'], ['title' => 'Đưa giải pháp', 'summary' => 'Tư vấn giải pháp phù hợp cho từng yêu cầu.'], ['title' => 'Thi công ngay', 'summary' => 'Tiến hành thi công theo giải pháp đã thống nhất.']])), 'en' => $heading('Our process')]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức xây dựng', 'description' => 'Tin tức động theo bộ lọc.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false, 'fallback_image' => $interior], 'settings_schema' => $sourceSchema('cms_posts', 'Tin tức'), 'data' => ['vi' => $heading('Cập Nhật Những Tin Tức Xây Dựng', 'Tin tức mới nhất', 'Thông tin mới nhất về xu hướng, công nghệ và giải pháp trong lĩnh vực xây dựng.'), 'en' => $heading('Construction news')]],
            ['block_type' => 'landing_contact', 'label' => 'Liên hệ tư vấn', 'description' => 'Form liên hệ và hotline.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => [], 'data' => ['vi' => $heading('Liên Hệ Ngay Để Được Tư Vấn', 'Tư vấn miễn phí', 'Đội ngũ chuyên viên luôn sẵn sàng hỗ trợ và mang đến giải pháp phù hợp nhất.'), 'en' => $heading('Contact us for consultation')]],
            ['block_type' => 'partner_logos', 'label' => 'Đối tác lâu năm', 'description' => 'Đối tác CMS hoặc nhập tay.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 8], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_partners', 'label' => 'Đối tác CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => array_merge($heading('Đối Tác Lâu Năm Của Chúng Tôi', 'Đối tác chúng tôi', 'Đồng hành cùng nhiều đối tác uy tín, xây dựng quan hệ lâu dài dựa trên niềm tin và chất lượng.'), $items(array_map(fn ($name) => ['title' => $name], ['CC14', 'ELand', 'BuildMax', 'Blanco Construction', 'Athens Holding', 'Kim Gia Hưng', 'Đất Xanh Group', 'Thăng Long']))), 'en' => $heading('Long-term partners')]],
        ];
    }

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
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
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
                'description' => 'Header sáng có đăng nhập/đăng ký và banner xe tải.',
                'preview_image' => '/theme-previews/XD0318/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0318-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Vận chuyển mọi lúc mọi nơi',
                        'subtitle' => '',
                        'description' => 'Bạn lo ngại về chất lượng hàng hóa dưới thời tiết nắng mưa, nóng lạnh thất thường của Việt Nam?',
                        'button_label' => 'Xem thêm',
                        'content' => ['slides' => [
                            ['title' => 'Vận chuyển mọi lúc mọi nơi', 'summary' => 'Giải pháp vận chuyển an toàn trước điều kiện thời tiết thất thường tại Việt Nam.', 'button_label' => 'Xem thêm', 'image' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#gioi-thieu'],
                            ['title' => 'Giao nhận nhanh chóng, an toàn', 'summary' => 'Mạng lưới vận tải linh hoạt cho hàng hóa nội địa và quốc tế.', 'button_label' => 'Dịch vụ', 'image' => 'https://images.unsplash.com/photo-1519003722824-194d4455a60c?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Shipping anywhere, anytime', 'subtitle' => '', 'description' => 'Reliable logistics services for every shipment.', 'button_label' => 'Learn more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'bizmax_about',
                'label' => 'Giới thiệu Fast Gear',
                'description' => 'Khối giới thiệu 2 cột với ảnh tài xế giao hàng.',
                'preview_image' => '/theme-previews/XD0318/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#dich-vu'],
                'media' => ['image' => 'https://images.unsplash.com/photo-1580674285054-bed31e145f59?auto=format&fit=crop&w=1200&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Giải pháp logistics toàn cầu tốt nhất',
                        'subtitle' => 'Về chúng tôi',
                        'description' => "Công ty Fast Gear được thành lập bởi đội ngũ nhân viên có hơn 20 năm kinh nghiệm trong lĩnh vực giao nhận quốc tế, chuyên cung cấp các giải pháp logistics cho khách hàng.\nMạng lưới hoạt động của công ty trên toàn thế giới với 100 đối tác tại 50 quốc gia, trụ sở chính ở Việt Nam và văn phòng chi nhánh tại Hoa Kỳ.",
                        'button_label' => 'Xem thêm',
                        'content' => [],
                    ],
                    'en' => ['title' => 'Best global logistics solutions', 'subtitle' => 'About us', 'description' => 'Fast Gear provides reliable international forwarding and logistics solutions.', 'button_label' => 'More', 'content' => []],
                ],
            ],
            [
                'block_type' => 'logistics_feature_panel',
                'label' => 'Video logistics',
                'description' => 'Khối video nền cảng container.',
                'preview_image' => '/theme-previews/XD0318/video.png',
                'anchor_id' => 'video',
                'settings' => ['video_url' => '#video'],
                'media' => ['background' => 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => ['title' => 'Đối tác logistics toàn cầu của bạn', 'subtitle' => 'Về chúng tôi', 'description' => '', 'button_label' => '', 'content' => []],
                    'en' => ['title' => 'Global logistics partner for the world', 'subtitle' => 'About us', 'description' => '', 'button_label' => '', 'content' => []],
                ],
            ],
            [
                'block_type' => 'business_service_grid',
                'label' => 'Dịch vụ Fast Gear',
                'description' => 'Lưới dịch vụ logistics và thẻ nhận báo giá.',
                'preview_image' => '/theme-previews/XD0318/services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'media' => ['quote_background' => 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1000&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Giải pháp thực tế, nhanh chóng',
                        'subtitle' => 'Dịch vụ của chúng tôi',
                        'description' => '',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Vận chuyển hàng không', 'summary' => 'Đại lý bán cước và hợp đồng vận chuyển với nhiều hãng hàng không lớn trên thế giới, tần suất bay cao.', 'image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Vận chuyển vật liệu xây dựng', 'summary' => 'Dịch vụ vận chuyển vật liệu xây dựng chuyên nghiệp cho doanh nghiệp và công trình.', 'image' => 'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Vận chuyển nhà', 'summary' => 'Dịch vụ chuyển đồ đạc sang nhà mới nhanh gọn và bảo đảm an toàn.', 'image' => 'https://images.unsplash.com/photo-1600518464441-9154a4dea21b?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Vận chuyển thú cưng', 'summary' => 'Giải pháp đặc biệt cho khách hàng có nhu cầu gửi động vật cảnh.', 'image' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Dịch vụ Air Cargo', 'summary' => 'Vận chuyển hàng hóa bằng máy bay, phù hợp với đơn hàng cần tốc độ cao.', 'image' => 'https://images.unsplash.com/photo-1569154941061-e231b4725ef1?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Practical and fast solutions', 'subtitle' => 'Our services', 'description' => '', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'FAQ logistics',
                'description' => 'Giải đáp thắc mắc và hình ảnh kho vận.',
                'preview_image' => '/theme-previews/XD0318/faq.png',
                'anchor_id' => 'faq',
                'settings' => [],
                'media' => [
                    'image_one' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=900&q=85',
                    'image_two' => 'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?auto=format&fit=crop&w=900&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Giải đáp các thắc mắc về dịch vụ',
                        'subtitle' => 'Câu hỏi thường gặp',
                        'description' => 'Các câu trả lời giúp bạn có cái nhìn tổng quát về chúng tôi và hiểu rõ hơn những dịch vụ đang cung cấp.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['question' => 'Các tiêu chí đánh giá độ uy tín và chất lượng của công ty vận tải là gì?', 'answer' => 'Độ uy tín được đánh giá qua kinh nghiệm, mạng lưới đối tác, quy trình giao nhận và khả năng xử lý sự cố.'],
                            ['question' => 'Tiêu chuẩn chất lượng dịch vụ vận tải hành khách bằng ô tô như thế nào?', 'answer' => 'Dịch vụ cần bảo đảm an toàn, đúng lịch trình, xe đạt chuẩn và thông tin minh bạch.'],
                            ['question' => 'Các dịch vụ hỗ trợ vận tải đường bộ gồm những gì?', 'answer' => 'Bao gồm đóng gói, kho bãi, bốc xếp, theo dõi đơn hàng và giao nhận chặng cuối.'],
                        ]],
                    ],
                    'en' => ['title' => 'Answers to your service questions', 'subtitle' => 'FAQ', 'description' => 'Helpful answers about our logistics services.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'landing_contact',
                'label' => 'Yêu cầu gọi lại',
                'description' => 'Biểu mẫu liên hệ trên nền cảng container.',
                'preview_image' => '/theme-previews/XD0318/contact.png',
                'anchor_id' => 'lien-he',
                'settings' => [],
                'media' => ['background' => 'https://images.unsplash.com/photo-1494412685616-a5d310fbb07d?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Yêu cầu một cuộc gọi lại',
                        'subtitle' => 'Liên hệ',
                        'description' => 'Chỉ mất 30 giây để gửi yêu cầu. Chúng tôi sẽ gọi lại cho bạn từ thứ Hai đến thứ Sáu, 8 giờ sáng đến 5 giờ chiều.',
                        'button_label' => 'Gửi tin nhắn',
                        'content' => [],
                    ],
                    'en' => ['title' => 'Request a call back', 'subtitle' => 'Contact', 'description' => 'It takes 30 seconds and we will call you back during business hours.', 'button_label' => 'Send message', 'content' => []],
                ],
            ],
            [
                'block_type' => 'bizmax_latest_posts',
                'label' => 'Tin tức mới',
                'description' => 'Tin tức mới nhất dạng 3 cột.',
                'preview_image' => '/theme-previews/XD0318/news.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Đọc tin tức mới nhất của chúng tôi',
                        'subtitle' => 'Tin tức mới',
                        'description' => '',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Cổng thông tin hướng dẫn xuất nhập khẩu hàng hóa chính thức', 'summary' => 'Ngày 10/12 tại Hà Nội, cổng thông tin hướng dẫn xuất nhập khẩu hàng hóa được giới thiệu đến doanh nghiệp.', 'image' => 'https://images.unsplash.com/photo-1586528116493-a029325540fa?auto=format&fit=crop&w=900&q=85', 'date' => '24/03/2022', 'views' => 359],
                            ['title' => 'Kỳ vọng gì từ việc kết thúc thỏa thuận FTA giữa Việt Nam và Anh?', 'summary' => 'Biên bản kết thúc đàm phán thương mại tự do sẽ mở ra nhiều cơ hội cho ngành logistics.', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85', 'date' => '24/03/2022', 'views' => 320],
                            ['title' => 'Đằng sau con số xuất siêu hơn 20 tỷ USD', 'summary' => 'Con số xuất siêu là tin vui trong bối cảnh hiện nay và tạo động lực cho chuỗi cung ứng.', 'image' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?auto=format&fit=crop&w=900&q=85', 'date' => '24/03/2022', 'views' => 273],
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

    /** @return array<int, array<string, mixed>> */
    private function nt503DefaultBlocks(): array
    {
        $preview = '/theme-previews/NT503/preview-nt503.svg';
        $sourceSchema = fn (array $options, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $options],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục lọc'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật', 'default' => false],
        ];
        $products = [['value' => 'cms_products', 'label' => 'Sản phẩm']];
        $posts = [['value' => 'cms_posts', 'label' => 'Tin tức']];
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button,
        ];
        $items = fn (array $values): array => ['content' => ['items' => $values]];
        $fallbackProducts = [
            ['title' => 'Nệm foam Goodnight Eva gấp 3 nâng đỡ', 'image' => '/theme-demo/nt503/mattress.png', 'price' => 3099000, 'original_price' => 4599000, 'url' => '#'],
            ['title' => 'Nệm foam tổng hợp Erica Smart Tech', 'image' => '/theme-demo/nt503/mattress.png', 'price' => 4299000, 'original_price' => 4999000, 'url' => '#'],
            ['title' => 'Gối bông trẻ em Deepsleep Khủng Long', 'image' => '/theme-demo/nt503/kids-pillow.png', 'price' => 300000, 'original_price' => 400000, 'url' => '#'],
            ['title' => 'Bộ chăn ga Cotton Thảo Mộc', 'image' => '/theme-demo/nt503/bedding.png', 'price' => 1290000, 'original_price' => 1690000, 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero WolfBed', 'description' => 'Slider lớn đầu trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'nt503-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy (ms)']], 'data' => ['vi' => array_merge($heading('WolfBed CUTIE', null, 'Êm dịu và nâng niu giấc mơ của con', 'Xem thêm sản phẩm'), ['content' => ['slides' => [['title' => 'WolfBed CUTIE', 'summary' => 'Êm dịu và nâng niu giấc mơ của con', 'button_label' => 'Xem thêm sản phẩm', 'image' => '/theme-demo/nt503/hero-wolfbed.png', 'link_url' => '#san-pham']]]]), 'en' => $heading('WolfBed CUTIE', null, 'Gentle comfort for every little dream.', 'Explore products')]],
            ['block_type' => 'nt503_categories', 'label' => 'Danh mục giấc ngủ', 'description' => 'Mười danh mục sản phẩm dạng ảnh.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 10], 'settings_schema' => $sourceSchema([['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm']], 10), 'data' => ['vi' => array_merge($heading('Trọn bộ sản phẩm cho giấc ngủ của bạn'), $items([])), 'en' => $heading('Everything for your best sleep')]],
            ['block_type' => 'nt503_mattresses', 'label' => 'Nệm êm giá mềm', 'description' => 'Lưới sản phẩm nệm nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'nem-em', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $sourceSchema($products, 4), 'data' => ['vi' => array_merge($heading('Nệm êm giá mềm'), $items($fallbackProducts)), 'en' => $heading('Soft mattresses, gentle prices')]],
            ['block_type' => 'nt503_promo_banners', 'label' => 'Banner đôi thiên nhiên', 'description' => 'Hai banner quảng bá chăn ga và gối.', 'preview_image' => $preview, 'anchor_id' => 'banner-thien-nhien', 'settings' => ['image_left' => '/theme-demo/nt503/bedding.png', 'image_right' => '/theme-demo/nt503/kids-pillow.png'], 'settings_schema' => ['image_left' => ['type' => 'text', 'label' => 'Ảnh trái'], 'image_right' => ['type' => 'text', 'label' => 'Ảnh phải']], 'data' => ['vi' => $heading('Giấc ngủ từ thiên nhiên'), 'en' => $heading('Sleep inspired by nature')]],
            ['block_type' => 'nt503_flash_sale', 'label' => 'Flash Sale', 'description' => 'Khối sản phẩm ưu đãi nền xanh.', 'preview_image' => $preview, 'anchor_id' => 'flash-sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => true], 'settings_schema' => $sourceSchema($products, 5), 'data' => ['vi' => array_merge($heading('Giá tốt, Ưu đãi khủng'), $items($fallbackProducts)), 'en' => $heading('Great prices, huge savings')]],
            ['block_type' => 'nt503_kids_collection', 'label' => 'Bộ sưu tập trẻ em', 'description' => 'Ảnh bộ sưu tập và sản phẩm gối trẻ em.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap-tre-em', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false, 'cover_image' => '/theme-demo/nt503/hero-wolfbed.png'], 'settings_schema' => array_merge($sourceSchema($products, 4), ['cover_image' => ['type' => 'text', 'label' => 'Ảnh bộ sưu tập']]), 'data' => ['vi' => array_merge($heading('BST Drap Trẻ em', 'BỘ SƯU TẬP MỚI', null, 'Khám phá BST'), $items(array_reverse($fallbackProducts))), 'en' => $heading('Kids bedding collection', 'NEW COLLECTION', null, 'Explore collection')]],
            ['block_type' => 'nt503_season_promo', 'label' => 'Khuyến mãi theo mùa', 'description' => 'Banner rộng quảng bá bộ sưu tập.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai-mua', 'settings' => ['background_image' => '/theme-demo/nt503/bedding.png'], 'settings_schema' => ['background_image' => ['type' => 'text', 'label' => 'Ảnh nền']], 'data' => ['vi' => $heading('Ngủ ngon mỗi ngày với giá ưu đãi', null, 'Giảm 30% toàn bộ bộ sưu tập mới nhất', 'Mua ngay'), 'en' => $heading('Sleep well every day for less', null, 'Save 30% on the newest collection', 'Shop now')]],
            ['block_type' => 'nt503_advice', 'label' => 'Góc tư vấn', 'description' => 'Bốn bài viết mới về giấc ngủ.', 'preview_image' => $preview, 'anchor_id' => 'goc-tu-van', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $sourceSchema($posts, 4), 'data' => ['vi' => $heading('Góc tư vấn'), 'en' => $heading('Sleep advice')]],
            ['block_type' => 'nt503_footer', 'label' => 'Hotline và giới thiệu footer', 'description' => 'Thông điệp thương hiệu và ba hotline hỗ trợ.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Wolf Bed', null, 'Mua nệm, chăn ga gối và phụ kiện chính hãng. Tư vấn cá nhân hoá, nằm thử 120 đêm, đổi trả dễ và giao tận nơi.'), 'en' => $heading('Wolf Bed', null, 'Official mattresses, bedding and accessories with personalized advice and easy returns.')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function nt502DefaultBlocks(): array
    {
        $preview = '/theme-previews/NT502/preview-nt502.svg';
        $sourceSchema = fn (array $options, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $options],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục lọc'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật', 'default' => false],
        ];
        $productsSource = [['value' => 'cms_products', 'label' => 'Sản phẩm']];
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $items = fn (array $values): array => ['content' => ['items' => $values]];
        $products = [
            ['title' => 'Bàn sofa gỗ cao su', 'image' => 'https://images.unsplash.com/photo-1532372320572-cda25653a26d?auto=format&fit=crop&w=900&q=85', 'price' => 1790000, 'original_price' => 2000000, 'url' => '#'],
            ['title' => 'Tủ kệ tivi gỗ', 'image' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=900&q=85', 'price' => 2490000, 'original_price' => 3000000, 'url' => '#'],
            ['title' => 'Ghế đơn sofa tự nhiên', 'image' => 'https://images.unsplash.com/photo-1567538096630-e0c55bd6374c?auto=format&fit=crop&w=900&q=85', 'price' => 1990000, 'original_price' => 2300000, 'url' => '#'],
            ['title' => 'Tủ đầu giường gỗ', 'image' => 'https://images.unsplash.com/photo-1615874694520-474822394e73?auto=format&fit=crop&w=900&q=85', 'price' => 1190000, 'original_price' => 1390000, 'url' => '#'],
            ['title' => 'Giường ngủ gỗ hiện đại', 'image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=85', 'price' => 4990000, 'original_price' => 5500000, 'url' => '#'],
            ['title' => 'Tủ quần áo gỗ', 'image' => 'https://images.unsplash.com/photo-1558997519-83ea9252edf8?auto=format&fit=crop&w=900&q=85', 'price' => 4490000, 'original_price' => 5000000, 'url' => '#'],
        ];
        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero nội thất', 'description' => 'Slider banner lớn đầu trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'nt502-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy (ms)']], 'data' => ['vi' => array_merge($heading('Nội thất phòng khách', 'DOLA FURNITURE', 'Giảm đến 50% khi đặt hàng qua web', 'Xem ngay'), ['content' => ['slides' => [['title' => 'Nội thất phòng khách', 'summary' => 'Giảm đến 50% khi đặt hàng qua web', 'button_label' => 'Xem ngay', 'image' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#phong-khach']]]]), 'en' => $heading('Living room furniture', 'DOLA FURNITURE', 'Save up to 50% when ordering online.', 'Shop now')]],
            ['block_type' => 'nt502_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Carousel danh mục sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 9], 'settings_schema' => $sourceSchema([['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm']], 9), 'data' => ['vi' => array_merge($heading('Danh mục nổi bật'), $items([])), 'en' => $heading('Featured categories')]],
            ['block_type' => 'nt502_about', 'label' => 'Giới thiệu Dola Furniture', 'description' => 'Giới thiệu thương hiệu, ảnh và ba cam kết do người dùng nhập.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => ['image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=1500&q=90'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh giới thiệu']], 'data' => ['vi' => array_merge($heading('Dola Furniture', 'Về chúng tôi', 'Với mong muốn phát triển thương hiệu Việt bằng nội lực, Dola Furniture chú trọng thiết kế và sản xuất nội thất trong nước.'), $items([['icon' => 'fa-solid fa-truck-fast', 'title' => 'Miễn phí vận chuyển', 'summary' => 'Cho tất cả đơn hàng trong nội thành'], ['icon' => 'fa-solid fa-box-open', 'title' => 'Miễn phí đổi - trả', 'summary' => 'Đối với sản phẩm lỗi sản xuất'], ['icon' => 'fa-solid fa-headset', 'title' => 'Hỗ trợ nhanh chóng', 'summary' => 'Gọi Hotline 19006750 để được hỗ trợ']])), 'en' => $heading('Dola Furniture', 'About us', 'Vietnamese furniture designed and produced with care.')]],
            ['block_type' => 'nt502_promotion', 'label' => 'Khuyến mãi đặc biệt', 'description' => 'Sản phẩm khuyến mãi theo điều kiện lọc và ảnh nền.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'featured_only' => true, 'background_image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=2200&q=90'], 'settings_schema' => array_merge($sourceSchema($productsSource, 3), ['background_image' => ['type' => 'text', 'label' => 'Ảnh nền']]), 'data' => ['vi' => array_merge($heading('Khuyến mãi đặc biệt', null, 'Ưu đãi giới hạn dành cho không gian sống hiện đại.', 'Xem tất cả'), $items(array_slice($products, 0, 3))), 'en' => $heading('Special promotion')]],
            ['block_type' => 'nt502_living_room', 'label' => 'Nội thất phòng khách', 'description' => 'Banner lớn và lưới sản phẩm theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'phong-khach', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 6, 'featured_only' => false, 'feature_image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1500&q=90'], 'settings_schema' => array_merge($sourceSchema($productsSource, 6), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh phòng khách']]), 'data' => ['vi' => array_merge($heading('Nội thất phòng khách', null, 'Nhiều sản phẩm giúp phòng khách của bạn trở nên phong phú hơn.', 'Xem ngay'), $items($products)), 'en' => $heading('Living room furniture')]],
            ['block_type' => 'nt502_bedroom', 'label' => 'Nội thất phòng ngủ', 'description' => 'Lưới sản phẩm và banner lớn đảo chiều.', 'preview_image' => $preview, 'anchor_id' => 'phong-ngu', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 6, 'featured_only' => false, 'feature_image' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1500&q=90'], 'settings_schema' => array_merge($sourceSchema($productsSource, 6), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh phòng ngủ']]), 'data' => ['vi' => array_merge($heading('Nội thất phòng ngủ', null, 'Nhiều sản phẩm giúp căn phòng của bạn trở nên ấm cúng hơn.', 'Xem ngay'), $items(array_reverse($products))), 'en' => $heading('Bedroom furniture')]],
            ['block_type' => 'testimonials', 'label' => 'Đánh giá từ khách hàng', 'description' => 'Slider đánh giá khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'danh-gia', 'dynamic' => true, 'settings' => ['source' => 'cms_testimonials', 'limit' => 5, 'featured_only' => true, 'background_image' => 'https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=2200&q=90'], 'settings_schema' => array_merge($sourceSchema([['value' => 'cms_testimonials', 'label' => 'Đánh giá khách hàng'], ['value' => 'custom', 'label' => 'Người dùng tự nhập']], 5), ['background_image' => ['type' => 'text', 'label' => 'Ảnh nền']]), 'data' => ['vi' => array_merge($heading('Đánh giá từ khách hàng'), $items([['name' => 'Ngọc Tuyến', 'role' => 'Đầu bếp', 'quote' => 'Những mẫu phòng ngủ của Dola Furniture mang đến cảm giác ấm cúng, gần gũi và thoải mái.', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=85']])), 'en' => $heading('Customer reviews')]],
            ['block_type' => 'nt502_latest_news', 'label' => 'Tin tức mới nhất', 'description' => 'Bài nổi bật lớn và danh sách bài viết mới.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 7, 'featured_only' => false], 'settings_schema' => $sourceSchema([['value' => 'cms_posts', 'label' => 'Tin tức']], 7), 'data' => ['vi' => $heading('Tin tức mới nhất'), 'en' => $heading('Latest news')]],
        ];
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
    private function foot403DefaultBlocks(): array
    {
        $sources = [
            ['value' => 'custom', 'label' => 'Người dùng tự nhập'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
        ];
        $schema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
        ];
        $preview = '/theme-previews/FOOT403/preview-foot403.svg';
        $dishes = [
            ['title' => 'Salad rau mùa sốt cam', 'category' => 'Khai vị', 'image' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=900&q=86', 'price' => 68000, 'old_price' => 70000, 'discount' => '-3%', 'url' => '#lien-he'],
            ['title' => 'Salad rau mùa sốt mắc mật', 'category' => 'Khai vị', 'image' => 'https://images.unsplash.com/photo-1546793665-c74683f339c1?auto=format&fit=crop&w=900&q=86', 'price' => 68000, 'url' => '#lien-he'],
            ['title' => 'Phở cuốn', 'category' => 'Món chính', 'image' => 'https://images.unsplash.com/photo-1555126634-323283e090fa?auto=format&fit=crop&w=900&q=86', 'price' => 82000, 'old_price' => 90000, 'discount' => '-9%', 'url' => '#lien-he'],
            ['title' => 'Gỏi tai heo hoa chuối', 'category' => 'Khai vị', 'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=86', 'price' => 125000, 'url' => '#lien-he'],
            ['title' => 'Gà cuốn lá dứa', 'category' => 'Món chính', 'image' => 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?auto=format&fit=crop&w=900&q=86', 'price' => 168000, 'url' => '#lien-he'],
            ['title' => 'Ức gà đút lò phủ lá chanh', 'category' => 'Món chính', 'image' => 'https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=900&q=86', 'price' => 185000, 'url' => '#lien-he'],
            ['title' => 'Sụn gà xóc muối Tây Ninh', 'category' => 'Món chính', 'image' => 'https://images.unsplash.com/photo-1569058242253-92a9c755a0ec?auto=format&fit=crop&w=900&q=86', 'price' => 135000, 'url' => '#lien-he'],
            ['title' => 'Nem lụi nướng mía', 'category' => 'Món chính', 'image' => 'https://images.unsplash.com/photo-1562967914-608f82629710?auto=format&fit=crop&w=900&q=86', 'price' => 158000, 'old_price' => 170000, 'discount' => '-7%', 'url' => '#lien-he'],
            ['title' => 'Mì spaghetti sốt kem nấm', 'category' => 'Cơm - mì - cháo', 'image' => 'https://images.unsplash.com/photo-1473093295043-cdd812d0e601?auto=format&fit=crop&w=900&q=86', 'price' => 99000, 'url' => '#lien-he'],
            ['title' => 'Cơm chiên hải sản', 'category' => 'Cơm - mì - cháo', 'image' => 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?auto=format&fit=crop&w=900&q=86', 'price' => 89000, 'old_price' => 99000, 'discount' => '-10%', 'url' => '#lien-he'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Header và banner nhà hàng', 'description' => 'Header dùng chung toàn website và banner ảnh lớn.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot403-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Vị trí banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => ['title' => 'HT Restaurant', 'subtitle' => 'Món ăn đa dạng', 'description' => 'Không gian ấm cúng, nguyên liệu tuyển chọn và những món ăn được chăm chút mỗi ngày.', 'button_label' => 'Đặt bàn ngay', 'content' => ['slides' => [
                ['kicker' => 'HT Restaurant', 'title' => 'Món ăn đa dạng', 'summary' => 'Mỗi bữa ăn là một trải nghiệm đáng nhớ.', 'button_label' => 'Đặt bàn ngay', 'link_url' => '#lien-he', 'image' => 'https://images.unsplash.com/photo-1516211697506-8360dbcfe9a4?auto=format&fit=crop&w=2200&q=90'],
                ['kicker' => 'Tinh hoa ẩm thực', 'title' => 'Hương vị được nấu bằng đam mê', 'summary' => 'Tươi ngon từ nguyên liệu đến cách phục vụ.', 'button_label' => 'Khám phá thực đơn', 'link_url' => '#thuc-don', 'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=2200&q=90'],
            ]]]]],
            ['block_type' => 'about_experience', 'label' => 'Về HT Restaurant', 'description' => 'Giới thiệu nhà hàng với cụm bốn ảnh.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => ['source' => 'custom'], 'media' => ['images' => ['https://images.unsplash.com/photo-1541544741938-0af808871cc0?auto=format&fit=crop&w=900&q=86', 'https://images.unsplash.com/photo-1529042410759-befb1204b468?auto=format&fit=crop&w=900&q=86', 'https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=900&q=86', 'https://images.unsplash.com/photo-1563379926898-05f4575a45d8?auto=format&fit=crop&w=900&q=86']], 'data' => ['vi' => ['title' => 'HT Restaurant', 'subtitle' => 'Về chúng tôi', 'description' => 'Nhà hàng chúng tôi luôn đặt khách hàng lên hàng đầu, tận tâm phục vụ và mang lại những trải nghiệm tuyệt vời nhất. Các món ăn với công thức độc quyền sẽ mang lại hương vị mới mẻ cho thực khách.', 'button_label' => 'Xem thêm', 'content' => []]]],
            ['block_type' => 'featured_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Danh mục món ăn dạng thẻ trượt ngang.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 8], 'settings_schema' => $schema, 'data' => ['vi' => ['title' => 'Danh mục nổi bật', 'subtitle' => 'Khám phá hương vị', 'content' => ['items' => [
                ['title' => 'Món bò', 'summary' => 'Các món bò được chế biến tinh tế với hương vị đặc biệt.', 'image' => 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=700&q=85'],
                ['title' => 'Món gà', 'summary' => 'Thịt gà tươi mềm cùng công thức riêng của bếp trưởng.', 'image' => 'https://images.unsplash.com/photo-1532550907401-a500c9a57435?auto=format&fit=crop&w=700&q=85'],
                ['title' => 'Món heo', 'summary' => 'Đậm đà, tròn vị và phù hợp cho mọi bữa ăn.', 'image' => 'https://images.unsplash.com/photo-1529692236671-f1f6cf9683ba?auto=format&fit=crop&w=700&q=85'],
                ['title' => 'Món cá', 'summary' => 'Nguồn hải sản tuyển chọn, chế biến giữ vị tươi ngon.', 'image' => 'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?auto=format&fit=crop&w=700&q=85'],
            ]]]]],
            ['block_type' => 'featured_products', 'label' => 'Thực đơn của chúng tôi', 'description' => 'Thực đơn có tab danh mục, lấy từ sản phẩm hoặc nhập tay.', 'preview_image' => $preview, 'anchor_id' => 'thuc-don', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 10], 'settings_schema' => $schema, 'data' => ['vi' => ['title' => 'Thực đơn của chúng tôi', 'subtitle' => 'Món ngon mỗi ngày', 'button_label' => 'Xem chi tiết', 'content' => ['items' => $dishes]]]],
            ['block_type' => 'featured_products', 'label' => 'Món ăn nổi bật', 'description' => 'Dải món ăn được yêu thích nhất.', 'preview_image' => $preview, 'anchor_id' => 'mon-noi-bat', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 5, 'featured_only' => true], 'settings_schema' => $schema, 'data' => ['vi' => ['title' => 'Món ăn nổi bật', 'subtitle' => 'Bếp trưởng đề xuất', 'button_label' => 'Xem chi tiết', 'content' => ['items' => array_slice($dishes, 0, 5)]]]],
            ['block_type' => 'content_mosaic', 'label' => 'Con số ấn tượng', 'description' => 'Ảnh không gian và các chỉ số nổi bật của nhà hàng.', 'preview_image' => $preview, 'anchor_id' => 'con-so', 'settings' => ['source' => 'custom'], 'data' => ['vi' => ['title' => 'Trải nghiệm được tạo nên mỗi ngày', 'subtitle' => 'HTRestaurant', 'content' => ['images' => ['https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=800&q=85', 'https://images.unsplash.com/photo-1498654896293-37aacf113fd9?auto=format&fit=crop&w=800&q=85', 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=800&q=85', 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=800&q=85'], 'items' => [['title' => '8+', 'summary' => 'Cửa hàng'], ['title' => '200+', 'summary' => 'Nhân viên'], ['title' => '5000+', 'summary' => 'Khách hàng'], ['title' => '50+', 'summary' => 'Món ăn']]]]]],
            ['block_type' => 'bizmax_latest_posts', 'label' => 'Tin tức ẩm thực', 'description' => 'Tin tức, công thức và câu chuyện từ căn bếp.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'custom', 'limit' => 3], 'settings_schema' => $schema, 'data' => ['vi' => ['title' => 'Tin tức', 'subtitle' => 'Chuyện từ căn bếp', 'button_label' => 'Xem thêm', 'content' => ['items' => [
                ['title' => 'Mách bạn công thức làm canh cá nấu mẻ thơm ngon đậm vị', 'summary' => 'Một món ăn dân dã, quen thuộc trong mâm cơm gia đình Việt.', 'author' => 'Admin Dola', 'published_at' => '24/10/2026', 'image' => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1000&q=86'],
                ['title' => 'Tuyển tập 8 món xào đơn giản, tiết kiệm thời gian', 'summary' => 'Các công thức nhanh gọn dành cho những ngày bận rộn.', 'author' => 'Admin Dola', 'published_at' => '24/10/2026', 'image' => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1000&q=86'],
                ['title' => 'Chìa khóa vàng giúp thiết lập công thức nấu ăn ngon', 'summary' => 'Nguyên liệu tươi ngon và cách cân bằng gia vị.', 'author' => 'Admin Dola', 'published_at' => '24/10/2026', 'image' => 'https://images.unsplash.com/photo-1601050690597-df0568f70950?auto=format&fit=crop&w=1000&q=86'],
            ]]]]],
            ['block_type' => 'testimonials', 'label' => 'Khách hàng nói gì', 'description' => 'Cảm nhận của thực khách trên nền ảnh bếp.', 'preview_image' => $preview, 'anchor_id' => 'cam-nhan', 'settings' => ['source' => 'custom'], 'data' => ['vi' => ['title' => 'Khách hàng nói gì', 'subtitle' => 'Cảm nhận thực khách', 'content' => ['background' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=2200&q=88', 'items' => [['name' => 'Hoàng Dung', 'role' => 'Nhân viên văn phòng', 'quote' => 'Món ăn ở đây rất ngon, khẩu vị phù hợp với tôi. Không gian ấm cúng và nhân viên phục vụ chu đáo.', 'image' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=300&q=85'], ['name' => 'Minh Anh', 'role' => 'Khách hàng thân thiết', 'quote' => 'Một địa chỉ lý tưởng cho những bữa tối cùng gia đình và bạn bè.', 'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=85']]]]]],
            ['block_type' => 'landing_contact', 'label' => 'Đặt bàn nhanh', 'description' => 'Khối liên hệ và đặt bàn do FOOT403 thiết kế.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => ['source' => 'custom'], 'data' => ['vi' => ['title' => 'Đặt bàn cho một bữa ăn đáng nhớ', 'subtitle' => 'Liên hệ với Dola', 'description' => 'Để lại thông tin, đội ngũ nhà hàng sẽ liên hệ xác nhận trong thời gian sớm nhất.', 'button_label' => 'Gửi yêu cầu đặt bàn', 'content' => ['address' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM', 'phone' => '1900 6750', 'email' => 'support@htvietnam.vn', 'hours' => '10:00 – 22:30, tất cả các ngày']]]],
        ];
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
        $blocks[0]['data']['vi'] = ['title' => 'Chúng tôi am hiểu các loại thực vật', 'subtitle' => 'Một khu vườn xanh hơn bao giờ hết', 'description' => 'Thiết kế, thi công và chăm sóc cảnh quan xanh cho không gian sống của bạn.', 'button_label' => '1900 9477', 'content' => ['slides' => []]];
        $blocks[1]['data']['vi']['title'] = 'Dịch vụ chính của chúng tôi';
        $blocks[1]['data']['vi']['subtitle'] = 'Dịch vụ';
        $blocks[2]['data']['vi']['title'] = 'Giới thiệu công ty';
        $blocks[2]['data']['vi']['subtitle'] = 'Về chúng tôi';
        $blocks[3]['data']['vi']['title'] = 'Hoàn thành công việc đúng quy trình';
        $blocks[4]['data']['vi']['title'] = 'Một số dự án tiêu biểu';
        $blocks[4]['data']['vi']['subtitle'] = 'Dự án cảnh quan';
        $blocks[5]['data']['vi']['title'] = 'Cảm nhận từ khách hàng';
        $blocks[6]['data']['vi']['title'] = 'Đội ngũ kiến trúc sư và kỹ sư';
        $blocks[7]['data']['vi']['title'] = 'Yêu cầu tư vấn và báo giá';
        $blocks[8]['data']['vi']['title'] = 'Tin tức mới';
        $blocks[9]['data']['vi']['title'] = 'Đối tác đồng hành';

        return $blocks;
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0309DefaultBlocks(): array
    {
        $blocks = $this->xd0305DefaultBlocks();
        $blocks[0]['settings']['placement'] = 'xd0309-hero-slider';
        $blocks[0]['settings_schema'][0]['default'] = 'xd0309-hero-slider';
        $blocks[0]['label'] = 'Header và banner';
        $blocks[0]['description'] = 'Banner giới thiệu dịch vụ và nút nhận báo giá.';
        $blocks[0]['settings_schema'][0]['label'] = 'Vị trí banner';
        $blocks[0]['data']['vi'] = [
            'title' => 'Tối ưu chất lượng và chi phí cho đối tác doanh nghiệp',
            'subtitle' => 'Giải pháp vận tải và hậu cần',
            'description' => 'Cung cấp giải pháp vận chuyển, kho bãi và giao nhận chuyên nghiệp cho doanh nghiệp.',
            'button_label' => 'Nhận báo giá miễn phí',
            'content' => ['slides' => []],
        ];

        $blocks[1]['label'] = 'Danh sách dịch vụ';
        $blocks[1]['description'] = 'Các dịch vụ nổi bật được lấy từ hệ thống nội dung.';
        $blocks[1]['data']['vi'] = [
            'title' => 'Dịch vụ của chúng tôi',
            'subtitle' => 'Dịch vụ',
            'description' => '',
            'button_label' => 'Xem chi tiết',
            'content' => ['items' => []],
        ];

        $blocks[2]['label'] = 'Giới thiệu doanh nghiệp';
        $blocks[2]['description'] = 'Thông tin doanh nghiệp, kinh nghiệm và năng lực vận hành.';
        $blocks[2]['data']['vi'] = [
            'title' => 'Giới thiệu công ty',
            'subtitle' => 'Về chúng tôi',
            'description' => 'Đồng hành cùng doanh nghiệp bằng quy trình minh bạch, đội ngũ giàu kinh nghiệm và mạng lưới vận hành linh hoạt.',
            'button_label' => 'Nhận báo giá',
            'content' => [
                'image_primary' => '',
                'image_secondary' => '',
                'years' => '15+',
                'years_label' => 'Năm kinh nghiệm',
                'progress_label' => 'Mức độ hài lòng',
                'progress_value' => 95,
            ],
        ];

        $blocks[3]['label'] = 'Lý do chọn chúng tôi';
        $blocks[3]['description'] = 'Các lợi ích nổi bật của dịch vụ.';
        $blocks[3]['data']['vi'] = [
            'title' => 'Tư vấn hợp lý và cam kết chất lượng',
            'subtitle' => 'Vì sao chọn chúng tôi',
            'description' => 'Mỗi phương án được thiết kế theo nhu cầu thực tế, có đầu mối theo dõi và tiến độ rõ ràng.',
            'button_label' => '',
            'content' => [
                'image' => '',
                'items' => [
                    ['title' => 'Cam kết chất lượng'],
                    ['title' => 'Theo dõi minh bạch'],
                    ['title' => 'Tối ưu chi phí'],
                    ['title' => 'Hỗ trợ tận tâm'],
                ],
            ],
        ];

        $blocks[4]['label'] = 'Cảm nhận khách hàng';
        $blocks[4]['description'] = 'Đánh giá thực tế từ khách hàng.';
        $blocks[4]['data']['vi'] = [
            'title' => 'Đối tác nói gì về chúng tôi',
            'subtitle' => 'Cảm nhận khách hàng',
            'description' => 'Những trải nghiệm thực tế từ khách hàng đã đồng hành cùng chúng tôi.',
            'button_label' => '',
            'content' => ['items' => []],
        ];

        $blocks[5]['label'] = 'Đội ngũ chuyên gia';
        $blocks[5]['description'] = 'Danh sách thành viên nổi bật của doanh nghiệp.';
        $blocks[5]['data']['vi'] = [
            'title' => 'Đội ngũ kỹ thuật của chúng tôi',
            'subtitle' => 'Đội ngũ',
            'description' => '',
            'button_label' => '',
            'content' => ['items' => []],
        ];

        $blocks[6]['label'] = 'Liên hệ';
        $blocks[6]['description'] = 'Thông tin liên hệ và biểu mẫu tư vấn.';
        $blocks[6]['data']['vi'] = [
            'title' => 'Yêu cầu tư vấn và báo giá',
            'subtitle' => 'Liên hệ với chúng tôi',
            'description' => 'Đội ngũ sẵn sàng lắng nghe nhu cầu và xây dựng phương án phù hợp.',
            'button_label' => 'Gửi ngay',
            'content' => [],
        ];

        $blocks[7]['label'] = 'Blog và bài viết';
        $blocks[7]['description'] = 'Các bài viết mới nhất từ hệ thống nội dung.';
        $blocks[7]['data']['vi'] = [
            'title' => 'Tin tức mới',
            'subtitle' => 'Blog và bài viết',
            'description' => '',
            'button_label' => 'Đọc thêm',
            'content' => ['items' => []],
        ];

        $blocks[8]['label'] = 'Logo đối tác';
        $blocks[8]['description'] = 'Danh sách đối tác đồng hành.';
        $blocks[8]['data']['vi'] = [
            'title' => 'Đối tác đồng hành',
            'subtitle' => 'Đối tác',
            'description' => '',
            'button_label' => '',
            'content' => ['items' => []],
        ];

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
                'settings' => ['cta_url' => FrontendRouteUrl::pagePath('gioi-thieu')],
                'media' => [],
                'data' => [
                    'vi' => ['title' => 'Chúng tôi đang phát triển các giải pháp năng lượng mặt trời', 'subtitle' => 'Giới thiệu của chúng tôi', 'description' => 'Giải pháp năng lượng xanh giúp doanh nghiệp chủ động chi phí và hướng tới tương lai bền vững.', 'button_label' => 'Về chúng tôi', 'content' => ['tabs' => [
                        ['label' => 'Về chúng tôi', 'description' => 'Giải pháp năng lượng xanh giúp doanh nghiệp chủ động chi phí và hướng tới tương lai bền vững.'],
                        ['label' => 'Tầm nhìn', 'description' => 'Trở thành đối tác năng lượng đáng tin cậy, đồng hành cùng doanh nghiệp trên hành trình vận hành xanh.'],
                        ['label' => 'Sứ mệnh', 'description' => 'Mang đến giải pháp năng lượng hiệu quả, an toàn và phù hợp với nhu cầu vận hành thực tế.'],
                    ]]],
                    'en' => ['title' => 'We develop solar energy solutions', 'subtitle' => 'About us', 'description' => 'Clean energy solutions for long-term operations.', 'button_label' => 'About us', 'content' => ['tabs' => [
                        ['label' => 'About', 'description' => 'Clean energy solutions help businesses control costs and build sustainable operations.'],
                        ['label' => 'Vision', 'description' => 'To become a trusted energy partner for businesses pursuing greener operations.'],
                        ['label' => 'Mission', 'description' => 'To deliver efficient and safe energy solutions tailored to real operational needs.'],
                    ]]],
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
                'settings' => ['source' => 'cms_testimonials', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_testimonials', 'label' => 'CMS Testimonials'], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
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
                'media' => ['aside_image' => 'https://images.unsplash.com/photo-1509391366360-2e959784a276?auto=format&fit=crop&w=800&q=85'],
                'data' => ['vi' => ['title' => 'Muốn hỏi điều gì đó từ chúng tôi?', 'subtitle' => 'Câu hỏi thường gặp', 'description' => '', 'button_label' => '', 'content' => ['aside_title' => 'Giải pháp được triển khai thực tế', 'aside_description' => 'Đội ngũ tư vấn cùng doanh nghiệp xác định quy mô, mục tiêu tiết kiệm và lộ trình đầu tư phù hợp.', 'aside_button_label' => 'Nhận tư vấn', 'aside_button_url' => '#lien-he', 'items' => [['question' => 'Năng lượng mặt trời là gì?', 'answer' => 'Đây là nguồn năng lượng tái tạo từ ánh sáng mặt trời, có thể chuyển đổi thành điện để phục vụ sinh hoạt và sản xuất.'], ['question' => 'Hệ thống hoạt động như thế nào?', 'answer' => 'Tấm pin hấp thụ ánh sáng, bộ biến tần chuyển đổi thành điện và đưa điện vào hệ thống sử dụng.'], ['question' => 'Lợi ích của việc sử dụng là gì?', 'answer' => 'Tiết kiệm chi phí vận hành, giảm phát thải và tăng giá trị bền vững cho công trình.']]]], 'en' => ['title' => 'What would you like to ask?', 'subtitle' => 'FAQ', 'description' => '', 'button_label' => '', 'content' => ['aside_title' => 'Solutions implemented in practice', 'aside_description' => 'Our consultants help businesses define the right scale, savings targets, and investment roadmap.', 'aside_button_label' => 'Get advice', 'aside_button_url' => '#lien-he', 'items' => []]]],
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
                'settings' => ['years' => 10, 'cta_url' => FrontendRouteUrl::pagePath('gioi-thieu')],
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bds701DefaultBlocks(): array
    {
        $preview = '/theme-previews/BDS701/preview-bds701.svg';
        $listingSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [
                ['value' => 'real_estate_listings', 'label' => 'Tin bất động sản'],
            ]],
            'limit' => ['type' => 'number', 'label' => 'Số tin hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'Loại hình bất động sản'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy tin nổi bật'],
            'transaction_type' => ['type' => 'select', 'label' => 'Loại giao dịch', 'options' => [
                ['value' => '', 'label' => 'Tất cả'],
                ['value' => 'sale', 'label' => 'Bán'],
                ['value' => 'rent', 'label' => 'Cho thuê'],
            ]],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [
                ['value' => 'cms_posts', 'label' => 'Tin tức CMS'],
            ]],
            'limit' => ['type' => 'number', 'label' => 'Số bài hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'Danh mục tin'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy tin nổi bật'],
        ];

        return [
            [
                'block_type' => 'bds701_hero_search',
                'label' => 'Hero tìm kiếm bất động sản',
                'description' => 'Hero toàn màn hình, thanh tìm kiếm nhanh và lối tắt theo loại hình.',
                'preview_image' => $preview,
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'real_estate_property_types', 'limit' => 5],
                'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số loại hình']],
                'media' => ['image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=2200&q=90'],
                'data' => [
                    'vi' => [
                        'title' => 'Tìm kiếm nhà đất mơ ước',
                        'subtitle' => 'Delta Platinum',
                        'description' => 'Khám phá không gian sống và cơ hội đầu tư phù hợp nhất với bạn.',
                        'button_label' => 'Tìm kiếm nhanh',
                    ],
                    'en' => [
                        'title' => 'Find your dream property',
                        'subtitle' => 'Delta Platinum',
                        'description' => 'Discover the right home and investment opportunity.',
                        'button_label' => 'Quick search',
                    ],
                ],
            ],
            [
                'block_type' => 'bds701_latest_listings',
                'label' => 'Dự án mới nhất',
                'description' => 'Danh sách tin bán và cho thuê mới nhất với bộ lọc loại hình.',
                'preview_image' => $preview,
                'anchor_id' => 'du-an-moi',
                'dynamic' => true,
                'settings' => ['source' => 'real_estate_listings', 'limit' => 6, 'featured_only' => false],
                'settings_schema' => $listingSchema,
                'data' => [
                    'vi' => ['title' => 'Dự án mới nhất', 'subtitle' => 'Dự án mới nhất hay có đang ở gần bạn?', 'description' => 'Những tin bất động sản được cập nhật gần đây.'],
                    'en' => ['title' => 'Latest properties', 'subtitle' => 'Discover what is new around you.'],
                ],
            ],
            [
                'block_type' => 'bds701_property_types',
                'label' => 'Mẫu dự án tiêu biểu',
                'description' => 'Mosaic hình ảnh điều hướng tới từng loại hình bất động sản.',
                'preview_image' => $preview,
                'anchor_id' => 'loai-hinh',
                'dynamic' => true,
                'settings' => ['source' => 'real_estate_property_types', 'limit' => 5],
                'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số loại hình']],
                'data' => [
                    'vi' => ['title' => 'Mẫu dự án tiêu biểu', 'subtitle' => 'Sự khác biệt mang tên phong cách'],
                    'en' => ['title' => 'Featured property types', 'subtitle' => 'A distinctive living style'],
                ],
            ],
            [
                'block_type' => 'bds701_rental_listings',
                'label' => 'Dự án cho thuê',
                'description' => 'Các bất động sản cho thuê nổi bật.',
                'preview_image' => $preview,
                'anchor_id' => 'cho-thue',
                'dynamic' => true,
                'settings' => ['source' => 'real_estate_listings', 'limit' => 3, 'transaction_type' => 'rent'],
                'settings_schema' => $listingSchema,
                'data' => [
                    'vi' => ['title' => 'Dự án cho thuê', 'subtitle' => 'Những dự án cho thuê hàng đầu đang được săn đón'],
                    'en' => ['title' => 'Properties for rent', 'subtitle' => 'Popular rental opportunities'],
                ],
            ],
            [
                'block_type' => 'bds701_market_news',
                'label' => 'Tin tức thị trường',
                'description' => 'Một bài nổi bật và danh sách tin thị trường bên cạnh.',
                'preview_image' => $preview,
                'anchor_id' => 'tin-thi-truong',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 5, 'featured_only' => false],
                'settings_schema' => $postSchema,
                'data' => [
                    'vi' => ['title' => 'Tin tức thị trường', 'subtitle' => 'Thông tin thị trường bất động sản 24/7'],
                    'en' => ['title' => 'Market news', 'subtitle' => 'Real estate insights 24/7'],
                ],
            ],
            [
                'block_type' => 'bds701_latest_news',
                'label' => 'Tin bất động sản mới',
                'description' => 'Danh sách bài viết mới dạng thẻ trượt ngang.',
                'preview_image' => $preview,
                'anchor_id' => 'tin-moi',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false],
                'settings_schema' => $postSchema,
                'data' => [
                    'vi' => ['title' => 'Tin bất động sản mới', 'subtitle' => 'Cập nhật nhanh chóng thông tin thị trường bất động sản'],
                    'en' => ['title' => 'Latest real estate news', 'subtitle' => 'Fresh market updates'],
                ],
            ],
            [
                'block_type' => 'bds701_newsletter',
                'label' => 'Đăng ký nhận tin',
                'description' => 'Khối đăng ký email trên nền hình bất động sản.',
                'preview_image' => $preview,
                'anchor_id' => 'nhan-tin',
                'settings' => [],
                'media' => ['image' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=2200&q=85'],
                'data' => [
                    'vi' => ['title' => 'Đăng ký nhận tin', 'subtitle' => 'Chúng tôi sẽ gửi bạn những thông tin bất động sản mới nhất', 'button_label' => 'Nhận tin miễn phí'],
                    'en' => ['title' => 'Subscribe', 'subtitle' => 'Get the latest real estate updates', 'button_label' => 'Subscribe free'],
                ],
            ],
        ];
    }
}
