<?php

namespace App\Support\LandingPages;

use App\Core\Cms\CmsMenuResolver;
use App\Enums\TranslationStatus;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
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
use App\Models\ThemeDemoRecord;
use App\Models\ThemeTranslation;
use App\Support\FrontendLocalization;
use App\Support\FrontendRouteUrl;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\Localization\TranslationRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LandingPageBuilder
{
    public function __construct(
        private readonly LocalizedContentRepository $localizedContent,
        private readonly CmsMenuResolver $menuResolver,
    ) {}

    public function supportsTheme(?string $themeKey): bool
    {
        return in_array(strtoupper((string) $themeKey), ['BOOK920', 'TH0050', 'SER0101', 'SER102', 'SER103', 'XD0301', 'XD0302', 'XD0303', 'XD0304', 'XD0305', 'XD0306', 'XD0307', 'XD0308', 'XD0309', 'XD0310', 'XD0311', 'XD0312', 'XD0313', 'XD0314', 'XD0315', 'XD0318', 'FOOT401', 'FOOT403', 'FOOT404', 'FOOT405', 'FOOT406', 'FOOT407', 'FOOT408', 'FOOT409', 'XD0320', 'NT501', 'NT502', 'NT503', 'NT504', 'XD321', 'XD0322', 'XD0323', 'XD0324', 'XD0325', 'DN202', 'DN302', 'DN350', 'DN351', 'BZ501', 'SPA502', 'SPA111', 'SHOP601', 'SHOP602', 'SHOP603', 'SHOP604', 'SHOP605', 'SHOP606', 'EC900', 'EC901', 'EC902', 'EC903', 'EC904', 'EC905', 'EC906', 'EC907', 'EC908', 'EC909', 'EC910', 'EC911', 'EC912', 'EC913', 'EC914', 'EC915', 'EC916', 'EC917', 'CA0050', 'BDS701', 'BDS702', 'DL750'], true);
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
                'schema_version' => (int) ($block['schema_version'] ?? 1),
                'label' => $block['label'],
                'description' => $block['description'],
                'default_anchor_id' => $block['anchor_id'],
                'preview_image' => $block['preview_image'] ?? null,
                'dynamic' => ($block['dynamic'] ?? false)
                    || $this->isCmsTestimonialBlock((string) $block['block_type'])
                    || $this->isCmsPartnerBlock((string) $block['block_type']),
                'settings_schema' => $this->cmsSectionSettingsSchema($block),
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

        return is_array($definition) ? $this->cmsSectionSettingsSchema($definition) : [];
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
    public function viewData(
        LandingPage $page,
        string $locale,
        string $fallbackLocale = 'vi',
        bool $includeEditableLocales = false,
    ): array
    {
        $pageData = $this->localizedPageData($page, $locale, $fallbackLocale, true);

        $blocks = $page->blocks
            ->filter(fn (LandingPageBlock $block): bool => $block->is_visible && $block->block_type !== 'footer_contact')
            ->map(fn (LandingPageBlock $block): array => $this->serializeBlock(
                $block,
                $locale,
                $fallbackLocale,
                true,
                $includeEditableLocales,
            ))
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
            'slug' => $data?->slug ?? $page->slug,
            'status' => $page->status,
            'template' => $page->template,
            'is_home' => $page->is_home,
            'settings' => $page->settings ?? [],
            'media' => $page->media ?? [],
            'title' => $data?->title,
            'excerpt' => $data?->excerpt,
            'meta_title' => $data?->meta_title,
            'meta_description' => $data?->meta_description,
            'translation_status' => $data?->translation_status?->value
                ?? TranslationStatus::Missing->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeBlock(
        LandingPageBlock $block,
        string $locale,
        string $fallbackLocale = 'vi',
        bool $includeDynamic = false,
        bool $includeEditableLocales = false,
    ): array {
        $availableData = $includeEditableLocales
            ? $block->data
            : $block->data->filter(
                fn (LandingPageBlockData $item): bool => $item->isPublishedTranslation(),
            );
        $data = $availableData->firstWhere('locale', $locale)
            ?? $availableData->firstWhere('locale', $fallbackLocale)
            ?? $availableData->first();
        $fallbackData = $availableData->firstWhere('locale', $fallbackLocale)
            ?? $availableData->first();
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
            'schema_version' => (int) ($block->schema_version ?? 1),
            'sort_order' => $block->sort_order,
            'is_visible' => $block->is_visible,
            'anchor_id' => $block->anchor_id,
            'settings' => $block->settings ?? [],
            'settings_schema' => $this->settingsSchemaFor((string) $block->theme_key, (string) $block->block_type),
            'media' => $block->media ?? [],
            'data' => [
                'locale' => $data?->locale ?? $locale,
                'schema_version' => (int) ($data?->schema_version ?? $block->schema_version ?? 1),
                'translation_status' => $data?->translation_status?->value
                    ?? TranslationStatus::Missing->value,
                'title' => $data?->title ?? $fallbackData?->title,
                'subtitle' => $data?->subtitle ?? $fallbackData?->subtitle,
                'description' => $data?->description ?? $fallbackData?->description,
                'button_label' => $data?->button_label ?? $fallbackData?->button_label,
                'content' => $content,
            ],
            'data_by_locale' => collect(
                $includeEditableLocales
                    ? FrontendLocalization::editableLocales()
                    : FrontendLocalization::publicLocales(),
            )
                ->mapWithKeys(function (string $supportedLocale) use ($availableData, $fallbackData, $fallbackContent, $includeEditableLocales, $block): array {
                    $localeData = $availableData->firstWhere('locale', $supportedLocale);
                    $localeContent = $this->decodeContent($localeData?->content);

                    if (
                        ! $includeEditableLocales
                        && ($localeContent === [] || (array_key_exists('items', $localeContent) && ($localeContent['items'] ?? []) === []))
                    ) {
                        $localeContent = $fallbackContent;
                    }

                    return [
                        $supportedLocale => [
                            'locale' => $supportedLocale,
                            'schema_version' => (int) ($localeData?->schema_version ?? $block->schema_version ?? 1),
                            'translation_status' => $localeData?->translation_status?->value
                                ?? TranslationStatus::Missing->value,
                            'allowed_transitions' => $localeData?->translation_status
                                ? collect($localeData->translation_status->allowedTransitions())
                                    ->map(fn (TranslationStatus $status): string => $status->value)
                                    ->all()
                                : [],
                            'title' => $localeData?->title ?? ($includeEditableLocales ? null : $fallbackData?->title),
                            'subtitle' => $localeData?->subtitle ?? ($includeEditableLocales ? null : $fallbackData?->subtitle),
                            'description' => $localeData?->description ?? ($includeEditableLocales ? null : $fallbackData?->description),
                            'button_label' => $localeData?->button_label ?? ($includeEditableLocales ? null : $fallbackData?->button_label),
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
            $pagePayload = [
                'slug' => 'home',
                'title' => strtoupper($themeKey).' Landing',
                'excerpt' => 'Trang chủ landingpage.',
                'meta_title' => strtoupper($themeKey).' Landing',
                'meta_description' => 'Landing page được quản lý theo từng block.',
            ];
            $status = $locale === FrontendLocalization::sourceLocale()
                ? TranslationStatus::Published
                : TranslationStatus::NeedsTranslation;
            $revision = TranslationRevision::fingerprint($pagePayload);
            LandingPageData::query()->create([
                'landing_page_id' => $page->id,
                'locale' => $locale,
                ...$pagePayload,
                'translation_status' => $status,
                'source_revision' => $revision,
                'translation_revision' => $revision,
                'translated_at' => now(),
                'reviewed_at' => $status === TranslationStatus::Published ? now() : null,
                'translation_published_at' => $status === TranslationStatus::Published ? now() : null,
            ]);
        }

        foreach ($this->defaultBlocksForTheme($themeKey) as $index => $definition) {
            $block = LandingPageBlock::query()->create([
                'landing_page_id' => $page->id,
                'theme_key' => strtoupper($themeKey),
                'block_type' => $definition['block_type'],
                'schema_version' => (int) ($definition['schema_version'] ?? 1),
                'sort_order' => ($index + 1) * 10,
                'is_visible' => true,
                'anchor_id' => $definition['anchor_id'],
                'settings' => $definition['settings'] ?? [],
                'media' => $definition['media'] ?? [],
            ]);

            $sourceData = $this->defaultBlockLocaleData(
                $definition,
                FrontendLocalization::sourceLocale(),
            );

            foreach ($this->supportedLocales() as $locale) {
                $data = $this->defaultBlockLocaleData($definition, $locale);
                $status = $locale === FrontendLocalization::sourceLocale()
                    ? TranslationStatus::Published
                    : ($data === $sourceData
                        ? TranslationStatus::NeedsTranslation
                        : TranslationStatus::Draft);
                LandingPageBlockData::query()->create([
                    'landing_page_block_id' => $block->id,
                    'locale' => $locale,
                    'schema_version' => (int) ($definition['schema_version'] ?? 1),
                    'title' => $data['title'] ?? null,
                    'subtitle' => $data['subtitle'] ?? null,
                    'description' => $data['description'] ?? null,
                    'button_label' => $data['button_label'] ?? null,
                    'content' => json_encode($data['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'translation_status' => $status,
                    'source_revision' => TranslationRevision::fingerprint($sourceData),
                    'translation_revision' => TranslationRevision::fingerprint($data),
                    'translated_at' => now(),
                    'reviewed_at' => $status === TranslationStatus::Published ? now() : null,
                    'translation_published_at' => $status === TranslationStatus::Published ? now() : null,
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
            'schema_version' => (int) ($definition['schema_version'] ?? 1),
            'sort_order' => $maxSort + 10,
            'is_visible' => true,
            'anchor_id' => $definition['anchor_id'].'-'.Str::lower(Str::random(4)),
            'settings' => $definition['settings'] ?? [],
            'media' => $definition['media'] ?? [],
        ]);

        $sourceData = $this->defaultBlockLocaleData(
            $definition,
            FrontendLocalization::sourceLocale(),
        );

        foreach ($this->supportedLocales() as $locale) {
            $data = $this->defaultBlockLocaleData($definition, $locale);
            $status = $locale === FrontendLocalization::sourceLocale()
                ? TranslationStatus::Published
                : ($data === $sourceData
                    ? TranslationStatus::NeedsTranslation
                    : TranslationStatus::Draft);
            LandingPageBlockData::query()->create([
                'landing_page_block_id' => $block->id,
                'locale' => $locale,
                'schema_version' => (int) ($definition['schema_version'] ?? 1),
                'title' => $data['title'] ?? null,
                'subtitle' => $data['subtitle'] ?? null,
                'description' => $data['description'] ?? null,
                'button_label' => $data['button_label'] ?? null,
                'content' => json_encode($data['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'translation_status' => $status,
                'source_revision' => TranslationRevision::fingerprint($sourceData),
                'translation_revision' => TranslationRevision::fingerprint($data),
                'translated_at' => now(),
                'reviewed_at' => $status === TranslationStatus::Published ? now() : null,
                'translation_published_at' => $status === TranslationStatus::Published ? now() : null,
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
        return array_values(array_unique(FrontendLocalization::editableLocales() ?: ['vi']));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function defaultBlockLocaleData(array $definition, string $locale): array
    {
        $dataByLocale = (array) ($definition['data'] ?? []);

        return (array) (
            $dataByLocale[$locale]
            ?? $dataByLocale[FrontendLocalization::sourceLocale()]
            ?? $dataByLocale[FrontendLocalization::fallbackLocale()]
            ?? collect($dataByLocale)->first()
            ?? []
        );
    }

    private function localizedPageData(
        LandingPage $page,
        string $locale,
        string $fallbackLocale,
        bool $publishedOnly = false,
    ): ?LandingPageData {
        $availableData = $publishedOnly
            ? $page->data->filter(
                fn (LandingPageData $item): bool => $item->isPublishedTranslation(),
            )
            : $page->data;

        return $availableData->firstWhere('locale', $locale)
            ?? $availableData->firstWhere('locale', $fallbackLocale)
            ?? $availableData->first();
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
        $settings['theme_key'] ??= (
            $block->landingPage?->theme_key
            ?: $block->theme_key
        );
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
            'ec900_featured_categories' => 12,
            'ec900_best_sellers' => 5,
            'ec900_exclusive_products' => 10,
            'ec900_advice_posts' => 4,
            'ec901_featured_categories', 'ec901_flash_deals', 'ec901_best_sellers' => 5,
            'ec901_product_grid' => 10,
            'ec901_luxury_collection', 'ec901_latest_posts' => 4,
            'ec902_featured_categories', 'ec902_featured_deals', 'ec902_phone_collection', 'ec902_tablet_collection' => 4,
            'ec902_product_tabs', 'ec902_accessory_products', 'ec902_latest_posts' => 5,
            'ec903_category_rail' => 10,
            'ec903_featured_deals', 'ec903_food_deals' => 8,
            'ec903_vegetarian_deals', 'ec903_beauty_deals', 'ec903_travel_deals' => 4,
            'ec904_category_carousel' => 10,
            'ec904_tabbed_sale', 'ec904_daily_suggestions' => 5,
            'ec904_technology_products', 'ec904_fashion_products' => 8,
            'ec904_latest_posts' => 4,
            'ec905_paint_products' => 4,
            'ec905_tile_products' => 10,
            'ec905_projects', 'ec905_news' => 5,
            'ec906_flash_sale' => 5,
            'ec906_family_care' => 6,
            'ec906_kitchen_products' => 8,
            'ec906_latest_posts' => 4,
            'ec907_category_grid' => 16,
            'ec907_best_sellers', 'ec907_gaming_products' => 4,
            'ec907_audio_showcase' => 9,
            'ec907_tech_news' => 4,
            'ec908_category_rail' => 10,
            'ec908_best_sellers' => 6,
            'ec908_accessory_products', 'ec908_health_posts' => 4,
            'ec909_category_cards', 'ec909_recommendations' => 4,
            'ec909_headphone_showcase', 'ec909_headphone_products', 'ec909_earphone_products', 'ec909_latest_posts' => 3,
            'ec910_promotions', 'ec910_product_tabs', 'ec910_experience' => 5,
            'ec910_mens_watches' => 8,
            'ec913_category_grid' => 10,
            'ec913_best_sellers', 'ec913_laptop_showcase' => 5,
            'ec913_technology_news' => 3,
            'ec914_category_rail', 'ec914_featured_products' => 8,
            'ec914_craft_sale' => 4,
            'ec914_basket_showcase', 'ec914_lamp_showcase', 'ec914_latest_posts' => 3,
            'ec915_best_sellers' => 8,
            'ec915_latest_posts' => 3,
            'ec916_featured_deals' => 4,
            'ec916_beauty_deals' => 8,
            'ec917_summer_sale' => 8,
            'ec917_inspiration' => 4,
            'dn351_category_rail' => 4,
            'dn351_featured_split' => 2,
            'dn351_product_grid' => 8,
            'spa111_services', 'spa111_testimonials' => 3,
            'spa111_featured_products' => 8,
            'spa111_team', 'spa111_latest_posts' => 4,
            'spa111_partners' => 9,
            'nt502_categories' => 9,
            'nt502_promotion' => 3,
            'nt502_living_room', 'nt502_bedroom' => 6,
            'nt502_latest_news' => 7,
            'nt503_categories' => 10,
            'nt503_mattresses', 'nt503_kids_collection' => 4,
            'nt503_flash_sale' => 5,
            'nt503_advice' => 4,
            'nt504_spaces', 'nt504_sale_products' => 5,
            'nt504_product_categories' => 4,
            'nt504_category_rail' => 8,
            'nt504_latest_news' => 4,
            'bds701_hero_search' => 5,
            'bds701_latest_listings' => 6,
            'bds701_property_types' => 5,
            'bds701_rental_listings' => 3,
            'bds701_market_news' => 5,
            'bds701_latest_news' => 3,
            'bds702_featured_projects' => 6,
            'bds702_investment_activities', 'bds702_recommended_projects' => 3,
            'dl750_categories' => 8,
            'dl750_services' => 6,
            'dl750_products' => 6,
            'dl750_news' => 4,
            'shop606_collections', 'shop606_sale', 'shop606_new' => 4,
            'shop606_outfit', 'shop606_news' => 3,
            default => 3,
        };
        $maximumLimit = $block->block_type === 'ec907_category_grid' ? 16 : 12;
        $limit = max(1, min($maximumLimit, (int) ($settings['limit'] ?? $defaultLimit)));

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
            return $this->realEstatePropertyTypeItems(
                $limit,
                $locale,
                $block->landingPage?->website_key,
            );
        }

        if (in_array($block->block_type, ['bds701_latest_listings', 'bds701_rental_listings', 'bds702_featured_projects', 'bds702_recommended_projects'], true)) {
            if ($block->block_type === 'bds701_rental_listings') {
                $settings['transaction_type'] = 'rent';
            }

            return $this->realEstateListingItems(
                $settings,
                $limit,
                $locale,
                $block->landingPage?->website_key,
            );
        }

        if (in_array($block->block_type, ['bds701_market_news', 'bds701_latest_news'], true)) {
            return $this->latestPostItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'bds702_investment_activities') {
            return $this->latestPostItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if (in_array($block->block_type, ['dl750_categories', 'dl750_services', 'dl750_products'], true)) {
            $source = match ($block->block_type) {
                'dl750_categories' => 'catalog_categories',
                'dl750_services' => 'cms_services',
                default => 'cms_products',
            };

            return $this->contentSourceItems($settings, $source, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($block->block_type === 'dl750_news') {
            return $this->latestPostItems($settings, $limit, $locale, $block->landingPage?->website_key);
        }

        if (in_array($block->block_type, ['shop606_collections', 'shop606_sale', 'shop606_new', 'shop606_outfit', 'shop606_news'], true)) {
            $source = match ($block->block_type) {
                'shop606_collections' => 'catalog_categories',
                'shop606_news' => 'cms_posts',
                default => 'cms_products',
            };

            return $this->contentSourceItems($settings, $source, $limit, $locale, $block->landingPage?->website_key);
        }

        if ($this->isCmsTestimonialBlock($block->block_type)) {
            if (($settings['source'] ?? 'cms_testimonials') === 'custom') {
                return [];
            }

            return $this->cmsTestimonialItems(
                $settings,
                $limit,
                $locale,
                $block->landingPage?->website_key,
                $block->landingPage?->theme_key,
            );
        }

        if ($this->isCmsPartnerBlock($block->block_type)) {
            if (($settings['source'] ?? 'cms_partners') === 'custom') {
                return [];
            }

            return $this->cmsPartnerItems(
                $settings,
                $limit,
                $locale,
                $block->landingPage?->website_key,
                $block->landingPage?->theme_key,
            );
        }

        if (in_array($block->block_type, [
            'ec917_summer_sale',
            'ec917_inspiration',
            'foot404_categories',
            'foot404_deals',
            'foot404_new_products',
            'foot404_best_sellers',
            'foot405_categories',
            'foot405_popular_products',
            'foot405_best_sellers',
            'foot405_daily_deals',
            'foot405_product_columns',
            'foot406_categories',
            'foot406_promo_products',
            'foot406_favorites',
            'foot406_latest_posts',
            'foot407_product_tabs',
            'foot407_media_posts',
            'foot407_knowledge_posts',
            'foot408_menu_products',
            'foot408_blog_posts',
            'foot409_categories',
            'foot409_featured_products',
            'foot409_recommendations',
            'foot409_blog_posts',
            'ec916_featured_deals',
            'ec916_beauty_deals',
            'ec915_best_sellers',
            'ec915_latest_posts',
            'ec914_category_rail',
            'ec914_craft_sale',
            'ec914_featured_products',
            'ec914_basket_showcase',
            'ec914_lamp_showcase',
            'ec914_latest_posts',
            'ec913_category_grid',
            'ec913_best_sellers',
            'ec913_laptop_showcase',
            'ec913_technology_news',
            'ec912_hot_sale',
            'ec912_featured_categories',
            'ec912_iphone_products',
            'ec912_technology_news',
            'dn351_category_rail',
            'dn351_featured_split',
            'dn351_product_grid',
        ], true)) {
            $defaultSource = match ($block->block_type) {
                'dn351_category_rail', 'ec914_category_rail', 'ec913_category_grid', 'ec912_featured_categories', 'foot404_categories', 'foot405_categories', 'foot406_categories', 'foot409_categories' => 'catalog_categories',
                'ec917_inspiration', 'ec915_latest_posts', 'ec914_latest_posts', 'ec913_technology_news', 'ec912_technology_news', 'foot406_latest_posts', 'foot407_media_posts', 'foot407_knowledge_posts', 'foot408_blog_posts', 'foot409_blog_posts' => 'cms_posts',
                default => 'cms_products',
            };

            return $this->contentSourceItems(
                $settings,
                $defaultSource,
                $limit,
                $locale,
                $block->landingPage?->website_key,
            );
        }

        if (in_array($block->block_type, ['book920_featured', 'book920_sale', 'book920_hot'], true)) {
            return $this->contentSourceItems(
                $settings,
                'cms_products',
                $limit,
                $locale,
                $block->landingPage?->website_key,
            );
        }

        if (in_array($block->block_type, ['featured_services', 'featured_service_list', 'completed_projects_list', 'content_mosaic', 'content_showcase', 'project_gallery', 'service_category_slider', 'solutions_split_list', 'collection_gallery', 'business_service_grid', 'bizmax_latest_posts', 'shop601_collection_cards', 'shop601_flash_sale', 'shop601_product_grid', 'shop601_feature_collection', 'shop601_product_carousel', 'shop601_latest_content', 'shop603_hot_products', 'shop603_new_arrivals', 'shop603_sale_slider', 'shop604_flash_sale', 'shop604_new_arrivals', 'shop604_collection_tabs', 'shop605_sale', 'shop605_new', 'shop605_best', 'ec900_featured_categories', 'ec900_best_sellers', 'ec900_exclusive_products', 'ec900_advice_posts', 'ec901_featured_categories', 'ec901_flash_deals', 'ec901_best_sellers', 'ec901_product_grid', 'ec901_luxury_collection', 'ec901_latest_posts', 'ec902_featured_categories', 'ec902_product_tabs', 'ec902_featured_deals', 'ec902_phone_collection', 'ec902_tablet_collection', 'ec902_accessory_products', 'ec902_latest_posts', 'ec903_category_rail', 'ec903_featured_deals', 'ec903_food_deals', 'ec903_vegetarian_deals', 'ec903_beauty_deals', 'ec903_travel_deals', 'ec904_category_carousel', 'ec904_tabbed_sale', 'ec904_technology_products', 'ec904_fashion_products', 'ec904_daily_suggestions', 'ec904_latest_posts', 'ec905_paint_products', 'ec905_tile_products', 'ec905_projects', 'ec905_news', 'ec906_flash_sale', 'ec906_family_care', 'ec906_kitchen_products', 'ec906_latest_posts', 'ec907_category_grid', 'ec907_best_sellers', 'ec907_audio_showcase', 'ec907_gaming_products', 'ec907_tech_news', 'ec908_category_rail', 'ec908_best_sellers', 'ec908_accessory_products', 'ec908_health_posts', 'ec909_category_cards', 'ec909_headphone_showcase', 'ec909_headphone_products', 'ec909_earphone_products', 'ec909_recommendations', 'ec909_latest_posts', 'ec910_promotions', 'ec910_product_tabs', 'ec910_mens_watches', 'ec910_experience', 'spa111_services', 'spa111_featured_products', 'spa111_testimonials', 'spa111_team', 'spa111_latest_posts', 'spa111_partners', 'ca0050_fish_products', 'ca0050_accessories', 'nt502_categories', 'nt502_promotion', 'nt502_living_room', 'nt502_bedroom', 'nt502_latest_news', 'nt503_categories', 'nt503_mattresses', 'nt503_flash_sale', 'nt503_kids_collection', 'nt503_advice'], true)) {
            $defaultSource = match ($block->block_type) {
                'content_mosaic' => 'cms_posts',
                'content_showcase' => 'cms_projects',
                'project_gallery' => 'cms_projects',
                'bizmax_latest_posts', 'shop601_latest_content' => 'cms_posts',
                'shop601_collection_cards' => 'custom',
                'shop601_flash_sale', 'shop601_product_grid', 'shop601_feature_collection', 'shop601_product_carousel' => 'cms_products',
                'shop603_hot_products', 'shop603_new_arrivals', 'shop603_sale_slider', 'shop604_flash_sale', 'shop604_new_arrivals', 'shop604_collection_tabs', 'shop605_sale', 'shop605_new', 'shop605_best', 'ca0050_fish_products', 'ca0050_accessories' => 'cms_products',
                'ec900_featured_categories' => 'catalog_categories',
                'ec900_best_sellers', 'ec900_exclusive_products' => 'cms_products',
                'ec900_advice_posts' => 'cms_posts',
                'ec901_featured_categories' => 'catalog_categories',
                'ec901_flash_deals', 'ec901_best_sellers', 'ec901_product_grid', 'ec901_luxury_collection' => 'cms_products',
                'ec901_latest_posts' => 'cms_posts',
                'ec902_featured_categories' => 'catalog_categories',
                'ec902_product_tabs', 'ec902_featured_deals', 'ec902_phone_collection', 'ec902_tablet_collection', 'ec902_accessory_products' => 'cms_products',
                'ec902_latest_posts' => 'cms_posts',
                'ec903_category_rail' => 'catalog_categories',
                'ec903_featured_deals', 'ec903_food_deals', 'ec903_vegetarian_deals', 'ec903_beauty_deals', 'ec903_travel_deals' => 'cms_products',
                'ec904_category_carousel' => 'catalog_categories',
                'ec904_tabbed_sale', 'ec904_technology_products', 'ec904_fashion_products', 'ec904_daily_suggestions' => 'cms_products',
                'ec904_latest_posts' => 'cms_posts',
                'ec905_paint_products', 'ec905_tile_products' => 'cms_products',
                'ec905_projects', 'ec905_news' => 'cms_posts',
                'ec906_flash_sale', 'ec906_family_care', 'ec906_kitchen_products' => 'cms_products',
                'ec906_latest_posts' => 'cms_posts',
                'ec907_category_grid' => 'catalog_categories',
                'ec907_best_sellers', 'ec907_audio_showcase', 'ec907_gaming_products' => 'cms_products',
                'ec907_tech_news' => 'cms_posts',
                'ec908_category_rail' => 'catalog_categories',
                'ec908_best_sellers', 'ec908_accessory_products' => 'cms_products',
                'ec908_health_posts' => 'cms_posts',
                'ec909_category_cards' => 'catalog_categories',
                'ec909_headphone_showcase', 'ec909_headphone_products', 'ec909_earphone_products', 'ec909_recommendations' => 'cms_products',
                'ec909_latest_posts' => 'cms_posts',
                'ec910_promotions', 'ec910_product_tabs', 'ec910_mens_watches' => 'cms_products',
                'ec910_experience' => 'cms_posts',
                'spa111_services' => 'cms_services',
                'spa111_featured_products' => 'cms_products',
                'spa111_testimonials' => 'cms_testimonials',
                'spa111_team' => 'cms_team_members',
                'spa111_latest_posts' => 'cms_posts',
                'spa111_partners' => 'cms_partners',
                'nt502_categories' => 'catalog_categories',
                'nt502_promotion', 'nt502_living_room', 'nt502_bedroom' => 'cms_products',
                'nt502_latest_news' => 'cms_posts',
                'nt503_categories' => 'catalog_categories',
                'nt503_mattresses', 'nt503_flash_sale', 'nt503_kids_collection' => 'cms_products',
                'nt503_advice' => 'cms_posts',
                'nt504_spaces', 'nt504_product_categories', 'nt504_category_rail' => 'catalog_categories',
                'nt504_sale_products' => 'cms_products',
                'nt504_latest_news' => 'cms_posts',
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
            return $this->menuResolver->items(
                (string) ($settings['location'] ?? 'primary'),
                $block->landingPage?->website_key,
                $locale,
                (string) ($block->landingPage?->theme_key ?: $block->theme_key),
            );
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function cmsSectionSettingsSchema(array $definition): array
    {
        $blockType = (string) ($definition['block_type'] ?? '');
        $schema = (array) ($definition['settings_schema'] ?? []);

        if ($this->isCmsTestimonialBlock($blockType)) {
            return [
                'source' => [
                    'type' => 'select',
                    'label' => 'Nguồn dữ liệu',
                    'options' => [
                        ['value' => 'cms_testimonials', 'label' => 'Cảm nhận khách hàng CMS'],
                        ['value' => 'custom', 'label' => 'Nội dung tùy chỉnh'],
                    ],
                ],
                ...$schema,
            ];
        }

        if ($this->isCmsPartnerBlock($blockType)) {
            return [
                'source' => [
                    'type' => 'select',
                    'label' => 'Nguồn dữ liệu',
                    'options' => [
                        ['value' => 'cms_partners', 'label' => 'Đối tác CMS'],
                        ['value' => 'custom', 'label' => 'Nội dung tùy chỉnh'],
                    ],
                ],
                ...$schema,
            ];
        }

        return $schema;
    }

    private function isCmsTestimonialBlock(string $blockType): bool
    {
        $blockType = strtolower($blockType);

        return Str::contains($blockType, 'testimonial')
            || $blockType === 'ec902_video_reviews';
    }

    private function isCmsPartnerBlock(string $blockType): bool
    {
        return Str::contains(strtolower($blockType), 'partner');
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
            'cms_team_members' => $this->cmsTeamMemberItems($settings, $limit, $locale, $websiteKey),
            'cms_testimonials' => $this->cmsTestimonialItems($settings, $limit, $locale, $websiteKey),
            'cms_partners' => $this->cmsPartnerItems($settings, $limit, $locale, $websiteKey),
            'catalog_categories', 'cms_categories', 'cms_service_categories', 'cms_project_categories' => $this->featuredCategoryItems($settings, $limit, $locale, $websiteKey),
            'cms_menus' => $this->cmsMenuItems($settings, $limit, $locale, $websiteKey),
            'real_estate_listings' => $this->realEstateListingItems($settings, $limit, $locale, $websiteKey),
            'real_estate_property_types' => $this->realEstatePropertyTypeItems($limit, $locale, $websiteKey),
            default => $this->contentSourceItems([...$settings, 'source' => $defaultSource], $defaultSource, $limit, $locale, $websiteKey),
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function realEstateListingItems(array $settings, int $limit, string $locale, ?string $websiteKey): array
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

        return $query->take($limit)->get()->map(function (RealEstateListing $listing) use ($locale, $websiteKey): array {
            $listing = $this->localizedContent->localize(
                $listing,
                'real_estate_listing',
                $locale,
                $websiteKey,
            );
            if ($listing->propertyType !== null) {
                $listing->setRelation('propertyType', $this->localizedContent->localize(
                    $listing->propertyType,
                    'real_estate_property_type',
                    $locale,
                    $websiteKey,
                ));
            }
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
    private function realEstatePropertyTypeItems(int $limit, string $locale, ?string $websiteKey): array
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
            ->map(function (RealEstatePropertyType $type, int $index) use ($locale, $websiteKey): array {
                $type = $this->localizedContent->localize(
                    $type,
                    'real_estate_property_type',
                    $locale,
                    $websiteKey,
                );

                return [
                    'id' => $type->id,
                    'title' => $type->name,
                    'summary' => $type->description,
                    'image' => $type->image_url ?: $this->fallbackCategoryImage($index),
                    'icon' => $type->icon ?: 'fa-solid fa-building',
                    'count_label' => $type->listings_count.' dự án',
                    'url' => FrontendRouteUrl::realEstate($locale).'?property_type='.rawurlencode($type->slug),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private function cmsMenuItems(
        array $settings,
        int $limit,
        string $locale,
        ?string $websiteKey,
    ): array {
        if (! Schema::hasTable('cms_menus')) {
            return [];
        }

        $location = trim((string) ($settings['menu_location'] ?? 'primary-navigation')) ?: 'primary-navigation';

        return collect($this->menuResolver->items(
            $location,
            $websiteKey,
            $locale,
            is_string($settings['theme_key'] ?? null)
                ? $settings['theme_key']
                : null,
        ))
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
            ->when(filled($settings['search'] ?? null), function (Builder $query) use ($settings): void {
                $search = '%'.trim((string) $settings['search']).'%';
                $query->where(fn (Builder $nested) => $nested
                    ->where('name', 'like', $search)
                    ->orWhere('slug', 'like', $search));
            })
            ->withCount(['products' => fn (Builder $query) => $query->where('is_active', true)])
            ->when(
                ($settings['order'] ?? null) === 'sort_order',
                fn (Builder $query) => $query->orderBy('sort_order'),
                fn (Builder $query) => $query->orderByDesc('products_count')->orderBy('sort_order'),
            )
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
    private function cmsTestimonialItems(
        array $settings,
        int $limit,
        string $locale,
        ?string $websiteKey,
        ?string $themeKey = null,
    ): array {
        if (! Schema::hasTable('cms_testimonials')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsTestimonial::query()
            ->where('status', 'published');
        $this->scopeThemeDemoRecords($query, CmsTestimonial::class, $themeKey);
        $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('updated_at');

        if (($settings['featured_only'] ?? true) === true) {
            $query->where('is_featured', true);
        }

        $resolvedWebsiteKey = (string) ($websiteKey ?: 'website-main');

        return $query->take($limit)->get()->map(function (CmsTestimonial $testimonial) use ($resolvedWebsiteKey, $locale): array {
            $name = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.name', $testimonial->id), $testimonial->name);
            $quote = $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.quote', $testimonial->id), $testimonial->quote);

            return [
                'name' => $name,
                'title' => $name,
                'role' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.role', $testimonial->id), $testimonial->role),
                'company' => $this->contentText($resolvedWebsiteKey, $locale, sprintf('cms_testimonial.%d.company', $testimonial->id), $testimonial->company),
                'quote' => $quote,
                'summary' => $quote,
                'description' => $quote,
                'image' => $testimonial->image_url,
                'avatar' => $testimonial->image_url,
                'alt' => $testimonial->image_alt ?: $testimonial->name,
                'url' => $testimonial->link_url ?: '#lien-he',
            ];
        })->all();
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
    private function cmsPartnerItems(
        array $settings,
        int $limit,
        string $locale,
        ?string $websiteKey,
        ?string $themeKey = null,
    ): array {
        if (! Schema::hasTable('cms_partners')) {
            return [];
        }

        /** @var Builder $query */
        $query = CmsPartner::query()
            ->where('status', 'published');
        $this->scopeThemeDemoRecords($query, CmsPartner::class, $themeKey);
        $query
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
            'logo' => $partner->image_url,
            'alt' => $partner->image_alt ?: $partner->title,
            'href' => $partner->link_url ?: '#top',
            'url' => $partner->link_url ?: '#top',
        ])->all();
    }

    /**
     * Keep reusable user content and demo rows of the current theme, while
     * preventing demo data from another installed theme leaking into a block.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function scopeThemeDemoRecords(
        Builder $query,
        string $modelClass,
        ?string $themeKey,
    ): void {
        $themeKey = strtoupper(trim((string) $themeKey));

        if ($themeKey === '') {
            return;
        }

        $records = ThemeDemoRecord::query()
            ->where('model_type', $modelClass);
        $currentIds = (clone $records)
            ->where('theme_key', $themeKey)
            ->pluck('model_id')
            ->filter()
            ->values()
            ->all();

        $query->whereNotIn(
            $query->getModel()->getQualifiedKeyName(),
            (clone $records)
                ->where('theme_key', '!=', $themeKey)
                ->select('model_id'),
        );

        if ($currentIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($currentIds), '?'));
            $query->orderByRaw(
                sprintf(
                    'CASE WHEN %s IN (%s) THEN 0 ELSE 1 END',
                    $query->getModel()->getQualifiedKeyName(),
                    $placeholders,
                ),
                $currentIds,
            );
        }
    }

    private function contentText(string $websiteKey, string $locale, string $key, ?string $fallback): ?string
    {
        $value = $this->localizedContent->textByKey(
            $websiteKey,
            FrontendLocalization::resolveLocale($locale),
            $key,
        );

        if ($value !== null) {
            return $value;
        }

        if (! Schema::hasTable('theme_translations')) {
            return $fallback;
        }

        $legacyValue = ThemeTranslation::query()
            ->where('theme_key', 'site-content:'.strtolower(trim($websiteKey) !== '' ? $websiteKey : 'default'))
            ->where('locale', FrontendLocalization::resolveLocale($locale))
            ->where('group', 'content')
            ->where('translation_key', $key)
            ->publishedTranslation()
            ->value('value');

        return filled($legacyValue) ? (string) $legacyValue : $fallback;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultBlocksForTheme(string $themeKey): array
    {
        return match (strtoupper($themeKey)) {
            'SHOP605' => $this->shop605DefaultBlocks(),
            'SHOP606' => $this->shop606DefaultBlocks(),
            'CA0050' => $this->ca0050DefaultBlocks(),
            'SHOP604' => $this->shop604DefaultBlocks(),
            'SHOP603' => $this->shop603DefaultBlocks(),
            'SHOP602' => $this->shop602DefaultBlocks(),
            'SHOP601' => $this->shop601DefaultBlocks(),
            'EC900' => $this->ec900DefaultBlocks(),
            'EC901' => $this->ec901DefaultBlocks(),
            'EC902' => $this->ec902DefaultBlocks(),
            'EC903' => $this->ec903DefaultBlocks(),
            'EC904' => $this->ec904DefaultBlocks(),
            'EC905' => $this->ec905DefaultBlocks(),
            'EC906' => $this->ec906DefaultBlocks(),
            'EC907' => $this->ec907DefaultBlocks(),
            'EC908' => $this->ec908DefaultBlocks(),
            'EC909' => $this->ec909DefaultBlocks(),
            'EC910' => $this->ec910DefaultBlocks(),
            'EC911' => $this->ec911DefaultBlocks(),
            'EC912' => $this->ec912DefaultBlocks(),
            'EC913' => $this->ec913DefaultBlocks(),
            'EC914' => $this->ec914DefaultBlocks(),
            'EC915' => $this->ec915DefaultBlocks(),
            'BOOK920' => $this->book920DefaultBlocks(),
            'EC916' => $this->ec916DefaultBlocks(),
            'EC917' => $this->ec917DefaultBlocks(),
            'FOOT404' => $this->foot404DefaultBlocks(),
            'FOOT405' => $this->foot405DefaultBlocks(),
            'FOOT406' => $this->foot406DefaultBlocks(),
            'FOOT407' => $this->foot407DefaultBlocks(),
            'FOOT408' => $this->foot408DefaultBlocks(),
            'FOOT409' => $this->foot409DefaultBlocks(),
            'TH0050' => $this->th0050DefaultBlocks(),
            'SER0101' => $this->legacyServiceDefaultBlocks($themeKey),
            'SER102' => $this->ser102DefaultBlocks(),
            'SER103' => $this->ser103DefaultBlocks(),
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
            'NT504' => $this->nt504DefaultBlocks(),
            'XD321' => $this->xd321DefaultBlocks(),
            'XD0322' => $this->xd0322DefaultBlocks(),
            'XD0323' => $this->xd0323EuroFarmDefaultBlocks(),
            'XD0324' => $this->xd0324DefaultBlocks(),
            'XD0325' => $this->xd0325DefaultBlocks(),
            'DN202' => $this->dn202DefaultBlocks(),
            'DN302' => $this->dn302DefaultBlocks(),
            'DN350' => $this->dn350DefaultBlocks(),
            'DN351' => $this->dn351DefaultBlocks(),
            'BZ501' => $this->bz501DefaultBlocks(),
            'SPA502' => $this->spa502DefaultBlocks(),
            'SPA111' => $this->spa111DefaultBlocks(),
            'BDS701' => $this->bds701DefaultBlocks(),
            'BDS702' => $this->bds702DefaultBlocks(),
            'DL750' => $this->dl750DefaultBlocks(),
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
    private function shop606DefaultBlocks(): array
    {
        $preview = '/theme-previews/SHOP606/preview-shop606.svg';
        $hero = '/theme-demo/shop604/hero-fashion.png';
        $fashion = '/theme-demo/shop605/hero-fashion.png';
        $beige = '/theme-demo/shop604/product-women-knit.png';
        $rose = '/theme-demo/shop604/product-women-rose.png';
        $navy = '/theme-demo/shop604/product-men-green.png';
        $editorial = '/theme-demo/shop604/ad-lac-quan.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $sourceSchema = fn (string $source, string $label, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => $source, 'label' => $label], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật', 'default' => false],
        ];
        $products = [
            ['title' => 'Đầm xếp ly cổ V thanh lịch', 'summary' => 'SORIA', 'price' => 950000, 'original_price' => 1200000, 'image' => $beige, 'url' => '#san-pham'],
            ['title' => 'Đầm midi kèm thắt lưng', 'summary' => 'SORIA', 'price' => 890000, 'original_price' => 1100000, 'image' => $rose, 'url' => '#san-pham'],
            ['title' => 'Đầm dạo phố cổ V', 'summary' => 'SORIA', 'price' => 790000, 'original_price' => 850000, 'image' => $navy, 'url' => '#san-pham'],
            ['title' => 'Đầm công sở thanh lịch', 'summary' => 'LOVINA', 'price' => 2500000, 'original_price' => 2950000, 'image' => $beige, 'url' => '#san-pham'],
        ];
        $categories = [
            ['title' => 'Đầm cao cấp', 'image' => $beige, 'url' => '#san-pham'],
            ['title' => 'Túi xách', 'image' => $rose, 'url' => '#san-pham'],
            ['title' => 'Áo khoác & Blazer', 'image' => $navy, 'url' => '#san-pham'],
            ['title' => 'Phụ kiện', 'image' => $editorial, 'url' => '#san-pham'],
        ];
        $benefits = [
            ['title' => 'Giao hỏa tốc', 'summary' => 'Nhanh chóng và an toàn', 'icon' => 'fa-solid fa-truck-fast'],
            ['title' => 'Đổi trả miễn phí', 'summary' => 'Trong vòng 30 ngày', 'icon' => 'fa-solid fa-box-open'],
            ['title' => 'Hỗ trợ 24/7', 'summary' => 'Luôn sẵn sàng đồng hành', 'icon' => 'fa-regular fa-credit-card'],
            ['title' => 'Ưu đãi hấp dẫn', 'summary' => 'Khuyến mãi được cập nhật', 'icon' => 'fa-solid fa-bag-shopping'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero thời trang', 'description' => 'Slider toàn màn hình cho chiến dịch thời trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'shop606-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => $hero], 'data' => ['vi' => array_merge($heading('TÚI XÁCH THỜI TRANG', null, 'Những thiết kế giản dị, tinh tế và thu hút', 'Mua ngay'), ['content' => ['slides' => [['title' => 'TÚI XÁCH THỜI TRANG', 'summary' => 'Những thiết kế giản dị, tinh tế và thu hút', 'button_label' => 'Mua ngay', 'image' => $hero, 'link_url' => '#san-pham']]]]), 'en' => $heading('FASHION BAGS', null, 'Simple, elegant and magnetic designs', 'Shop now')]],
            ['block_type' => 'shop606_collections', 'label' => 'Khám phá bộ sưu tập', 'description' => 'Bốn danh mục thời trang lấy từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $sourceSchema('catalog_categories', 'Danh mục Catalog', 4), 'data' => ['vi' => $withItems($heading('Khám phá các bộ sưu tập'), $categories), 'en' => $withItems($heading('Explore collections'), $categories)]],
            ['block_type' => 'shop606_sale', 'label' => 'Ưu đãi có giới hạn', 'description' => 'Flash sale với bộ đếm và bốn sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false, 'countdown_hours' => 24], 'settings_schema' => array_merge($sourceSchema('cms_products', 'Sản phẩm Catalog', 4), ['countdown_hours' => ['type' => 'number', 'label' => 'Thời lượng đếm ngược (giờ)', 'default' => 24]]), 'data' => ['vi' => $withItems($heading('Ưu đãi có giới hạn', 'Nhanh lên nào!', 'Sự kiện sắp kết thúc'), $products), 'en' => $withItems($heading('Limited offer'), $products)]],
            ['block_type' => 'shop606_feature', 'label' => 'Sản phẩm nổi bật', 'description' => 'Ảnh editorial và nội dung giới thiệu sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'noi-bat', 'media' => ['image' => $fashion], 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading('Sản phẩm nổi bật', null, 'Phom dáng hiện đại, chất liệu mềm mại và hoàn thiện chỉn chu tạo nên một thiết kế dễ mặc trong nhiều dịp.', 'Xem ngay'), [['title' => 'Chất liệu cao cấp, thoáng khí'], ['title' => 'Kiểu dáng năng động, thanh lịch'], ['title' => 'Dễ dàng phối cùng nhiều phong cách']]), 'en' => $heading('Featured product', null, 'Modern form and carefully selected materials.', 'View now')]],
            ['block_type' => 'shop606_new', 'label' => 'Hàng mới về', 'description' => 'Bốn sản phẩm mới nhất từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_products', 'Sản phẩm Catalog', 4), 'data' => ['vi' => $withItems($heading('Hàng mới về'), $products), 'en' => $withItems($heading('New arrivals'), $products)]],
            ['block_type' => 'shop606_campaign', 'label' => 'Banner bộ sưu tập', 'description' => 'Banner editorial toàn chiều rộng.', 'preview_image' => $preview, 'anchor_id' => 'chien-dich', 'media' => ['image' => $editorial], 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('SẮC MÀU THANH XUÂN', 'Bộ sưu tập mới', null, 'Xem bộ sưu tập'), 'en' => $heading('COLORS OF YOUTH', 'New collection', null, 'Explore')]],
            ['block_type' => 'shop606_outfit', 'label' => 'Mua trọn bộ trang phục', 'description' => 'Ảnh phối đồ và ba sản phẩm liên quan.', 'preview_image' => $preview, 'anchor_id' => 'phoi-do', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'featured_only' => false, 'feature_image' => $fashion], 'settings_schema' => array_merge($sourceSchema('cms_products', 'Sản phẩm Catalog', 3), ['feature_image' => ['type' => 'image', 'label' => 'Ảnh phối đồ']]), 'media' => ['image' => $fashion], 'data' => ['vi' => $withItems($heading('Mua trọn bộ trang phục', null, null, 'Thêm tất cả'), array_slice($products, 0, 3)), 'en' => $withItems($heading('Shop the look', null, null, 'Add all'), array_slice($products, 0, 3))]],
            ['block_type' => 'shop606_news', 'label' => 'Tin tức thời trang', 'description' => 'Ba bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_posts', 'Bài viết CMS', 3), 'data' => ['vi' => $withItems($heading('Tin tức'), [['title' => 'Bí quyết chăm sóc trang phục bền đẹp', 'summary' => 'Những lưu ý đơn giản giúp trang phục luôn giữ phom.', 'image' => $beige], ['title' => 'Xu hướng phối đồ thanh lịch', 'summary' => 'Cảm hứng mới cho tủ đồ hằng ngày.', 'image' => $rose], ['title' => 'Phong cách cá nhân và sự tự tin', 'summary' => 'Chọn thiết kế phù hợp với chính bạn.', 'image' => $navy]]), 'en' => $heading('News')]],
            ['block_type' => 'shop606_gallery', 'label' => 'Bộ sưu tập hình ảnh', 'description' => 'Gallery ba ảnh phong cách.', 'preview_image' => $preview, 'anchor_id' => 'thu-vien', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading('Bộ sưu tập'), [['title' => 'Phong cách mùa hè', 'image' => $rose], ['title' => 'Thanh lịch hiện đại', 'image' => $fashion], ['title' => 'Dạo phố tự tin', 'image' => $hero]]), 'en' => $heading('Gallery')]],
            ['block_type' => 'shop606_benefits', 'label' => 'Tiện ích mua sắm', 'description' => 'Bốn cam kết dịch vụ trước footer.', 'preview_image' => $preview, 'anchor_id' => 'tien-ich', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $withItems($heading(), $benefits), 'en' => $withItems($heading(), $benefits)]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function shop605DefaultBlocks(): array
    {
        $preview = '/theme-previews/SHOP605/preview-shop605.svg';
        $hero = '/theme-demo/shop605/hero-fashion.png';
        $a = '/theme-demo/shop605/product-women-knit.png';
        $b = '/theme-demo/shop605/product-women-rose.png';
        $c = '/theme-demo/shop605/product-men-green.png';
        $d = '/theme-demo/shop605/ad-lac-quan.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description];
        $with = fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $schema = fn (int $limit): array => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit], 'search' => ['type' => 'text', 'label' => 'Từ khóa'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false]];
        $products = [['title' => 'Áo ngực su không gọng FA05072292', 'price' => 359000, 'original_price' => 429000, 'image' => $a, 'url' => '#'], ['title' => 'Đồ mặc nhà cotton DMN02083646', 'price' => 399000, 'original_price' => 429000, 'image' => $b, 'url' => '#'], ['title' => 'Bộ mặc nhà lụa DH04013279', 'price' => 559000, 'image' => $c, 'url' => '#'], ['title' => 'Áo ngực ren không gọng', 'price' => 329000, 'image' => $d, 'url' => '#']];
        $benefits = [['title' => 'Freeship toàn quốc đơn > 499k', 'icon' => 'fa-solid fa-truck'], ['title' => 'Kiểm hàng trước khi thanh toán', 'icon' => 'fa-solid fa-list-check'], ['title' => 'Hỗ trợ đóng gói miễn phí', 'icon' => 'fa-regular fa-bookmark']];
        $collections = [['title' => 'BST Đến bên em', 'image' => $a], ['title' => 'BST Mùa yêu dấu', 'image' => $b], ['title' => 'Giải thưởng Top 100', 'image' => $d], ['title' => 'BST Em Xinh', 'image' => $c], ['title' => 'BST Thiết yếu', 'image' => $a], ['title' => 'BST Tơ vương', 'image' => $b]];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero gallery OH!Under', 'description' => 'Ba ảnh lifestyle trên nền hồng.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'shop605-hero-slider', 'limit' => 3], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement'], 'limit' => ['type' => 'number', 'label' => 'Số ảnh']], 'media' => ['image' => $hero], 'data' => ['vi' => array_merge($heading('OH!Under'), ['content' => ['slides' => $products]]), 'en' => $heading('OH!Under')]],
            ['block_type' => 'shop605_benefits', 'label' => 'Quyền lợi mua hàng', 'description' => 'Ba quyền lợi ngay dưới hero.', 'preview_image' => $preview, 'anchor_id' => 'quyen-loi', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $with($heading(), $benefits), 'en' => $with($heading(), $benefits)]],
            ['block_type' => 'shop605_sale', 'label' => 'End of season sale', 'description' => 'Bốn sản phẩm sale và bộ đếm.', 'preview_image' => $preview, 'anchor_id' => 'sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $schema(4), 'data' => ['vi' => $with($heading('END OF SEASON SALE - MUA 1 TẶNG 1'), $products), 'en' => $with($heading('END OF SEASON SALE'), $products)]],
            ['block_type' => 'shop605_new', 'label' => 'Sản phẩm mới', 'description' => 'Danh mục bên trái và ba sản phẩm dọc.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $schema(3), 'data' => ['vi' => $with($heading('Sản phẩm mới'), $products), 'en' => $with($heading('New products'), $products)]],
            ['block_type' => 'shop605_best', 'label' => 'Sản phẩm bán chạy', 'description' => 'Lưới mười sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'featured_only' => false], 'settings_schema' => $schema(10), 'data' => ['vi' => $with($heading('Sản phẩm bán chạy'), $products), 'en' => $with($heading('Best sellers'), $products)]],
            ['block_type' => 'shop605_editorial', 'label' => 'BST Mùa yêu dấu', 'description' => 'Banner editorial toàn chiều rộng.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'media' => ['image' => $hero], 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('Mùa yêu dấu', null, 'Hãy thả mình vào tiết trời xuân hè với những mẫu sản phẩm không thể xinh yêu hơn tại OH!Under.'), 'en' => $heading('Beloved season')]],
            ['block_type' => 'shop605_collections', 'label' => 'OH!Under Collection', 'description' => 'Mosaic sáu bộ sưu tập.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $with($heading('OH!Under Collection'), $collections), 'en' => $with($heading('OH!Under Collection'), $collections)]],
            ['block_type' => 'shop605_story', 'label' => 'Khách hàng cảm nhận', 'description' => 'Câu chuyện thương hiệu và khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'cam-nhan', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('Khách hàng cảm nhận gì về OH! Under', null, 'Với OH!Under, sự thành công đến từ đam mê, sáng tạo và nỗ lực thấu hiểu, trân trọng phái đẹp.'), 'en' => $heading('Customer stories')]],
            ['block_type' => 'latest_posts', 'label' => 'Blog & Chia sẻ', 'description' => 'Ba bài viết mới nhất.', 'preview_image' => $preview, 'anchor_id' => 'blog', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3], 'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số bài']], 'data' => ['vi' => $heading('Blog & Chia sẻ'), 'en' => $heading('Blog & Stories')]],
            ['block_type' => 'shop605_footer', 'label' => 'Footer và nhận tin', 'description' => 'Liên hệ, hướng dẫn và newsletter.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => [], 'settings_schema' => [], 'data' => ['vi' => $heading('NHẬN TIN KHUYẾN MÃI'), 'en' => $heading('NEWSLETTER')]],
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
    private function ec900DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC900/preview-ec900.webp';
        $heroImage = '/theme-demo/ec900/hero-appliances.webp';
        $promoImage = '/theme-demo/ec900/home-promo.webp';
        $tvImage = '/theme-demo/ec900/tv-lifestyle.webp';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $productFallbacks = [
            ['title' => 'Máy lọc không khí AirPure 360', 'image' => '/theme-demo/ec900/air-purifier.webp', 'price' => 5490000, 'original_price' => 8260000, 'url' => '#'],
            ['title' => 'Nồi chiên không dầu HeatFlow 5L', 'image' => '/theme-demo/ec900/air-fryer.webp', 'price' => 1990000, 'original_price' => 3490000, 'url' => '#'],
            ['title' => 'Máy giặt Inverter SmartCare 11kg', 'image' => '/theme-demo/ec900/washing-machine.webp', 'price' => 9900000, 'original_price' => 11300000, 'url' => '#'],
            ['title' => 'Robot hút bụi CleanMate X20+', 'image' => '/theme-demo/ec900/robot-vacuum.webp', 'price' => 8990000, 'original_price' => 12700000, 'url' => '#'],
            ['title' => 'Máy rửa chén MiniWash', 'image' => '/theme-demo/ec900/dishwasher.webp', 'price' => 6100000, 'original_price' => 9800000, 'url' => '#'],
        ];
        $categories = [
            ['title' => 'Tủ lạnh', 'image' => '/theme-demo/ec900/refrigerator.webp', 'url' => '#'],
            ['title' => 'Máy giặt & sấy', 'image' => '/theme-demo/ec900/washing-machine.webp', 'url' => '#'],
            ['title' => 'Thiết bị làm sạch', 'image' => '/theme-demo/ec900/robot-vacuum.webp', 'url' => '#'],
            ['title' => 'Chăm sóc không khí', 'image' => '/theme-demo/ec900/air-purifier.webp', 'url' => '#'],
            ['title' => 'Gia dụng bếp', 'image' => '/theme-demo/ec900/air-fryer.webp', 'url' => '#'],
            ['title' => 'Máy rửa bát', 'image' => '/theme-demo/ec900/dishwasher.webp', 'url' => '#'],
        ];
        $needs = [
            ['group' => 'Nấu cơm sang, mịn', 'title' => 'Nồi chiên thông minh', 'image' => '/theme-demo/ec900/air-fryer.webp', 'url' => '#'],
            ['group' => 'Nấu cơm sang, mịn', 'title' => 'Bếp đa năng', 'image' => '/theme-demo/ec900/air-fryer.webp', 'url' => '#'],
            ['group' => 'Nấu cơm sang, mịn', 'title' => 'Nồi cơm điện tử', 'image' => '/theme-demo/ec900/air-fryer.webp', 'url' => '#'],
            ['group' => 'Nấu cơm sang, mịn', 'title' => 'Máy rửa chén', 'image' => '/theme-demo/ec900/dishwasher.webp', 'url' => '#'],
            ['group' => 'Sống khỏe mỗi ngày', 'title' => 'Robot hút bụi', 'image' => '/theme-demo/ec900/robot-vacuum.webp', 'url' => '#'],
            ['group' => 'Sống khỏe mỗi ngày', 'title' => 'Máy lọc không khí', 'image' => '/theme-demo/ec900/air-purifier.webp', 'url' => '#'],
            ['group' => 'Sống khỏe mỗi ngày', 'title' => 'Máy giặt Inverter', 'image' => '/theme-demo/ec900/washing-machine.webp', 'url' => '#'],
            ['group' => 'Sống khỏe mỗi ngày', 'title' => 'Máy hút bụi', 'image' => '/theme-demo/ec900/robot-vacuum.webp', 'url' => '#'],
            ['group' => 'Nhà nhỏ sắm đồ gọn', 'title' => 'Tủ lạnh 2 cửa', 'image' => '/theme-demo/ec900/refrigerator.webp', 'url' => '#'],
            ['group' => 'Nhà nhỏ sắm đồ gọn', 'title' => 'Máy giặt gọn', 'image' => '/theme-demo/ec900/washing-machine.webp', 'url' => '#'],
            ['group' => 'Nhà nhỏ sắm đồ gọn', 'title' => 'Máy rửa chén mini', 'image' => '/theme-demo/ec900/dishwasher.webp', 'url' => '#'],
            ['group' => 'Nhà nhỏ sắm đồ gọn', 'title' => 'Lọc khí phòng nhỏ', 'image' => '/theme-demo/ec900/air-purifier.webp', 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero và menu danh mục', 'description' => 'Slider đầu trang đặt cạnh menu danh mục sản phẩm bên trái.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec900-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => $heroImage], 'data' => ['vi' => array_merge($heading('Đặc quyền gia dụng thông minh', 'Ưu đãi tháng này', 'Bảo hành tận tâm, trả góp 0% lãi suất.', 'Mua ngay'), ['content' => ['slides' => [['title' => 'Đặc quyền gia dụng thông minh', 'summary' => 'Bảo hành tận tâm, trả góp 0% lãi suất.', 'button_label' => 'Mua ngay', 'image' => $heroImage, 'link_url' => '#san-pham-ban-chay']]]]), 'en' => $heading('Smart home exclusives', 'Monthly offers', 'Dedicated warranty and flexible payments.', 'Shop now')]],
            ['block_type' => 'ec900_featured_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Danh mục động, đồng thời cấp dữ liệu cho menu danh mục cạnh hero.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc-noi-bat', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 12, 'search' => 'ec900-', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 12], 'search' => ['type' => 'text', 'label' => 'Từ khóa tên hoặc slug']], 'data' => ['vi' => $withItems($heading('Danh mục nổi bật'), $categories), 'en' => $withItems($heading('Featured categories'), $categories)]],
            ['block_type' => 'ec900_best_sellers', 'label' => 'Top sản phẩm bán chạy', 'description' => 'Lưới sản phẩm nổi bật trên nền cam khuyến mãi.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC900-', 'featured_only' => true], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $withItems($heading('Top sản phẩm bán chạy', null, null, 'Xem thêm sản phẩm'), $productFallbacks), 'en' => $withItems($heading('Best sellers', null, null, 'View more'), $productFallbacks)]],
            ['block_type' => 'ec900_need_mosaic', 'label' => 'Lựa chọn theo nhu cầu', 'description' => 'Banner ưu đãi và nhóm sản phẩm theo từng nhu cầu sử dụng.', 'preview_image' => $preview, 'anchor_id' => 'lua-chon-phu-hop', 'settings' => ['feature_image' => $promoImage, 'feature_url' => '#san-pham-dac-quyen'], 'settings_schema' => ['feature_image' => ['type' => 'text', 'label' => 'Ảnh ưu đãi lớn'], 'feature_url' => ['type' => 'text', 'label' => 'Liên kết ảnh lớn']], 'media' => ['image' => $promoImage], 'data' => ['vi' => $withItems($heading('Lựa chọn phù hợp với mọi nhu cầu', 'Giảm đến 3,1 triệu'), $needs), 'en' => $withItems($heading('Made for every need', 'Save up to 3.1 million'), $needs)]],
            ['block_type' => 'ec900_campaign_mosaic', 'label' => 'Mosaic chiến dịch', 'description' => 'Ba banner quảng cáo lớn nhỏ sắp theo dạng mosaic.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'data' => ['vi' => $withItems($heading('Nhà gọn gàng, việc nhà nhẹ tênh'), [['title' => 'Nâng cấp tổ ấm thông minh', 'image' => $promoImage, 'url' => '#san-pham-dac-quyen'], ['title' => 'Giặt sạch sâu, tiết kiệm điện', 'image' => $heroImage, 'url' => '#san-pham-dac-quyen'], ['title' => 'Thiết bị đồng bộ cho gia đình', 'image' => $tvImage, 'url' => '#thuong-hieu']]), 'en' => $withItems($heading('A tidy home, effortless chores'), [['title' => 'Upgrade your smart home', 'image' => $promoImage]])]],
            ['block_type' => 'ec900_exclusive_products', 'label' => 'Sản phẩm đặc quyền', 'description' => 'Lưới mười sản phẩm kèm thanh danh mục nhanh.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-dac-quyen', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'search' => 'EC900-', 'featured_only' => false], 'settings_schema' => $productSchema(10), 'data' => ['vi' => $withItems($heading('Sản phẩm đặc quyền'), array_merge($productFallbacks, $productFallbacks)), 'en' => $withItems($heading('Exclusive products'), $productFallbacks)]],
            ['block_type' => 'ec900_brand_banner', 'label' => 'Banner thương hiệu', 'description' => 'Banner ngang toàn chiều rộng cho chiến dịch thương hiệu.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading(), [['eyebrow' => 'CÔNG NGHỆ CHO TỔ ẤM', 'title' => 'Hình ảnh đẹp, trải nghiệm khác biệt', 'image' => $tvImage, 'url' => '#san-pham-dac-quyen']]), 'en' => $withItems($heading(), [['eyebrow' => 'TECHNOLOGY FOR HOME', 'title' => 'Beautiful picture, a different experience', 'image' => $tvImage, 'url' => '#san-pham-dac-quyen']])]],
            ['block_type' => 'ec900_advice_posts', 'label' => 'Tư vấn sản phẩm', 'description' => 'Một bài tư vấn lớn và ba bài mới từ hệ thống tin tức.', 'preview_image' => $preview, 'anchor_id' => 'tu-van-san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài', 'default' => 4], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin']], 'data' => ['vi' => $heading('Tư vấn sản phẩm'), 'en' => $heading('Product advice')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec901DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC901/preview-ec901.webp';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $products = [
            ['title' => 'Tempo Classic Silver 40mm', 'image' => '/theme-demo/ec901/classic-silver.webp', 'price' => 4050000, 'original_price' => 4590000, 'url' => '#'],
            ['title' => 'Aurelius Open Heart Rose 42mm', 'image' => '/theme-demo/ec901/automatic-rose.webp', 'price' => 12890000, 'original_price' => 16990000, 'url' => '#'],
            ['title' => 'Norden Sport Chronograph 44mm', 'image' => '/theme-demo/ec901/sport-black.webp', 'price' => 24500000, 'original_price' => 28990000, 'url' => '#'],
            ['title' => 'Vela Lady Pearl 28mm', 'image' => '/theme-demo/ec901/women-pink.webp', 'price' => 3589000, 'original_price' => 4200000, 'url' => '#'],
            ['title' => 'Tempo Smart One 44mm', 'image' => '/theme-demo/ec901/smartwatch-black.webp', 'price' => 6890000, 'original_price' => 7900000, 'url' => '#'],
            ['title' => 'Monarch Diver Blue 41mm', 'image' => '/theme-demo/ec901/diver-blue.webp', 'price' => 23690000, 'original_price' => 29530000, 'url' => '#'],
        ];
        $categories = [
            ['title' => 'Đồng hồ nam', 'image' => '/theme-demo/ec901/watch-men.webp', 'url' => '#ban-chay'],
            ['title' => 'Đồng hồ nữ', 'image' => '/theme-demo/ec901/watch-women.webp', 'url' => '#ban-chay'],
            ['title' => 'Đồng hồ trẻ em', 'image' => '/theme-demo/ec901/women-pink.webp', 'url' => '#ban-chay'],
            ['title' => 'Phụ kiện đồng hồ', 'image' => '/theme-demo/ec901/automatic-rose.webp', 'url' => '#ban-chay'],
            ['title' => 'Smart watch', 'image' => '/theme-demo/ec901/smartwatch-black.webp', 'url' => '#ban-chay'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero bộ sưu tập đồng hồ', 'description' => 'Slider tràn chiều rộng với thông điệp ưu đãi và nút mua hàng.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec901-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Khoảnh khắc tạo nên phong cách', 'TEMPO SIGNATURE', 'Bộ sưu tập đồng hồ cơ khí dành cho những dấu ấn khác biệt.', 'Khám phá ngay'), ['content' => ['slides' => [['title' => 'Khoảnh khắc tạo nên phong cách', 'summary' => 'Bộ sưu tập đồng hồ cơ khí dành cho những dấu ấn khác biệt.', 'button_label' => 'Khám phá ngay', 'image' => '/theme-demo/ec901/hero-watches.webp', 'link_url' => '#deal-chop-nhoang']]]]), 'en' => $heading('Time defines style', 'TEMPO SIGNATURE', 'Mechanical timepieces made for distinctive moments.', 'Explore now')]],
            ['block_type' => 'ec901_flash_deals', 'label' => 'Deal chớp nhoáng', 'description' => 'Carousel sản phẩm có đồng hồ đếm ngược trên nền đỏ.', 'preview_image' => $preview, 'anchor_id' => 'deal-chop-nhoang', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC901-', 'featured_only' => false, 'ends_at' => now()->addDays(7)->toIso8601String()], 'settings_schema' => array_merge($productSchema(5), ['ends_at' => ['type' => 'text', 'label' => 'Thời điểm kết thúc']]), 'data' => ['vi' => $withItems($heading('Deal chớp nhoáng'), array_slice($products, 0, 5)), 'en' => $withItems($heading('Flash deals'), array_slice($products, 0, 5))]],
            ['block_type' => 'ec901_featured_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Năm danh mục đồng hồ hiển thị dạng thẻ ảnh ngang.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 5, 'search' => 'ec901-', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 5], 'search' => ['type' => 'text', 'label' => 'Từ khóa tên hoặc slug']], 'data' => ['vi' => $withItems($heading('Danh mục nổi bật'), $categories), 'en' => $withItems($heading('Featured categories'), $categories)]],
            ['block_type' => 'ec901_best_sellers', 'label' => 'Sản phẩm bán chạy', 'description' => 'Carousel năm sản phẩm nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC901-', 'featured_only' => true], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $withItems($heading('Sản phẩm bán chạy'), array_slice($products, 0, 5)), 'en' => $withItems($heading('Best sellers'), array_slice($products, 0, 5))]],
            ['block_type' => 'ec901_gender_banners', 'label' => 'Bộ sưu tập nam và nữ', 'description' => 'Hai banner phong cách dành cho nam và nữ.', 'preview_image' => $preview, 'anchor_id' => 'phong-cach', 'data' => ['vi' => $withItems($heading(), [['title' => 'Đồng hồ cho nam', 'image' => '/theme-demo/ec901/watch-men.webp', 'url' => '#san-pham'], ['title' => 'Đồng hồ cho nữ', 'image' => '/theme-demo/ec901/watch-women.webp', 'url' => '#san-pham']]), 'en' => $withItems($heading(), [['title' => 'Watches for men', 'image' => '/theme-demo/ec901/watch-men.webp'], ['title' => 'Watches for women', 'image' => '/theme-demo/ec901/watch-women.webp']])]],
            ['block_type' => 'ec901_promotion_mosaic', 'label' => 'Khuyến mãi nổi bật', 'description' => 'Mosaic một banner lớn và hai banner nhỏ.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'data' => ['vi' => $withItems($heading('Khuyến mãi nổi bật'), [['title' => 'Bộ ba cơ khí cuốn hút', 'summary' => 'Ưu đãi đến 35%', 'image' => '/theme-demo/ec901/promo-main.webp', 'url' => '#san-pham'], ['title' => 'Sắc vàng thanh lịch', 'summary' => 'Quà tặng dây da', 'image' => '/theme-demo/ec901/promo-gold.webp', 'url' => '#san-pham'], ['title' => 'Tôn vinh dấu ấn', 'summary' => 'Trả góp 0%', 'image' => '/theme-demo/ec901/promo-red.webp', 'url' => '#san-pham']]), 'en' => $heading('Featured promotions')]],
            ['block_type' => 'ec901_product_grid', 'label' => 'Lưới sản phẩm bán chạy', 'description' => 'Lưới mười sản phẩm theo điều kiện lọc.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'search' => 'EC901-', 'featured_only' => false], 'settings_schema' => $productSchema(10), 'data' => ['vi' => $withItems($heading('Bán chạy nhất'), array_merge($products, array_slice($products, 0, 4))), 'en' => $withItems($heading('Top selling'), $products)]],
            ['block_type' => 'ec901_mini_promotions', 'label' => 'Banner ưu đãi nhỏ', 'description' => 'Ba banner chiến dịch nằm ngang.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai-nho', 'data' => ['vi' => $withItems($heading(), [['title' => 'Quà tặng dây đeo', 'image' => '/theme-demo/ec901/promo-gold.webp', 'url' => '#san-pham'], ['title' => 'Cặp đôi hoàn hảo', 'image' => '/theme-demo/ec901/promo-main.webp', 'url' => '#san-pham'], ['title' => 'Ưu đãi thành viên', 'image' => '/theme-demo/ec901/promo-red.webp', 'url' => '#san-pham']]), 'en' => $heading()]],
            ['block_type' => 'ec901_luxury_collection', 'label' => 'Đồng hồ cao cấp', 'description' => 'Banner dọc đi cùng carousel bốn sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'dong-ho-cao-cap', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC901-', 'featured_only' => true, 'feature_image' => '/theme-demo/ec901/promo-main.webp'], 'settings_schema' => array_merge($productSchema(4), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh bộ sưu tập']]), 'data' => ['vi' => $withItems($heading('Đồng hồ cao cấp'), array_slice($products, 0, 4)), 'en' => $withItems($heading('Luxury watches'), array_slice($products, 0, 4))]],
            ['block_type' => 'ec901_testimonials', 'label' => 'Đánh giá khách hàng', 'description' => 'Ba đánh giá có ảnh đại diện và số sao.', 'preview_image' => $preview, 'anchor_id' => 'danh-gia', 'data' => ['vi' => $withItems($heading('Đánh giá từ khách hàng'), [['title' => 'Minh Anh · Nhà thiết kế', 'summary' => 'Mẫu đồng hồ thanh lịch, hoàn thiện đẹp và dịch vụ tư vấn rất chu đáo.', 'image' => '/theme-demo/ec901/watch-women.webp'], ['title' => 'Tuấn Khôi · Doanh nhân', 'summary' => 'Một phụ kiện đáng tin cậy cho mọi cuộc gặp quan trọng trong ngày.', 'image' => '/theme-demo/ec901/watch-men.webp'], ['title' => 'Thu Vy · Content creator', 'summary' => 'Thiết kế dễ phối đồ, giao hàng nhanh và đóng gói thực sự cao cấp.', 'image' => '/theme-demo/ec901/lifestyle-source.png']]), 'en' => $heading('Customer reviews')]],
            ['block_type' => 'ec901_featured_brands', 'label' => 'Thương hiệu nổi bật', 'description' => 'Dải tên thương hiệu đồng hồ do người dùng quản lý.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu nổi bật'), [['title' => 'AURELIUS'], ['title' => 'NORDEN'], ['title' => 'VELA'], ['title' => 'ASTER'], ['title' => 'MONARCH'], ['title' => 'TEMPO']]), 'en' => $heading('Featured brands')]],
            ['block_type' => 'ec901_latest_posts', 'label' => 'Tin mới cập nhật', 'description' => 'Bốn bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => 'đồng hồ', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài', 'default' => 4], 'search' => ['type' => 'text', 'label' => 'Từ khóa bài viết'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin']], 'data' => ['vi' => $heading('Tin mới cập nhật'), 'en' => $heading('Latest stories')]],
            ['block_type' => 'ec901_benefits', 'label' => 'Cam kết mua hàng', 'description' => 'Bốn lợi ích dịch vụ ở cuối trang.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'data' => ['vi' => $withItems($heading(), [['title' => '5000+ mẫu đồng hồ', 'icon' => 'fa-regular fa-clock'], ['title' => 'Miễn phí vận chuyển', 'icon' => 'fa-solid fa-truck-fast'], ['title' => 'Thanh toán COD, Online', 'icon' => 'fa-regular fa-credit-card'], ['title' => 'Quà tặng thành viên', 'icon' => 'fa-solid fa-gift']]), 'en' => $heading()]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec910DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC910/preview-ec910.png';
        $heading = static fn (?string $title = null, ?string $description = null): array => ['title' => $title, 'description' => $description];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $products = [
            ['title' => 'CASIO MTP-1370D - Nam Quartz', 'image' => '/theme-demo/ec910/classic-silver.webp', 'price' => 1607000, 'original_price' => 2000000, 'summary' => 'Miễn phí thay pin trọn đời', 'url' => '#'],
            ['title' => 'CASIO Edifice Chronograph', 'image' => '/theme-demo/ec910/sport-black.webp', 'price' => 3529000, 'original_price' => 4100000, 'summary' => 'Tặng gói bảo hành 5 năm', 'url' => '#'],
            ['title' => 'TISSOT Tradition Rose Gold', 'image' => '/theme-demo/ec910/automatic-rose.webp', 'price' => 14700000, 'original_price' => 18300000, 'summary' => 'Miễn phí thay pin trọn đời', 'url' => '#'],
            ['title' => 'G-SHOCK GA-2000 Sport', 'image' => '/theme-demo/ec910/diver-blue.webp', 'price' => 4638000, 'original_price' => 5200000, 'summary' => 'Bảo hành chính hãng', 'url' => '#'],
            ['title' => 'SEIKO 5 Field Sports', 'image' => '/theme-demo/ec910/smartwatch-black.webp', 'price' => 7090000, 'original_price' => 8200000, 'summary' => 'Ưu đãi thành viên', 'url' => '#'],
            ['title' => 'ORIENT Open Heart', 'image' => '/theme-demo/ec910/automatic-rose.webp', 'price' => 11760000, 'original_price' => 13900000, 'summary' => 'Tặng gói bảo hành 5 năm', 'url' => '#'],
            ['title' => 'DOXA Calex Sapphire', 'image' => '/theme-demo/ec910/classic-silver.webp', 'price' => 33010000, 'original_price' => 35900000, 'summary' => 'Kính sapphire chính hãng', 'url' => '#'],
            ['title' => 'FOSSIL Heritage Automatic', 'image' => '/theme-demo/ec910/women-pink.webp', 'price' => 8590000, 'original_price' => 9323000, 'summary' => 'Áp dụng ưu đãi thành viên', 'url' => '#'],
        ];
        $posts = [
            ['title' => 'Toàn bộ sự thật về mặt kính sapphire', 'summary' => 'Kính sapphire có độ cứng rất cao, chống trầy xước và giữ vẻ đẹp bền lâu.', 'image' => '/theme-demo/ec910/classic-silver.webp'],
            ['title' => '“Lá chắn” thép không gỉ 904L có tốt như lời đồn?', 'summary' => 'Vật liệu và quy trình chế tạo thép không gỉ cao cấp.', 'image' => '/theme-demo/ec910/sport-black.webp'],
            ['title' => 'Kính cứng đồng hồ là gì?', 'summary' => 'Khám phá cấu tạo, công dụng và cách bảo quản mặt kính.', 'image' => '/theme-demo/ec910/diver-blue.webp'],
            ['title' => 'Kính khoáng đồng hồ và 4 lý do được dùng phổ biến', 'summary' => 'Giải thích ưu điểm của mineral crystal trong sử dụng hằng ngày.', 'image' => '/theme-demo/ec910/classic-silver.webp'],
            ['title' => 'Cách khử mùi dây da đồng hồ tại nhà', 'summary' => 'Những bước đơn giản giúp dây da luôn sạch và bền.', 'image' => '/theme-demo/ec910/automatic-rose.webp'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Dola Watch', 'description' => 'Banner đồng hồ cao cấp toàn chiều rộng.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec910-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide']], 'data' => ['vi' => array_merge($heading('Tinh hoa thời gian', 'Đồng hồ chính hãng cho mọi phong cách.'), ['content' => ['slides' => [['title' => 'Tinh hoa thời gian', 'summary' => 'Đồng hồ chính hãng cho mọi phong cách.', 'image' => '/theme-demo/ec910/hero-watches.webp', 'link_url' => '#khuyen-mai']]]]), 'en' => $heading('Timeless distinction')]],
            ['block_type' => 'ec910_benefits', 'label' => 'Quyền lợi mua hàng', 'description' => 'Bốn cam kết dịch vụ màu đen vàng.', 'preview_image' => $preview, 'anchor_id' => 'quyen-loi', 'data' => ['vi' => $withItems($heading(), [
                ['title' => 'Miễn phí vận chuyển', 'summary' => 'Cho đơn hàng trong nội thành', 'icon' => 'fa-solid fa-truck-fast'],
                ['title' => 'Miễn phí đổi - trả', 'summary' => 'Đổi sản phẩm lỗi sản xuất', 'icon' => 'fa-solid fa-rotate'],
                ['title' => 'Hỗ trợ nhanh chóng', 'summary' => 'Hotline 0399162342', 'icon' => 'fa-solid fa-headset'],
                ['title' => 'Ưu đãi thành viên', 'summary' => 'Nhiều khuyến mãi độc quyền', 'icon' => 'fa-solid fa-percent'],
            ]), 'en' => $heading()]],
            ['block_type' => 'ec910_promotions', 'label' => 'Khuyến mãi hấp dẫn', 'description' => 'Hai banner chiến dịch và năm sản phẩm ưu đãi.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC910-', 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => array_merge($withItems($heading('Khuyến mãi hấp dẫn'), array_slice($products, 0, 5)), ['content' => ['items' => array_slice($products, 0, 5), 'banners' => [
                ['title' => 'Đồng hồ lộ cơ giảm đến 49%', 'image' => '/theme-demo/ec910/promo-main.webp', 'url' => '#san-pham-moi'],
                ['title' => 'Seiko 5 quân đội giảm đến 30%', 'image' => '/theme-demo/ec910/promo-gold.webp', 'url' => '#san-pham-moi'],
            ]]]), 'en' => $heading('Promotions')]],
            ['block_type' => 'ec910_product_tabs', 'label' => 'Sản phẩm theo tab', 'description' => 'Sản phẩm mới, nổi bật và bán chạy.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC910-', 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $withItems($heading('Sản phẩm mới'), array_slice($products, 0, 5)), 'en' => $heading('New arrivals')]],
            ['block_type' => 'ec910_about', 'label' => 'Giới thiệu Dola Watch', 'description' => 'Khối giới thiệu nền đen, ảnh phong cách.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => ['image' => '/theme-demo/ec910/lifestyle-source.png'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh giới thiệu']], 'data' => ['vi' => $heading('Dola Watch - Đồng hồ chính hãng', 'Được thành lập từ năm 2019, Dola Watch chuyên bán đồng hồ chính hãng với chính sách bảo hành minh bạch và dịch vụ tận tâm.'), 'en' => $heading('Dola Watch - Authentic timepieces')]],
            ['block_type' => 'ec910_mens_watches', 'label' => 'Đồng hồ nam', 'description' => 'Banner dọc, bộ lọc và lưới tám sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'dong-ho-nam', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC910-', 'featured_only' => false, 'feature_image' => '/theme-demo/ec910/watch-men.webp'], 'settings_schema' => array_merge($productSchema(8), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh phong cách']]), 'data' => ['vi' => $withItems($heading('Đồng hồ nam'), $products), 'en' => $heading("Men's watches")]],
            ['block_type' => 'ec910_orient_banner', 'label' => 'Banner Orient', 'description' => 'Banner chiến dịch đen vàng.', 'preview_image' => $preview, 'anchor_id' => 'orient', 'settings' => ['image' => '/theme-demo/ec910/promo-main.webp'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh banner']], 'data' => ['vi' => $heading('Mua đồng hồ Orient', 'Nhận quà độc quyền'), 'en' => $heading('Orient collection')]],
            ['block_type' => 'ec910_experience', 'label' => 'Kinh nghiệm đồng hồ', 'description' => 'Một bài nổi bật và bốn bài kiến thức.', 'preview_image' => $preview, 'anchor_id' => 'kinh-nghiem', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 5, 'search' => 'đồng hồ', 'featured_only' => false, 'feature_image' => '/theme-demo/ec910/watch-women.webp'], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài'], 'search' => ['type' => 'text', 'label' => 'Từ khóa'], 'feature_image' => ['type' => 'text', 'label' => 'Ảnh trang trí']], 'data' => ['vi' => $withItems($heading('Kinh nghiệm'), $posts), 'en' => $heading('Watch journal')]],
            ['block_type' => 'ec910_brands', 'label' => 'Thương hiệu nổi bật', 'description' => 'Lưới mười hai thương hiệu đồng hồ.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu nổi bật'), array_map(fn ($title) => ['title' => $title], ['CASIO', 'G-SHOCK', 'CITIZEN', 'OP', 'ORIENT', 'SEIKO', 'MICHAEL KORS', 'DANIEL WELLINGTON', 'CANDINO', 'SOKOLOV', 'DOXA', 'LONGINES'])), 'en' => $heading('Featured brands')]],
            ['block_type' => 'ec910_footer', 'label' => 'Chân trang Dola Watch', 'description' => 'Thông tin, chính sách, danh mục và đăng ký nhận tin.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Dola Watch'), 'en' => $heading('Dola Watch')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec902DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC902/preview-ec902.webp';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $products = [
            ['title' => 'Nova X Pro 1TB', 'image' => '/theme-demo/ec902/phone-silver.webp', 'price' => 45590000, 'original_price' => 49990000, 'summary' => 'Bảo hành chính hãng 12 tháng', 'url' => '#'],
            ['title' => 'Nova X Plus 512GB', 'image' => '/theme-demo/ec902/phone-green.webp', 'price' => 32790000, 'original_price' => 36990000, 'summary' => 'Hỗ trợ đổi mới trong 30 ngày', 'url' => '#'],
            ['title' => 'Nova X 512GB', 'image' => '/theme-demo/ec902/phone-blue.webp', 'price' => 29990000, 'original_price' => 33990000, 'summary' => 'Sản phẩm chính hãng, giao nhanh', 'url' => '#'],
            ['title' => 'Nova 14 Pro 256GB', 'image' => '/theme-demo/ec902/phone-graphite.webp', 'price' => 27490000, 'original_price' => 31990000, 'summary' => 'Trả góp linh hoạt 0%', 'url' => '#'],
            ['title' => 'NovaTab Pro 11 1TB', 'image' => '/theme-demo/ec902/tablet-blue.webp', 'price' => 42990000, 'original_price' => 43990000, 'summary' => 'Bảo hành chính hãng 12 tháng', 'url' => '#'],
            ['title' => 'NovaTab Air 256GB', 'image' => '/theme-demo/ec902/tablet-coral.webp', 'price' => 21790000, 'original_price' => 24990000, 'summary' => 'Màn hình lớn cho sáng tạo', 'url' => '#'],
            ['title' => 'NovaTab 10 256GB', 'image' => '/theme-demo/ec902/tablet-green.webp', 'price' => 14590000, 'original_price' => 16790000, 'summary' => 'Gọn nhẹ, pin bền bỉ', 'url' => '#'],
            ['title' => 'NovaBook Air 14', 'image' => '/theme-demo/ec902/laptop-silver.webp', 'price' => 27990000, 'original_price' => 31990000, 'summary' => 'Hiệu năng cho công việc mỗi ngày', 'url' => '#'],
            ['title' => 'Nova Watch Active', 'image' => '/theme-demo/ec902/watch-white.webp', 'price' => 6390000, 'original_price' => 8990000, 'summary' => 'Theo dõi sức khỏe thông minh', 'url' => '#'],
            ['title' => 'Nova Buds Pro', 'image' => '/theme-demo/ec902/earbuds-white.webp', 'price' => 3190000, 'original_price' => 3990000, 'summary' => 'Âm thanh rõ nét, chống ồn chủ động', 'url' => '#'],
            ['title' => 'Sạc không dây NovaPad', 'image' => '/theme-demo/ec902/charger-wireless.webp', 'price' => 1290000, 'original_price' => 1590000, 'summary' => 'Sạc nhanh, an toàn cho thiết bị', 'url' => '#'],
            ['title' => 'Cốc sạc nhanh Nova 30W', 'image' => '/theme-demo/ec902/charger-wall.webp', 'price' => 690000, 'original_price' => 990000, 'summary' => 'Bảo hành chính hãng 12 tháng', 'url' => '#'],
        ];
        $categories = [
            ['title' => 'Smartphone', 'summary' => 'Ưu đãi cho dòng Nova X', 'image' => '/theme-demo/ec902/phone-blue.webp', 'url' => '#dien-thoai'],
            ['title' => 'Tablet', 'summary' => 'Màn hình lớn, giá tốt', 'image' => '/theme-demo/ec902/tablet-coral.webp', 'url' => '#may-tinh-bang'],
            ['title' => 'Laptop', 'summary' => 'Hiệu năng mỗi ngày', 'image' => '/theme-demo/ec902/laptop-silver.webp', 'url' => '#may-tinh-bang'],
            ['title' => 'Smart Watch', 'summary' => 'Sống khỏe, sống thông minh', 'image' => '/theme-demo/ec902/watch-white.webp', 'url' => '#phu-kien'],
        ];
        $accessoryCategories = [
            ['title' => 'Pin dự phòng', 'image' => '/theme-demo/ec902/charger-wireless.webp', 'url' => '#phu-kien'],
            ['title' => 'Dán màn hình', 'image' => '/theme-demo/ec902/phone-green.webp', 'url' => '#phu-kien'],
            ['title' => 'Củ sạc - Cáp sạc', 'image' => '/theme-demo/ec902/charger-wall.webp', 'url' => '#phu-kien'],
            ['title' => 'Ốp lưng', 'image' => '/theme-demo/ec902/phone-graphite.webp', 'url' => '#phu-kien'],
            ['title' => 'Cổng chuyển HUB', 'image' => '/theme-demo/ec902/charger-wireless.webp', 'url' => '#phu-kien'],
            ['title' => 'Tai nghe', 'image' => '/theme-demo/ec902/earbuds-white.webp', 'url' => '#phu-kien'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero công nghệ', 'description' => 'Slider đầu trang cùng hai banner ưu đãi sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec902-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Công nghệ dẫn lối tương lai', 'NOVA X PRO', 'Thiết kế bền bỉ, hiệu năng mạnh mẽ và trải nghiệm liền mạch.', 'Khám phá ngay'), ['content' => ['slides' => [['title' => 'Công nghệ dẫn lối tương lai', 'summary' => 'Thiết kế bền bỉ, hiệu năng mạnh mẽ và trải nghiệm liền mạch.', 'button_label' => 'Khám phá ngay', 'image' => '/theme-demo/ec902/hero-tech.webp', 'link_url' => '#san-pham-moi']], 'promos' => [['title' => 'Nova X Series', 'summary' => 'Ưu đãi đến 5 triệu', 'image' => '/theme-demo/ec902/promo-phone.webp', 'url' => '#dien-thoai'], ['title' => 'Smart accessories', 'summary' => 'Đồng bộ hệ sinh thái', 'image' => '/theme-demo/ec902/promo-accessories.webp', 'url' => '#phu-kien']]]]), 'en' => $heading('Technology for tomorrow', 'NOVA X PRO', 'Durable design, powerful performance and a seamless experience.', 'Explore now')]],
            ['block_type' => 'ec902_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn lợi ích mua sắm nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'data' => ['vi' => $withItems($heading(), [['title' => 'Giao hàng nhanh', 'icon' => 'fa-solid fa-truck-fast'], ['title' => 'Tư vấn chuyên nghiệp', 'icon' => 'fa-solid fa-headset'], ['title' => '100% chính hãng', 'icon' => 'fa-solid fa-shield-halved'], ['title' => 'Thanh toán linh hoạt', 'icon' => 'fa-solid fa-credit-card']]), 'en' => $heading()]],
            ['block_type' => 'ec902_featured_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Bốn danh mục sản phẩm chính của cửa hàng.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 4, 'search' => 'ec902-', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 4], 'search' => ['type' => 'text', 'label' => 'Từ khóa tên hoặc slug']], 'data' => ['vi' => $withItems($heading('Danh mục nổi bật'), $categories), 'en' => $withItems($heading('Featured categories'), $categories)]],
            ['block_type' => 'ec902_product_tabs', 'label' => 'Tabs sản phẩm', 'description' => 'Sản phẩm mới, nổi bật và bán chạy.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC902-', 'featured_only' => true], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $withItems($heading('Sản phẩm mới'), array_slice($products, 0, 5)), 'en' => $withItems($heading('New products'), array_slice($products, 0, 5))]],
            ['block_type' => 'ec902_featured_deals', 'label' => 'Deal nổi bật', 'description' => 'Khu vực khuyến mãi nền xanh với banner và sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'deal-noi-bat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC902-', 'featured_only' => true, 'feature_image' => '/theme-demo/ec902/promo-phone.webp'], 'settings_schema' => array_merge($productSchema(4), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh chiến dịch']]), 'data' => ['vi' => $withItems($heading('Deal nổi bật', null, 'Sản phẩm chính hãng, mới 100%, bảo hành tận tâm'), array_slice($products, 8, 4)), 'en' => $withItems($heading('Featured deals'), array_slice($products, 8, 4))]],
            ['block_type' => 'ec902_phone_collection', 'label' => 'Bộ sưu tập Smartphone', 'description' => 'Banner dọc và bốn điện thoại nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'dien-thoai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC902-', 'featured_only' => false, 'feature_image' => '/theme-demo/ec902/promo-phone.webp'], 'settings_schema' => array_merge($productSchema(4), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh danh mục']]), 'data' => ['vi' => $withItems($heading('Smartphone'), array_slice($products, 0, 4)), 'en' => $withItems($heading('Smartphones'), array_slice($products, 0, 4))]],
            ['block_type' => 'ec902_tablet_collection', 'label' => 'Bộ sưu tập Tablet', 'description' => 'Bốn tablet và laptop đi cùng banner chiến dịch.', 'preview_image' => $preview, 'anchor_id' => 'may-tinh-bang', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC902-', 'featured_only' => false, 'feature_image' => '/theme-demo/ec902/promo-computing.webp'], 'settings_schema' => array_merge($productSchema(4), ['feature_image' => ['type' => 'text', 'label' => 'Ảnh danh mục']]), 'data' => ['vi' => $withItems($heading('Tablet & Laptop'), array_slice($products, 4, 4)), 'en' => $withItems($heading('Tablets & Laptops'), array_slice($products, 4, 4))]],
            ['block_type' => 'ec902_accessory_categories', 'label' => 'Danh mục phụ kiện', 'description' => 'Sáu nhóm phụ kiện nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'phu-kien-noi-bat', 'data' => ['vi' => $withItems($heading('Phụ kiện nổi bật'), $accessoryCategories), 'en' => $withItems($heading('Featured accessories'), $accessoryCategories)]],
            ['block_type' => 'ec902_accessory_products', 'label' => 'Sản phẩm phụ kiện', 'description' => 'Lưới phụ kiện công nghệ chính hãng.', 'preview_image' => $preview, 'anchor_id' => 'phu-kien', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC902-', 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $withItems($heading('Phụ kiện'), array_slice($products, 8, 4)), 'en' => $withItems($heading('Accessories'), array_slice($products, 8, 4))]],
            ['block_type' => 'ec902_wide_banner', 'label' => 'Banner công nghệ ngang', 'description' => 'Banner ngang cho chiến dịch tablet và laptop.', 'preview_image' => $preview, 'anchor_id' => 'banner-cong-nghe', 'data' => ['vi' => $withItems($heading(), [['title' => 'NovaTab Pro', 'summary' => 'Sức mạnh cho mọi ý tưởng', 'image' => '/theme-demo/ec902/promo-computing.webp', 'url' => '#may-tinh-bang']]), 'en' => $heading()]],
            ['block_type' => 'ec902_latest_posts', 'label' => 'Tin tức mới nhất', 'description' => 'Một tin lớn và bốn tin công nghệ mới.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 5, 'search' => 'Nova', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài', 'default' => 5], 'search' => ['type' => 'text', 'label' => 'Từ khóa'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin']], 'data' => ['vi' => $heading('Tin tức mới nhất'), 'en' => $heading('Latest news')]],
            ['block_type' => 'ec902_video_reviews', 'label' => 'Video xem nhiều nhất', 'description' => 'Bốn video review sản phẩm công nghệ.', 'preview_image' => $preview, 'anchor_id' => 'video-review', 'data' => ['vi' => $withItems($heading('Xem nhiều nhất'), [['title' => 'Mở hộp Nova X Pro: chi tiết đáng giá', 'image' => '/theme-demo/ec902/story-phone.webp', 'url' => '#'], ['title' => 'Trải nghiệm camera Nova X', 'image' => '/theme-demo/ec902/story-review.webp', 'url' => '#'], ['title' => 'NovaTab Pro thay đổi cách sáng tạo', 'image' => '/theme-demo/ec902/story-tablet.webp', 'url' => '#'], ['title' => 'Sạc nhanh thế hệ mới có gì khác?', 'image' => '/theme-demo/ec902/story-charging.webp', 'url' => '#']]), 'en' => $heading('Most watched')]],
            ['block_type' => 'ec902_testimonials', 'label' => 'Feedback khách hàng', 'description' => 'Đánh giá thực tế từ hai khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'feedback', 'data' => ['vi' => $withItems($heading('Feedback từ khách hàng'), [['title' => 'Hoàng Dung', 'role' => 'Nhân viên văn phòng', 'summary' => 'Sản phẩm hoàn thiện tốt, nhân viên tư vấn rõ ràng và giao hàng nhanh hơn mong đợi.', 'image' => '/theme-demo/ec902/story-phone.webp'], ['title' => 'Sơn Bình', 'role' => 'Nhà sáng tạo nội dung', 'summary' => 'Thiết bị chính hãng, chính sách đổi trả minh bạch nên mình rất yên tâm khi lựa chọn.', 'image' => '/theme-demo/ec902/story-review.webp']]), 'en' => $heading('Customer feedback')]],
            ['block_type' => 'ec902_support_strip', 'label' => 'Dải hỗ trợ cuối trang', 'description' => 'Cam kết, hotline và liên kết hệ thống cửa hàng.', 'preview_image' => $preview, 'anchor_id' => 'ho-tro', 'data' => ['vi' => $withItems($heading(), [['title' => 'Thanh toán khi nhận hàng', 'summary' => 'COD toàn quốc', 'icon' => 'fa-solid fa-hand-holding-dollar'], ['title' => 'Cam kết chính hãng', 'summary' => '100% nguồn gốc rõ ràng', 'icon' => 'fa-solid fa-certificate'], ['title' => 'Giao hàng miễn phí', 'summary' => 'Nội thành trong 2 giờ', 'icon' => 'fa-solid fa-truck-fast'], ['title' => '14 ngày đổi trả', 'summary' => 'Miễn phí nếu lỗi', 'icon' => 'fa-solid fa-rotate'], ['title' => 'Khiếu nại, góp ý', 'summary' => '0399162342', 'icon' => 'fa-solid fa-circle-question'], ['title' => 'Tư vấn', 'summary' => '0399162342', 'icon' => 'fa-solid fa-phone'], ['title' => 'Tìm chi nhánh', 'summary' => 'Hệ thống Nova', 'icon' => 'fa-solid fa-location-dot']]), 'en' => $heading()]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec903DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC903/preview-ec903.webp';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Voucher Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số deal', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Mã nhóm deal'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ deal nổi bật', 'default' => false],
        ];
        $deal = static fn (string $title, string $image, int $price, int $original): array => [
            'title' => $title,
            'image' => $image,
            'price' => $price,
            'original_price' => $original,
            'url' => '#',
        ];
        $featured = [
            $deal('Buffet hải sản tôm hùm cao cấp', '/theme-demo/ec903/deal-seafood.webp', 1050000, 1500000),
            $deal('Spa thư giãn đá nóng 90 phút', '/theme-demo/ec903/deal-spa.webp', 299000, 650000),
            $deal('Tour sông nước miền Tây trong ngày', '/theme-demo/ec903/deal-tour.webp', 699000, 990000),
            $deal('Vui chơi công viên trọn gói', '/theme-demo/ec903/deal-amusement.webp', 255000, 300000),
            $deal('Chăm sóc da chuyên sâu Vitamin C', '/theme-demo/ec903/deal-skincare.webp', 149000, 900000),
            $deal('Kỳ nghỉ resort hồ bơi nhiệt đới', '/theme-demo/ec903/deal-resort.webp', 1699000, 2500000),
            $deal('Phòng khách sạn thành phố hạng sang', '/theme-demo/ec903/deal-hotel.webp', 1399000, 1990000),
            $deal('Massage phục hồi toàn thân', '/theme-demo/ec903/deal-massage.webp', 199000, 550000),
        ];
        $food = [
            $deal('Buffet tôm hùm và hải sản không giới hạn', '/theme-demo/ec903/food-lobster.webp', 1050000, 1502000),
            $deal('Buffet dim sum chuẩn vị Á Đông', '/theme-demo/ec903/food-dimsum.webp', 519000, 646000),
            $deal('Buffet lẩu hai vị hơn 60 món', '/theme-demo/ec903/food-hotpot.webp', 349000, 459000),
            $deal('Tiệc nướng hải sản cao cấp', '/theme-demo/ec903/food-grill.webp', 635000, 793000),
            $deal('Bàn tiệc Việt cho gia đình', '/theme-demo/ec903/food-banquet.webp', 770000, 906000),
            $deal('Bữa tối du thuyền ngắm thành phố', '/theme-demo/ec903/food-cruise.webp', 1150000, 1450000),
            $deal('Buffet brunch cuối tuần', '/theme-demo/ec903/food-brunch.webp', 599000, 750000),
            $deal('Buffet bánh ngọt và trà chiều', '/theme-demo/ec903/food-dessert.webp', 379000, 419000),
        ];
        $vegan = [
            $deal('Buffet chay Việt thanh lành', '/theme-demo/ec903/vegan-buffet.webp', 178000, 209000),
            $deal('Lẩu nấm rau xanh thanh vị', '/theme-demo/ec903/vegan-hotpot.webp', 189000, 219000),
            $deal('Thực đơn chay phong cách hiện đại', '/theme-demo/ec903/vegan-finedining.webp', 99000, 168000),
            $deal('Brunch chay và trà thảo mộc', '/theme-demo/ec903/vegan-brunch.webp', 159000, 208000),
        ];
        $beauty = [
            $deal('Liệu trình spa đá nóng thư giãn', '/theme-demo/ec903/deal-spa.webp', 299000, 650000),
            $deal('Chăm sóc da sáng khỏe chuyên sâu', '/theme-demo/ec903/deal-skincare.webp', 149000, 900000),
            $deal('Massage dưỡng sinh toàn thân', '/theme-demo/ec903/deal-massage.webp', 199000, 550000),
            $deal('Gói chăm sóc nụ cười chuyên nghiệp', '/theme-demo/ec903/deal-dental.webp', 399000, 750000),
        ];
        $travel = [
            $deal('Tour khám phá sông nước nhiệt đới', '/theme-demo/ec903/deal-tour.webp', 699000, 990000),
            $deal('Vé công viên vui chơi cả ngày', '/theme-demo/ec903/deal-amusement.webp', 255000, 300000),
            $deal('Nghỉ dưỡng resort bên hồ bơi', '/theme-demo/ec903/deal-resort.webp', 1699000, 2500000),
            $deal('Khách sạn trung tâm thành phố', '/theme-demo/ec903/deal-hotel.webp', 1399000, 1990000),
        ];
        $categories = [
            ['title' => 'Khuyến mãi hot', 'icon' => 'fa-solid fa-fire', 'url' => '#deal-noi-bat'],
            ['title' => 'Ẩm thực', 'icon' => 'fa-solid fa-utensils', 'url' => '#am-thuc'],
            ['title' => 'Spa & Làm đẹp', 'icon' => 'fa-solid fa-spa', 'url' => '#lam-dep'],
            ['title' => 'Giải trí & Thể thao', 'icon' => 'fa-solid fa-film', 'url' => '#du-lich'],
            ['title' => 'Massage Nam Nữ', 'icon' => 'fa-solid fa-hand-sparkles', 'url' => '#lam-dep'],
            ['title' => 'Đào tạo & Hội thảo', 'icon' => 'fa-solid fa-chalkboard-user', 'url' => '#'],
            ['title' => 'Bệnh viện & Phòng khám', 'icon' => 'fa-solid fa-house-medical', 'url' => '#'],
            ['title' => 'Buffet', 'icon' => 'fa-solid fa-kitchen-set', 'url' => '#am-thuc'],
            ['title' => 'Nha khoa', 'icon' => 'fa-solid fa-tooth', 'url' => '#lam-dep'],
            ['title' => 'Tour du lịch', 'icon' => 'fa-solid fa-suitcase-rolling', 'url' => '#du-lich'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero sàn voucher', 'description' => 'Slider chiến dịch ở giữa và ba banner ưu đãi bên phải.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec903-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Tận hưởng dịch vụ đỉnh cao', 'Deal độc quyền', 'Voucher làm đẹp và ẩm thực cao cấp với mức giá hấp dẫn.', 'Đặt ngay'), ['content' => ['slides' => [['title' => 'Tận hưởng dịch vụ đỉnh cao', 'summary' => 'Voucher làm đẹp và ẩm thực cao cấp với mức giá hấp dẫn.', 'price_label' => 'Chỉ từ 99.000đ', 'button_label' => 'Đặt ngay', 'image' => '/theme-demo/ec903/hero-marketplace.webp', 'link_url' => '#deal-noi-bat']], 'promos' => [['title' => 'Spa thư giãn', 'summary' => 'Ưu đãi đến 55%', 'image' => '/theme-demo/ec903/promo-spa.webp', 'url' => '#lam-dep'], ['title' => 'Buffet hải sản', 'summary' => 'Deal mới mỗi ngày', 'image' => '/theme-demo/ec903/promo-dining.webp', 'url' => '#am-thuc'], ['title' => 'Kỳ nghỉ cuối tuần', 'summary' => 'Giá tốt cho gia đình', 'image' => '/theme-demo/ec903/deal-resort.webp', 'url' => '#du-lich']], 'brands' => [['title' => 'Urban Stay', 'icon' => 'fa-solid fa-hotel'], ['title' => 'Green Park', 'icon' => 'fa-solid fa-tree'], ['title' => 'Ocean Table', 'icon' => 'fa-solid fa-fish'], ['title' => 'Serene Spa', 'icon' => 'fa-solid fa-spa'], ['title' => 'River Tour', 'icon' => 'fa-solid fa-ship']]]]), 'en' => $heading('Premium experiences, better prices')]],
            ['block_type' => 'ec903_category_rail', 'label' => 'Menu danh mục dịch vụ', 'description' => 'Danh mục động hiển thị dọc bên trái hero.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 10, 'search' => 'ec903-', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 10], 'search' => ['type' => 'text', 'label' => 'Từ khóa']], 'data' => ['vi' => $withItems($heading('Danh mục'), $categories), 'en' => $withItems($heading('Categories'), $categories)]],
            ['block_type' => 'ec903_featured_deals', 'label' => 'Deal nổi bật', 'description' => 'Tám voucher đa ngành nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'deal-noi-bat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC903-HOT', 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $withItems($heading('Deal nổi bật'), $featured), 'en' => $withItems($heading('Featured deals'), $featured)]],
            ['block_type' => 'ec903_food_deals', 'label' => 'Deal ẩm thực', 'description' => 'Tám deal buffet và nhà hàng.', 'preview_image' => $preview, 'anchor_id' => 'am-thuc', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC903-FOOD', 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $withItems($heading('Ẩm thực'), $food), 'en' => $withItems($heading('Dining'), $food)]],
            ['block_type' => 'ec903_vegetarian_deals', 'label' => 'Deal ẩm thực chay', 'description' => 'Bốn voucher buffet và nhà hàng chay.', 'preview_image' => $preview, 'anchor_id' => 'am-thuc-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC903-VEGAN', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Ẩm thực chay'), $vegan), 'en' => $withItems($heading('Vegetarian dining'), $vegan)]],
            ['block_type' => 'ec903_beauty_deals', 'label' => 'Deal spa & làm đẹp', 'description' => 'Bốn voucher chăm sóc sức khỏe và sắc đẹp.', 'preview_image' => $preview, 'anchor_id' => 'lam-dep', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC903-BEAUTY', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Spa & Làm đẹp'), $beauty), 'en' => $withItems($heading('Spa & Beauty'), $beauty)]],
            ['block_type' => 'ec903_travel_deals', 'label' => 'Deal du lịch & giải trí', 'description' => 'Bốn voucher nghỉ dưỡng, tour và vui chơi.', 'preview_image' => $preview, 'anchor_id' => 'du-lich', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC903-TRAVEL', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Du lịch & Giải trí'), $travel), 'en' => $withItems($heading('Travel & Entertainment'), $travel)]],
            ['block_type' => 'ec903_newsletter', 'label' => 'Đăng ký bản tin', 'description' => 'Biểu mẫu nhận deal tốt mỗi tuần.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Nhận deal tốt mỗi tuần', null, 'Ưu đãi ẩm thực, làm đẹp và du lịch được chọn lọc.'), 'en' => $heading('Weekly best deals')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec904DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC904/preview-ec904.webp';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $schema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Mã nhóm sản phẩm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật', 'default' => false],
        ];
        $product = static fn (string $title, string $image, int $price, int $original): array => [
            'title' => $title, 'image' => $image, 'price' => $price, 'original_price' => $original, 'url' => '#',
        ];
        $tech = [
            $product('NovaPhone X Pro 256GB', '/theme-demo/ec904/phone-front.webp', 21990000, 24990000),
            $product('NovaPhone X Graphite 128GB', '/theme-demo/ec904/phone-back.webp', 16990000, 19990000),
            $product('Tai nghe Gaming SoundMax', '/theme-demo/ec904/headset.webp', 1290000, 1690000),
            $product('Máy ảnh Mirrorless Vision M5', '/theme-demo/ec904/camera.webp', 18900000, 21900000),
            $product('Nồi chiên không dầu HomeChef', '/theme-demo/ec904/air-fryer.webp', 1990000, 2690000),
            $product('Laptop NovaBook Air 14', '/theme-demo/ec904/laptop.webp', 22990000, 25990000),
            $product('Máy chơi game NovaStation', '/theme-demo/ec904/game-console.webp', 11990000, 13990000),
            $product('Đồng hồ thông minh Active S', '/theme-demo/ec904/smartwatch.webp', 3290000, 3990000),
        ];
        $fashion = [
            $product('Giày chạy bộ Cloud White', '/theme-demo/ec904/shoe-white.webp', 790000, 1790000),
            $product('Giày thể thao Urban Black', '/theme-demo/ec904/shoe-black.webp', 890000, 1890000),
            $product('Giày cổ cao Pastel Move', '/theme-demo/ec904/shoe-pastel.webp', 990000, 1990000),
            $product('Giày casual Canvas Beige', '/theme-demo/ec904/shoe-beige.webp', 690000, 1490000),
            $product('Áo len xanh Soft Knit', '/theme-demo/ec904/sweater-blue.webp', 590000, 990000),
            $product('Khuyên tai tròn Golden Glow', '/theme-demo/ec904/earrings-gold.webp', 490000, 790000),
            $product('Túi da City Satchel', '/theme-demo/ec904/handbag-tan.webp', 1290000, 1890000),
            $product('Ghế thư giãn Mustard Home', '/theme-demo/ec904/chair-mustard.webp', 3690000, 4990000),
        ];
        $categories = [
            ['title' => 'Điện thoại - Máy tính bảng', 'image' => '/theme-demo/ec904/phone-front.webp', 'url' => '#dien-thoai'],
            ['title' => 'Phụ kiện - Thiết bị số', 'image' => '/theme-demo/ec904/headset.webp', 'url' => '#do-cong-nghe'],
            ['title' => 'Máy ảnh - Quay phim', 'image' => '/theme-demo/ec904/camera.webp', 'url' => '#do-cong-nghe'],
            ['title' => 'Điện gia dụng - Nhà bếp', 'image' => '/theme-demo/ec904/air-fryer.webp', 'url' => '#do-cong-nghe'],
            ['title' => 'Laptop - Thiết bị IT', 'image' => '/theme-demo/ec904/laptop.webp', 'url' => '#do-cong-nghe'],
            ['title' => 'Máy chơi game - Trò chơi', 'image' => '/theme-demo/ec904/game-console.webp', 'url' => '#do-cong-nghe'],
            ['title' => 'Trang sức - Sành điệu', 'image' => '/theme-demo/ec904/earrings-gold.webp', 'url' => '#thoi-trang'],
            ['title' => 'Thời trang - Làm đẹp', 'image' => '/theme-demo/ec904/sweater-blue.webp', 'url' => '#thoi-trang'],
            ['title' => 'Nhà cửa đời sống', 'image' => '/theme-demo/ec904/chair-mustard.webp', 'url' => '#goi-y'],
            ['title' => 'Âm thanh - Giải trí', 'image' => '/theme-demo/ec904/speaker.webp', 'url' => '#do-cong-nghe'],
        ];
        $posts = [
            ['title' => 'Điện thoại màn hình gập ngày càng dễ tiếp cận', 'summary' => 'Thiết kế gọn nhẹ và công nghệ bản lề mới giúp trải nghiệm linh hoạt hơn.', 'image' => '/theme-demo/ec904/news-foldable.webp', 'url' => '#'],
            ['title' => 'Cách làm bún bò thơm ngon cho cả gia đình', 'summary' => 'Công thức cân bằng vị ngọt thanh, sả và các loại rau ăn kèm.', 'image' => '/theme-demo/ec904/news-noodles.webp', 'url' => '#'],
            ['title' => 'Chọn TV thông minh phù hợp không gian sống', 'summary' => 'Kích thước, độ phân giải và khoảng cách xem là ba yếu tố quan trọng.', 'image' => '/theme-demo/ec904/news-tv.webp', 'url' => '#'],
            ['title' => 'Phụ kiện đeo thông minh cho ngày năng động', 'summary' => 'Tai nghe và đồng hồ giúp công việc lẫn luyện tập liền mạch hơn.', 'image' => '/theme-demo/ec904/news-wearables.webp', 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero siêu sale', 'description' => 'Banner chiến dịch đa ngành đầu trang.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec904-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Siêu sale đa ngành', 'Thiên đường mua sắm', 'Sắm công nghệ, thời trang và đồ dùng nhà cửa với giá tốt.', 'Mua ngay'), ['content' => ['slides' => [['title' => 'Siêu sale đa ngành', 'summary' => 'Sắm công nghệ, thời trang và đồ dùng nhà cửa với giá tốt.', 'button_label' => 'Mua ngay', 'image' => '/theme-demo/ec904/hero-super-sale.webp', 'link_url' => '#dien-thoai']]]]), 'en' => $heading('Department store super sale')]],
            ['block_type' => 'ec904_category_carousel', 'label' => 'Danh mục tròn', 'description' => 'Mười danh mục đa ngành dạng carousel.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 10, 'search' => 'ec904-', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục'], 'search' => ['type' => 'text', 'label' => 'Từ khóa']], 'data' => ['vi' => $withItems($heading('Danh mục sản phẩm'), $categories), 'en' => $withItems($heading('Categories'), $categories)]],
            ['block_type' => 'ec904_tabbed_sale', 'label' => 'Sale theo nhóm', 'description' => 'Khối tab điện thoại, thời trang và gia dụng.', 'preview_image' => $preview, 'anchor_id' => 'dien-thoai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC904-TECH', 'featured_only' => false], 'settings_schema' => $schema(5), 'data' => ['vi' => array_merge($withItems($heading('Điện thoại', 'Giảm ngay 1 triệu khi mua online'), array_slice($tech, 0, 5)), ['content' => ['items' => array_slice($tech, 0, 5), 'promo_image' => '/theme-demo/ec904/promo-home.webp']]), 'en' => $heading('Mobile sale')]],
            ['block_type' => 'ec904_technology_products', 'label' => 'Đồ công nghệ', 'description' => 'Tám sản phẩm công nghệ kèm banner dọc.', 'preview_image' => $preview, 'anchor_id' => 'do-cong-nghe', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC904-TECH', 'featured_only' => false], 'settings_schema' => $schema(8), 'data' => ['vi' => array_merge($withItems($heading('Đồ công nghệ'), $tech), ['content' => ['items' => $tech, 'promo_image' => '/theme-demo/ec904/promo-tech.webp']]), 'en' => $heading('Technology')]],
            ['block_type' => 'ec904_fashion_products', 'label' => 'Thời trang', 'description' => 'Tám sản phẩm thời trang và đời sống.', 'preview_image' => $preview, 'anchor_id' => 'thoi-trang', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC904-FASHION', 'featured_only' => false], 'settings_schema' => $schema(8), 'data' => ['vi' => array_merge($withItems($heading('Thời trang'), $fashion), ['content' => ['items' => $fashion, 'promo_image' => '/theme-demo/ec904/promo-fashion.webp']]), 'en' => $heading('Fashion')]],
            ['block_type' => 'ec904_daily_suggestions', 'label' => 'Gợi ý hôm nay', 'description' => 'Carousel năm sản phẩm ưu đãi.', 'preview_image' => $preview, 'anchor_id' => 'goi-y', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC904-HOT', 'featured_only' => false], 'settings_schema' => $schema(5), 'data' => ['vi' => $withItems($heading('Gợi ý hôm nay'), array_slice(array_merge($tech, $fashion), 0, 5)), 'en' => $heading('Today picks')]],
            ['block_type' => 'ec904_latest_posts', 'label' => 'Tin tức mới nhất', 'description' => 'Bốn bài viết mua sắm và đời sống.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => 'Poco', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài'], 'search' => ['type' => 'text', 'label' => 'Từ khóa'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin']], 'data' => ['vi' => $withItems($heading('Tin tức mới nhất'), $posts), 'en' => $heading('Latest news')]],
            ['block_type' => 'ec904_newsletter', 'label' => 'Nhận tin khuyến mãi', 'description' => 'Khu đăng ký email cuối trang.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Nhận tin khuyến mãi', null, 'Ưu đãi mới được gửi đến email của bạn.'), 'en' => $heading('Promotion newsletter')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec906DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC906/preview-ec906.png';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật'],
        ];
        $product = static fn (string $title, string $image, int $price, int $original): array => [
            'title' => $title, 'image' => $image, 'price' => $price, 'original_price' => $original, 'url' => '#',
        ];
        $home = [
            $product('Nước lau sàn tinh dầu thảo mộc', '/theme-demo/ec906/home-care.png', 30000, 45000),
            $product('Nước rửa chén hương quế và bồ hòn', '/theme-demo/ec906/home-care.png', 121000, 129000),
            $product('Nước xả vải hương hoa oải hương', '/theme-demo/ec906/home-care.png', 167000, 182000),
            $product('Túi nước xả vải thanh khiết 1.7L', '/theme-demo/ec906/home-care.png', 140000, 175000),
            $product('Nước giặt thiên nhiên dịu nhẹ', '/theme-demo/ec906/home-care.png', 159000, 270000),
            $product('Sáp thơm phòng khử mùi Pure Aroma', '/theme-demo/ec906/home-care.png', 41000, 65000),
        ];
        $kitchenNames = [
            ['Chảo chiên trứng tạo hình', 20000, 30000],
            ['Khuôn silicone làm bánh donut', 35000, 50000],
            ['Cây lăn bột chống dính', 75000, 149000],
            ['Cán bột gỗ tay cầm dài', 35000, 40000],
            ['Khay làm kem silicone dễ thương', 36000, 70000],
            ['Dụng cụ ép bột tiện dụng', 69000, 99000],
            ['Khuôn làm bánh xếp 2 trong 1', 33000, 77000],
            ['Máy đánh trứng cầm tay mini', 52000, 80000],
        ];
        $kitchen = collect($kitchenNames)->map(fn (array $item): array => $product($item[0], '/theme-demo/ec906/kitchen-tools.png', $item[1], $item[2]))->all();
        $benefits = [
            ['title' => 'Giao hỏa tốc', 'summary' => 'Nội thành TP.HCM trong 4h', 'icon' => 'fa-truck-fast'],
            ['title' => 'Đổi trả miễn phí', 'summary' => 'Trong vòng 30 ngày', 'icon' => 'fa-rotate'],
            ['title' => 'Hỗ trợ 24/7', 'summary' => 'Hỗ trợ khách hàng 24/7', 'icon' => 'fa-thumbs-up'],
            ['title' => 'Deal hot bùng nổ', 'summary' => 'Flash sale giảm giá cực sốc', 'icon' => 'fa-ticket'],
        ];
        $promos = [
            ['title' => 'Sữa các loại thông dụng', 'button_label' => 'Mua ngay', 'url' => '#'],
            ['title' => 'Tã bỉm, hóa phẩm', 'button_label' => 'Mua ngay', 'url' => '#'],
            ['title' => 'Đồ dùng cho mẹ & bé', 'button_label' => 'Mua ngay', 'url' => '#'],
        ];
        $posts = [
            ['title' => 'Các loại nước chống lão hóa hiệu quả nên uống mỗi ngày', 'summary' => 'Những thức uống giàu dưỡng chất cần thiết cho cơ thể.', 'image' => '/theme-demo/ec906/nutrition.png', 'url' => '#'],
            ['title' => 'Trái cây mùa đông giúp giảm cân hiệu quả', 'summary' => 'Lựa chọn thực phẩm theo mùa để cân bằng dinh dưỡng.', 'image' => '/theme-demo/ec906/nutrition.png', 'url' => '#'],
            ['title' => 'Cách chọn rau củ quả sạch, tươi ngon và an toàn', 'summary' => 'Những dấu hiệu đơn giản giúp chọn thực phẩm chất lượng.', 'image' => '/theme-demo/ec906/home-care.png', 'url' => '#'],
            ['title' => '10 mẹo giúp người bận rộn giữ nhà luôn sạch sẽ', 'summary' => 'Các thói quen nhỏ giúp không gian sống luôn thoáng sạch.', 'image' => '/theme-demo/ec906/home-care.png', 'url' => '#'],
        ];
        $brands = collect(['OMO', 'Tide', 'SUNHOUSE', 'Kangaroo', 'THIÊN LONG', 'LG', 'Goldsun', 'Comfort', 'Panasonic', 'Ariel', 'Surf', 'Viso'])
            ->map(fn (string $title): array => ['title' => $title, 'url' => '#'])->all();

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero đại tiệc khuyến mãi', 'description' => 'Banner toàn chiều rộng với thông điệp giảm giá và ảnh sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec906-hero-slider', 'limit' => 3, 'autoplay_ms' => 5500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('ĐẠI TIỆC KHUYẾN MÃI', 'Duy nhất tại EGA Mini Mart', 'Giảm giá lên đến 65%', 'Mua ngay'), ['content' => ['slides' => [['title' => 'ĐẠI TIỆC KHUYẾN MÃI', 'summary' => 'Giảm giá lên đến 65%', 'button_label' => 'Mua ngay', 'image' => '/theme-demo/ec906/hero-minimart.png', 'link_url' => '#flash-sale']]]]), 'en' => $heading('MEGA PROMOTION PARTY')]],
            ['block_type' => 'ec906_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn cam kết giao hàng, đổi trả, hỗ trợ và ưu đãi.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Service promises'), $benefits)]],
            ['block_type' => 'ec906_flash_sale', 'label' => 'Flash sale đếm ngược', 'description' => 'Năm sản phẩm nổi bật kèm đồng hồ đếm ngược.', 'preview_image' => $preview, 'anchor_id' => 'flash-sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC906-HOME', 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $withItems($heading('Chớp thời cơ. Giá như mơ!'), array_slice($home, 0, 5)), 'en' => $heading('Dream prices. Limited time!')]],
            ['block_type' => 'ec906_family_care', 'label' => 'Chăm sóc gia đình', 'description' => 'Dải sáu sản phẩm vệ sinh và chăm sóc nhà cửa.', 'preview_image' => $preview, 'anchor_id' => 'cham-soc-gia-dinh', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 6, 'search' => 'EC906-HOME', 'featured_only' => false], 'settings_schema' => $productSchema(6), 'data' => ['vi' => $withItems($heading('Chăm sóc gia đình'), $home), 'en' => $heading('Family care')]],
            ['block_type' => 'ec906_category_promos', 'label' => 'Banner nhóm hàng', 'description' => 'Ba banner sữa, hóa phẩm và đồ dùng mẹ & bé.', 'preview_image' => $preview, 'anchor_id' => 'nhom-hang', 'data' => ['vi' => $withItems($heading('Nhóm hàng nổi bật'), $promos), 'en' => $withItems($heading('Featured categories'), $promos)]],
            ['block_type' => 'ec906_kitchen_products', 'label' => 'Đồ dùng nhà bếp', 'description' => 'Tám sản phẩm bao quanh banner khuyến mãi dọc.', 'preview_image' => $preview, 'anchor_id' => 'do-dung-nha-bep', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC906-KITCHEN', 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => array_merge($withItems($heading('Đồ dùng nhà bếp'), $kitchen), ['content' => ['items' => $kitchen, 'promo_image' => '/theme-demo/ec906/kitchen-promo.png']]), 'en' => $heading('Kitchen essentials')]],
            ['block_type' => 'ec906_latest_posts', 'label' => 'Tin tức gia đình', 'description' => 'Bốn bài viết cẩm nang sức khỏe và nhà cửa.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => 'EC906', 'featured_only' => false], 'settings_schema' => $postSchema(4), 'data' => ['vi' => $withItems($heading('Tin tức'), $posts), 'en' => $withItems($heading('News'), $posts)]],
            ['block_type' => 'ec906_brand_strip', 'label' => 'Thương hiệu đồng hành', 'description' => 'Dải logo chữ đơn sắc của các thương hiệu.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu đồng hành'), $brands), 'en' => $withItems($heading('Our brands'), $brands)]],
            ['block_type' => 'ec906_newsletter', 'label' => 'Đăng ký ưu đãi', 'description' => 'Biểu mẫu email được trình bày trong chân trang.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Đăng ký nhận ưu đãi', null, 'Cập nhật khuyến mãi đặc biệt ngay lập tức.'), 'en' => $heading('Get special offers')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec907DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC907/preview-ec907.png';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $product = static fn (string $title, string $image, int $price, int $original): array => ['title' => $title, 'image' => $image, 'price' => $price, 'original_price' => $original, 'url' => '#'];
        $best = [
            $product('Bàn phím cơ không dây Dot Foundation', '/theme-demo/ec907/keyboard-white.png', 4700000, 4800000),
            $product('Bàn phím cơ không dây Flow 75', '/theme-demo/ec907/keyboard-white.png', 4400000, 6800000),
            $product('Bàn phím cơ Chocolate Retro', '/theme-demo/ec907/keyboard-white.png', 3800000, 4200000),
            $product('Bàn phím cơ B-Duck Wireless', '/theme-demo/ec907/keyboard-white.png', 3800000, 4200000),
        ];
        $audio = collect([
            ['Tai nghe Studio W830BT', 4350000, 5220000], ['Tai nghe Gaming Barracuda X', 4350000, 5220000],
            ['Tai nghe SoundForm Play', 2220000, 3500000], ['Tai nghe Virtuoso Pro', 4220000, 4800000],
            ['Tai nghe Fnatic React+ 7.1', 4520000, 4800000], ['Loa không dây di động MiniBeat', 1850000, 2200000],
            ['Tai nghe Momentum 4 Wireless', 6520000, 7000000], ['Tai nghe SoundForm Mini Kids', 3220000, 3500000],
            ['Tai nghe Lightspeed G733', 4520000, 4800000],
        ])->map(fn (array $item, int $index): array => $product($item[0], $index === 4 ? '/theme-demo/ec907/headset-black.png' : ($index === 5 ? '/theme-demo/ec907/speaker.webp' : '/theme-demo/ec907/headset-black.png'), $item[1], $item[2]))->all();
        $gaming = [
            $product('Máy chơi game PlayStation thế hệ mới', '/theme-demo/ec907/game-console.webp', 15900000, 17900000),
            $product('Kính thực tế ảo Meta Quest', '/theme-demo/ec907/headset.webp', 16690000, 19800000),
            $product('Tay cầm DualSense Edge', '/theme-demo/ec907/game-console.webp', 6690000, 9800000),
            $product('Tay cầm chơi game không dây', '/theme-demo/ec907/game-console.webp', 2690000, 3800000),
        ];
        $categories = collect([
            ['Laptop', 'laptop.webp'], ['Máy tính bảng', 'laptop.webp'], ['Điện thoại', 'phone-front.webp'], ['Tai nghe', 'headset-black.png'],
            ['Bàn phím', 'keyboard-white.png'], ['Sạc dự phòng', 'smartwatch.webp'], ['Chuột + Lót chuột', 'keyboard-white.png'], ['Củ sạc', 'earbuds.webp'],
            ['Máy tính bàn (PC)', 'television.webp'], ['Màn hình', 'television.webp'], ['Thiết bị âm thanh', 'speaker.webp'], ['Máy chơi game', 'game-console.webp'],
            ['Ghế gaming', 'headset.webp'], ['Balo laptop', 'laptop.webp'], ['Cáp sạc', 'earbuds.webp'], ['Phụ kiện', 'smartwatch.webp'],
        ])->map(fn (array $item): array => ['title' => $item[0], 'image' => '/theme-demo/ec907/'.$item[1], 'url' => '#'])->all();
        $benefits = [
            ['title' => 'Giao hỏa tốc', 'summary' => 'Nội thành TP.HCM trong 4h', 'icon' => 'fa-truck-fast'],
            ['title' => 'Trả góp ưu đãi%', 'summary' => 'Hỗ trợ vay với lãi suất thấp', 'icon' => 'fa-circle-dollar-to-slot'],
            ['title' => 'Deal hot bùng nổ', 'summary' => 'Flash sale giảm giá cực sốc', 'icon' => 'fa-ticket'],
            ['title' => 'Miễn phí đổi trả', 'summary' => 'Trong vòng 30 ngày miễn phí', 'icon' => 'fa-rotate'],
            ['title' => 'Hỗ trợ 24/7', 'summary' => 'Hỗ trợ khách hàng 24/7', 'icon' => 'fa-thumbs-up'],
        ];
        $campaigns = [
            ['title' => 'GIẢM GIÁ NHẬP HỌC 30%', 'summary' => 'Tai nghe, Phụ kiện', 'image' => '/theme-demo/ec907/campaign-gaming.png', 'url' => '#'],
            ['title' => 'ƯU ĐÃI MÙA HÈ ĐẾN 60%', 'summary' => 'Laptop, Màn hình máy tính', 'image' => '/theme-demo/ec907/campaign-gaming.png', 'url' => '#'],
            ['title' => 'GIẢM 500K CHO ĐƠN 2TR', 'summary' => 'Laptop Gaming', 'image' => '/theme-demo/ec907/campaign-gaming.png', 'url' => '#'],
        ];
        $posts = [
            ['title' => 'Trình làng trợ lý AI thế hệ mới cho người dùng công nghệ', 'summary' => 'Mô hình thông minh ngày càng hữu ích trong công việc và sáng tạo.', 'image' => '/theme-demo/ec907/news-foldable.webp', 'url' => '#'],
            ['title' => 'Laptop thế hệ mới được trang bị chip hiệu năng cao', 'summary' => 'Thiết kế mỏng nhẹ đi cùng thời lượng pin và sức mạnh xử lý tốt hơn.', 'image' => '/theme-demo/ec907/laptop.webp', 'url' => '#'],
            ['title' => 'Cách xuất màn hình laptop ra màn hình ngoài cực đơn giản', 'summary' => 'Hướng dẫn kết nối và tối ưu không gian làm việc đa màn hình.', 'image' => '/theme-demo/ec907/news-tv.webp', 'url' => '#'],
            ['title' => 'Laptop AI mới: hiệu năng mạnh và siêu mỏng nhẹ', 'summary' => 'Những thay đổi đáng chú ý của thế hệ máy tính cá nhân mới.', 'image' => '/theme-demo/ec907/news-wearables.webp', 'url' => '#'],
        ];
        $brands = collect(['SONY', 'XIAOMI', 'ASUS', 'OPPO', 'SAMSUNG', 'LG', 'realme', 'HUAWEI', 'NOKIA', 'MSI', 'DELL', 'Apple', 'GEFORCE RTX', 'Lenovo', 'acer', 'ThinkPad'])->map(fn (string $title): array => ['title' => $title, 'url' => '#'])->all();
        $promos = [
            ['title' => 'LAPTOP VĂN PHÒNG', 'summary' => 'Giảm 30% cho sinh viên', 'price' => '8.450.000₫', 'image' => '/theme-demo/ec907/laptop.webp', 'url' => '#'],
            ['title' => 'MÀN HÌNH 4K', 'summary' => 'Giảm lên đến 20%', 'price' => '7.690.000₫', 'image' => '/theme-demo/ec907/television.webp', 'url' => '#'],
            ['title' => 'BÀN PHÍM CƠ', 'summary' => 'Giảm lên đến 60%', 'price' => '1.590.000₫', 'image' => '/theme-demo/ec907/keyboard-white.png', 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Gear và banner dọc', 'description' => 'Banner gaming chính và ba banner khuyến mãi sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec907-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Gear giá sốc', 'Giao siêu tốc', 'Giảm giá cực sốc đến 45%', 'Xem ngay'), ['content' => ['slides' => [['title' => 'Gear giá sốc', 'summary' => 'Giao siêu tốc · Giảm đến 45%', 'button_label' => 'Xem ngay', 'image' => '/theme-demo/ec907/hero-gear.png', 'link_url' => '#san-pham-ban-chay']], 'promos' => $promos]]), 'en' => $heading('Gaming gear mega sale')]],
            ['block_type' => 'ec907_benefits', 'label' => 'Cam kết mua sắm', 'description' => 'Năm cam kết giao hàng, trả góp, deal, đổi trả và hỗ trợ.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'data' => ['vi' => $withItems($heading('Cam kết mua sắm'), $benefits), 'en' => $withItems($heading('Shopping benefits'), $benefits)]],
            ['block_type' => 'ec907_category_grid', 'label' => 'Danh mục thiết bị', 'description' => 'Lưới 16 danh mục thiết bị và phụ kiện.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 16, 'search' => 'ec907-', 'featured_only' => false, 'order' => 'sort_order'], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục'], 'search' => ['type' => 'text', 'label' => 'Từ khóa']], 'data' => ['vi' => $withItems($heading('Danh mục sản phẩm'), $categories), 'en' => $withItems($heading('Product categories'), $categories)]],
            ['block_type' => 'ec907_best_sellers', 'label' => 'Sản phẩm bán chạy', 'description' => 'Bốn sản phẩm bàn phím bán chạy.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC907-KEYBOARD', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Sản phẩm bán chạy'), $best), 'en' => $heading('Best sellers')]],
            ['block_type' => 'ec907_campaign_cards', 'label' => 'Ba chiến dịch ưu đãi', 'description' => 'Ba banner nhập học, mùa hè và laptop gaming.', 'preview_image' => $preview, 'anchor_id' => 'chien-dich', 'data' => ['vi' => $withItems($heading('Ưu đãi nổi bật'), $campaigns), 'en' => $withItems($heading('Featured offers'), $campaigns)]],
            ['block_type' => 'ec907_audio_showcase', 'label' => 'Showcase tai nghe', 'description' => 'Lưới tai nghe bất đối xứng với sản phẩm trung tâm.', 'preview_image' => $preview, 'anchor_id' => 'tai-nghe', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 9, 'search' => 'EC907-AUDIO', 'featured_only' => false], 'settings_schema' => $productSchema(9), 'data' => ['vi' => $withItems($heading('Tai nghe và âm thanh'), $audio), 'en' => $heading('Audio showcase')]],
            ['block_type' => 'ec907_gaming_products', 'label' => 'Phụ kiện chơi game', 'description' => 'Bốn sản phẩm console, kính VR và tay cầm.', 'preview_image' => $preview, 'anchor_id' => 'gaming', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC907-GAMING', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Phụ kiện chơi game'), $gaming), 'en' => $heading('Gaming accessories')]],
            ['block_type' => 'ec907_brand_strip', 'label' => 'Thương hiệu công nghệ', 'description' => 'Dải thương hiệu hai hàng.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu'), $brands), 'en' => $withItems($heading('Brands'), $brands)]],
            ['block_type' => 'ec907_tech_news', 'label' => 'Bản tin công nghệ', 'description' => 'Bốn bài viết công nghệ mới nhất.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => '', 'featured_only' => false], 'settings_schema' => $postSchema(4), 'data' => ['vi' => $withItems($heading('Bản tin công nghệ'), $posts), 'en' => $heading('Technology news')]],
            ['block_type' => 'ec907_newsletter', 'label' => 'Đăng ký ưu đãi', 'description' => 'Biểu mẫu nhận tin trong chân trang.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Đăng ký nhận ưu đãi'), 'en' => $heading('Get special offers')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec908DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC908/preview-ec908.png';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $product = static fn (string $title, string $image, int $price, int $original): array => ['title' => $title, 'image' => $image, 'price' => $price, 'original_price' => $original, 'delivery' => 'Giao siêu tốc 2H', 'url' => '#'];
        $categories = collect(['Sản phẩm nổi bật', 'Tăng cân', 'Tăng cơ', 'Sức bền', 'Giảm cân', 'Protein', 'Dầu cá', 'Phụ kiện', 'Dưỡng chất', 'Vitamin'])->map(fn (string $title, int $index): array => ['title' => $title, 'image' => $index % 2 === 0 ? '/theme-demo/ec908/whey-black.png' : '/theme-demo/ec908/whey-white.png', 'url' => '#'])->all();
        $best = [
            $product('Premium Whey Protein chuyên nghiệp', '/theme-demo/ec908/whey-black.png', 950000, 1120000),
            $product('Whey tinh khiết 100% Pure', '/theme-demo/ec908/whey-white.png', 350000, 399000),
            $product('ISO Whey Zero nhập khẩu', '/theme-demo/ec908/whey-black.png', 840000, 910000),
            $product('Whey Protein Isolate cao cấp', '/theme-demo/ec908/whey-white.png', 1240000, 1340000),
            $product('Mass Gainer tăng cân bền vững', '/theme-demo/ec908/whey-black.png', 1580000, 1790000),
            $product('Creatine Monohydrate sức mạnh', '/theme-demo/ec908/whey-black.png', 1440000, 1650000),
        ];
        $promos = [
            ['badge' => 'CÓ GÌ HOT', 'title' => 'Bán chạy nhất sản phẩm dinh dưỡng', 'summary' => 'Giảm tới 50%', 'button_label' => 'XEM NGAY', 'image' => '/theme-demo/ec908/promo-red.png', 'url' => '#'],
            ['badge' => 'SIÊU HẠ GIÁ', 'title' => 'Cho tất cả sản phẩm tăng lực', 'summary' => 'Chỉ từ 150.000 VNĐ', 'button_label' => 'MUA NGAY', 'image' => '/theme-demo/ec908/promo-gray.png', 'url' => '#'],
        ];
        $posts = [
            ['title' => 'Cẩn thận với các thói quen tập gym khiến làn da xấu đi', 'summary' => 'Tập gym đúng cách giúp cơ thể khỏe mạnh và hạn chế những tác động không mong muốn.', 'date' => '24/08/2023', 'image' => '/theme-demo/ec908/health-main.png', 'url' => '#'],
            ['title' => '5 dấu hiệu chứng tỏ bạn đang tập gym sai cách và quá sức', 'summary' => 'Những tín hiệu cơ thể cần được nghỉ ngơi và điều chỉnh cường độ luyện tập.', 'date' => '24/08/2023', 'image' => '/theme-demo/ec908/health-triptych.png', 'url' => '#'],
            ['title' => 'Bí quyết cho đôi chân thon dài quyến rũ như Wonder Woman', 'summary' => 'Một giáo án cân bằng giúp cải thiện sức mạnh và độ săn chắc của đôi chân.', 'date' => '24/08/2023', 'image' => '/theme-demo/ec908/health-triptych.png', 'url' => '#'],
            ['title' => 'Cơ bắp cuồn cuộn của nữ vận động viên thể hình', 'summary' => 'Câu chuyện truyền cảm hứng về kỷ luật, dinh dưỡng và luyện tập bền bỉ.', 'date' => '24/08/2023', 'image' => '/theme-demo/ec908/health-triptych.png', 'url' => '#'],
        ];
        $benefits = [
            ['title' => 'Hàng chính hãng', 'summary' => 'Đa dạng và chuyên sâu', 'icon' => 'fa-award'],
            ['title' => 'Đổi trả trong 7 ngày', 'summary' => 'Kể từ ngày mua hàng', 'icon' => 'fa-rotate-left'],
            ['title' => 'Cam kết 100%', 'summary' => 'Chất lượng sản phẩm', 'icon' => 'fa-shield-halved'],
            ['title' => 'Giao hàng 2H', 'summary' => 'Theo từng sản phẩm', 'icon' => 'fa-truck-fast'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Hot Deal', 'description' => 'Banner ưu đãi thực phẩm bổ sung toàn chiều ngang.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec908-hero-slider', 'limit' => 3, 'autoplay_ms' => 5500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Hot Deal tháng này', null, 'Quà tặng thể thao cho mọi đơn hàng', 'Mua ngay'), ['content' => ['slides' => [['badge' => 'HOT DEAL', 'title' => 'Bùng nổ ưu đãi', 'summary' => 'Giảm đến 50% sản phẩm dinh dưỡng thể thao', 'button_label' => 'Mua ngay', 'image' => '/theme-demo/ec908/hero-fitness.png', 'link_url' => '#san-pham-ban-chay']]]]), 'en' => $heading('Fitness hot deals')]],
            ['block_type' => 'ec908_category_rail', 'label' => 'Danh mục dinh dưỡng', 'description' => 'Mười danh mục tròn theo dạng thanh trượt.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 10, 'search' => 'ec908-', 'featured_only' => false, 'order' => 'sort_order'], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục'], 'search' => ['type' => 'text', 'label' => 'Từ khóa']], 'data' => ['vi' => $withItems($heading('Danh mục sản phẩm'), $categories), 'en' => $withItems($heading('Product categories'), $categories)]],
            ['block_type' => 'ec908_best_sellers', 'label' => 'Sản phẩm bán chạy', 'description' => 'Sáu sản phẩm dinh dưỡng bán chạy.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 6, 'search' => 'EC908-BEST', 'featured_only' => false], 'settings_schema' => $productSchema(6), 'data' => ['vi' => $withItems($heading('Sản phẩm bán chạy'), $best), 'en' => $heading('Best sellers')]],
            ['block_type' => 'ec908_promo_pair', 'label' => 'Banner ưu đãi đôi', 'description' => 'Hai banner whey và tăng lực.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai', 'data' => ['vi' => $withItems($heading('Ưu đãi nổi bật'), $promos), 'en' => $withItems($heading('Featured offers'), $promos)]],
            ['block_type' => 'ec908_accessory_products', 'label' => 'Phụ kiện hot', 'description' => 'Banner phụ kiện lớn và bốn sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'phu-kien-hot', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC908-ACCESSORY', 'featured_only' => false, 'banner_image' => '/theme-demo/ec908/accessory-banner.png', 'banner_url' => '#'], 'settings_schema' => array_merge($productSchema(4), ['banner_image' => ['type' => 'text', 'label' => 'Ảnh banner'], 'banner_url' => ['type' => 'text', 'label' => 'Liên kết banner']]), 'data' => ['vi' => $withItems($heading('Phụ kiện hot'), array_slice($best, 0, 4)), 'en' => $heading('Hot accessories')]],
            ['block_type' => 'ec908_health_posts', 'label' => 'Góc sức khỏe', 'description' => 'Một bài nổi bật và ba bài viết luyện tập.', 'preview_image' => $preview, 'anchor_id' => 'goc-suc-khoe', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => '', 'featured_only' => false], 'settings_schema' => $postSchema(4), 'data' => ['vi' => $withItems($heading('Góc sức khỏe'), $posts), 'en' => $heading('Health corner')]],
            ['block_type' => 'ec908_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn cam kết chính hãng, đổi trả, chất lượng và giao 2H.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Service benefits'), $benefits)]],
            ['block_type' => 'ec908_footer', 'label' => 'Chân trang', 'description' => 'Các nhóm hỗ trợ, chính sách, thông tin và liên hệ.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin Ego Fitness'), 'en' => $heading('Ego Fitness information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function foot404DefaultBlocks(): array
    {
        $preview = '/theme-previews/FOOT404/cover-foot404.svg';
        $heading = static fn (?string $title = null, ?string $description = null, ?string $subtitle = null, ?string $button = null): array => array_filter([
            'title' => $title,
            'description' => $description,
            'subtitle' => $subtitle,
            'button_label' => $button,
        ], static fn (mixed $value): bool => $value !== null);
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $categorySchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 6],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $promoTrio = [
            ['title' => 'Món ngon thuần tự nhiên', 'summary' => 'Nguyên liệu được chọn lọc kỹ lưỡng', 'image' => '/theme-demo/ec903/food-dessert.webp', 'url' => '#san-pham-uu-dai'],
            ['title' => 'Quà tặng sức khỏe', 'summary' => 'Tinh tế trong từng lựa chọn', 'image' => '/theme-demo/ec903/food-brunch.webp', 'url' => '#san-pham-moi'],
            ['title' => 'Gắn kết yêu thương', 'summary' => 'Chia sẻ khoảnh khắc trọn vẹn', 'image' => '/theme-demo/ec903/food-dimsum.webp', 'url' => '#ban-chay'],
        ];
        $promoDuo = [
            ['title' => 'Thương hiệu chuẩn nguồn gốc', 'summary' => 'Chọn sản phẩm minh bạch, an tâm mỗi ngày', 'image' => '/theme-demo/ec903/food-banquet.webp', 'url' => '#san-pham-moi'],
            ['title' => 'Chất lượng tạo nên khác biệt', 'summary' => 'Ưu đãi dành cho những lựa chọn tinh tế', 'image' => '/theme-demo/ec903/food-brunch.webp', 'url' => '#ban-chay'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero khuyến mãi dạng mosaic', 'description' => 'Banner chính và hai banner phụ lấy từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot404-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Ưu đãi chọn lọc', 'Khám phá sản phẩm chất lượng cho gia đình', 'Mua sắm an tâm', 'Khám phá ngay'), ['content' => ['slides' => [['title' => 'Ưu đãi chọn lọc', 'summary' => 'Sản phẩm chất lượng, nguồn gốc rõ ràng', 'button_label' => 'Khám phá ngay', 'image' => '/theme-demo/ec903/food-banquet.webp', 'link_url' => '#san-pham-uu-dai']]]]), 'en' => $heading('Curated offers', 'Quality products for every family')]],
            ['block_type' => 'foot404_categories', 'label' => 'Dải danh mục sản phẩm', 'description' => 'Sáu danh mục lấy trực tiếp từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 6, 'featured_only' => false, 'order' => 'sort_order'], 'settings_schema' => $categorySchema, 'data' => ['vi' => $heading('Danh mục sản phẩm'), 'en' => $heading('Product categories')]],
            ['block_type' => 'foot404_promo_trio', 'label' => 'Ba banner giá trị nổi bật', 'description' => 'Ba banner nhỏ theo bố cục tham chiếu.', 'preview_image' => $preview, 'anchor_id' => 'gia-tri', 'data' => ['vi' => $withItems($heading('Giá trị chúng tôi theo đuổi'), $promoTrio), 'en' => $withItems($heading('Our values'), $promoTrio)]],
            ['block_type' => 'foot404_deals', 'label' => 'Sản phẩm ưu đãi', 'description' => 'Bốn sản phẩm ưu đãi từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-uu-dai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $heading('Sản phẩm ưu đãi', 'Đừng bỏ lỡ những lựa chọn nổi bật dành cho bạn', 'Ưu đãi trong tuần', 'Xem tất cả'), 'en' => $heading('Featured offers')]],
            ['block_type' => 'foot404_new_products', 'label' => 'Sản phẩm mới', 'description' => 'Banner dọc đi cùng bốn sản phẩm mới.', 'preview_image' => $preview, 'anchor_id' => 'san-pham-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false, 'feature_image' => '/theme-demo/ec903/food-cruise.webp'], 'settings_schema' => array_merge($productSchema(4), ['feature_image' => ['type' => 'image', 'label' => 'Ảnh banner bên trái']]), 'data' => ['vi' => $heading('Sản phẩm mới', 'Những sản phẩm vừa được cập nhật', 'Lựa chọn mới', 'Xem tất cả'), 'en' => $heading('New products')]],
            ['block_type' => 'foot404_coupon', 'label' => 'Banner mã ưu đãi', 'description' => 'Banner ngang cho mã khuyến mãi.', 'preview_image' => $preview, 'anchor_id' => 'ma-uu-dai', 'settings' => ['coupon_code' => 'FOOT404', 'background_image' => '/theme-demo/ec903/food-source.png'], 'settings_schema' => ['coupon_code' => ['type' => 'text', 'label' => 'Mã ưu đãi'], 'background_image' => ['type' => 'image', 'label' => 'Ảnh nền']], 'data' => ['vi' => $heading('Nhận ưu đãi cho đơn hàng đầu tiên', 'Áp dụng theo điều kiện chương trình', 'Ưu đãi thành viên'), 'en' => $heading('Welcome offer')]],
            ['block_type' => 'foot404_best_sellers', 'label' => 'Sản phẩm bán chạy', 'description' => 'Năm sản phẩm được quan tâm nhiều.', 'preview_image' => $preview, 'anchor_id' => 'ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $heading('Sản phẩm bán chạy', 'Những lựa chọn được khách hàng yêu thích', 'Được yêu thích', 'Xem tất cả'), 'en' => $heading('Best sellers')]],
            ['block_type' => 'foot404_promo_duo', 'label' => 'Hai banner thương hiệu', 'description' => 'Hai banner lớn trước chân trang.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Mua sắm an tâm'), $promoDuo), 'en' => $withItems($heading('Shop with confidence'), $promoDuo)]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function foot408DefaultBlocks(): array
    {
        $preview = '/theme-previews/FOOT408/cover-foot408.svg';
        $heading = static fn (?string $title = null, ?string $description = null, ?string $subtitle = null, ?string $button = null): array => array_filter([
            'title' => $title,
            'description' => $description,
            'subtitle' => $subtitle,
            'button_label' => $button,
        ], static fn (mixed $value): bool => $value !== null);
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số món ăn', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục món ăn'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ món nổi bật'],
        ];
        $postSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $welcomeCards = [
            ['title' => 'Phiếu quà tặng ẩm thực', 'summary' => 'Món quà nhỏ cho những cuộc gặp gỡ đáng nhớ', 'image' => '/theme-demo/ec903/food-banquet.webp', 'url' => '#thuc-don'],
            ['title' => 'Hương vị theo mùa', 'summary' => 'Khám phá thực đơn được cập nhật mỗi ngày', 'image' => '/theme-demo/ec903/deal-restaurant.webp', 'url' => '#thuc-don'],
        ];
        $comboCards = [
            ['title' => 'Combo sum họp', 'subtitle' => 'Món ngon đủ đầy', 'summary' => 'Bữa ăn trọn vị cho gia đình và bạn bè.', 'image' => '/theme-demo/ec903/food-banquet.webp', 'url' => '#thuc-don'],
            ['title' => 'Combo tiện lợi', 'subtitle' => 'Ưu đãi trong ngày', 'summary' => 'Lựa chọn nhanh gọn cho một ngày bận rộn.', 'image' => '/theme-demo/ec903/food-brunch.webp', 'url' => '#thuc-don'],
            ['title' => 'Thức uống mát lành', 'subtitle' => 'Dùng kèm món chính', 'summary' => 'Hương vị cân bằng cho bữa ăn thêm vui.', 'image' => '/theme-demo/ec903/deal-tea.webp', 'url' => '#thuc-don'],
            ['title' => 'Món ăn kèm giòn ngon', 'subtitle' => 'Được yêu thích', 'summary' => 'Những phần ăn vừa miệng để sẻ chia.', 'image' => '/theme-demo/ec903/food-grill.webp', 'url' => '#thuc-don'],
        ];
        $testimonials = [
            ['name' => 'Minh Anh', 'role' => 'Khách hàng thân thiết', 'quote' => 'Món ăn được chuẩn bị chỉn chu, giao đúng giờ và đội ngũ tư vấn rất nhiệt tình.', 'image' => '/theme-demo/ec903/food-source.png'],
            ['name' => 'Hoàng Nam', 'role' => 'Khách hàng', 'quote' => 'Không gian thân thiện, thực đơn dễ chọn và hương vị phù hợp với cả gia đình.', 'image' => '/theme-demo/ec903/food-brunch.webp'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero nhà hàng', 'description' => 'Banner toàn chiều rộng lấy từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot408-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Bữa ngon thêm gắn kết', 'Thực đơn hấp dẫn dành cho những khoảnh khắc sum họp', 'Ưu đãi trong tuần', 'Tìm hiểu ngay'), ['content' => ['slides' => [['title' => 'Bữa ngon thêm gắn kết', 'summary' => 'Khám phá những món ăn được chuẩn bị từ nguyên liệu chọn lọc', 'button_label' => 'Xem thực đơn', 'image' => '/theme-demo/ec903/deal-restaurant.webp', 'link_url' => '#thuc-don']]]]), 'en' => $heading('Great food, better moments', 'A warm menu for every gathering')]],
            ['block_type' => 'foot408_welcome', 'label' => 'Giới thiệu nhà hàng', 'description' => 'Nội dung chào mừng và hai banner giới thiệu.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'data' => ['vi' => $withItems($heading('Chào mừng đến với nhà hàng', 'Chúng tôi mang đến không gian gần gũi cùng những món ăn được chuẩn bị tận tâm.', 'Phục vụ mỗi ngày', 'Tìm hiểu thêm'), $welcomeCards), 'en' => $withItems($heading('Welcome to our restaurant', 'Warm hospitality and carefully prepared food.'), $welcomeCards)]],
            ['block_type' => 'foot408_menu_products', 'label' => 'Thực đơn món ăn', 'description' => 'Tám món ăn lấy trực tiếp từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'thuc-don', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $heading('Thực đơn', 'Những món ăn được khách hàng yêu thích', 'Món ngon mỗi ngày'), 'en' => $heading('Our menu', 'Customer favourites')]],
            ['block_type' => 'foot408_combo_mosaic', 'label' => 'Combo và ưu đãi', 'description' => 'Bốn banner ưu đãi dạng lưới bất đối xứng.', 'preview_image' => $preview, 'anchor_id' => 'combo', 'data' => ['vi' => $withItems($heading('Combo và ưu đãi', 'Chọn phần ăn phù hợp với từng khoảnh khắc'), $comboCards), 'en' => $withItems($heading('Combos and offers'), $comboCards)]],
            ['block_type' => 'foot408_testimonials', 'label' => 'Phản hồi khách hàng', 'description' => 'Phản hồi lấy từ CMS Testimonials.', 'preview_image' => $preview, 'anchor_id' => 'phan-hoi', 'dynamic' => true, 'settings' => ['source' => 'cms_testimonials', 'limit' => 4, 'featured_only' => false, 'background_image' => '/theme-demo/ec903/food-grill.webp'], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_testimonials', 'label' => 'Phản hồi CMS'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số phản hồi', 'default' => 4], 'background_image' => ['type' => 'image', 'label' => 'Ảnh nền']], 'data' => ['vi' => $withItems($heading('Phản hồi của khách hàng', 'Những chia sẻ giúp chúng tôi phục vụ tốt hơn'), $testimonials), 'en' => $withItems($heading('Customer feedback'), $testimonials)]],
            ['block_type' => 'foot408_blog_posts', 'label' => 'Blog - Tin tức', 'description' => 'Ba bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $postSchema(3), 'data' => ['vi' => $heading('Blog - Tin tức', 'Câu chuyện ẩm thực, mẹo hay và thông tin mới'), 'en' => $heading('Blog and news', 'Food stories, tips and updates')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function foot409DefaultBlocks(): array
    {
        $preview = '/theme-previews/FOOT409/cover-foot409.svg';
        $heading = static fn (?string $title = null, ?string $description = null, ?string $subtitle = null, ?string $button = null): array => array_filter(['title' => $title, 'description' => $description, 'subtitle' => $subtitle, 'button_label' => $button], static fn (mixed $value): bool => $value !== null);
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số món ăn', 'default' => $limit], 'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục món ăn'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ món nổi bật']];
        $categorySchema = ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 6], 'order' => ['type' => 'select', 'label' => 'Sắp xếp', 'options' => [['value' => 'sort_order', 'label' => 'Thứ tự quản trị'], ['value' => 'name', 'label' => 'Tên']]]];
        $postSchema = ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 4], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin']];
        $promos = [
            ['title' => 'Gà cuộn ngon tuyệt', 'subtitle' => 'Giá sốc 28K', 'summary' => 'Ưu đãi 50% OFF', 'image' => '/theme-demo/foot409/promo-wrap.png', 'url' => '#mon-moi'],
            ['title' => 'Gà rán cay giòn', 'subtitle' => 'Giá sốc 55K', 'summary' => 'Ưu đãi hấp dẫn', 'image' => '/theme-demo/foot409/promo-feast.png', 'url' => '#mon-moi'],
            ['title' => 'Burger giòn đặc biệt', 'subtitle' => 'Deal trong ngày', 'summary' => 'Chốt đơn nhanh', 'image' => '/theme-demo/foot409/promo-burger.png', 'url' => '#mon-moi'],
        ];
        $benefits = [
            ['title' => 'Giao hàng siêu tốc', 'summary' => 'Nội thành chỉ trong 30 phút'],
            ['title' => 'Đổi món dễ dàng', 'summary' => 'Miễn phí đổi món trong 15 phút'],
            ['title' => 'Hỗ trợ tức thì', 'summary' => 'Hỗ trợ khách hàng 24/7'],
            ['title' => 'Deal hot bùng nổ', 'summary' => 'Giảm giá sốc mỗi ngày'],
        ];
        $suppliers = collect(['Coca-Cola', 'Dalat Milk', 'CP', 'Maggi', 'Kamereo', 'Ajinomoto'])->map(fn (string $title): array => ['title' => $title])->all();

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero đồ ăn nhanh', 'description' => 'Banner lớn toàn chiều rộng lấy từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot409-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Gà cay giòn tan', 'Giòn rụm bên ngoài, mềm ngon bên trong', 'TỚI FOOT409', 'Đặt ngay'), ['content' => ['slides' => [['title' => 'Gà cay giòn tan', 'summary' => 'Deal nóng hổi giao nhanh tận nơi', 'button_label' => 'Đặt ngay', 'image' => '/theme-demo/foot409/hero-fried-chicken.png', 'link_url' => '#mon-moi']]]]), 'en' => $heading('Hot and crispy chicken', 'Freshly made and fast delivered')]],
            ['block_type' => 'foot409_categories', 'label' => 'Lựa chọn thực đơn', 'description' => 'Sáu danh mục lấy trực tiếp từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'thuc-don', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 6, 'order' => 'sort_order'], 'settings_schema' => $categorySchema, 'data' => ['vi' => $heading('Lựa chọn thực đơn'), 'en' => $heading('Choose your menu')]],
            ['block_type' => 'foot409_promo_banner', 'label' => 'Banner Pizza', 'description' => 'Banner khuyến mãi pizza có thể thay ảnh và nội dung.', 'preview_image' => $preview, 'anchor_id' => 'pizza', 'settings' => ['background_image' => '/theme-demo/foot409/promo-pizza.png'], 'settings_schema' => ['background_image' => ['type' => 'image', 'label' => 'Ảnh nền']], 'data' => ['vi' => $heading('Pizza hôm nay', 'Giá sốc bất ngờ', 'ƯU ĐÃI 45%', 'Chốt đơn liền tay'), 'en' => $heading('Pizza today', 'A surprising deal', '45% OFF', 'Order now')]],
            ['block_type' => 'foot409_featured_products', 'label' => 'Chào ngày mới', 'description' => 'Bốn món nổi bật lấy từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'mon-moi', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $heading('Chào ngày mới'), 'en' => $heading('Hello new day')]],
            ['block_type' => 'foot409_dual_promos', 'label' => 'Hai banner ưu đãi', 'description' => 'Hai banner khuyến mãi đặt cạnh nhau.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai-doi', 'data' => ['vi' => $withItems($heading('Ưu đãi hấp dẫn'), array_slice($promos, 0, 2)), 'en' => $withItems($heading('Hot offers'), array_slice($promos, 0, 2))]],
            ['block_type' => 'foot409_recommendations', 'label' => 'Gợi ý cho bạn', 'description' => 'Tám món gợi ý lấy từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'goi-y', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $heading('Gợi ý cho bạn'), 'en' => $heading('Recommended for you')]],
            ['block_type' => 'foot409_triple_promos', 'label' => 'Ba banner khuyến mãi', 'description' => 'Ba banner ngang trước khu tin tức.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai-ba', 'data' => ['vi' => $withItems($heading('Deal hôm nay'), $promos), 'en' => $withItems($heading('Today deals'), $promos)]],
            ['block_type' => 'foot409_blog_posts', 'label' => 'Bảng tin khuyến mãi', 'description' => 'Bốn bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4], 'settings_schema' => $postSchema, 'data' => ['vi' => $heading('Bảng tin khuyến mãi'), 'en' => $heading('Promotion news')]],
            ['block_type' => 'foot409_suppliers', 'label' => 'Nhà cung cấp uy tín', 'description' => 'Dải tên nhà cung cấp có thể chỉnh sửa.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'data' => ['vi' => $withItems($heading('Nhà cung cấp uy tín'), $suppliers), 'en' => $withItems($heading('Trusted suppliers'), $suppliers)]],
            ['block_type' => 'foot409_benefits', 'label' => 'Quyền lợi khách hàng', 'description' => 'Bốn cam kết dịch vụ trước chân trang.', 'preview_image' => $preview, 'anchor_id' => 'quyen-loi', 'data' => ['vi' => $withItems($heading('Quyền lợi khách hàng'), $benefits), 'en' => $withItems($heading('Customer benefits'), $benefits)]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function foot407DefaultBlocks(): array
    {
        $preview = '/theme-previews/FOOT407/cover-foot407.svg';
        $heading = static fn (?string $title = null, ?string $description = null, ?string $subtitle = null, ?string $button = null): array => array_filter(['title' => $title, 'description' => $description, 'subtitle' => $subtitle, 'button_label' => $button], static fn (mixed $value): bool => $value !== null);
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit], 'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật']];
        $postSchema = static fn (int $limit): array => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit], 'search' => ['type' => 'text', 'label' => 'Từ khóa'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục tin']];
        $benefits = [
            ['title' => 'Sản phẩm nguồn gốc rõ ràng', 'summary' => 'Minh bạch thông tin và tiêu chuẩn chất lượng', 'icon' => 'fa-medal'],
            ['title' => 'Giao hàng thuận tiện', 'summary' => 'Đóng gói cẩn thận, hỗ trợ tận nơi', 'icon' => 'fa-truck-fast'],
            ['title' => 'Kiểm tra khi nhận hàng', 'summary' => 'Yên tâm trước khi hoàn tất thanh toán', 'icon' => 'fa-shield-heart'],
            ['title' => 'Chăm sóc khách hàng', 'summary' => 'Đội ngũ tư vấn tận tâm và chuyên nghiệp', 'icon' => 'fa-headset'],
        ];
        $whyItems = [
            ['title' => 'Nguồn nguyên liệu chọn lọc', 'summary' => 'Mỗi sản phẩm được tuyển chọn kỹ lưỡng và công bố thông tin minh bạch.', 'icon' => 'fa-seedling'],
            ['title' => 'Quy trình kiểm soát chặt chẽ', 'summary' => 'Chất lượng được theo dõi xuyên suốt từ đầu vào đến khi giao tới khách hàng.', 'icon' => 'fa-magnifying-glass-chart'],
            ['title' => 'Đồng hành cùng sức khỏe', 'summary' => 'Tư vấn phù hợp để khách hàng có lựa chọn an tâm và chủ động.', 'icon' => 'fa-heart-pulse'],
        ];
        $gallery = [
            ['title' => 'Tinh hoa từ thiên nhiên', 'image' => '/theme-demo/ec903/food-source.png', 'url' => '#san-pham'],
            ['title' => 'Quà tặng sức khỏe', 'image' => '/theme-demo/ec903/food-banquet.webp', 'url' => '#san-pham'],
            ['title' => 'Chọn lựa an tâm', 'image' => '/theme-demo/ec903/food-dessert.webp', 'url' => '#san-pham'],
        ];
        $partners = [
            ['title' => 'Đối tác chất lượng', 'image' => '/theme-demo/service/brand-mark.svg', 'url' => '#'],
            ['title' => 'Đối tác phân phối', 'image' => '/theme-demo/service/ser0101-slide-01.svg', 'url' => '#'],
            ['title' => 'Đối tác dịch vụ', 'image' => '/theme-demo/service/ser0101-slide-02.svg', 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero sức khỏe cao cấp', 'description' => 'Banner lớn xanh vàng lấy từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot407-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Tinh hoa chăm sóc sức khỏe', 'Sản phẩm chọn lọc cho cuộc sống chủ động và an tâm', 'Quà tặng sức khỏe', 'Khám phá sản phẩm'), ['content' => ['slides' => [['title' => 'Tinh hoa chăm sóc sức khỏe', 'summary' => 'Lựa chọn chất lượng cho người thân và gia đình', 'button_label' => 'Khám phá sản phẩm', 'image' => '/theme-demo/ec903/food-source.png', 'link_url' => '#san-pham']]]]), 'en' => $heading('Wellness essentials', 'Curated products for proactive living')]],
            ['block_type' => 'foot407_benefits', 'label' => 'Bốn cam kết', 'description' => 'Bốn cam kết dịch vụ dưới hero.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Service commitments'), $benefits)]],
            ['block_type' => 'foot407_product_tabs', 'label' => 'Sản phẩm theo nhu cầu', 'description' => 'Bốn sản phẩm và tab danh mục động.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $heading('Chọn sản phẩm bạn đang cần', 'Những lựa chọn nổi bật dành cho bạn'), 'en' => $heading('Choose what you need')]],
            ['block_type' => 'foot407_why_choose', 'label' => 'Vì sao chọn chúng tôi', 'description' => 'Khối giới thiệu hai cột xanh vàng.', 'preview_image' => $preview, 'anchor_id' => 'vi-sao', 'settings' => ['image' => '/theme-demo/ec903/food-banquet.webp'], 'settings_schema' => ['image' => ['type' => 'image', 'label' => 'Ảnh giới thiệu']], 'data' => ['vi' => $withItems($heading('Vì sao chọn chúng tôi', 'Để có một cuộc sống khỏe mạnh và chủ động', 'Giá trị khác biệt', 'Tìm hiểu thêm'), $whyItems), 'en' => $withItems($heading('Why choose us', 'For a healthier and more proactive life'), $whyItems)]],
            ['block_type' => 'foot407_gallery', 'label' => 'Bộ sưu tập hình ảnh', 'description' => 'Ba ảnh sản phẩm và thương hiệu.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'data' => ['vi' => $withItems($heading('Hành trình chất lượng'), $gallery), 'en' => $withItems($heading('Quality journey'), $gallery)]],
            ['block_type' => 'foot407_media_posts', 'label' => 'Truyền thông nói về chúng tôi', 'description' => 'Bốn bài viết từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'truyen-thong', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $postSchema(4), 'data' => ['vi' => $heading('Truyền thông nói về chúng tôi', 'Thông tin và câu chuyện nổi bật'), 'en' => $heading('Media highlights')]],
            ['block_type' => 'foot407_knowledge_posts', 'label' => 'Kiến thức chuyên sâu', 'description' => 'Ba bài kiến thức mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'kien-thuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $postSchema(3), 'data' => ['vi' => $heading('Kiến thức chuyên sâu', 'Góc chia sẻ hữu ích cho sức khỏe'), 'en' => $heading('Expert knowledge')]],
            ['block_type' => 'foot407_partners', 'label' => 'Đối tác khách hàng', 'description' => 'Dải logo đối tác lấy từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 8], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_partners', 'label' => 'Đối tác CMS'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác', 'default' => 8]], 'data' => ['vi' => $withItems($heading('Đối tác - khách hàng'), $partners), 'en' => $withItems($heading('Partners and customers'), $partners)]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function foot406DefaultBlocks(): array
    {
        $preview = '/theme-previews/FOOT406/cover-foot406.svg';
        $heading = static fn (?string $title = null, ?string $description = null, ?string $subtitle = null, ?string $button = null): array => array_filter(['title' => $title, 'description' => $description, 'subtitle' => $subtitle, 'button_label' => $button], static fn (mixed $value): bool => $value !== null);
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $categorySchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 3],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $promoBanners = [
            ['title' => 'Mát lành ngày mới', 'summary' => 'Ưu đãi cho thức uống thanh mát', 'image' => '/theme-demo/ec903/deal-tea.webp', 'url' => '#khuyen-mai'],
            ['title' => 'Hương vị đầy cảm hứng', 'summary' => 'Khám phá lựa chọn được yêu thích', 'image' => '/theme-demo/ec903/food-dessert.webp', 'url' => '#yeu-thich'],
        ];
        $partnerFallback = [
            ['title' => 'Đối tác nguyên liệu', 'image' => '/theme-demo/service/brand-mark.svg', 'url' => '#'],
            ['title' => 'Đối tác vận chuyển', 'image' => '/theme-demo/service/ser0101-slide-01.svg', 'url' => '#'],
            ['title' => 'Đối tác dịch vụ', 'image' => '/theme-demo/service/ser0101-slide-02.svg', 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero đồ uống', 'description' => 'Banner toàn chiều rộng lấy từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot406-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Hương vị tươi mát mỗi ngày', 'Thưởng thức đồ uống được pha chế từ nguyên liệu chọn lọc', 'Ưu đãi trong tuần', 'Khám phá thực đơn'), ['content' => ['slides' => [['title' => 'Hương vị tươi mát mỗi ngày', 'summary' => 'Đồ uống thơm ngon cho mọi khoảnh khắc', 'button_label' => 'Khám phá thực đơn', 'image' => '/theme-demo/ec903/deal-tea.webp', 'link_url' => '#khuyen-mai']]]]), 'en' => $heading('Fresh flavours every day', 'Drinks crafted from selected ingredients')]],
            ['block_type' => 'foot406_categories', 'label' => 'Ba nhóm đồ uống', 'description' => 'Ba danh mục ảnh tròn lấy từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 3, 'featured_only' => false, 'order' => 'sort_order'], 'settings_schema' => $categorySchema, 'data' => ['vi' => $heading('Chào mừng bạn đến với cửa hàng', 'Khám phá ba nhóm hương vị nổi bật'), 'en' => $heading('Welcome to our store')]],
            ['block_type' => 'foot406_promo_products', 'label' => 'Đồ uống khuyến mãi', 'description' => 'Mười đồ uống khuyến mãi từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'featured_only' => true], 'settings_schema' => $productSchema(10), 'data' => ['vi' => $heading('Đồ uống khuyến mãi', 'Những hương vị đang có giá tốt'), 'en' => $heading('Drink promotions')]],
            ['block_type' => 'foot406_promo_duo', 'label' => 'Hai banner khuyến mãi', 'description' => 'Hai banner ngang giữa các dải sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'banner-uu-dai', 'data' => ['vi' => $withItems($heading('Ưu đãi nổi bật'), $promoBanners), 'en' => $withItems($heading('Featured offers'), $promoBanners)]],
            ['block_type' => 'foot406_favorites', 'label' => 'Đồ uống yêu thích', 'description' => 'Năm sản phẩm được yêu thích.', 'preview_image' => $preview, 'anchor_id' => 'yeu-thich', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $heading('Đồ uống yêu thích', 'Những lựa chọn được khách hàng quan tâm'), 'en' => $heading('Favourite drinks')]],
            ['block_type' => 'foot406_latest_posts', 'label' => 'Bài viết mới nhất', 'description' => 'Bốn bài viết mới từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'bai-viet', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $postSchema, 'data' => ['vi' => $heading('Các bài viết mới nhất', 'Câu chuyện, bí quyết và xu hướng đồ uống'), 'en' => $heading('Latest articles')]],
            ['block_type' => 'foot406_partners', 'label' => 'Đối tác', 'description' => 'Logo đối tác lấy từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 5], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_partners', 'label' => 'Đối tác CMS'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác', 'default' => 5]], 'data' => ['vi' => $withItems($heading('Đối tác đồng hành'), $partnerFallback), 'en' => $withItems($heading('Our partners'), $partnerFallback)]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function foot405DefaultBlocks(): array
    {
        $preview = '/theme-previews/FOOT405/cover-foot405.svg';
        $heading = static fn (?string $title = null, ?string $description = null, ?string $subtitle = null, ?string $button = null): array => array_filter([
            'title' => $title,
            'description' => $description,
            'subtitle' => $subtitle,
            'button_label' => $button,
        ], static fn (mixed $value): bool => $value !== null);
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $categorySchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 6],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $promos = [
            ['title' => 'Tươi ngon mỗi ngày', 'summary' => 'Sản phẩm chọn lọc từ nguồn uy tín', 'image' => '/theme-demo/ec916/promo-grocery.webp', 'url' => '#pho-bien'],
            ['title' => 'Bữa sáng nhẹ nhàng', 'summary' => 'Khởi đầu ngày mới đầy năng lượng', 'image' => '/theme-demo/ec903/food-brunch.webp', 'url' => '#ban-chay'],
            ['title' => 'Dinh dưỡng từ thiên nhiên', 'summary' => 'Lựa chọn an tâm cho mọi gia đình', 'image' => '/theme-demo/ec916/promo-family.webp', 'url' => '#khuyen-mai'],
        ];
        $benefits = [
            ['title' => 'Giá hợp lý', 'summary' => 'Minh bạch và cạnh tranh', 'icon' => 'fa-tags'],
            ['title' => 'Giao hàng thuận tiện', 'summary' => 'Theo chính sách từng khu vực', 'icon' => 'fa-truck-fast'],
            ['title' => 'Ưu đãi thường xuyên', 'summary' => 'Nhiều chương trình hấp dẫn', 'icon' => 'fa-gift'],
            ['title' => 'Hỗ trợ đổi trả', 'summary' => 'Tư vấn rõ ràng, tận tâm', 'icon' => 'fa-box-open'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero siêu thị xanh', 'description' => 'Banner lớn bo góc, tự chuyển từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'foot405-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Ưu đãi tươi mới mỗi ngày', 'Mua sắm thuận tiện với sản phẩm được chọn lọc', 'Chất lượng cho gia đình', 'Khám phá sản phẩm'), ['content' => ['slides' => [['title' => 'Ưu đãi tươi mới mỗi ngày', 'summary' => 'Sản phẩm được chọn lọc cho bữa ăn ngon lành', 'button_label' => 'Khám phá sản phẩm', 'image' => '/theme-demo/ec916/hero-mega-sale.webp', 'link_url' => '#pho-bien']]]]), 'en' => $heading('Fresh offers every day', 'Curated products for your family')]],
            ['block_type' => 'foot405_categories', 'label' => 'Danh mục chính', 'description' => 'Sáu danh mục lấy trực tiếp từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 6, 'featured_only' => false, 'order' => 'sort_order'], 'settings_schema' => $categorySchema, 'data' => ['vi' => $heading('Danh mục chính', null, null, 'Tất cả sản phẩm'), 'en' => $heading('Main categories', null, null, 'All products')]],
            ['block_type' => 'foot405_popular_products', 'label' => 'Sản phẩm phổ biến', 'description' => 'Bốn sản phẩm phổ biến từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'pho-bien', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => true], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $heading('Sản phẩm phổ biến', 'Các lựa chọn được quan tâm trong tuần'), 'en' => $heading('Popular products')]],
            ['block_type' => 'foot405_promo_trio', 'label' => 'Ba banner ngành hàng', 'description' => 'Ba banner quảng bá nhỏ theo bố cục tham chiếu.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai-noi-bat', 'data' => ['vi' => $withItems($heading('Ưu đãi nổi bật'), $promos), 'en' => $withItems($heading('Featured offers'), $promos)]],
            ['block_type' => 'foot405_best_sellers', 'label' => 'Sản phẩm bán chạy', 'description' => 'Banner dọc đi cùng ba sản phẩm bán chạy.', 'preview_image' => $preview, 'anchor_id' => 'ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'featured_only' => false, 'feature_image' => '/theme-demo/ec916/product-grocery.webp'], 'settings_schema' => array_merge($productSchema(3), ['feature_image' => ['type' => 'image', 'label' => 'Ảnh banner bên trái']]), 'data' => ['vi' => $heading('Sản phẩm bán chạy', 'Những lựa chọn được khách hàng yêu thích', null, 'Xem tất cả'), 'en' => $heading('Best sellers')]],
            ['block_type' => 'foot405_daily_deals', 'label' => 'Khuyến mãi trong ngày', 'description' => 'Ba sản phẩm khuyến mãi dạng thẻ lớn.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'featured_only' => true], 'settings_schema' => $productSchema(3), 'data' => ['vi' => $heading('Khuyến mãi trong ngày', 'Ưu đãi giới hạn dành cho bạn', null, 'Xem tất cả'), 'en' => $heading('Daily deals')]],
            ['block_type' => 'foot405_product_columns', 'label' => 'Ba cột sản phẩm', 'description' => 'Chín sản phẩm chia thành ba nhóm gọn.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 9, 'featured_only' => false], 'settings_schema' => $productSchema(9), 'data' => ['vi' => $heading('Gợi ý mua sắm', 'Sản phẩm mới · Được mua nhiều · Gợi ý cho bạn'), 'en' => $heading('Shopping suggestions')]],
            ['block_type' => 'foot405_newsletter', 'label' => 'Đăng ký nhận ưu đãi', 'description' => 'Banner đăng ký nhận tin và khuyến mãi.', 'preview_image' => $preview, 'anchor_id' => 'nhan-tin', 'settings' => ['background_image' => '/theme-demo/ec906/hero-minimart.png'], 'settings_schema' => ['background_image' => ['type' => 'image', 'label' => 'Ảnh minh họa']], 'data' => ['vi' => $heading('Mua sắm tại nhà thật dễ dàng', 'Đăng ký để nhận thông tin sản phẩm và ưu đãi mới', 'Bản tin ưu đãi', 'Đăng ký'), 'en' => $heading('Easy shopping at home', 'Subscribe for product news and offers', 'Newsletter', 'Subscribe')]],
            ['block_type' => 'foot405_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn cam kết dịch vụ trước chân trang.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Service benefits'), $benefits)]],
        ];
    }

    private function ec917DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC917/cover-ec917.png';
        $heading = static fn (?string $title = null, ?string $summary = null, ?string $subtitle = null): array => ['title' => $title, 'summary' => $summary, 'subtitle' => $subtitle];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $categories = [
            ['title' => 'Phòng khách', 'summary' => '11 sản phẩm', 'image' => '/theme-demo/ec917/room-living-room.webp', 'url' => '#san-pham'],
            ['title' => 'Phòng ngủ', 'summary' => '8 sản phẩm', 'image' => '/theme-demo/ec917/room-bedroom.webp', 'url' => '#san-pham'],
            ['title' => 'Nhà bếp', 'summary' => '8 sản phẩm', 'image' => '/theme-demo/ec917/room-dining-room.webp', 'url' => '#san-pham'],
            ['title' => 'Phòng làm việc', 'summary' => '9 sản phẩm', 'image' => '/theme-demo/ec917/room-office.webp', 'url' => '#san-pham'],
            ['title' => 'Đèn trang trí', 'summary' => '2 sản phẩm', 'image' => '/theme-demo/ec917/product-lamp-black.webp', 'url' => '#san-pham'],
            ['title' => 'Kệ lưu giữ', 'summary' => '11 sản phẩm', 'image' => '/theme-demo/ec917/product-sideboard-walnut.webp', 'url' => '#san-pham'],
        ];
        $collections = [
            ['title' => 'BST Phòng Bếp', 'image' => '/theme-demo/ec917/room-dining-room.webp', 'url' => '#san-pham', 'x' => 47, 'y' => 36, 'x2' => 20, 'y2' => 59],
            ['title' => 'BST Phòng Ngủ', 'image' => '/theme-demo/ec917/room-bedroom.webp', 'url' => '#san-pham', 'x' => 45, 'y' => 24, 'x2' => 34, 'y2' => 55],
            ['title' => 'BST Phòng Khách', 'image' => '/theme-demo/ec917/room-living-room.webp', 'url' => '#san-pham', 'x' => 42, 'y' => 34, 'x2' => 73, 'y2' => 46],
        ];
        $posts = [
            ['title' => 'Cách trang trí cầu thang gỗ', 'summary' => 'Trang trí cầu thang là một phần quan trọng trong nội thất của một ngôi nhà hiện đại.', 'date' => '27/07/2023', 'read_time' => '1 phút đọc', 'image' => '/theme-demo/ec917/room-office.webp', 'url' => '#'],
            ['title' => 'Vợ chồng và cách chọn giường ngủ', 'summary' => 'Lựa chọn giường ngủ phù hợp giúp cân bằng công năng và cảm xúc trong phòng ngủ.', 'date' => '27/07/2023', 'read_time' => '2 phút đọc', 'image' => '/theme-demo/ec917/room-bedroom.webp', 'url' => '#'],
            ['title' => 'Sofa gia đình - bài trí sao cho hợp phong thủy?', 'summary' => 'Bố trí sofa đúng cách giúp phòng khách đẹp hơn và tạo luồng di chuyển thuận tiện.', 'date' => '27/07/2023', 'read_time' => '2 phút đọc', 'image' => '/theme-demo/ec917/room-living-room.webp', 'url' => '#'],
            ['title' => 'Sofa góc và bí quyết tăng tài lộc cho ngôi nhà', 'summary' => 'Một vài gợi ý chọn vị trí và màu sắc sofa để hoàn thiện không gian sống.', 'date' => '27/07/2023', 'read_time' => '3 phút đọc', 'image' => '/theme-demo/ec917/room-dining-room.webp', 'url' => '#'],
        ];
        $benefits = [
            ['title' => 'Hotline: 19001993', 'summary' => 'Dịch vụ hỗ trợ bạn 24/7', 'icon' => 'fa-comments'],
            ['title' => 'Quà tặng hấp dẫn', 'summary' => 'Nhiều ưu đãi khuyến mãi hot', 'icon' => 'fa-gift'],
            ['title' => 'Đổi trả miễn phí', 'summary' => 'Trong vòng 7 ngày', 'icon' => 'fa-rotate-left'],
            ['title' => 'Giá luôn tốt nhất', 'summary' => 'Hoàn tiền nếu nơi khác rẻ hơn', 'icon' => 'fa-dollar-sign'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Black Friday', 'description' => 'Banner nội thất toàn chiều rộng với CTA màu cam.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec917-hero-slider', 'limit' => 2, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Săn ngay deal nội thất khủng', 'Giảm 50% tất cả sản phẩm'), ['content' => ['slides' => [['badge' => 'BLACK FRIDAY', 'title' => 'Săn ngay deal nội thất khủng', 'summary' => 'Giảm 50% tất cả sản phẩm', 'button_label' => 'MUA NGAY', 'image' => '/theme-demo/ec917/hero-interior.webp', 'link_url' => '#san-pham']]]]), 'en' => $heading('Black Friday furniture deals')]],
            ['block_type' => 'ec917_categories', 'label' => 'Danh mục sản phẩm', 'description' => 'Sáu danh mục nội thất theo phòng dạng hình tròn.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'data' => ['vi' => $withItems($heading('DANH MỤC SẢN PHẨM'), $categories), 'en' => $withItems($heading('PRODUCT CATEGORIES'), $categories)]],
            ['block_type' => 'ec917_summer_sale', 'label' => 'Happy Summer Sale', 'description' => 'Tám sản phẩm nội thất giảm đến 50%.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC917-', 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $heading('HAPPY SUMMER - GIẢM ĐẾN 50% 🔥', null, 'Bán chạy'), 'en' => $heading('HAPPY SUMMER - UP TO 50% OFF')]],
            ['block_type' => 'ec917_promo_banner', 'label' => 'Banner sofa mới về', 'description' => 'Banner khuyến mãi sofa toàn chiều ngang.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'settings_schema' => ['image' => ['type' => 'image', 'label' => 'Ảnh banner'], 'url' => ['type' => 'text', 'label' => 'Liên kết']], 'data' => ['vi' => array_merge($heading('Sofa hàng mới về'), ['url' => '#san-pham']), 'en' => $heading('New sofa promotion')], 'media' => ['image' => '/theme-demo/ec917/promo-sofa.png']],
            ['block_type' => 'ec917_collections', 'label' => 'Bộ sưu tập theo phòng', 'description' => 'Ba bộ sưu tập phòng bếp, phòng ngủ và phòng khách.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'data' => ['vi' => $withItems($heading('BST NỘI THẤT DÀNH CHO BẠN'), $collections), 'en' => $withItems($heading('COLLECTIONS FOR YOU'), $collections)]],
            ['block_type' => 'ec917_inspiration', 'label' => 'Góc cảm hứng', 'description' => 'Bốn bài viết tư vấn và xu hướng nội thất.', 'preview_image' => $preview, 'anchor_id' => 'cam-hung', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => '', 'featured_only' => false], 'settings_schema' => $postSchema, 'data' => ['vi' => $withItems($heading('GÓC CẢM HỨNG'), $posts), 'en' => $withItems($heading('INSPIRATION'), $posts)]],
            ['block_type' => 'ec917_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Hotline, quà tặng, đổi trả và cam kết giá.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Service benefits'), $benefits)]],
            ['block_type' => 'ec917_footer', 'label' => 'Chân trang', 'description' => 'Thông tin doanh nghiệp, hỗ trợ, chính sách và đăng ký nhận tin.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin EGA Furniture'), 'en' => $heading('EGA Furniture information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function book920DefaultBlocks(): array
    {
        $preview = '/theme-previews/BOOK920/cover-book920.webp';
        $heading = static fn (?string $title = null, ?string $summary = null): array => ['title' => $title, 'summary' => $summary];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $benefits = [
            ['title' => 'Chất lượng hàng đầu', 'summary' => 'Cam kết sách chính hãng 100%', 'icon' => 'fa-solid fa-award'],
            ['title' => 'Giao hàng toàn quốc', 'summary' => 'Giao hàng nhanh trong 24h', 'icon' => 'fa-solid fa-truck-fast'],
            ['title' => 'Mua hàng tiết kiệm', 'summary' => 'Khuyến mãi với ưu đãi cực lớn', 'icon' => 'fa-solid fa-hand-holding-dollar'],
            ['title' => 'Hỗ trợ online', 'summary' => 'Gọi 1900 9477 để được tư vấn', 'icon' => 'fa-solid fa-headset'],
        ];
        $testimonials = [
            ['title' => 'Dianne Russell', 'role' => 'Quản lý dự án', 'summary' => 'Bookle có không gian ấm cúng, sách tuyển chọn kỹ và đội ngũ tư vấn rất tận tâm.'],
            ['title' => 'Nguyễn Minh Anh', 'role' => 'Biên tập viên', 'summary' => 'Không chỉ là nơi mua sách, Bookle còn là điểm đến văn hóa đầy cảm hứng.'],
            ['title' => 'Ronald Richards', 'role' => 'Điều phối tiếp thị', 'summary' => 'Dịch vụ nhanh chóng, nhiều đầu sách hay và trải nghiệm mua sắm thật dễ chịu.'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero nhà sách', 'description' => 'Không gian nhà sách toàn chiều rộng.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'book920-hero-slider', 'limit' => 2], 'data' => ['vi' => array_merge($heading('Không gian đọc đầy cảm hứng'), ['content' => ['slides' => [['title' => null, 'image' => '/theme-demo/book920/hero-bookstore.png']]]]), 'en' => $heading('An inspiring reading space')]],
            ['block_type' => 'book920_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn cam kết mua sắm.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'data' => ['vi' => $withItems($heading('Cam kết Bookle'), $benefits), 'en' => $withItems($heading('Bookle benefits'), $benefits)]],
            ['block_type' => 'book920_featured', 'label' => 'Sách nổi bật', 'description' => 'Mười sách nổi bật từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'sach-noi-bat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'featured_only' => true], 'settings_schema' => $productSchema(10), 'data' => ['vi' => $heading('Sách nổi bật'), 'en' => $heading('Featured books')]],
            ['block_type' => 'book920_sale', 'label' => 'Sách khuyến mãi', 'description' => 'Bốn sách đang ưu đãi.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $heading('Sách khuyến mãi'), 'en' => $heading('Books on sale')]],
            ['block_type' => 'book920_promo', 'label' => 'Banner ưu đãi', 'description' => 'Banner sách bán chạy giảm 25%.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai', 'data' => ['vi' => $heading('Giảm giá 25% cho tất cả các loại sách bán chạy'), 'en' => $heading('25% off bestselling books')]],
            ['block_type' => 'book920_hot', 'label' => 'Sản phẩm HOT', 'description' => 'Sáu sách hot dạng danh sách.', 'preview_image' => $preview, 'anchor_id' => 'sach-hot', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 6, 'featured_only' => false], 'settings_schema' => $productSchema(6), 'data' => ['vi' => $heading('Sản phẩm HOT'), 'en' => $heading('Hot products')]],
            ['block_type' => 'book920_testimonials', 'label' => 'Ý kiến khách hàng', 'description' => 'Ba đánh giá của độc giả.', 'preview_image' => $preview, 'anchor_id' => 'cam-nhan', 'data' => ['vi' => $withItems($heading('Khách hàng của chúng tôi nói gì'), $testimonials), 'en' => $withItems($heading('What our readers say'), $testimonials)]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức mới nhất', 'description' => 'Bốn bài viết từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $postSchema, 'data' => ['vi' => $heading('Tin tức mới nhất', 'Cảm hứng đọc sách và những câu chuyện mới từ Bookle.'), 'en' => $heading('Latest news')]],
            ['block_type' => 'book920_footer', 'label' => 'Chân trang', 'description' => 'Liên hệ, danh mục và đăng ký nhận tin.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin Bookle'), 'en' => $heading('Bookle information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec916DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC916/cover-ec916.webp';
        $heading = static fn (?string $title = null, ?string $summary = null): array => ['title' => $title, 'summary' => $summary];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $categories = [
            ['title' => 'Thực phẩm', 'summary' => 'Tươi ngon mỗi ngày', 'image' => '/theme-demo/ec916/product-grocery.webp', 'url' => '#noi-bat'],
            ['title' => 'Mobile & Tablet', 'summary' => 'Công nghệ mới', 'image' => '/theme-demo/ec916/product-phone.webp', 'url' => '#noi-bat'],
            ['title' => 'Thời trang', 'summary' => 'Phong cách nổi bật', 'image' => '/theme-demo/ec916/product-dress.webp', 'url' => '#noi-bat'],
            ['title' => 'Sức khỏe & Làm đẹp', 'summary' => 'Chăm sóc toàn diện', 'image' => '/theme-demo/ec916/product-skincare.webp', 'url' => '#lam-dep'],
            ['title' => 'Gia dụng', 'summary' => 'Tiện nghi trong nhà', 'image' => '/theme-demo/ec916/product-blender.webp', 'url' => '#chien-dich'],
            ['title' => 'Nhà cửa', 'summary' => 'Không gian ấm cúng', 'image' => '/theme-demo/ec916/product-sofa.webp', 'url' => '#chien-dich'],
            ['title' => 'Mẹ & Bé', 'summary' => 'An tâm chăm sóc', 'image' => '/theme-demo/ec916/product-baby.webp', 'url' => '#chien-dich'],
            ['title' => 'Phụ kiện số', 'summary' => 'Âm thanh sống động', 'image' => '/theme-demo/ec916/product-headphones.webp', 'url' => '#noi-bat'],
        ];
        $promos = [
            ['badge' => 'LÀN DA RẠNG RỠ', 'title' => 'Chăm sóc da mùa nắng', 'summary' => 'Ưu đãi đến 45%', 'image' => '/theme-demo/ec916/promo-beauty.webp', 'url' => '#lam-dep'],
            ['badge' => 'MỪNG GIA ĐÌNH', 'title' => 'Yêu thương cho bé', 'summary' => 'Quà tặng cho đơn từ 399k', 'image' => '/theme-demo/ec916/promo-family.webp', 'url' => '#chien-dich'],
        ];
        $campaigns = [
            ['badge' => 'TƯƠI NGON', 'title' => 'Thực phẩm chọn lọc mỗi ngày', 'summary' => 'Giao nhanh trong 2 giờ', 'image' => '/theme-demo/ec916/promo-grocery.webp', 'url' => '#noi-bat'],
            ['badge' => 'ĐẸP HƠN MỖI NGÀY', 'title' => 'Thiên đường làm đẹp', 'summary' => 'Chính hãng, giá tốt', 'image' => '/theme-demo/ec916/promo-beauty.webp', 'url' => '#lam-dep'],
            ['badge' => 'CẢ NHÀ VUI', 'title' => 'Ưu đãi gia đình', 'summary' => 'Combo tiết kiệm', 'image' => '/theme-demo/ec916/promo-family.webp', 'url' => '#noi-bat'],
            ['badge' => 'CÔNG NGHỆ', 'title' => 'Nâng cấp tiện nghi', 'summary' => 'Trả góp linh hoạt', 'image' => '/theme-demo/ec916/promo-electronics.webp', 'url' => '#noi-bat'],
        ];
        $brands = collect(['FreshGo', 'NovaTech', 'Belle', 'HomePlus', 'Momy', 'PureCare', 'Viva', 'DailyMart'])->map(fn (string $title): array => ['title' => $title])->all();

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero đại tiệc mua sắm', 'description' => 'Banner toàn chiều rộng cho ưu đãi đa ngành.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec916-hero-slider', 'limit' => 2, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Hàng ngàn ưu đãi – Giá tốt mỗi ngày', 'Hàng chính hãng, giao nhanh tận nơi và đổi trả dễ dàng.'), ['content' => ['slides' => [['badge' => 'ĐẠI TIỆC MUA SẮM', 'title' => 'Hàng ngàn ưu đãi – Giá tốt mỗi ngày', 'summary' => 'Mọi nhu cầu trong một điểm đến tiện lợi.', 'image' => '/theme-demo/ec916/hero-mega-sale.webp', 'link_url' => '#noi-bat']]]]), 'en' => $heading('Everyday deals for every need')]],
            ['block_type' => 'ec916_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Tám danh mục đa ngành dạng thẻ tròn.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'data' => ['vi' => $withItems($heading('Danh mục nổi bật'), $categories), 'en' => $withItems($heading('Featured categories'), $categories)]],
            ['block_type' => 'ec916_featured_deals', 'label' => 'Sản phẩm nổi bật', 'description' => 'Bốn ưu đãi chính trên trang chủ.', 'preview_image' => $preview, 'anchor_id' => 'noi-bat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC916-FEATURED', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $heading('Sản phẩm nổi bật'), 'en' => $heading('Featured deals')]],
            ['block_type' => 'ec916_promo_pair', 'label' => 'Banner ưu đãi đôi', 'description' => 'Hai banner làm đẹp và mẹ bé.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai', 'data' => ['vi' => $withItems($heading('Ưu đãi trong tuần'), $promos), 'en' => $withItems($heading('Weekly offers'), $promos)]],
            ['block_type' => 'ec916_beauty_deals', 'label' => 'Sức khỏe & Làm đẹp', 'description' => 'Tám sản phẩm chăm sóc nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'lam-dep', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC916-BEAUTY', 'featured_only' => false], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $heading('Sức khỏe & Làm đẹp'), 'en' => $heading('Health & beauty')]],
            ['block_type' => 'ec916_campaign_mosaic', 'label' => 'Chiến dịch mua sắm', 'description' => 'Mosaic bốn chiến dịch theo ngành.', 'preview_image' => $preview, 'anchor_id' => 'chien-dich', 'data' => ['vi' => $withItems($heading('Chiến dịch mua sắm trong tuần'), $campaigns), 'en' => $withItems($heading('Weekly shopping campaigns'), $campaigns)]],
            ['block_type' => 'ec916_brands', 'label' => 'Thương hiệu', 'description' => 'Dải tám thương hiệu nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu được yêu thích'), $brands), 'en' => $withItems($heading('Loved brands'), $brands)]],
            ['block_type' => 'ec916_newsletter', 'label' => 'Đăng ký khuyến mãi', 'description' => 'Biểu mẫu nhận ưu đãi qua email.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Đăng ký nhận thông tin ưu đãi và khuyến mãi', 'Thông tin của bạn được bảo mật và có thể hủy đăng ký bất cứ lúc nào.'), 'en' => $heading('Get deals in your inbox')]],
            ['block_type' => 'ec916_footer', 'label' => 'Chân trang', 'description' => 'Hỗ trợ, thông tin doanh nghiệp và hợp tác.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin Bách Hóa Xanh Plus'), 'en' => $heading('Store information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec915DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC915/cover-ec915.webp';
        $heading = static fn (?string $title = null, ?string $summary = null): array => ['title' => $title, 'summary' => $summary];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 3],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $rooms = [
            ['title' => 'Sản phẩm mới', 'summary' => '20+ thiết kế mới', 'image' => '/theme-demo/ec915/product-lamp-black.webp', 'url' => '#ban-chay'],
            ['title' => 'Văn phòng', 'summary' => 'Bàn, ghế và tủ', 'image' => '/theme-demo/ec915/room-office.webp', 'url' => '#ban-chay'],
            ['title' => 'Phòng khách', 'summary' => 'Sofa và bàn trà', 'image' => '/theme-demo/ec915/room-living-room.webp', 'url' => '#ban-chay'],
            ['title' => 'Phòng ngủ', 'summary' => 'Giường và tủ đầu giường', 'image' => '/theme-demo/ec915/room-bedroom.webp', 'url' => '#ban-chay'],
            ['title' => 'Phòng bếp', 'summary' => 'Bàn ăn và ghế', 'image' => '/theme-demo/ec915/room-dining-room.webp', 'url' => '#ban-chay'],
        ];
        $stats = [
            ['value' => '1600+', 'title' => 'Dự án hoàn thành'],
            ['value' => '180+', 'title' => 'Nhân sự chuyên môn cao'],
            ['value' => '38+', 'title' => 'Đối tác uy tín toàn quốc'],
        ];
        $process = [
            ['title' => 'Khảo sát và lên kế hoạch', 'summary' => 'Đo đạc, đánh giá hiện trạng và lập kế hoạch thi công.', 'icon' => 'fa-clipboard-check'],
            ['title' => 'Thiết kế và phê duyệt', 'summary' => 'Xây dựng và phê duyệt bản vẽ thiết kế nội thất.', 'icon' => 'fa-compass-drafting'],
            ['title' => 'Thi công và lắp đặt', 'summary' => 'Sản xuất, thi công và lắp đặt từng hạng mục.', 'icon' => 'fa-screwdriver-wrench'],
            ['title' => 'Kiểm tra và bàn giao', 'summary' => 'Kiểm tra hoàn thiện trước khi bàn giao công trình.', 'icon' => 'fa-house-circle-check'],
        ];
        $reasons = [
            ['title' => 'Chất lượng và thẩm mỹ vượt trội', 'summary' => 'Vật liệu cao cấp, thiết kế tinh tế và quy trình kiểm soát chất lượng nghiêm ngặt.'],
            ['title' => 'Dịch vụ chuyên nghiệp, tận tâm', 'summary' => 'Lắng nghe nhu cầu và đồng hành từ bản vẽ đầu tiên đến ngày bàn giao.'],
        ];
        $faqs = [
            ['title' => 'ND Interior cung cấp những dịch vụ nào?', 'summary' => 'Thiết kế, thi công nội thất trọn gói và cung cấp sản phẩm nội thất cao cấp.'],
            ['title' => 'Thời gian thi công nội thất mất bao lâu?', 'summary' => 'Thời gian phụ thuộc quy mô và vật liệu, thông thường từ 30 đến 90 ngày.'],
            ['title' => 'Có hỗ trợ thiết kế theo yêu cầu không?', 'summary' => 'Có. Đội ngũ thiết kế phát triển phương án riêng theo công năng, ngân sách và phong cách của bạn.'],
            ['title' => 'Chính sách bảo hành sản phẩm như thế nào?', 'summary' => 'Mỗi sản phẩm và hạng mục đều có thời hạn bảo hành rõ ràng trong hợp đồng.'],
        ];
        $testimonials = [
            ['title' => 'Hồng Mến', 'role' => 'Kinh doanh', 'summary' => 'Đội ngũ tư vấn tận tình, thi công đúng tiến độ và không gian hoàn thiện rất tinh tế.'],
            ['title' => 'Phương Ly', 'role' => 'Chủ căn hộ', 'summary' => 'Sản phẩm đẹp, chất liệu cao cấp và dịch vụ hậu mãi chu đáo.'],
            ['title' => 'Hà Phương', 'role' => 'Kế toán', 'summary' => 'Ý tưởng của tôi đã trở thành một không gian sống sang trọng, tiện nghi và đáng tin cậy.'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero nội thất', 'description' => 'Banner toàn màn hình cho studio nội thất.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec915-hero-slider', 'limit' => 2, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Chuyên thi công & cung cấp sản phẩm nội thất cao cấp', 'Kiến tạo không gian sống và làm việc hoàn hảo.'), ['content' => ['slides' => [['badge' => 'CHÀO MỪNG BẠN ĐẾN VỚI CHÚNG TÔI', 'title' => 'Chuyên thi công & cung cấp sản phẩm nội thất cao cấp', 'summary' => 'Thiết kế tinh tế, thi công bền vững và dịch vụ tận tâm.', 'image' => '/theme-demo/ec915/hero-interior.webp', 'link_url' => '#gioi-thieu']]]]), 'en' => $heading('Premium interior design and build')]],
            ['block_type' => 'ec915_about', 'label' => 'Về chúng tôi', 'description' => 'Giới thiệu, hình ảnh và ba số liệu năng lực.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'media' => ['image' => '/theme-demo/ec915/room-living-room.webp'], 'data' => ['vi' => $withItems($heading('Giải pháp nội thất hoàn hảo cho không gian của bạn', 'ND Interior chuyên thiết kế, thi công nội thất trọn gói và cung cấp sản phẩm cao cấp cho nhà ở, căn hộ, văn phòng và showroom.'), $stats), 'en' => $withItems($heading('Complete interior solutions'), $stats)]],
            ['block_type' => 'ec915_room_categories', 'label' => 'Không gian nội thất', 'description' => 'Lưới danh mục theo từng không gian.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'data' => ['vi' => $withItems($heading('Khám phá theo không gian'), $rooms), 'en' => $withItems($heading('Shop by room'), $rooms)]],
            ['block_type' => 'ec915_best_sellers', 'label' => 'Sản phẩm bán chạy', 'description' => 'Tám sản phẩm nội thất nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC915-PRODUCT', 'featured_only' => true], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $heading('Sản phẩm bán chạy'), 'en' => $heading('Best sellers')]],
            ['block_type' => 'ec915_contact_banner', 'label' => 'Banner tư vấn', 'description' => 'Ảnh panorama cùng hotline và nút liên hệ.', 'preview_image' => $preview, 'anchor_id' => 'tu-van', 'data' => ['vi' => $heading('Liên hệ với ND Interior để được tư vấn', 'Đội ngũ chuyên gia luôn sẵn sàng hỗ trợ bạn tận tình.'), 'en' => $heading('Contact our interior consultants')]],
            ['block_type' => 'ec915_process', 'label' => 'Quy trình làm việc', 'description' => 'Bốn bước triển khai dự án nội thất.', 'preview_image' => $preview, 'anchor_id' => 'quy-trinh', 'data' => ['vi' => $withItems($heading('Cam kết chất lượng từ ND Interior'), $process), 'en' => $withItems($heading('Our working process'), $process)]],
            ['block_type' => 'ec915_reasons', 'label' => 'Lý do lựa chọn', 'description' => 'Lợi thế dịch vụ và hình ảnh dự án.', 'preview_image' => $preview, 'anchor_id' => 'ly-do', 'data' => ['vi' => $withItems($heading('ND Interior luôn ưu tiên sự hài lòng khách hàng', 'Chúng tôi cam kết chất lượng, thẩm mỹ và trải nghiệm dịch vụ xuyên suốt.'), $reasons), 'en' => $withItems($heading('Why choose ND Interior?'), $reasons)]],
            ['block_type' => 'ec915_faq', 'label' => 'Câu hỏi thường gặp', 'description' => 'Khối hỏi đáp dạng accordion.', 'preview_image' => $preview, 'anchor_id' => 'faq', 'data' => ['vi' => $withItems($heading('Câu hỏi thường gặp?', 'Những thông tin quan trọng về thiết kế, thi công và bảo hành.'), $faqs), 'en' => $withItems($heading('Frequently asked questions'), $faqs)]],
            ['block_type' => 'ec915_testimonials', 'label' => 'Ý kiến khách hàng', 'description' => 'Ba đánh giá khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'danh-gia', 'data' => ['vi' => $withItems($heading('Khách hàng nói gì về chúng tôi?'), $testimonials), 'en' => $withItems($heading('What clients say'), $testimonials)]],
            ['block_type' => 'ec915_latest_posts', 'label' => 'Tin tức nội thất', 'description' => 'Ba bài viết và xu hướng mới.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'search' => ''], 'settings_schema' => $postSchema, 'data' => ['vi' => $heading('Tin tức và xu hướng nội thất!'), 'en' => $heading('Interior news and trends')]],
            ['block_type' => 'ec915_footer', 'label' => 'Chân trang', 'description' => 'Thông tin liên hệ, dịch vụ và Instagram.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin ND Interior'), 'en' => $heading('ND Interior information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec914DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC914/cover-ec914.webp';
        $heading = static fn (?string $title = null, ?string $summary = null): array => ['title' => $title, 'summary' => $summary];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $categorySchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 8],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 3],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $collections = [
            ['title' => 'BST Hơi Thở Mộc', 'summary' => 'Giá chỉ từ 499k', 'image' => '/theme-demo/ec914/collection-wide.webp', 'url' => '#noi-bat'],
            ['title' => 'BST Nét Đan', 'summary' => 'Giá chỉ từ 299k', 'image' => '/theme-demo/ec914/product-basket-picnic.webp', 'url' => '#noi-bat'],
            ['title' => 'BST Ánh Mây', 'summary' => 'Giá chỉ từ 399k', 'image' => '/theme-demo/ec914/collection-lamps.webp', 'url' => '#noi-bat'],
            ['title' => 'BST Mộc Nhiên Quà Tặng', 'summary' => 'Giá chỉ từ 199k', 'image' => '/theme-demo/ec914/artisan-detail.webp', 'url' => '#noi-bat'],
        ];
        $testimonials = [
            ['title' => 'Ngọc Vy', 'role' => 'Nhà thiết kế nội thất', 'summary' => 'Tôi ấn tượng với từng đường đan tỉ mỉ, chắc tay và mùi thơm tự nhiên của mây tre. Mỗi món đồ khiến căn nhà có thêm sự ấm áp và một câu chuyện riêng.'],
        ];
        $partners = [
            ['title' => 'The Green House'], ['title' => 'An Nhiên Living'], ['title' => 'Làng Mộc'], ['title' => 'Maison Nature'], ['title' => 'Bamboo Home'], ['title' => 'Mây Studio'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero câu chuyện mộc', 'description' => 'Banner toàn chiều rộng cho sản phẩm mây tre thủ công.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec914-hero-slider', 'limit' => 2, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Mỗi món đồ là một câu chuyện mộc mạc', 'Thủ công truyền thống gặp thiết kế hiện đại.'), ['content' => ['slides' => [['title' => 'Mỗi món đồ là một câu chuyện mộc mạc', 'summary' => 'Từng sợi mây đan lưu giữ hơi thở thiên nhiên và bàn tay người thợ Việt.', 'badge' => 'CHẠM VÀO VẺ ĐẸP MỘC', 'image' => '/theme-demo/ec914/hero-craft.webp', 'link_url' => '#noi-bat']]]]), 'en' => $heading('Every craft carries a story')]],
            ['block_type' => 'ec914_category_rail', 'label' => 'Danh mục thủ công', 'description' => 'Tám danh mục hình tròn.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 8, 'search' => 'ec914-'], 'settings_schema' => $categorySchema, 'data' => ['vi' => $heading('Danh mục thủ công'), 'en' => $heading('Craft categories')]],
            ['block_type' => 'ec914_craft_sale', 'label' => 'Craft Sale', 'description' => 'Bốn sản phẩm ưu đãi kèm đếm ngược.', 'preview_image' => $preview, 'anchor_id' => 'sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC914-SALE', 'featured_only' => false, 'end_at' => '2027-12-31T23:59:59+07:00'], 'settings_schema' => array_merge($productSchema(4), ['end_at' => ['type' => 'datetime-local', 'label' => 'Thời gian kết thúc']]), 'data' => ['vi' => $heading('Year End Craft Sale – Xả kho cuối mùa', 'Ưu đãi cho những món đồ thủ công được yêu thích nhất.'), 'en' => $heading('Year End Craft Sale')]],
            ['block_type' => 'ec914_featured_products', 'label' => 'Sản phẩm nổi bật', 'description' => 'Tám sản phẩm thủ công nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'noi-bat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC914-', 'featured_only' => true], 'settings_schema' => $productSchema(8), 'data' => ['vi' => $heading('Các sản phẩm nổi bật', 'Sự kết hợp hài hòa giữa thủ công truyền thống và thiết kế hiện đại.'), 'en' => $heading('Featured crafts')]],
            ['block_type' => 'ec914_collection_gallery', 'label' => 'Bộ sưu tập', 'description' => 'Gallery ảnh phong cách editorial.', 'preview_image' => $preview, 'anchor_id' => 'bo-suu-tap', 'data' => ['vi' => $withItems($heading('Bộ sưu tập mới nhất', 'Mộc mạc nhưng tinh tế, lưu giữ hơi thở thiên nhiên.'), $collections), 'en' => $withItems($heading('Latest collections'), $collections)]],
            ['block_type' => 'ec914_basket_showcase', 'label' => 'Giỏ & khay đan tay', 'description' => 'Ảnh lifestyle và ba sản phẩm giỏ khay.', 'preview_image' => $preview, 'anchor_id' => 'gio-khay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'search' => 'EC914-BASKET'], 'settings_schema' => $productSchema(3), 'data' => ['vi' => $heading('Giỏ & Khay Đan Tay', 'Từng sợi mây đan thủ công, lưu giữ vẻ tự nhiên cho không gian sống.'), 'en' => $heading('Handwoven baskets & trays')]],
            ['block_type' => 'ec914_lamp_showcase', 'label' => 'Đèn mây tre', 'description' => 'Ảnh lifestyle và ba sản phẩm đèn.', 'preview_image' => $preview, 'anchor_id' => 'den-may-tre', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'search' => 'EC914-LAMP'], 'settings_schema' => $productSchema(3), 'data' => ['vi' => $heading('Đèn Mây Tre Trang Trí', 'Ánh sáng len qua sợi mây, mang hơi thở thiên nhiên và sự ấm áp.'), 'en' => $heading('Rattan pendant lights')]],
            ['block_type' => 'ec914_artisan_story', 'label' => 'Câu chuyện nghệ nhân', 'description' => 'Giới thiệu nghề đan và chất liệu tự nhiên.', 'preview_image' => $preview, 'anchor_id' => 'cau-chuyen', 'media' => ['image' => '/theme-demo/ec914/artisan-story.webp'], 'data' => ['vi' => $heading('Câu chuyện từ những đôi tay', 'Chúng tôi tôn vinh chất liệu tự nhiên và kỹ nghệ của người thợ Việt. Mỗi thiết kế là sự gặp gỡ giữa ký ức truyền thống và thẩm mỹ đương đại.'), 'en' => $heading('Stories shaped by hand')]],
            ['block_type' => 'ec914_testimonials', 'label' => 'Cảm nhận khách hàng', 'description' => 'Một câu chuyện khách hàng nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'cam-nhan', 'data' => ['vi' => $withItems($heading('Mang một góc làng nghề về nhà'), $testimonials), 'en' => $withItems($heading('Bring the craft village home'), $testimonials)]],
            ['block_type' => 'ec914_partners', 'label' => 'Đối tác', 'description' => 'Dải tên đối tác và không gian nội thất.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'data' => ['vi' => $withItems($heading('Đối tác của Mộc Nhiên'), $partners), 'en' => $withItems($heading('Our partners'), $partners)]],
            ['block_type' => 'ec914_latest_posts', 'label' => 'Tin tức & cảm hứng', 'description' => 'Ba bài viết mới nhất.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'search' => ''], 'settings_schema' => $postSchema, 'data' => ['vi' => $heading('Tin tức & cảm hứng mới nhất'), 'en' => $heading('Latest stories & inspiration')]],
            ['block_type' => 'ec914_footer', 'label' => 'Chân trang', 'description' => 'Liên hệ, chính sách và newsletter.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin Mộc Nhiên Craft'), 'en' => $heading('Moc Nhien Craft information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec913DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC913/cover-ec913.webp';
        $heading = static fn (?string $title = null, ?string $summary = null): array => [
            'title' => $title,
            'summary' => $summary,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, [
            'content' => ['items' => $items],
        ]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $categorySchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 10],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 3],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $benefits = [
            ['title' => 'Miễn phí giao hàng', 'summary' => 'Trong bán kính 20km', 'icon' => 'fa-truck-fast'],
            ['title' => 'Thanh toán an toàn', 'summary' => 'Bảo mật và minh bạch', 'icon' => 'fa-credit-card'],
            ['title' => 'Ưu đãi thành viên', 'summary' => 'Tích điểm đổi quà', 'icon' => 'fa-gift'],
            ['title' => 'Hỗ trợ 24/7', 'summary' => 'Nhân viên tận tình', 'icon' => 'fa-headset'],
        ];
        $categories = [
            ['title' => 'Điện thoại & Tablet', 'image' => '/theme-demo/ec913/phone-blue.webp', 'url' => '#ban-chay'],
            ['title' => 'Laptop & Máy tính', 'image' => '/theme-demo/ec913/laptop-silver.webp', 'url' => '#laptop'],
            ['title' => 'TV & Màn hình', 'image' => '/theme-demo/ec913/tv-lifestyle.webp', 'url' => '#ban-chay'],
            ['title' => 'Điện lạnh', 'image' => '/theme-demo/ec913/refrigerator.webp', 'url' => '#ban-chay'],
            ['title' => 'Gia dụng thông minh', 'image' => '/theme-demo/ec913/air-fryer.webp', 'url' => '#ban-chay'],
            ['title' => 'Âm thanh', 'image' => '/theme-demo/ec913/earbuds-white.webp', 'url' => '#ban-chay'],
            ['title' => 'Gaming & Console', 'image' => '/theme-demo/ec913/promo-gaming.webp', 'url' => '#ban-chay'],
            ['title' => 'Camera & Kỹ thuật số', 'image' => '/theme-demo/ec913/phone-graphite.webp', 'url' => '#ban-chay'],
            ['title' => 'Phụ kiện số', 'image' => '/theme-demo/ec913/charger-wireless.webp', 'url' => '#ban-chay'],
            ['title' => 'Thiết bị nhà bếp', 'image' => '/theme-demo/ec913/washing-machine.webp', 'url' => '#ban-chay'],
        ];
        $promotions = [
            ['title' => 'Điện máy cho tổ ấm', 'summary' => 'Giảm đến 35%', 'badge' => 'TUẦN LỄ GIA DỤNG', 'image' => '/theme-demo/ec913/promo-appliances.webp', 'url' => '#ban-chay'],
            ['title' => 'Gaming bứt phá giới hạn', 'summary' => 'Quà tặng đến 2 triệu', 'badge' => 'BATTLE READY', 'image' => '/theme-demo/ec913/promo-gaming.webp', 'url' => '#ban-chay'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero công nghệ',
                'description' => 'Banner chính cho chiến dịch điện tử và điện máy.',
                'preview_image' => $preview,
                'anchor_id' => 'hero',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'ec913-hero-slider', 'limit' => 2, 'autoplay_ms' => 5200],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Placement banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                    'autoplay_ms' => ['type' => 'number', 'label' => 'Thời gian tự chuyển'],
                ],
                'data' => [
                    'vi' => array_merge($heading('Sắm công nghệ, sống tiện nghi', 'Ưu đãi đến 35% cho hàng ngàn sản phẩm chính hãng.'), ['content' => ['slides' => [[
                        'title' => 'Sắm công nghệ, sống tiện nghi',
                        'summary' => 'Ưu đãi đến 35% cho điện tử, điện máy và phụ kiện chính hãng.',
                        'badge' => 'ĐẠI TIỆC CÔNG NGHỆ',
                        'image' => '/theme-demo/ec913/hero-digital-mall.webp',
                        'link_url' => '#ban-chay',
                    ]]]]),
                    'en' => $heading('Smarter tech, easier living', 'Save up to 35% on genuine technology products.'),
                ],
            ],
            [
                'block_type' => 'ec913_benefits',
                'label' => 'Cam kết dịch vụ',
                'description' => 'Bốn lợi ích mua sắm nổi bật.',
                'preview_image' => $preview,
                'anchor_id' => 'loi-ich',
                'data' => ['vi' => $withItems($heading('Quyền lợi khách hàng'), $benefits), 'en' => $withItems($heading('Customer benefits'), $benefits)],
            ],
            [
                'block_type' => 'ec913_category_grid',
                'label' => 'Danh mục nổi bật',
                'description' => 'Mười nhóm sản phẩm điện tử và điện máy.',
                'preview_image' => $preview,
                'anchor_id' => 'danh-muc',
                'dynamic' => true,
                'settings' => ['source' => 'catalog_categories', 'limit' => 10, 'search' => 'ec913-', 'featured_only' => false],
                'settings_schema' => $categorySchema,
                'data' => ['vi' => $withItems($heading('Danh mục nổi bật'), $categories), 'en' => $withItems($heading('Featured categories'), $categories)],
            ],
            [
                'block_type' => 'ec913_promotion_banners',
                'label' => 'Banner khuyến mãi',
                'description' => 'Hai chiến dịch gia dụng và gaming.',
                'preview_image' => $preview,
                'anchor_id' => 'khuyen-mai',
                'data' => ['vi' => $withItems($heading('Khuyến mãi nổi bật'), $promotions), 'en' => $withItems($heading('Featured promotions'), $promotions)],
            ],
            [
                'block_type' => 'ec913_best_sellers',
                'label' => 'Sản phẩm bán chạy',
                'description' => 'Năm sản phẩm công nghệ được chọn mua nhiều.',
                'preview_image' => $preview,
                'anchor_id' => 'ban-chay',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC913-BEST', 'featured_only' => true],
                'settings_schema' => $productSchema(5),
                'data' => ['vi' => $heading('Sản phẩm bán chạy'), 'en' => $heading('Best sellers')],
            ],
            [
                'block_type' => 'ec913_laptop_showcase',
                'label' => 'Laptop & thiết bị tin học',
                'description' => 'Một sản phẩm chủ đạo và bốn laptop bổ trợ.',
                'preview_image' => $preview,
                'anchor_id' => 'laptop',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC913-LAPTOP', 'featured_only' => false],
                'settings_schema' => $productSchema(5),
                'data' => ['vi' => $heading('Laptop & Thiết bị tin học'), 'en' => $heading('Laptops & computing')],
            ],
            [
                'block_type' => 'ec913_technology_news',
                'label' => 'Tin mới & tư vấn',
                'description' => 'Ba bài viết công nghệ mới nhất.',
                'preview_image' => $preview,
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'search' => '', 'featured_only' => false],
                'settings_schema' => $postSchema,
                'data' => ['vi' => $heading('Tin mới & tư vấn'), 'en' => $heading('News & advice')],
            ],
            [
                'block_type' => 'ec913_footer',
                'label' => 'Chân trang',
                'description' => 'Thông tin doanh nghiệp, đăng ký tin và liên kết hỗ trợ.',
                'preview_image' => $preview,
                'anchor_id' => 'footer',
                'data' => ['vi' => $heading('Thông tin NovaTech Mall'), 'en' => $heading('NovaTech Mall information')],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec912DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC912/cover-ec912.png';
        $heading = static fn (?string $title = null, ?string $summary = null): array => [
            'title' => $title,
            'summary' => $summary,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, [
            'content' => ['items' => $items],
        ]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $benefits = [
            ['title' => 'Vận chuyển miễn phí', 'summary' => 'Hóa đơn trên 5 triệu', 'icon' => 'fa-truck-fast'],
            ['title' => 'Quà tặng hấp dẫn', 'summary' => 'Hóa đơn trên 10 triệu', 'icon' => 'fa-bag-shopping'],
            ['title' => 'Chứng nhận chất lượng', 'summary' => 'Sản phẩm chính hãng', 'icon' => 'fa-award'],
            ['title' => 'Hotline: 0399162342', 'summary' => 'Hỗ trợ 24/7', 'icon' => 'fa-headset'],
        ];
        $categories = [
            ['title' => 'iPhone', 'image' => '/theme-demo/ec912/phone-graphite.webp', 'url' => '#iphone'],
            ['title' => 'Mac', 'image' => '/theme-demo/ec912/laptop-silver.webp', 'url' => '#iphone'],
            ['title' => 'iPad', 'image' => '/theme-demo/ec912/tablet-blue.webp', 'url' => '#iphone'],
            ['title' => 'Watch', 'image' => '/theme-demo/ec912/watch-white.webp', 'url' => '#iphone'],
            ['title' => 'Âm thanh', 'image' => '/theme-demo/ec912/earbuds-white.webp', 'url' => '#iphone'],
            ['title' => 'Phụ kiện', 'image' => '/theme-demo/ec912/charger-wireless.webp', 'url' => '#iphone'],
        ];
        $promotions = [
            ['title' => 'Apple Watch Series', 'summary' => 'Đặt hàng ngay', 'image' => '/theme-demo/ec912/promo-accessories.webp', 'url' => '#iphone'],
            ['title' => 'AirPods Pro', 'summary' => 'Âm thanh sống động', 'image' => '/theme-demo/ec912/promo-computing.webp', 'url' => '#iphone'],
            ['title' => 'iPhone chính hãng', 'summary' => 'Đặt gạch ngay', 'image' => '/theme-demo/ec912/promo-phone.webp', 'url' => '#iphone'],
        ];
        $gallery = [
            ['title' => 'Khách hàng Sudes Phone', 'image' => '/theme-demo/ec912/story-review.webp'],
            ['title' => 'Tư vấn tận tâm', 'image' => '/theme-demo/ec912/story-phone.webp'],
            ['title' => 'Sản phẩm chính hãng', 'image' => '/theme-demo/ec912/story-tablet.webp'],
            ['title' => 'Trải nghiệm tại cửa hàng', 'image' => '/theme-demo/ec912/story-charging.webp'],
            ['title' => 'Đồng hành cùng khách hàng', 'image' => '/theme-demo/ec912/hero-tech.webp'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Hero iPhone',
                'description' => 'Banner chính giới thiệu sản phẩm và ưu đãi iPhone.',
                'preview_image' => $preview,
                'anchor_id' => 'hero',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'ec912-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Placement banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                    'autoplay_ms' => ['type' => 'number', 'label' => 'Thời gian tự chuyển'],
                ],
                'data' => [
                    'vi' => array_merge($heading('iPhone 14 Pro Max', 'Giá cực sốc'), ['content' => ['slides' => [[
                        'title' => 'GIÁ CỰC SỐC',
                        'summary' => 'Giá chỉ từ 28.190.000đ',
                        'badge' => 'iPhone 14 Pro Max',
                        'image' => '/theme-demo/ec912/hero-tech.webp',
                        'link_url' => '#hot-sale',
                    ]]]]),
                    'en' => $heading('iPhone 14 Pro Max', 'Amazing price'),
                ],
            ],
            [
                'block_type' => 'ec912_benefits',
                'label' => 'Cam kết dịch vụ',
                'description' => 'Bốn quyền lợi mua sắm với màu nền pastel.',
                'preview_image' => $preview,
                'anchor_id' => 'loi-ich',
                'data' => [
                    'vi' => $withItems($heading('Quyền lợi khách hàng'), $benefits),
                    'en' => $withItems($heading('Customer benefits'), $benefits),
                ],
            ],
            [
                'block_type' => 'ec912_hot_sale',
                'label' => 'Hot sale cuối tuần',
                'description' => 'Sản phẩm giảm giá, đồng hồ đếm ngược và tiến độ đã bán.',
                'preview_image' => $preview,
                'anchor_id' => 'hot-sale',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC912-IPHONE', 'featured_only' => true, 'end_at' => '2027-01-01T00:00:00+07:00'],
                'settings_schema' => array_merge($productSchema(4), [
                    'end_at' => ['type' => 'datetime-local', 'label' => 'Thời gian kết thúc'],
                ]),
                'data' => ['vi' => $heading('HOT SALE CUỐI TUẦN 🔥'), 'en' => $heading('WEEKEND HOT SALE 🔥')],
            ],
            [
                'block_type' => 'ec912_featured_categories',
                'label' => 'Danh mục nổi bật',
                'description' => 'Sáu danh mục thiết bị Apple nổi bật.',
                'preview_image' => $preview,
                'anchor_id' => 'danh-muc',
                'dynamic' => true,
                'settings' => ['source' => 'catalog_categories', 'limit' => 6, 'search' => 'ec912-', 'featured_only' => false],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]],
                    'limit' => ['type' => 'number', 'label' => 'Số danh mục', 'default' => 6],
                    'search' => ['type' => 'text', 'label' => 'Từ khóa'],
                ],
                'data' => [
                    'vi' => $withItems($heading('DANH MỤC NỔI BẬT'), $categories),
                    'en' => $withItems($heading('FEATURED CATEGORIES'), $categories),
                ],
            ],
            [
                'block_type' => 'ec912_promotion_banners',
                'label' => 'Banner khuyến mãi',
                'description' => 'Ba banner Watch, AirPods và iPhone.',
                'preview_image' => $preview,
                'anchor_id' => 'khuyen-mai',
                'data' => [
                    'vi' => $withItems($heading('Khuyến mãi nổi bật'), $promotions),
                    'en' => $withItems($heading('Featured promotions'), $promotions),
                ],
            ],
            [
                'block_type' => 'ec912_iphone_products',
                'label' => 'Sản phẩm iPhone',
                'description' => 'Lưới tám sản phẩm iPhone chính hãng.',
                'preview_image' => $preview,
                'anchor_id' => 'iphone',
                'dynamic' => true,
                'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'EC912-IPHONE', 'featured_only' => false],
                'settings_schema' => $productSchema(8),
                'data' => ['vi' => $heading('IPHONE'), 'en' => $heading('IPHONE')],
            ],
            [
                'block_type' => 'ec912_technology_news',
                'label' => 'Tin tức công nghệ',
                'description' => 'Bốn bài viết công nghệ mới nhất.',
                'preview_image' => $preview,
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => '', 'featured_only' => false],
                'settings_schema' => $postSchema,
                'data' => ['vi' => $heading('TIN TỨC'), 'en' => $heading('TECH NEWS')],
            ],
            [
                'block_type' => 'ec912_customer_gallery',
                'label' => 'Khách hàng Sudes',
                'description' => 'Bộ ảnh khách hàng và trải nghiệm tại cửa hàng.',
                'preview_image' => $preview,
                'anchor_id' => 'khach-hang',
                'data' => [
                    'vi' => $withItems($heading('KHÁCH HÀNG CỦA'), $gallery),
                    'en' => $withItems($heading('OUR CUSTOMERS'), $gallery),
                ],
            ],
            [
                'block_type' => 'ec912_footer',
                'label' => 'Chân trang',
                'description' => 'Thông tin website, chính sách, hướng dẫn và thanh toán.',
                'preview_image' => $preview,
                'anchor_id' => 'footer',
                'data' => ['vi' => $heading('Thông tin Sudes Phone'), 'en' => $heading('Sudes Phone information')],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec911DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC911/preview-ec911.png';
        $heading = static fn (?string $title = null, ?string $summary = null): array => ['title' => $title, 'summary' => $summary];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => 4],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
        ];
        $benefits = [
            ['title' => 'ĐỔI TRẢ DỄ DÀNG', 'summary' => 'Đổi trả trong 30 ngày đầu tiên cho tất cả sản phẩm', 'icon' => 'fa-rotate'],
            ['title' => 'GIAO HÀNG TOÀN QUỐC', 'summary' => 'Miễn phí giao hàng với đơn từ 5.000.000₫', 'icon' => 'fa-truck-fast'],
            ['title' => 'QUÀ TẶNG HẤP DẪN', 'summary' => 'Chương trình khuyến mãi lớn hàng tháng', 'icon' => 'fa-gift'],
            ['title' => 'HỖ TRỢ ONLINE 24/7', 'summary' => 'Luôn hỗ trợ khách hàng mọi ngày trong tuần', 'icon' => 'fa-headset'],
        ];
        $categories = [
            ['title' => 'Máy ảnh', 'summary' => '12 sản phẩm', 'image' => '/theme-demo/ec911/camera-pro.png', 'url' => '#may-anh'],
            ['title' => 'Ống kính', 'summary' => '8 sản phẩm', 'image' => '/theme-demo/ec911/lens-tele.png', 'url' => '#flash-sale'],
            ['title' => 'Máy quay phim', 'summary' => '6 sản phẩm', 'image' => '/theme-demo/ec911/camera-pro.png', 'url' => '#may-anh'],
            ['title' => 'Camera hành động', 'summary' => '6 sản phẩm', 'image' => '/theme-demo/ec911/action-camera.webp', 'url' => '#may-anh'],
            ['title' => 'Phụ kiện', 'summary' => '10 sản phẩm', 'image' => '/theme-demo/ec911/lens-tele.png', 'url' => '#may-anh'],
        ];
        $brands = collect(['Creator Pro', 'Digi Vision', 'Focus Lab'])->map(fn (string $title, int $index): array => [
            'title' => $title,
            'image' => $index === 1 ? '/theme-demo/ec911/campaign-cameras.png' : ($index === 2 ? '/theme-demo/ec911/hero-vlog.png' : '/theme-demo/ec911/camera-pro.png'),
            'url' => '#may-anh',
        ])->all();

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero máy quay Vlog', 'description' => 'Banner chính và hai banner thiết bị nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec911-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide']], 'data' => ['vi' => array_merge($heading('Máy quay Vlog Creator X1', 'Khơi nguồn sáng tạo'), ['content' => ['slides' => [['title' => 'DIGITECH CREATOR X1', 'summary' => 'Ghi trọn mọi khoảnh khắc', 'image' => '/theme-demo/ec911/hero-vlog.png', 'link_url' => '#flash-sale']]]]), 'en' => $heading('Creator camera')]],
            ['block_type' => 'ec911_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn cam kết mua sắm.', 'preview_image' => $preview, 'anchor_id' => 'loi-ich', 'data' => ['vi' => $withItems($heading('Cam kết DIGITECH'), $benefits), 'en' => $withItems($heading('Our benefits'), $benefits)]],
            ['block_type' => 'ec911_category_rail', 'label' => 'Danh mục thiết bị', 'description' => 'Thanh danh mục máy ảnh, ống kính và phụ kiện.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 5, 'search' => 'ec911-', 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục']], 'data' => ['vi' => $withItems($heading('Danh mục'), $categories), 'en' => $withItems($heading('Categories'), $categories)]],
            ['block_type' => 'ec911_flash_sale', 'label' => 'Flash sale', 'description' => 'Năm sản phẩm ống kính giảm giá.', 'preview_image' => $preview, 'anchor_id' => 'flash-sale', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC911-LENS', 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $heading('Flash sale'), 'en' => $heading('Flash sale')]],
            ['block_type' => 'ec911_camera_products', 'label' => 'Sản phẩm máy ảnh', 'description' => 'Năm máy ảnh chính hãng.', 'preview_image' => $preview, 'anchor_id' => 'may-anh', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'search' => 'EC911-CAMERA', 'featured_only' => false], 'settings_schema' => $productSchema(5), 'data' => ['vi' => $heading('MÁY ẢNH'), 'en' => $heading('CAMERAS')]],
            ['block_type' => 'ec911_campaign_banner', 'label' => 'Banner camera thịnh hành', 'description' => 'Banner toàn chiều ngang.', 'preview_image' => $preview, 'anchor_id' => 'camera-thinh-hanh', 'settings' => ['image' => '/theme-demo/ec911/campaign-cameras.png', 'link_url' => '#may-anh'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh banner'], 'link_url' => ['type' => 'text', 'label' => 'Liên kết']], 'data' => ['vi' => $heading('Top camera thịnh hành'), 'en' => $heading('Trending cameras')]],
            ['block_type' => 'ec911_brand_cards', 'label' => 'Thương hiệu máy ảnh', 'description' => 'Ba thẻ thương hiệu hình ảnh.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu nổi bật'), $brands), 'en' => $withItems($heading('Featured brands'), $brands)]],
            ['block_type' => 'ec911_news', 'label' => 'Tin tức nhiếp ảnh', 'description' => 'Bốn bài viết công nghệ hình ảnh.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'search' => '', 'featured_only' => false], 'settings_schema' => $postSchema, 'data' => ['vi' => $heading('TIN TỨC'), 'en' => $heading('NEWS')]],
            ['block_type' => 'ec911_newsletter', 'label' => 'Đăng ký tin khuyến mãi', 'description' => 'Dải đăng ký email nền xanh.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Đăng ký nhận tin'), 'en' => $heading('Newsletter')]],
            ['block_type' => 'ec911_footer', 'label' => 'Chân trang DIGITECH', 'description' => 'Thông tin, chính sách và tư vấn khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin DIGITECH'), 'en' => $heading('DIGITECH information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec909DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC909/preview-ec909.png';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'summary' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
        ];
        $product = static fn (string $title, string $image, int $price, int $original): array => [
            'title' => $title, 'image' => $image, 'price' => $price, 'original_price' => $original, 'url' => '#',
        ];
        $categories = [
            ['title' => 'All Products', 'summary' => 'Khám phá tất cả sản phẩm.', 'image' => '/theme-demo/ec909/headphone-black.png', 'url' => '#'],
            ['title' => 'Headphones', 'summary' => 'Đắm mình trong âm thanh.', 'image' => '/theme-demo/ec909/headphone-burgundy.png', 'url' => '#'],
            ['title' => 'Earphones', 'summary' => 'Thiết kế nhỏ gọn, âm thanh tuyệt vời.', 'image' => '/theme-demo/ec909/earbuds-silver.png', 'url' => '#'],
            ['title' => 'Speakers', 'summary' => 'Âm thanh sống động cho mọi không gian.', 'image' => '/theme-demo/ec909/speaker-oak.png', 'url' => '#'],
        ];
        $headphones = [
            $product('Cloud Dream', '/theme-demo/ec909/headphone-burgundy.png', 26329000, 28329000),
            $product('Nebula Whisper', '/theme-demo/ec909/headphone-beige.png', 9724000, 10724000),
            $product('Oasis Flow', '/theme-demo/ec909/headphone-black.png', 7906000, 9906000),
        ];
        $earphones = [
            $product('Trio Athletica', '/theme-demo/ec909/earbuds-silver.png', 5270000, 6750000),
            $product('Stealth Precision', '/theme-demo/ec909/earbuds-silver.png', 6590000, 7990000),
            $product('Aqua Sprint', '/theme-demo/ec909/earbuds-silver.png', 9200000, 11300000),
        ];
        $recommendations = [
            $product('Air Beats Black', '/theme-demo/ec909/headphone-black.png', 13151000, 14151000),
            $headphones[0],
            $product('Cover for Echo Sphere', '/theme-demo/ec909/speaker-oak.png', 2690000, 3490000),
            $product('Echo Elegance', '/theme-demo/ec909/speaker-oak.png', 88000000, 99490000),
        ];
        $brands = collect(['Bowers & Wilkins', 'B&O', 'urbanista', 'Apple', 'logitech', 'Master & Dynamic', 'BOSE'])
            ->map(fn (string $title): array => ['title' => $title, 'url' => '#'])
            ->all();
        $posts = [
            ['title' => 'JBL Tune 520BT và Tune 720BT khác biệt thực sự nằm ở đâu?', 'summary' => 'So sánh chi tiết trước khi xuống tiền.', 'date' => '25/07/2026', 'image' => '/theme-demo/ec909/news-audio.png', 'url' => '#'],
            ['title' => 'Wave Beam hay Wave Buds? So sánh chi tiết trước khi xuống tiền', 'summary' => 'Những khác biệt đáng chú ý giữa hai dòng tai nghe true wireless.', 'date' => '25/07/2026', 'image' => '/theme-demo/ec909/earbud-feature.png', 'url' => '#'],
            ['title' => 'Top loa bluetooth karaoke bass mạnh, quẩy cực sung', 'summary' => 'Gợi ý thiết bị cho tiệc tại nhà và những chuyến đi cuối tuần.', 'date' => '25/07/2026', 'image' => '/theme-demo/ec909/stereo-feature.png', 'url' => '#'],
        ];
        $benefits = [
            ['title' => 'Giao hàng nhanh chóng', 'summary' => 'Xử lý đơn nhanh, giao hàng toàn quốc.', 'icon' => 'fa-truck-fast'],
            ['title' => 'Đổi trả dễ dàng', 'summary' => 'Hỗ trợ đổi trả linh hoạt nếu sản phẩm lỗi.', 'icon' => 'fa-headphones-simple'],
            ['title' => 'Bảo hành uy tín', 'summary' => 'Cam kết bảo hành chính hãng, hỗ trợ tận tâm.', 'icon' => 'fa-award'],
            ['title' => 'Thanh toán tiện lợi', 'summary' => 'Đa dạng phương thức, an toàn và nhanh chóng.', 'icon' => 'fa-cube'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero âm thanh điện ảnh', 'description' => 'Banner dưới nước toàn chiều ngang với nội dung và nút điều hướng.', 'preview_image' => $preview, 'anchor_id' => 'hero', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec909-hero-slider', 'limit' => 2, 'autoplay_ms' => 6500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Âm thanh stereo sống động – Kết nối đôi, trải nghiệm gấp bội', null, null, 'Xem ngay'), ['content' => ['slides' => [['title' => 'Âm thanh stereo sống động – Kết nối đôi, trải nghiệm gấp bội', 'button_label' => 'Xem ngay', 'image' => '/theme-demo/ec909/hero-underwater.png', 'link_url' => '#goi-y']]]]), 'en' => $heading('Immersive stereo sound', null, null, 'Discover')]],
            ['block_type' => 'ec909_about', 'label' => 'Giới thiệu Euro Sound', 'description' => 'Khối editorial ảnh studio và nội dung thương hiệu.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => ['image' => '/theme-demo/ec909/about-studio.png', 'secondary_image' => '/theme-demo/ec909/headphone-black.png'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh chính'], 'secondary_image' => ['type' => 'text', 'label' => 'Ảnh phụ']], 'data' => ['vi' => array_merge($heading('Euro Sound - Âm Thanh Chuẩn, Công Nghệ Chất', null, 'Euro Sound là cửa hàng chuyên cung cấp các thiết bị âm thanh chính hãng như loa, tai nghe có dây, tai nghe không dây và headphone chất lượng cao.'), ['description' => 'Với tiêu chí đặt trải nghiệm người dùng lên hàng đầu, Euro Sound luôn chú trọng vào chất lượng sản phẩm, giá thành hợp lý và dịch vụ tận tâm.']), 'en' => $heading('Euro Sound - Precision sound, premium technology')]],
            ['block_type' => 'ec909_category_cards', 'label' => 'Danh mục âm thanh', 'description' => 'Bốn thẻ danh mục sản phẩm cỡ lớn.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 4, 'search' => 'ec909-', 'featured_only' => false, 'order' => 'sort_order'], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục'], 'search' => ['type' => 'text', 'label' => 'Từ khóa']], 'data' => ['vi' => $withItems($heading('Danh mục sản phẩm'), $categories), 'en' => $withItems($heading('Product categories'), $categories)]],
            ['block_type' => 'ec909_headphone_showcase', 'label' => 'Bộ sưu tập Headphones', 'description' => 'Nền lifestyle và ba sản phẩm headphones.', 'preview_image' => $preview, 'anchor_id' => 'headphones-showcase', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'search' => 'EC909-HEADPHONE', 'featured_only' => false, 'background_image' => '/theme-demo/ec909/headphone-beige.png'], 'settings_schema' => array_merge($productSchema(3), ['background_image' => ['type' => 'text', 'label' => 'Ảnh nền']]), 'data' => ['vi' => $withItems($heading('Âm Thanh Định Hình Phong Cách', null, 'Khám phá bộ sưu tập headphones mới với thiết kế tinh giản, chất âm sâu và chi tiết.'), $headphones), 'en' => $heading('Sound that defines your style')]],
            ['block_type' => 'ec909_headphone_products', 'label' => 'Sản phẩm Headphones', 'description' => 'Banner dọc và ba thẻ sản phẩm.', 'preview_image' => $preview, 'anchor_id' => 'headphones', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'search' => 'EC909-HEADPHONE', 'featured_only' => false, 'promo_image' => '/theme-demo/ec909/about-studio.png', 'promo_url' => '#'], 'settings_schema' => array_merge($productSchema(3), ['promo_image' => ['type' => 'text', 'label' => 'Ảnh banner'], 'promo_url' => ['type' => 'text', 'label' => 'Liên kết banner']]), 'data' => ['vi' => $withItems($heading('Headphones'), $headphones), 'en' => $heading('Headphones')]],
            ['block_type' => 'ec909_microphone_feature', 'label' => 'Tính năng microphone', 'description' => 'Khối chia đôi nội dung và ảnh cận cảnh tai nghe.', 'preview_image' => $preview, 'anchor_id' => 'microphone', 'settings' => ['image' => '/theme-demo/ec909/earbud-feature.png'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh tính năng']], 'data' => ['vi' => $heading('Micro rõ nét – Thu âm chuẩn xác', null, 'Thiết bị tích hợp hệ thống microphone tối ưu giúp giọng nói luôn nổi bật và rõ ràng trong mọi cuộc gọi.'), 'en' => $heading('Clear microphone, precise voice pickup')]],
            ['block_type' => 'ec909_earphone_products', 'label' => 'Sản phẩm Earphones', 'description' => 'Ba thẻ earphones và banner dọc.', 'preview_image' => $preview, 'anchor_id' => 'earphones', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 3, 'search' => 'EC909-EARPHONE', 'featured_only' => false, 'promo_image' => '/theme-demo/ec909/earbuds-silver.png', 'promo_url' => '#'], 'settings_schema' => array_merge($productSchema(3), ['promo_image' => ['type' => 'text', 'label' => 'Ảnh banner'], 'promo_url' => ['type' => 'text', 'label' => 'Liên kết banner']]), 'data' => ['vi' => $withItems($heading('Earphones'), $earphones), 'en' => $heading('Earphones')]],
            ['block_type' => 'ec909_stereo_feature', 'label' => 'Tính năng stereo', 'description' => 'Khối chia đôi ảnh loa và nội dung kết nối đôi.', 'preview_image' => $preview, 'anchor_id' => 'stereo', 'settings' => ['image' => '/theme-demo/ec909/stereo-feature.png'], 'settings_schema' => ['image' => ['type' => 'text', 'label' => 'Ảnh tính năng']], 'data' => ['vi' => $heading('Âm thanh stereo sống động - Kết nối đôi, trải nghiệm gấp bội', null, 'Nhân đôi trải nghiệm âm thanh ấn tượng bằng cách kết nối hai loa cùng lúc. Ghép nối nhanh chóng chỉ trong vài giây.'), 'en' => $heading('Immersive stereo sound')]],
            ['block_type' => 'ec909_recommendations', 'label' => 'Gợi ý sản phẩm', 'description' => 'Tabs và bốn sản phẩm gợi ý.', 'preview_image' => $preview, 'anchor_id' => 'goi-y', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC909-RECOMMEND', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Gợi ý sản phẩm cho bạn', null, 'Đây là những sản phẩm chúng tôi tổng hợp lại cho bạn tham khảo'), $recommendations), 'en' => $heading('Recommended for you')]],
            ['block_type' => 'ec909_brand_strip', 'label' => 'Thương hiệu nổi bật', 'description' => 'Dải tên thương hiệu âm thanh cao cấp.', 'preview_image' => $preview, 'anchor_id' => 'thuong-hieu', 'data' => ['vi' => $withItems($heading('Thương hiệu nổi bật', null, 'Chúng tôi cung cấp sản phẩm từ các thương hiệu uy tín'), $brands), 'en' => $withItems($heading('Featured brands'), $brands)]],
            ['block_type' => 'ec909_latest_posts', 'label' => 'Tin tức mới nhất', 'description' => 'Một bài nổi bật và hai bài viết liên quan.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'search' => '', 'featured_only' => false], 'settings_schema' => $postSchema(3), 'data' => ['vi' => $withItems($heading('Tin tức mới nhất', null, 'Thông tin thực tế, dễ hiểu giúp bạn đưa ra quyết định đầu tư hiệu quả'), $posts), 'en' => $heading('Latest news')]],
            ['block_type' => 'ec909_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn lợi ích giao hàng, đổi trả, bảo hành và thanh toán.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Service benefits'), $benefits)]],
            ['block_type' => 'ec909_footer', 'label' => 'Chân trang Euro Sound', 'description' => 'Thông tin, chính sách, hỗ trợ và danh mục.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Thông tin Euro Sound'), 'en' => $heading('Euro Sound information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function ec905DefaultBlocks(): array
    {
        $preview = '/theme-previews/EC905/preview-ec905.webp';
        $heading = static fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = static fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $productSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]],
            'limit' => ['type' => 'number', 'label' => 'Số sản phẩm', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa / SKU'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục sản phẩm'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật'],
        ];
        $postSchema = static fn (int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Tin tức CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số bài viết', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục tin'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật'],
        ];
        $product = static fn (string $title, string $image, int $price, int $original): array => [
            'title' => $title, 'image' => $image, 'price' => $price, 'original_price' => $original, 'url' => '#',
        ];
        $paint = [
            $product('Sơn nội thất mịn HomeCare 18L', '/theme-demo/ec905/product-01.webp', 2090000, 2390000),
            $product('Sơn ngoại thất WeatherShield 18L', '/theme-demo/ec905/product-02.webp', 2680000, 2980000),
            $product('Sơn chống thấm AquaGuard 15L', '/theme-demo/ec905/product-03.webp', 2450000, 2750000),
            $product('Sơn bóng cao cấp SatinPro 5L', '/theme-demo/ec905/product-04.webp', 1800000, 2100000),
        ];
        $tiles = collect([
            ['Gạch bê tông sáng Urban 60×60', 260000, 350000],
            ['Gạch vân gỗ Nordic Oak 15×90', 597000, 690000],
            ['Gạch travertine Sandstone 60×120', 475000, 530000],
            ['Gạch onyx ngọc trai Ivory 80×80', 710000, 720000],
            ['Gạch đá graphite Slate 60×60', 288000, 330000],
            ['Gạch marble Frost Grey 60×120', 520000, 590000],
            ['Gạch vân gỗ Walnut 20×120', 610000, 690000],
            ['Gạch limestone Cream 60×60', 390000, 450000],
            ['Gạch slate Cool Grey 30×60', 330000, 380000],
            ['Gạch marble Pearl 80×80', 680000, 720000],
        ])->map(fn (array $item, int $index): array => $product($item[0], '/theme-demo/ec905/tile-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.webp', $item[1], $item[2]))->all();
        $projects = [
            ['title' => 'Dự án phòng tắm căn hộ Riverside', 'summary' => 'Không gian sáng, gọn với thiết bị vệ sinh đồng bộ.', 'image' => '/theme-demo/ec905/project-01.webp', 'url' => '#'],
            ['title' => 'Dự án phòng tắm boutique tại Tây Hồ', 'summary' => 'Gạch xanh trầm kết hợp ánh sáng ấm.', 'image' => '/theme-demo/ec905/project-02.webp', 'url' => '#'],
            ['title' => 'Dự án lắp đặt thiết bị vệ sinh trọn gói', 'summary' => 'Thi công đúng kỹ thuật, bàn giao sạch sẽ.', 'image' => '/theme-demo/ec905/project-03.webp', 'url' => '#'],
            ['title' => 'Dự án bếp mở cho nhà phố hiện đại', 'summary' => 'Tối ưu lưu trữ và luồng di chuyển.', 'image' => '/theme-demo/ec905/project-04.webp', 'url' => '#'],
            ['title' => 'Dự án phòng tắm đá tự nhiên', 'summary' => 'Trải nghiệm thư giãn ngay tại nhà.', 'image' => '/theme-demo/ec905/project-05.webp', 'url' => '#'],
        ];
        $news = [
            ['title' => 'Tư vấn chọn sơn nội thất an toàn cho gia đình', 'summary' => 'Cân nhắc độ phủ, khả năng lau chùi và phát thải.', 'image' => '/theme-demo/ec905/project-06.webp', 'url' => '#'],
            ['title' => 'Tư vấn phối gạch cho phòng tắm nhỏ rộng hơn', 'summary' => 'Màu sáng và đường ron gọn giúp không gian thoáng.', 'image' => '/theme-demo/ec905/project-01.webp', 'url' => '#'],
            ['title' => 'Tư vấn sử dụng bồn cầu thông minh hiệu quả', 'summary' => 'Lưu ý nguồn điện, cấp nước và vệ sinh định kỳ.', 'image' => '/theme-demo/ec905/project-03.webp', 'url' => '#'],
            ['title' => 'Tư vấn bảo trì sen tắm và vòi nước tại nhà', 'summary' => 'Vệ sinh đầu phun giúp thiết bị bền hơn.', 'image' => '/theme-demo/ec905/project-05.webp', 'url' => '#'],
            ['title' => 'Tư vấn chọn thiết bị bếp cho căn hộ mới', 'summary' => 'Ưu tiên kích thước đồng bộ và dễ vệ sinh.', 'image' => '/theme-demo/ec905/project-04.webp', 'url' => '#'],
        ];
        $benefits = [
            ['title' => 'Miễn phí giao hàng', 'summary' => 'Áp dụng toàn quốc', 'icon' => 'fa-truck-fast'],
            ['title' => 'Đảm bảo chất lượng', 'summary' => 'Sản phẩm đã kiểm định', 'icon' => 'fa-award'],
            ['title' => 'Hỗ trợ 24/7', 'summary' => 'Chăm sóc khách hàng uy tín', 'icon' => 'fa-headset'],
            ['title' => 'Tư vấn bán hàng', 'summary' => 'Hotline 0399162342', 'icon' => 'fa-user-check'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero nhà đẹp', 'description' => 'Banner phòng tắm cùng menu danh mục và ba ô quảng bá.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'ec905-hero-slider', 'limit' => 3, 'autoplay_ms' => 5200], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Kiến tạo phòng tắm hiện đại', 'Giải pháp nhà đẹp 2026', 'Thiết bị đồng bộ, vật liệu bền đẹp và hỗ trợ thi công tận tâm.', 'Khám phá ngay'), ['content' => ['slides' => [['title' => 'Kiến tạo phòng tắm hiện đại', 'summary' => 'Thiết bị đồng bộ, vật liệu bền đẹp và hỗ trợ thi công tận tâm.', 'button_label' => 'Khám phá ngay', 'image' => '/theme-demo/ec905/hero-bathroom.webp', 'link_url' => '#son-noi-ngoai']]]]), 'en' => $heading('Create a modern bathroom')]],
            ['block_type' => 'ec905_benefits', 'label' => 'Cam kết dịch vụ', 'description' => 'Bốn cam kết giao hàng, chất lượng, hỗ trợ và tư vấn.', 'preview_image' => $preview, 'anchor_id' => 'cam-ket', 'data' => ['vi' => $withItems($heading('Cam kết dịch vụ'), $benefits), 'en' => $withItems($heading('Our commitments'), $benefits)]],
            ['block_type' => 'ec905_paint_products', 'label' => 'Sơn nội & ngoại thất', 'description' => 'Bốn sản phẩm sơn kèm ảnh tư vấn phối màu.', 'preview_image' => $preview, 'anchor_id' => 'son-noi-ngoai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 4, 'search' => 'EC905-PAINT', 'featured_only' => false], 'settings_schema' => $productSchema(4), 'data' => ['vi' => $withItems($heading('Sơn nội & ngoại thất'), $paint), 'en' => $withItems($heading('Interior & exterior paint'), $paint)]],
            ['block_type' => 'ec905_tile_products', 'label' => 'Ốp lát cao cấp', 'description' => 'Lưới mười mẫu gạch và bề mặt hoàn thiện.', 'preview_image' => $preview, 'anchor_id' => 'op-lat', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 10, 'search' => 'EC905-TILE', 'featured_only' => false], 'settings_schema' => $productSchema(10), 'data' => ['vi' => $withItems($heading('Ốp lát cao cấp'), $tiles), 'en' => $withItems($heading('Premium tiles'), $tiles)]],
            ['block_type' => 'ec905_projects', 'label' => 'Dự án thi công', 'description' => 'Thư viện năm dự án nhà tắm và nhà bếp.', 'preview_image' => $preview, 'anchor_id' => 'du-an', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 5, 'search' => 'Dự án', 'featured_only' => false], 'settings_schema' => $postSchema(5), 'data' => ['vi' => $withItems($heading('Dự án thi công nổi bật'), $projects), 'en' => $withItems($heading('Featured projects'), $projects)]],
            ['block_type' => 'ec905_news', 'label' => 'Tin tức nhà đẹp', 'description' => 'Hai tin lớn và ba tin tư vấn nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 5, 'search' => 'Tư vấn', 'featured_only' => false], 'settings_schema' => $postSchema(5), 'data' => ['vi' => $withItems($heading('Tin tức khuyến mại'), $news), 'en' => $withItems($heading('Home advice'), $news)]],
            ['block_type' => 'ec905_newsletter', 'label' => 'Đăng ký bản tin', 'description' => 'Biểu mẫu nhận tin sản phẩm và mã giảm giá.', 'preview_image' => $preview, 'anchor_id' => 'newsletter', 'data' => ['vi' => $heading('Đăng ký nhận bản tin', null, 'Tin mới nhất về sản phẩm và mã giảm giá.'), 'en' => $heading('Newsletter', null, 'Latest products and offers.')]],
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
    private function ser103DefaultBlocks(): array
    {
        $preview = fn (string $name): string => '/theme-previews/SER103/'.$name;
        $heading = fn (string $title, string $subtitle = '', string $description = '', string $button = ''): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = fn (array $data, array $items): array => array_merge($data, ['content' => ['items' => $items]]);
        $sourceSchema = fn (string $source, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [
                ['value' => $source, 'label' => $source === 'cms_posts' ? 'Tin tức CMS' : 'Dịch vụ CMS'],
                ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ]],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
        ];
        $services = [
            ['title' => 'Trang điểm cô dâu', 'summary' => 'Phong cách trang điểm trong trẻo, tôn nét riêng và bền đẹp suốt ngày cưới.', 'image' => $preview('service-makeup.webp'), 'url' => route('site.services.index')],
            ['title' => 'Chụp ảnh cưới', 'summary' => 'Kể câu chuyện tình yêu bằng những khung hình tự nhiên, tinh tế và giàu cảm xúc.', 'image' => $preview('service-photo.webp'), 'url' => route('site.services.index')],
            ['title' => 'Quay phóng sự cưới', 'summary' => 'Lưu giữ những khoảnh khắc chân thật nhất bằng ngôn ngữ điện ảnh hiện đại.', 'image' => $preview('service-video.webp'), 'url' => route('site.services.index')],
            ['title' => 'Thuê xe cưới', 'summary' => 'Bộ sưu tập xe cưới thanh lịch, trang trí đồng điệu với phong cách buổi lễ.', 'image' => $preview('service-car.webp'), 'url' => route('site.services.index')],
            ['title' => 'Trang trí & bàn ghế', 'summary' => 'Không gian tiệc cưới chỉn chu từ hoa, ánh sáng đến từng chi tiết bàn tiệc.', 'image' => $preview('service-banquet.webp'), 'url' => route('site.services.index')],
        ];
        $posts = [
            ['title' => 'Xu hướng các mẫu váy cưới đẹp nhất 2026', 'summary' => 'Những phom dáng hiện đại giúp cô dâu tỏa sáng theo cách riêng trong ngày trọng đại.', 'image' => $preview('news-minimal.webp'), 'date' => '18/11/2025', 'views' => 94, 'url' => route('site.blog.index')],
            ['title' => 'Các mẫu váy cưới dẫn đầu xu hướng 2026', 'summary' => 'Từ chất liệu ánh ngọc đến đường cắt couture, đây là những lựa chọn đáng mong chờ.', 'image' => $preview('news-couture.webp'), 'date' => '18/11/2025', 'views' => 65, 'url' => route('site.blog.index')],
            ['title' => 'Những kiểu váy cưới hot năm 2026', 'summary' => 'Vẻ đẹp tối giản và phom dáng thanh lịch tiếp tục chinh phục các cô dâu hiện đại.', 'image' => $preview('news-garden.webp'), 'date' => '18/11/2025', 'views' => 38, 'url' => route('site.blog.index')],
        ];
        $gallery = [
            ['title' => 'Lễ cưới truyền thống', 'image' => $preview('gallery-aodai.webp'), 'url' => '#'],
            ['title' => 'Khoảnh khắc bên nhau', 'image' => $preview('service-photo.webp'), 'url' => '#'],
            ['title' => 'Tình yêu giữa thiên nhiên', 'image' => $preview('gallery-mountain.webp'), 'url' => '#'],
            ['title' => 'Ngày cưới thanh lịch', 'image' => $preview('about-fashion.webp'), 'url' => '#'],
            ['title' => 'Hoàng hôn hạnh phúc', 'image' => $preview('gallery-lake.webp'), 'url' => '#'],
        ];

        return [
            [
                'block_type' => 'hero_slider', 'label' => 'Hero Bøhu Wedding',
                'description' => 'Hero chia đôi với nội dung tư vấn và ảnh cưới tự chuyển.',
                'preview_image' => $preview('cover-ser103.png'), 'anchor_id' => 'trang-chu', 'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'ser103-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                    'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)'],
                ],
                'data' => [
                    'vi' => array_merge($heading('Lập kế hoạch cho đám cưới của bạn', 'Bøhu Wedding', 'Chúng tôi giúp bạn tổ chức một ngày cưới tinh tế, trọn vẹn và mang dấu ấn riêng.', 'Đặt lịch hẹn'), ['content' => ['slides' => [[
                        'kicker' => 'Bøhu Wedding', 'title' => 'Lập kế hoạch cho đám cưới của bạn', 'summary' => 'Chúng tôi giúp bạn tổ chức một ngày cưới tinh tế, trọn vẹn và mang dấu ấn riêng.', 'button_label' => 'Đặt lịch hẹn', 'link_url' => '#lien-he', 'image' => $preview('hero-wedding.webp'),
                    ]]]]),
                    'en' => $heading('Plan the wedding of your dreams', 'Bøhu Wedding', 'A refined celebration shaped around your story.', 'Book a consultation'),
                ],
            ],
            [
                'block_type' => 'ser103_about', 'label' => 'Câu chuyện Bøhu',
                'description' => 'Bố cục collage hình cưới và lời giới thiệu thương hiệu.',
                'preview_image' => $preview('about-couple.webp'), 'anchor_id' => 'gioi-thieu',
                'settings' => ['source' => 'custom'],
                'data' => [
                    'vi' => $withItems($heading('Người lên kế hoạch cho đám cưới hoàn hảo của bạn', 'Bøhu Wedding', 'Chúng tôi dành trọn sự tinh tế cho từng chi tiết, thay bạn gửi lời yêu thương và mang tình yêu của đôi bạn đến chung vui trong ngày trọng đại.'), [
                        ['title' => 'Khoảnh khắc của hai người', 'image' => $preview('about-couple.webp')],
                        ['title' => 'Cô dâu Bøhu', 'image' => $preview('about-bride.webp')],
                        ['title' => 'Lời hẹn ước', 'image' => $preview('about-rings.webp')],
                        ['title' => 'Vẻ đẹp thanh lịch', 'image' => $preview('about-fashion.webp')],
                    ]),
                    'en' => $heading('The planner behind your perfect wedding', 'Bøhu Wedding', 'Thoughtful details, heartfelt moments and a celebration that feels entirely yours.'),
                ],
            ],
            [
                'block_type' => 'business_service_grid', 'label' => 'Dịch vụ cưới',
                'description' => 'Năm dịch vụ cưới dạng lưới, có thể lấy từ CMS Services.',
                'preview_image' => $preview('service-photo.webp'), 'anchor_id' => 'dich-vu', 'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 5], 'settings_schema' => $sourceSchema('cms_services', 5),
                'data' => [
                    'vi' => $withItems($heading('Chúng tôi có thể làm gì', 'Dịch vụ của chúng tôi', 'Một hệ sinh thái dịch vụ được kết nối để ngày cưới của bạn diễn ra thật nhẹ nhàng.'), $services),
                    'en' => $heading('What we can do for you', 'Our services', 'A connected wedding service ecosystem for an effortless celebration.'),
                ],
            ],
            [
                'block_type' => 'latest_posts', 'label' => 'Tin tức cưới',
                'description' => 'Ba bài viết mới nhất, có thể lấy từ CMS Posts.',
                'preview_image' => $preview('news-couture.webp'), 'anchor_id' => 'tin-tuc', 'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 3], 'settings_schema' => $sourceSchema('cms_posts', 3),
                'data' => [
                    'vi' => $withItems($heading('Tin tức - Sự kiện mới nhất', 'Tin tức mới', 'Cảm hứng váy cưới, trang trí và những câu chuyện phía sau ngày trọng đại.'), $posts),
                    'en' => $heading('Latest news and events', 'New stories', 'Wedding fashion, decor and inspiration for your celebration.'),
                ],
            ],
            [
                'block_type' => 'landing_contact', 'label' => 'Liên hệ sự kiện',
                'description' => 'Ảnh cưới và form gửi yêu cầu trực tiếp.',
                'preview_image' => $preview('contact-couple.webp'), 'anchor_id' => 'lien-he',
                'settings' => ['source' => 'custom'], 'media' => ['image' => $preview('contact-couple.webp')],
                'data' => [
                    'vi' => $heading('Liên hệ chúng tôi cho sự kiện của bạn', 'Liên lạc', 'Hãy kể Bøhu nghe về ngày cưới bạn đang mong đợi.', 'Gửi đi'),
                    'en' => $heading('Contact us for your celebration', 'Get in touch', 'Tell Bøhu about the wedding you are dreaming of.', 'Send'),
                ],
            ],
            [
                'block_type' => 'collection_gallery', 'label' => 'Thư viện ảnh cưới',
                'description' => 'Dải năm ảnh cưới toàn chiều rộng.',
                'preview_image' => $preview('gallery-mountain.webp'), 'anchor_id' => 'thu-vien',
                'settings' => ['source' => 'custom', 'limit' => 5],
                'data' => [
                    'vi' => $withItems($heading('Thư viện ảnh cưới'), $gallery),
                    'en' => $heading('Wedding gallery'),
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
                        ['title' => 'Rửa xe tiêu chuẩn', 'price' => '200.000đ', 'features' => 'Rửa xe ngoại thất|Hút bụi nội thất|Lau chùi cơ bản|Dưỡng lốp', 'icon' => 'fa-solid fa-car-side'],
                        ['title' => 'Rửa xe cao cấp', 'price' => '400.000đ', 'features' => 'Rửa xe chi tiết|Vệ sinh nội thất|Dưỡng lốp, phủ bóng|Khử mùi', 'icon' => 'fa-solid fa-spray-can-sparkles'],
                        ['title' => 'Phủ ceramic', 'price' => '4.500.000đ', 'features' => 'Phủ ceramic cao cấp|Bảo vệ sơn xe|Kéo dài độ bóng|Hiệu chỉnh bề mặt', 'icon' => 'fa-solid fa-shield-halved', 'featured' => true],
                        ['title' => 'Vệ sinh nội thất', 'price' => '800.000đ', 'features' => 'Vệ sinh chi tiết|Khử mùi, diệt khuẩn|Dưỡng da và nhựa|Vệ sinh trần xe', 'icon' => 'fa-solid fa-couch'],
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
    /**
     * @return array<int, array<string, mixed>>
     */
    private function spa111DefaultBlocks(): array
    {
        $preview = fn (string $name): string => '/theme-previews/SPA111/'.$name.'.png';
        $sourceSchema = fn (array $sources, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật'],
        ];
        $static = fn (string $type, string $label, string $anchor, string $title, string $subtitle, string $description, string $previewName, array $settings = []): array => [
            'block_type' => $type, 'label' => $label, 'description' => $description,
            'preview_image' => $preview($previewName), 'anchor_id' => $anchor, 'dynamic' => false,
            'settings' => array_merge(['source' => 'custom'], $settings), 'settings_schema' => [],
            'data' => [
                'vi' => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]],
                'en' => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => 'View details', 'content' => ['items' => []]],
            ],
        ];
        $dynamic = fn (string $type, string $label, string $anchor, string $title, string $subtitle, string $description, string $previewName, string $source, int $limit): array => [
            'block_type' => $type, 'label' => $label, 'description' => $description,
            'preview_image' => $preview($previewName), 'anchor_id' => $anchor, 'dynamic' => true,
            'settings' => ['source' => $source, 'limit' => $limit, 'featured_only' => true],
            'settings_schema' => $sourceSchema([
                ['value' => $source, 'label' => Str::headline($source)],
                ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ], $limit),
            'data' => [
                'vi' => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => 'Xem chi tiết', 'content' => ['items' => []]],
                'en' => ['title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => 'View details', 'content' => ['items' => []]],
            ],
        ];

        return [
            [
                'block_type' => 'hero_slider', 'label' => 'Hero Bean Spa', 'description' => 'Ảnh chủ đạo, thông điệp và nút đặt lịch.',
                'preview_image' => $preview('hero'), 'anchor_id' => 'trang-chu', 'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'spa111-hero-slider', 'limit' => 2, 'autoplay_ms' => 6500],
                'settings_schema' => ['limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']],
                'data' => ['vi' => ['title' => 'Nâng Niu Vẻ Đẹp Của Bạn', 'subtitle' => 'Chăm Sóc Sắc Đẹp Toàn Diện', 'description' => '', 'button_label' => 'Xem thêm', 'content' => ['slides' => []]], 'en' => ['title' => 'Elevate Your Beauty', 'subtitle' => 'Complete Beauty Care', 'description' => '', 'button_label' => 'Discover', 'content' => ['slides' => []]]],
            ],
            $static('spa111_service_highlights', 'Điểm nổi bật dịch vụ', 'noi-bat', 'Ba liệu pháp thư giãn', 'Chăm sóc tinh tế', 'Bấm huyệt bàn chân, massage đầu và massage đá nóng thư giãn.', 'service-highlights'),
            $static('spa111_about', 'Giới thiệu Bean Spa', 'gioi-thieu', 'Chào mừng bạn đến với', 'Về chúng tôi', 'Bean Spa mang đến trải nghiệm chăm sóc sức khỏe và sắc đẹp trọn vẹn.', 'about'),
            $dynamic('spa111_services', 'Hành trình dịch vụ', 'dich-vu', 'Hành Trình Nâng Niu Bản Thân', 'Dịch vụ của chúng tôi', 'Liệu trình tinh chỉnh, sản phẩm cao cấp và không gian yên tĩnh.', 'services', 'cms_services', 3),
            $static('spa111_stats', 'Số liệu Bean Spa', 'thanh-tuu', 'Hành trình được tin yêu', 'Thành tựu', '5000+ khách hàng, 98% đánh giá tích cực, 10+ liệu trình và 5 năm kinh nghiệm.', 'stats'),
            $dynamic('spa111_featured_products', 'Sản phẩm nổi bật', 'san-pham', 'Sản Phẩm Nổi Bật', 'Sản phẩm chọn lọc', 'Sản phẩm chăm sóc spa được tuyển chọn kỹ lưỡng và an toàn.', 'products', 'cms_products', 8),
            $static('spa111_why_choose', 'Tại sao chọn Bean Spa', 'ly-do', 'Đồng hành cùng khách hàng chăm sóc Sức Khỏe & Thư Giãn', 'Tại sao chọn chúng tôi', 'Chuyên môn vững vàng, liệu trình cá nhân hóa và sự tận tâm.', 'why-choose'),
            $dynamic('spa111_testimonials', 'Phản hồi khách hàng', 'danh-gia', 'Khách Hàng Nói Gì?', 'Phản hồi từ khách hàng', 'Những lời chia sẻ chân thành là động lực để Bean Spa hoàn thiện mỗi ngày.', 'testimonials', 'cms_testimonials', 3),
            $static('spa111_faq', 'Câu hỏi thường gặp', 'faq', 'Giải Đáp Thắc Mắc Cùng Bean Spa', 'Câu hỏi thường gặp', 'Thông tin về liệu trình, chi phí và cách chuẩn bị trước khi massage.', 'faq'),
            $dynamic('spa111_team', 'Đội ngũ chuyên viên', 'doi-ngu', 'Đội Ngũ Nhân Viên Chuyên Nghiệp', 'Nhân viên tận tâm', 'Đội ngũ chuyên viên trị liệu được đào tạo bài bản và giàu kinh nghiệm.', 'team', 'cms_team_members', 4),
            $dynamic('spa111_latest_posts', 'Tin tức làm đẹp', 'tin-tuc', 'Xu Hướng Làm Đẹp & Chăm Sóc Sức Khỏe', 'Tin tức mới nhất', 'Thông tin mới nhất giúp bạn luôn khỏe đẹp từ bên trong.', 'latest-posts', 'cms_posts', 4),
            $dynamic('spa111_partners', 'Đối tác tin cậy', 'doi-tac', 'Tự Hào Là Đối Tác Tin Cậy', 'Đối tác chúng tôi', 'Mối quan hệ hợp tác xây dựng trên niềm tin, chất lượng và giá trị bền vững.', 'partners', 'cms_partners', 9),
            $static('spa111_booking', 'Tư vấn và đặt lịch', 'lien-he', 'Liên Hệ Bean Spa Để Được Chăm Sóc Tận Tâm', 'Tư vấn & đặt lịch miễn phí', 'Chuyên viên sẵn sàng lắng nghe và đề xuất liệu trình phù hợp nhất.', 'booking'),
            $static('spa111_footer', 'Chân trang Bean Spa', 'footer', 'Bean Spa', 'Thông tin, chính sách và dịch vụ', 'Thông tin liên hệ, chính sách, dịch vụ và đăng ký nhận tin.', 'footer'),
        ];
    }

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
    private function dn351DefaultBlocks(): array
    {
        $preview = '/theme-previews/DN351/preview-dn351.png';
        $asset = fn (string $name): string => '/theme-demo/dn351/'.$name.'.jpg';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $withItems = fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $sourceSchema = fn (string $source, string $label, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [
                ['value' => $source, 'label' => $label],
                ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ]],
            'limit' => ['type' => 'number', 'label' => 'Số lượng', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật', 'default' => false],
        ];

        $categories = [
            ['title' => 'Hải sản', 'summary' => 'Hải sản tươi sống tuyển chọn', 'image' => $asset('category-seafood'), 'url' => '#san-pham'],
            ['title' => 'Rau sạch', 'summary' => 'Rau củ theo mùa', 'image' => $asset('category-vegetables'), 'url' => '#san-pham'],
            ['title' => 'Các loại thịt', 'summary' => 'Thịt tươi chuẩn nhà hàng', 'image' => $asset('category-meat'), 'url' => '#san-pham'],
            ['title' => 'Sản phẩm mới', 'summary' => 'Lựa chọn mới mỗi tuần', 'image' => $asset('product-chicken'), 'url' => '#san-pham'],
        ];
        $products = [
            ['title' => 'Ức gà phi lê', 'price' => 60000, 'original_price' => 70000, 'image' => $asset('product-chicken'), 'url' => '#'],
            ['title' => 'Cồi sò điệp Nhật Bản', 'price' => 1090000, 'image' => $asset('product-scallops'), 'url' => '#'],
            ['title' => 'Ghẹ xanh loại 2', 'price' => 550000, 'original_price' => 650000, 'image' => $asset('product-crab'), 'url' => '#'],
            ['title' => 'Cua lông Hong Kong', 'price' => 846000, 'original_price' => 950000, 'image' => $asset('product-crab'), 'url' => '#'],
            ['title' => 'Cua King Crab đỏ sống', 'price' => 1650000, 'original_price' => 2150000, 'image' => $asset('category-seafood'), 'url' => '#'],
            ['title' => 'Bạch tuộc Nhật', 'price' => 500000, 'original_price' => 750000, 'image' => $asset('blog-squid-grilled'), 'url' => '#'],
            ['title' => 'Bò Wagyu cắt lát', 'price' => 1290000, 'image' => $asset('category-meat'), 'url' => '#'],
            ['title' => 'Rau củ hữu cơ', 'price' => 99000, 'image' => $asset('category-vegetables'), 'url' => '#'],
        ];
        $posts = [
            ['title' => 'Đậm vị với mực nướng chanh tỏi ớt', 'summary' => 'Công thức nhanh gọn giúp mực giữ được độ ngọt và hương thơm đặc trưng.', 'date' => '09/05/2026', 'views' => 102, 'image' => $asset('blog-squid-grilled'), 'url' => '#'],
            ['title' => 'Canh cá saba chua ngọt', 'summary' => 'Món canh thanh mát, giàu dinh dưỡng cho bữa cơm gia đình.', 'date' => '09/05/2026', 'views' => 105, 'image' => $asset('blog-fish-soup'), 'url' => '#'],
            ['title' => 'Mực lá tốt cho sức khỏe tim mạch', 'summary' => 'Gợi ý cách lựa chọn và chế biến mực tươi ngon, an toàn.', 'date' => '09/05/2026', 'views' => 91, 'image' => $asset('blog-stuffed-calamari'), 'url' => '#'],
        ];
        $partners = collect(['CAFE', 'BARBECUE', 'BRANDSON', 'BAKERY', 'GUARANTEED', 'COFFEE SHOP', 'RESTAURANT'])
            ->map(fn (string $name): array => ['title' => $name, 'summary' => 'PREMIUM PARTNER', 'icon' => 'fa-solid fa-award', 'url' => '#'])
            ->all();

        return [
            ['block_type' => 'hero_slider', 'label' => 'Banner Meatlers', 'description' => 'Slider mở đầu toàn màn hình.', 'preview_image' => $preview, 'anchor_id' => 'trang-chu', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'dn351-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500, 'primary_url' => '#danh-muc'], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Vị trí banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'data' => ['vi' => array_merge($heading('Nhà cung cấp trái cây tươi tốt nhất thị trường', 'Meatlers', 'Nguồn thực phẩm minh bạch, tuyển chọn mỗi ngày và giao tươi tận nơi.', 'Khám phá ngay'), ['content' => ['slides' => [['title' => 'Thực phẩm tươi tuyển chọn', 'image' => $asset('hero-market')]]]]), 'en' => $heading('The freshest food on the market', 'Meatlers', 'Transparent sourcing, selected daily and delivered fresh.', 'Explore now')]],
            ['block_type' => 'about_experience', 'label' => 'Giới thiệu Meatlers', 'description' => 'Câu chuyện thương hiệu với hai ảnh dọc.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => [], 'media' => ['images' => [$asset('about-butcher'), $asset('about-washing')]], 'data' => ['vi' => $heading('Chúng tôi cung cấp các loại sản phẩm tốt nhất', 'Giới thiệu về công ty', 'Meatlers kết nối nguồn nguyên liệu sạch với người tiêu dùng bằng tiêu chuẩn tuyển chọn minh bạch, tươi ngon và được kiểm định.', 'Tìm hiểu ngay'), 'en' => $heading('We provide the finest products', 'About our company', 'Meatlers connects trusted producers with discerning customers.', 'Learn more')]],
            ['block_type' => 'dn351_promo_mosaic', 'label' => 'Khuyến mại ba mảng', 'description' => 'Thịt, trái cây nhập khẩu và lời mời ghé cửa hàng.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'settings' => [], 'media' => ['meat' => $asset('category-meat'), 'fruit' => $asset('hero-market'), 'store' => $asset('testimonial-butcher')], 'data' => ['vi' => $heading('Thịt chất lượng nhà hàng', null, 'Giao hàng tươi ngon tận nhà.'), 'en' => $heading('Restaurant-quality meat', null, 'Fresh delivery to your door.')]],
            ['block_type' => 'dn351_category_rail', 'label' => 'Mua theo danh mục', 'description' => 'Bốn danh mục ảnh oval.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 4], 'settings_schema' => $sourceSchema('catalog_categories', 'Danh mục sản phẩm', 4), 'data' => ['vi' => $withItems($heading('Mua sắm theo danh mục', 'Khám phá ngay'), $categories), 'en' => $withItems($heading('Shop by category', 'Explore now'), $categories)]],
            ['block_type' => 'dn351_featured_split', 'label' => 'Ưu đãi & bán chạy', 'description' => 'Một nửa ưu đãi, một nửa sản phẩm nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'ban-chay', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 2, 'search' => 'DN351'], 'settings_schema' => $sourceSchema('cms_products', 'Sản phẩm', 2), 'media' => ['image' => $asset('category-meat')], 'data' => ['vi' => $withItems($heading('Giảm giá lên đến 15% cho toàn bộ sản phẩm của chúng tôi', 'Lựa chọn của chúng tôi', 'Đặt mua thực phẩm tươi trực tuyến'), array_slice($products, 0, 2)), 'en' => $withItems($heading('Save up to 15% on our products', 'Our selection', 'Order fresh food online'), array_slice($products, 0, 2))]],
            ['block_type' => 'dn351_product_grid', 'label' => 'Lưới sản phẩm', 'description' => 'Tám sản phẩm với bộ lọc danh mục.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 8, 'search' => 'DN351'], 'settings_schema' => $sourceSchema('cms_products', 'Sản phẩm', 8), 'data' => ['vi' => $withItems($heading('Sản phẩm của chúng tôi', 'Các loại thực phẩm'), $products), 'en' => $withItems($heading('Our products', 'Fresh food'), $products)]],
            ['block_type' => 'testimonials', 'label' => 'Khách hàng chia sẻ', 'description' => 'Đánh giá khách hàng ở giữa hai ảnh.', 'preview_image' => $preview, 'anchor_id' => 'khach-hang', 'settings' => ['source' => 'custom', 'limit' => 1], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_testimonials', 'label' => 'Đánh giá CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đánh giá']], 'media' => ['left' => $asset('testimonial-butcher'), 'right' => $asset('testimonial-customer')], 'data' => ['vi' => $withItems($heading('Hãy nghe những gì khách hàng của chúng tôi nói', 'Lời chứng thực'), [['name' => 'Nguyễn Văn An', 'role' => 'Doanh nhân', 'quote' => 'Thực phẩm luôn tươi mới, nguồn gốc rõ ràng và đội ngũ giao hàng rất tận tâm.', 'image' => $asset('testimonial-customer')]]), 'en' => $heading('What our customers say', 'Testimonials')]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức Meatlers', 'description' => 'Ba bài viết mới nhất.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'search' => '', 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_posts', 'Tin tức CMS', 3), 'data' => ['vi' => $withItems($heading('Tin tức mới nhất của chúng tôi', 'Blog của chúng tôi'), $posts), 'en' => $withItems($heading('Our latest news', 'Our blog'), $posts)]],
            ['block_type' => 'partner_logos', 'label' => 'Đối tác thực phẩm', 'description' => 'Hàng logo đối tác.', 'preview_image' => $preview, 'anchor_id' => 'thu-vien', 'settings' => ['source' => 'custom', 'limit' => 7], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_partners', 'label' => 'Đối tác CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => $withItems($heading('Đối tác của chúng tôi'), $partners), 'en' => $withItems($heading('Our partners'), $partners)]],
            ['block_type' => 'newsletter_signup', 'label' => 'Đăng ký nhận tin', 'description' => 'Biểu mẫu email trên nền thực phẩm tối.', 'preview_image' => $preview, 'anchor_id' => 'dang-ky', 'settings' => [], 'media' => ['image' => $asset('hero-market')], 'data' => ['vi' => $heading('Đăng ký để nhận cập nhật hàng tuần', null, 'Nhận ưu đãi độc quyền, mẹo nấu ăn và thông tin thực phẩm mới nhất.'), 'en' => $heading('Subscribe for weekly updates', null, 'Receive exclusive offers, recipes and fresh market news.')]],
            ['block_type' => 'footer_contact', 'label' => 'Chân trang liên hệ', 'description' => 'Mốc quản trị cho chân trang dùng chung.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'settings' => [], 'data' => ['vi' => $heading('Thông tin liên hệ'), 'en' => $heading('Contact information')]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function dn350DefaultBlocks(): array
    {
        $preview = '/theme-previews/DN350/preview-dn350.png';
        $asset = fn (string $name): string => '/theme-demo/dn350/'.$name.'.webp';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title, 'subtitle' => $subtitle, 'description' => $description, 'button_label' => $button,
        ];
        $withItems = fn (array $base, array $items): array => array_merge($base, ['content' => ['items' => $items]]);
        $sourceSchema = fn (string $source, string $label, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => $source, 'label' => $label], ['value' => 'custom', 'label' => 'Nhập thủ công']]],
            'limit' => ['type' => 'number', 'label' => 'Số lượng', 'default' => $limit],
            'search' => ['type' => 'text', 'label' => 'Từ khóa tìm kiếm'],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ nội dung nổi bật', 'default' => false],
        ];

        $services = [
            ['title' => 'Giúp việc theo giờ', 'summary' => 'Chăm sóc nhà cửa linh hoạt theo khung giờ, quy trình rõ ràng và nhân sự tận tâm.', 'image' => $asset('service-hourly'), 'url' => '#lien-he'],
            ['title' => 'Tổng vệ sinh', 'summary' => 'Làm sạch chuyên sâu cho nhà ở và văn phòng trước bàn giao hoặc sau xây dựng.', 'image' => $asset('service-deep-clean'), 'url' => '#lien-he'],
            ['title' => 'Vệ sinh sofa, rèm, nệm', 'summary' => 'Giặt hút chuyên dụng giúp loại bỏ bụi mịn, mùi khó chịu và vết bẩn bám sâu.', 'image' => $asset('service-upholstery'), 'url' => '#lien-he'],
            ['title' => 'Vệ sinh máy lạnh', 'summary' => 'Bảo dưỡng và vệ sinh điều hòa an toàn, cải thiện chất lượng không khí trong phòng.', 'image' => $asset('service-air-conditioner'), 'url' => '#lien-he'],
            ['title' => 'Môi giới giúp việc', 'summary' => 'Kết nối nhân sự đáng tin cậy, phù hợp nhu cầu chăm sóc nhà cửa dài hạn.', 'image' => $asset('service-housekeeper'), 'url' => '#lien-he'],
            ['title' => 'Chăm sóc dọn vườn', 'summary' => 'Cắt tỉa, vệ sinh và chăm sóc khuôn viên luôn xanh sạch, gọn gàng.', 'image' => $asset('service-garden'), 'url' => '#lien-he'],
        ];
        $reasons = [
            ['title' => 'Tận tâm trong từng góc nhỏ', 'icon' => 'fa-solid fa-wand-magic-sparkles'],
            ['title' => 'An toàn tuyệt đối cho sức khỏe', 'icon' => 'fa-solid fa-seedling'],
            ['title' => 'Đội ngũ chuyên nghiệp, trung thực', 'icon' => 'fa-solid fa-briefcase'],
            ['title' => 'Linh hoạt và đúng giờ', 'icon' => 'fa-solid fa-bolt'],
        ];
        $testimonials = [
            ['name' => 'Tô Trung Nhân', 'role' => 'CEO & Đồng sáng lập', 'quote' => 'Prinash đã giúp gia đình tôi giải phóng sức lao động. Mọi không gian đều được chăm sóc kỹ lưỡng và đúng hẹn.', 'image' => $asset('gallery-cleaner')],
            ['name' => 'Nguyễn Hoàng Anh', 'role' => 'Giám đốc', 'quote' => 'Trải nghiệm dịch vụ tuyệt hảo. Đội ngũ chuyên nghiệp, quy trình minh bạch và kết quả vượt mong đợi.', 'image' => $asset('reasons-team')],
            ['name' => 'Tăng Hữu Dũng', 'role' => 'Người sáng lập', 'quote' => 'Giải pháp vệ sinh doanh nghiệp rất linh hoạt, tiết kiệm thời gian và giúp văn phòng luôn sạch chuẩn.', 'image' => $asset('service-deep-clean')],
        ];
        $gallery = [
            ['title' => 'Đội ngũ tận tâm', 'image' => $asset('gallery-cleaner'), 'url' => '#dich-vu'],
            ['title' => 'Bếp sạch tinh tươm', 'image' => $asset('gallery-kitchen-work'), 'url' => '#dich-vu'],
            ['title' => 'Không gian sau vệ sinh', 'image' => $asset('gallery-kitchen'), 'url' => '#dich-vu'],
            ['title' => 'Làm sạch theo nhóm', 'image' => $asset('gallery-team'), 'url' => '#dich-vu'],
            ['title' => 'Sân vườn xanh sạch', 'image' => $asset('gallery-garden'), 'url' => '#dich-vu'],
        ];
        $posts = [
            ['title' => 'Những điểm thường thấy của người nghiện dọn nhà', 'summary' => 'Liệu bạn có những đặc điểm đáng tiền, ẩn tiện này không?', 'date' => '29/06/2026', 'views' => 93, 'image' => $asset('gallery-team'), 'url' => '#'],
            ['title' => 'Khi nào nên thuê người dọn nhà một lần?', 'summary' => 'Gợi ý giúp bạn chọn đúng thời điểm và phạm vi làm sạch chuyên sâu.', 'date' => '29/06/2026', 'views' => 61, 'image' => $asset('gallery-kitchen-work'), 'url' => '#'],
            ['title' => 'Đừng giữ lại những thứ hết hạn này khi chuyển nhà', 'summary' => 'Một checklist nhỏ giúp việc dọn nhà và sắp xếp không gian dễ dàng hơn.', 'date' => '29/06/2026', 'views' => 53, 'image' => $asset('gallery-moving'), 'url' => '#'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero Prinash', 'description' => 'Hero toàn chiều rộng với ảnh vệ sinh áp lực cao.', 'preview_image' => $preview, 'anchor_id' => 'trang-chu', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'dn350-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500, 'primary_url' => '#dich-vu'], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Vị trí banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => $asset('hero-pressure-washing')], 'data' => ['vi' => array_merge($heading('Chúng tôi là lựa chọn tốt nhất cho bạn', 'Giải pháp vệ sinh chuyên nghiệp', 'Chi phí phải chăng, cam kết mức giá cạnh tranh và chất lượng làm sạch không đổi.', 'Khám phá thêm'), ['content' => ['slides' => [['title' => 'Chúng tôi là lựa chọn tốt nhất cho bạn', 'image' => $asset('hero-pressure-washing')]]]]), 'en' => array_merge($heading('The better choice for a cleaner space', 'Professional cleaning solutions', 'Fair prices and dependable quality for homes and businesses.', 'Discover more'), ['content' => ['slides' => [['title' => 'Professional cleaning', 'image' => $asset('hero-pressure-washing')]]]])]],
            ['block_type' => 'about_experience', 'label' => 'Sứ mệnh Prinash', 'description' => 'Ảnh ghép vệ sinh áp lực cao và cam kết thương hiệu.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'settings' => [], 'media' => ['images' => [$asset('mission-deck'), $asset('mission-railing')]], 'data' => ['vi' => $withItems($heading('Mục tiêu chính của chúng tôi là vệ sinh bằng máy phun áp lực cao', 'Sứ mệnh của chúng tôi', 'Công nghệ phun áp lực cao phù hợp cho sân vườn, tường nhà, bãi xe và các vết bẩn cứng đầu lâu năm.', 'Tìm hiểu ngay'), [['summary' => 'Ứng dụng công nghệ hiện đại để tối ưu hiệu quả làm sạch sâu, nhanh chóng và an toàn.'], ['summary' => 'Cung cấp giải pháp vệ sinh chuẩn mực, tận tâm với mức chi phí minh bạch và phải chăng.']]), 'en' => $heading('Our mission is effective pressure cleaning', 'Our mission')]],
            ['block_type' => 'featured_services', 'label' => 'Dịch vụ vệ sinh', 'description' => 'Sáu dịch vụ lấy từ CMS hoặc nhập thủ công.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_services', 'Dịch vụ CMS', 6), 'data' => ['vi' => $withItems($heading('Dịch vụ tốt nhất mà chúng tôi cung cấp', 'Dịch vụ của chúng tôi'), $services), 'en' => $withItems($heading('Our best cleaning services', 'Our services'), $services)]],
            ['block_type' => 'featured_categories', 'label' => 'Lý do chọn Prinash', 'description' => 'Bốn giá trị khác biệt và ảnh đội ngũ.', 'preview_image' => $preview, 'anchor_id' => 'ly-do', 'settings' => ['source' => 'custom', 'limit' => 4], 'media' => ['image' => $asset('reasons-team')], 'data' => ['vi' => $withItems($heading('Tại sao bạn nên chọn dịch vụ của chúng tôi?', 'Tại sao nên chọn chúng tôi?', 'Giữa rất nhiều đơn vị trên thị trường, chúng tôi là người bạn đồng hành đáng tin cậy cho không gian sống và làm việc chuẩn sạch, trong lành.'), $reasons), 'en' => $withItems($heading('Why choose our service?', 'Why choose us?'), $reasons)]],
            ['block_type' => 'testimonials', 'label' => 'Lời chứng thực', 'description' => 'Ba nhận xét khách hàng.', 'preview_image' => $preview, 'anchor_id' => 'khach-hang', 'settings' => ['source' => 'custom', 'limit' => 3], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_testimonials', 'label' => 'Đánh giá CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số đánh giá']], 'data' => ['vi' => $withItems($heading('Mọi người đang nói gì', 'Lời chứng thực'), $testimonials), 'en' => $withItems($heading('What people are saying', 'Testimonials'), $testimonials)]],
            ['block_type' => 'project_gallery', 'label' => 'Thư viện ảnh', 'description' => 'Năm ảnh dịch vụ dạng hàng ngang.', 'preview_image' => $preview, 'anchor_id' => 'thu-vien', 'settings' => ['source' => 'custom', 'limit' => 5], 'settings_schema' => $sourceSchema('cms_projects', 'Dự án CMS', 5), 'data' => ['vi' => $withItems($heading('Bộ sưu tập ảnh mới nhất', 'Phòng trưng bày của chúng tôi'), $gallery), 'en' => $withItems($heading('Latest photo collection', 'Our gallery'), $gallery)]],
            ['block_type' => 'latest_posts', 'label' => 'Tin tức & Blog', 'description' => 'Ba bài viết mới nhất từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false], 'settings_schema' => $sourceSchema('cms_posts', 'Tin tức CMS', 3), 'data' => ['vi' => $withItems($heading('Tin tức & Blog mới nhất', 'Blog của chúng tôi'), $posts), 'en' => $withItems($heading('Latest news & blog', 'Our blog'), $posts)]],
            ['block_type' => 'newsletter_signup', 'label' => 'Đăng ký nhận tin', 'description' => 'Dải đăng ký email màu cam.', 'preview_image' => $preview, 'anchor_id' => 'dang-ky', 'settings' => [], 'data' => ['vi' => $heading('Đừng bỏ lỡ các cập nhật của chúng tôi – hãy đăng ký ngay!'), 'en' => $heading('Do not miss our latest updates — subscribe today!')]],
            ['block_type' => 'footer_contact', 'label' => 'Chân trang liên hệ', 'description' => 'Mốc quản trị cho chân trang dùng chung.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'settings' => [], 'data' => ['vi' => $heading('Thông tin liên hệ'), 'en' => $heading('Contact information')]],
        ];
    }

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
                        'content' => ['phone' => '0399162342', 'items' => [
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
                'description' => 'Banner bo tròn nền xanh, CTA và video.',
                'preview_image' => '/theme-previews/XD0313/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0313-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Visa dễ dàng, giấc mơ thành hiện thực',
                        'subtitle' => '',
                        'description' => 'Visa chỉ là phương tiện, còn giấc mơ du học, du lịch và định cư mới là mục tiêu cuối cùng.',
                        'button_label' => 'Đọc thêm',
                        'content' => ['slides' => [
                            ['title' => 'Visa dễ dàng, giấc mơ thành hiện thực', 'summary' => 'Visa chỉ là phương tiện, còn giấc mơ du học, du lịch và định cư mới là mục tiêu cuối cùng. Quy trình thuận lợi giúp bạn tập trung hơn vào hành trình của mình.', 'button_label' => 'Đọc thêm', 'image' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=900&q=85', 'link_url' => '#gioi-thieu', 'video_url' => '#video'],
                            ['title' => 'Đồng hành trên mọi hành trình quốc tế', 'summary' => 'Tư vấn hồ sơ, đặt lịch hẹn và hỗ trợ visa nhanh chóng cho từng mục tiêu của bạn.', 'button_label' => 'Dịch vụ Visa', 'image' => 'https://images.unsplash.com/photo-1521791055366-0d553872125f?auto=format&fit=crop&w=900&q=85', 'link_url' => '#dich-vu', 'video_url' => '#video'],
                        ]],
                    ],
                    'en' => ['title' => 'Easy visa, real dreams', 'subtitle' => '', 'description' => 'RouteX helps make your travel, study and work dreams easier.', 'button_label' => 'Read more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Ưu điểm RouteX',
                'description' => 'Bốn thẻ lợi ích đầu trang.',
                'preview_image' => '/theme-previews/XD0313/benefits.png',
                'anchor_id' => 'uu-diem',
                'settings' => ['limit' => 4],
                'data' => [
                    'vi' => [
                        'title' => 'Ưu điểm',
                        'subtitle' => '',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Hồ sơ đơn giản', 'summary' => 'Hồ sơ được rà soát rõ ràng, hướng dẫn đầy đủ và phù hợp với từng mục tiêu xin visa.', 'icon' => '01'],
                            ['title' => 'Xử lý nhanh chóng', 'summary' => 'Liên hệ RouteX để nhận tư vấn miễn phí, chuyên sâu và lộ trình xử lý phù hợp.', 'icon' => '02'],
                            ['title' => 'Tư vấn tận tâm', 'summary' => 'RouteX là đối tác tin cậy, cung cấp dịch vụ tư vấn và hỗ trợ visa chuyên nghiệp.', 'icon' => '03'],
                            ['title' => 'Bảo mật tuyệt đối', 'summary' => 'Mọi dữ liệu khách hàng đều được bảo mật, đảm bảo sự an tâm và tin tưởng.', 'icon' => '04'],
                        ]],
                    ],
                    'en' => ['title' => 'Benefits', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Về RouteX',
                'description' => 'Giới thiệu công ty với hình ảnh và gói dịch vụ.',
                'preview_image' => '/theme-previews/XD0313/about.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#dich-vu'],
                'media' => [
                    'image_one' => 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=900&q=85',
                    'image_two' => 'https://images.unsplash.com/photo-1521791055366-0d553872125f?auto=format&fit=crop&w=900&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Nơi niềm đam mê chạm đến những điểm đến trong mơ',
                        'subtitle' => 'Về chúng tôi',
                        'description' => 'RouteX Visa tự hào là đối tác tin cậy của bạn trên mọi hành trình khám phá thế giới. Chúng tôi cung cấp các giải pháp visa toàn diện, minh bạch và hiệu quả.',
                        'button_label' => 'Đọc thêm',
                        'content' => [
                            'years' => '25',
                            'items' => [
                                ['title' => 'Hộ chiếu Plus', 'icon' => 'P', 'bullets' => ['Di trú xuyên biên giới', 'Hỗ trợ thị thực toàn cầu']],
                                ['title' => 'Nhập cảnh toàn cầu', 'icon' => 'G', 'bullets' => ['Dịch vụ Visa GlobeTrot', 'Giải pháp Visa Infinity']],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'Passion for dream destinations', 'subtitle' => 'About us', 'description' => 'RouteX provides complete visa consulting solutions.', 'button_label' => 'Read more', 'content' => []],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Danh mục visa nổi bật',
                'description' => 'Slider ảnh danh mục visa trên nền xanh đậm.',
                'preview_image' => '/theme-previews/XD0313/featured-visa.png',
                'anchor_id' => 'visa-noi-bat',
                'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 6],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Danh mục Visa nổi bật',
                        'subtitle' => 'Visa nổi bật',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Visa châu Mỹ', 'image' => 'https://images.unsplash.com/photo-1508433957232-3107f5fd5995?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa châu Âu', 'image' => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa du học', 'image' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Featured visa categories', 'subtitle' => 'Featured visas', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Các loại visa phổ biến',
                'description' => 'Lưới dịch vụ visa lấy từ dịch vụ, bài viết, sản phẩm, dự án hoặc dữ liệu tùy chỉnh.',
                'preview_image' => '/theme-previews/XD0313/services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Các loại Visa phổ biến',
                        'subtitle' => 'Visa nổi bật',
                        'description' => '',
                        'button_label' => 'Xem chi tiết',
                        'content' => ['items' => [
                            ['title' => 'Dịch vụ xin Visa Brunei nhanh chóng', 'summary' => 'Công dân Việt Nam cần xin visa Brunei nếu dự kiến lưu trú trên 14 ngày.', 'image' => 'https://images.unsplash.com/photo-1560264280-88b68371db39?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa du học châu Âu', 'summary' => 'Thủ tục xin visa du học châu Âu được tư vấn theo quy định riêng của từng quốc gia.', 'image' => 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Dịch vụ gia hạn Visa Mỹ', 'summary' => 'Hỗ trợ gia hạn thị thực Mỹ cho khách hàng đang có visa đủ điều kiện.', 'image' => 'https://images.unsplash.com/photo-1527853787696-f7be74f2e39a?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa thăm người thân', 'summary' => 'Loại visa dành cho mục đích thăm thân, du lịch ngắn hạn hoặc bảo lãnh đặc biệt.', 'image' => 'https://images.unsplash.com/photo-1516426122078-c23e76319801?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa du lịch', 'summary' => 'Thị thực cho phép nhập cảnh với mục đích tham quan, nghỉ dưỡng và khám phá.', 'image' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Visa công tác', 'summary' => 'Hỗ trợ hồ sơ công tác, thương mại, ký kết hợp đồng và tham dự sự kiện.', 'image' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Popular visa services', 'subtitle' => 'Featured visas', 'description' => '', 'button_label' => 'View details', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'logistics_feature_panel',
                'label' => 'Ưu đãi và thống kê',
                'description' => 'Khối CTA kèm thống kê RouteX.',
                'preview_image' => '/theme-previews/XD0313/promo.png',
                'anchor_id' => 'uu-dai',
                'settings' => ['cta_url' => '#footer'],
                'media' => [
                    'image' => 'https://images.unsplash.com/photo-1502685104226-ee32379fefbe?auto=format&fit=crop&w=900&q=85',
                    'card_image' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=900&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Nhận ưu đãi tốt nhất của chúng tôi một cách nhanh chóng',
                        'subtitle' => '',
                        'description' => 'Đừng ngần ngại liên hệ RouteX qua hotline hoặc email để được tư vấn về các chương trình ưu đãi hiện tại.',
                        'button_label' => 'Liên hệ ngay',
                        'content' => ['stats' => [
                            ['value' => '17+', 'label' => 'Năm kinh nghiệm'],
                            ['value' => '99,8%', 'label' => 'Khách hàng hài lòng'],
                            ['value' => '24/7', 'label' => 'Tư vấn miễn phí'],
                            ['value' => '98,6%', 'label' => 'Tỷ lệ đậu visa'],
                        ]],
                    ],
                    'en' => ['title' => 'Get our best offer quickly', 'subtitle' => '', 'description' => 'Contact RouteX for the latest visa consulting offers.', 'button_label' => 'Contact now', 'content' => []],
                ],
            ],
            [
                'block_type' => 'process_steps',
                'label' => 'Quy trình visa',
                'description' => 'Các bước làm visa tại RouteX.',
                'preview_image' => '/theme-previews/XD0313/process.png',
                'anchor_id' => 'quy-trinh',
                'settings' => [],
                'media' => ['image' => 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=900&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Các bước làm Visa tại RouteX',
                        'subtitle' => 'Quy trình tư vấn',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Đăng ký (1 phút)', 'summary' => 'Điền biểu mẫu thông tin đơn giản, nhanh chóng. Mọi dữ liệu đều được bảo mật an toàn.'],
                            ['title' => 'Tư vấn', 'summary' => 'Chuyên viên liên hệ lại trong vòng 4 giờ và tư vấn lộ trình phù hợp với hồ sơ.'],
                            ['title' => 'Hoàn thiện hồ sơ (2–3 ngày)', 'summary' => 'Chuyên viên giàu kinh nghiệm đồng hành và hỗ trợ bạn trong suốt quá trình.'],
                            ['title' => 'Nhận Visa', 'summary' => 'Khách hàng nhận Visa trực tiếp hoặc qua dịch vụ chuyển phát tận tay.'],
                        ]],
                    ],
                    'en' => ['title' => 'RouteX visa process', 'subtitle' => 'Consulting process', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonials',
                'label' => 'Đánh giá khách hàng',
                'description' => 'Khối cảm nhận khách hàng nền xanh.',
                'preview_image' => '/theme-previews/XD0313/testimonials.png',
                'anchor_id' => 'danh-gia',
                'settings' => ['source' => 'custom', 'limit' => 3],
                'media' => ['image' => 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=900&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Đánh giá',
                        'subtitle' => '',
                        'description' => 'Tôi là chủ doanh nghiệp, không có thời gian tìm hiểu thủ tục phức tạp. RouteX đã hỗ trợ mọi công đoạn, tôi chỉ cần đến và nộp hồ sơ.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Trần Minh Hoàng', 'role' => 'Giám đốc công ty ABC', 'quote' => 'Tôi không có thời gian tìm hiểu thủ tục phức tạp. RouteX đã hỗ trợ từ dịch thuật, công chứng đến đặt lịch hẹn. Dịch vụ rất tiện lợi và chuyên nghiệp.', 'avatar' => 'https://images.unsplash.com/photo-1502685104226-ee32379fefbe?auto=format&fit=crop&w=300&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Testimonials', 'subtitle' => '', 'description' => 'RouteX made the process simple and professional.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'bizmax_latest_posts',
                'label' => 'Blog gần đây',
                'description' => 'Bài viết RouteX dạng thẻ.',
                'preview_image' => '/theme-previews/XD0313/blog.png',
                'anchor_id' => 'blog',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $contentSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Một số bài viết của chúng tôi',
                        'subtitle' => 'Blog gần đây',
                        'description' => '',
                        'button_label' => 'Xem chi tiết',
                        'content' => ['items' => [
                            ['title' => 'Học bổng du học Đức lên tới 100% học phí', 'summary' => 'Thông tin học bổng và cơ hội học tập tại các trường đại học uy tín của Đức.', 'image' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 238],
                            ['title' => 'Vương quốc Anh: Các trường đại học giữ chỗ cho sinh viên', 'summary' => 'Du học sinh vẫn có thể bảo toàn cơ hội ghi danh khi chuẩn bị hồ sơ đúng lộ trình.', 'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 138],
                            ['title' => 'Cập nhật các chính sách visa mới nhất', 'summary' => 'Thông tin cập nhật về những thay đổi chính sách và lưu ý quan trọng cho hồ sơ visa.', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 192],
                            ['title' => 'Sôi động hội thảo du học và cuộc sống quốc tế', 'summary' => 'Buổi hội thảo về du học và cuộc sống ở nước ngoài thu hút nhiều bạn trẻ tham dự.', 'image' => 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b?auto=format&fit=crop&w=900&q=85', 'date' => '06/08/2025', 'views' => 920],
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
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
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
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Header và hero Athletic',
                'description' => 'Header đen, đăng nhập/đăng ký và hero slider toàn màn hình.',
                'preview_image' => '/theme-previews/XD0315/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0315-hero-slider', 'limit' => 3, 'autoplay_ms' => 6200],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Hàng đầu Việt Nam',
                        'subtitle' => 'Tập luyện cùng các chuyên gia thể hình',
                        'description' => 'Không gian tập luyện hiện đại dành cho người yêu thể thao.',
                        'button_label' => 'Đăng ký tập thử',
                        'content' => ['slides' => [
                            ['kicker' => 'Tập luyện cùng các chuyên gia thể hình', 'title' => 'Hàng đầu Việt Nam', 'button_label' => 'Đăng ký tập thử', 'image' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#lop-tap'],
                            ['kicker' => 'Sức mạnh, kỷ luật và năng lượng mỗi ngày', 'title' => 'Athletic Fitness', 'button_label' => 'Khám phá lớp tập', 'image' => 'https://images.unsplash.com/photo-1549060279-7e168fcee0c2?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#lop-tap'],
                        ]],
                    ],
                    'en' => ['title' => 'Vietnam leading fitness center', 'subtitle' => 'Train with professional coaches', 'description' => 'Modern fitness experiences for active members.', 'button_label' => 'Start trial', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Lớp tập và dịch vụ',
                'description' => 'Mosaic lớp tập, có thể lấy từ tin tức/sản phẩm/dịch vụ/dự án hoặc nhập tay.',
                'preview_image' => '/theme-previews/XD0315/classes.png',
                'anchor_id' => 'lop-tap',
                'dynamic' => true,
                'settings' => ['source' => 'custom', 'limit' => 6, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $multiSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Trung tâm thể dục Athletic!',
                        'subtitle' => '',
                        'description' => 'Các dịch vụ luyện tập và những cách giảm cân hiệu quả. Chúng tôi sẽ giúp bạn.',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Huấn luyện cá nhân', 'summary' => 'Huấn luyện viên cá nhân đánh giá chỉ số cơ thể và xây dựng định hướng tập luyện riêng.', 'image' => 'https://images.unsplash.com/photo-1534367610401-9f5ed68180aa?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Yoga', 'summary' => 'Hơn 50 lớp yoga từ cơ bản đến nâng cao cho nhiều mục tiêu tập luyện.', 'image' => 'https://images.unsplash.com/photo-1599901860904-17e6ed7083a0?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Giảm cân', 'summary' => 'Hệ thống bài tập chuyên sâu giúp giảm mỡ và cải thiện sức bền.', 'image' => 'https://images.unsplash.com/photo-1605296867304-46d5465a13f1?auto=format&fit=crop&w=1200&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Dance', 'summary' => 'Giải phóng năng lượng, tăng sự linh hoạt và giúp lớp tập luôn đầy hứng khởi.', 'image' => 'https://images.unsplash.com/photo-1524594152303-9fd13543fe6e?auto=format&fit=crop&w=1200&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Giảm căng cơ', 'summary' => 'Liệu pháp giãn cơ thư giãn sau khi tập luyện cường độ cao.', 'image' => 'https://images.unsplash.com/photo-1599058917212-d750089bc07e?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                            ['title' => 'Kickfit', 'summary' => 'Kết hợp võ thuật và cardio với các động tác mạnh mẽ.', 'image' => 'https://images.unsplash.com/photo-1517438322307-e67111335449?auto=format&fit=crop&w=900&q=85', 'url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Athletic fitness center', 'subtitle' => '', 'description' => 'Training services and effective weight control programs.', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu và video',
                'description' => 'Khối giới thiệu nền đen với hình ảnh lớn và video.',
                'preview_image' => '/theme-previews/XD0315/about-video.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => ['cta_url' => '#lop-tap', 'video_url' => '#video'],
                'media' => [
                    'image' => 'https://images.unsplash.com/photo-1574680096145-d05b474e2155?auto=format&fit=crop&w=1200&q=85',
                    'video_image' => 'https://images.unsplash.com/photo-1518611012118-696072aa579a?auto=format&fit=crop&w=1200&q=85',
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Chúng tôi tạo ra sự khác biệt',
                        'subtitle' => 'But I must explain to you how all this mistaken idea denouncing pleasure and praising pain was born.',
                        'description' => 'Chúng tôi muốn chứng minh rằng để có được cuộc sống tốt và lành mạnh hơn, bạn không nhất thiết phải hy sinh quá nhiều. Chỉ cần đưa vào lối sống của mình những thói quen giúp nâng cao chất lượng sống.',
                        'button_label' => 'Xem thêm',
                        'content' => [],
                    ],
                    'en' => ['title' => 'We make the difference', 'subtitle' => 'Train smarter every day.', 'description' => 'Healthy habits and practical coaching for a stronger lifestyle.', 'button_label' => 'More', 'content' => []],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Gặp gỡ chuyên gia',
                'description' => 'Danh sách huấn luyện viên với tên và chức danh.',
                'preview_image' => '/theme-previews/XD0315/trainers.png',
                'anchor_id' => 'chuyen-gia',
                'dynamic' => true,
                'settings' => ['source' => 'cms_team_members', 'limit' => 4, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_team_members', 'label' => 'Đội ngũ']]],
                    'limit' => ['type' => 'number', 'label' => 'Số nhân sự'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Gặp gỡ chuyên gia',
                        'subtitle' => '',
                        'description' => 'Các chuyên gia hàng đầu của Athletic đã sẵn sàng để cùng bạn tập luyện, vươn tới thân hình săn chắc và lối sống khỏe mạnh.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Brad Trần', 'role' => 'Huấn luyện viên tạ', 'image' => 'https://images.unsplash.com/photo-1571731956672-f2b94d7dd0cb?auto=format&fit=crop&w=800&q=85'],
                            ['name' => 'Raymond L. Brown', 'role' => 'Huấn luyện viên quyền anh', 'image' => 'https://images.unsplash.com/photo-1581009146145-b5ef050c2e1e?auto=format&fit=crop&w=800&q=85'],
                            ['name' => 'Tiểu Phương', 'role' => 'Chuyên gia thể hình', 'image' => 'https://images.unsplash.com/photo-1603988363607-e1e4a66962c6?auto=format&fit=crop&w=800&q=85'],
                            ['name' => 'Solomon K. Sawyers', 'role' => 'Huấn luyện viên làm đẹp', 'image' => 'https://images.unsplash.com/photo-1532384748853-8f54a8f476e2?auto=format&fit=crop&w=800&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Meet the experts', 'subtitle' => '', 'description' => 'Professional coaches ready to guide your fitness journey.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'project_gallery',
                'label' => 'Câu lạc bộ',
                'description' => 'Thư viện cơ sở/câu lạc bộ với nhãn cam trên ảnh.',
                'preview_image' => '/theme-previews/XD0315/clubs.png',
                'anchor_id' => 'cau-lac-bo',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $multiSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Câu lạc bộ',
                        'subtitle' => '',
                        'description' => 'Hệ thống phòng tập tiêu chuẩn 5 sao với trang thiết bị, máy móc tập luyện nhập khẩu từ các thương hiệu hàng đầu thế giới.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Athletic Fitness Center 3 Tháng 2, Quận 10', 'image' => 'https://images.unsplash.com/photo-1558611848-73f7eb4001a1?auto=format&fit=crop&w=1200&q=85'],
                            ['title' => 'Athletic Fitness Center Thiên Sơn Plaza, Quận 7', 'image' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1200&q=85'],
                            ['title' => 'Athletic Fitness Center Hồ Xuân Hương, Quận 3', 'image' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Athletic Fitness Center Hoàng Sa, Quận 3', 'image' => 'https://images.unsplash.com/photo-1576678927484-cc907957088c?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Athletic Fitness Center Phạm Văn Hai, Quận Tân Bình', 'image' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Clubs', 'subtitle' => '', 'description' => 'Premium fitness clubs with modern imported equipment.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Tin tức sự kiện',
                'description' => 'Danh sách tin tức/sự kiện 3 cột.',
                'preview_image' => '/theme-previews/XD0315/news.png',
                'anchor_id' => 'tin-tuc',
                'dynamic' => true,
                'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $multiSources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Tin tức sự kiện',
                        'subtitle' => '',
                        'description' => '',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Sở hữu body chuẩn không khó nếu nắm được bí quyết dinh dưỡng khi tập gym này', 'summary' => 'Nếu chỉ tập gym mà không ăn uống hợp lý thì cơ thể sẽ không có những thay đổi đáng kể.', 'image' => 'https://images.unsplash.com/photo-1583454110551-21f2fa2afe61?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Không ăn kiêng vẫn có body săn chắc nhờ chế độ ăn tăng cơ giảm mỡ này!', 'summary' => 'Ăn uống theo chế độ tăng cơ giảm mỡ giúp nam giới có thân hình quyến rũ hơn.', 'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Thực đơn cho người mới tập gym có nên sử dụng nhiều trứng?', 'summary' => 'Thực đơn cho người mới tập gym nên bắt đầu với khẩu phần ăn hợp lý và đa dạng.', 'image' => 'https://images.unsplash.com/photo-1490645935967-10de6ba17061?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'News and events', 'subtitle' => '', 'description' => '', 'button_label' => 'More', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'testimonials',
                'label' => 'Câu chuyện thành công',
                'description' => 'Câu chuyện trước/sau với chỉ số cơ thể.',
                'preview_image' => '/theme-previews/XD0315/success-stories.png',
                'anchor_id' => 'cau-chuyen',
                'settings' => ['source' => 'custom', 'limit' => 4],
                'media' => ['background' => 'https://images.unsplash.com/photo-1534258936925-c58bed479fcb?auto=format&fit=crop&w=1800&q=80'],
                'data' => [
                    'vi' => [
                        'title' => 'Câu chuyện thành công',
                        'subtitle' => '',
                        'description' => 'Khám phá phương pháp đã giúp thay đổi cuộc sống của hàng trăm nghìn người tại Việt Nam.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['name' => 'Bùi Quốc Thái', 'image' => 'https://images.unsplash.com/photo-1532384748853-8f54a8f476e2?auto=format&fit=crop&w=700&q=85', 'before_weight' => '100kg', 'after_weight' => '77kg', 'before_muscle' => '40.5kg', 'after_muscle' => '47.2kg', 'before_fat' => '25%', 'after_fat' => '14.3%'],
                            ['name' => 'Nguyễn Hữu Trọng', 'image' => 'https://images.unsplash.com/photo-1571731956672-f2b94d7dd0cb?auto=format&fit=crop&w=700&q=85', 'before_weight' => '62kg', 'after_weight' => '61.5kg', 'before_muscle' => '29.3kg', 'after_muscle' => '31.2kg', 'before_fat' => '25%', 'after_fat' => '20.3%'],
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
        $quality['dynamic'] = false;
        $quality['settings'] = ['source' => 'custom', 'limit' => 5];
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
        $projects['dynamic'] = false;
        $projects['settings'] = ['source' => 'custom', 'limit' => 8, 'featured_only' => true];
        $projects['data']['vi'] = ['title' => 'Một số dự án đã thực hiện cho khách hàng', 'subtitle' => 'Dự án tiêu biểu', 'description' => 'Các dự án chuyên ngành tiêu biểu đã giúp XD0320 khẳng định vị thế và năng lực triển khai.', 'button_label' => 'Tất cả dự án', 'content' => ['items' => [
            ['title' => 'Nhà máy sản xuất tự động', 'summary' => 'Tích hợp dây chuyền và hệ thống điều khiển vận hành.', 'image' => 'https://images.unsplash.com/photo-1565793298595-6a879b1d9492?auto=format&fit=crop&w=1100&q=85'],
            ['title' => 'Trung tâm gia công cơ khí', 'summary' => 'Nâng cấp thiết bị và tối ưu quy trình kiểm soát chất lượng.', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1100&q=85'],
            ['title' => 'Hệ thống năng lượng nhà xưởng', 'summary' => 'Giải pháp cấp năng lượng an toàn và tiết kiệm.', 'image' => 'https://images.unsplash.com/photo-1508514177221-188b1cf16e9d?auto=format&fit=crop&w=1100&q=85'],
            ['title' => 'Kho vận công nghiệp thông minh', 'summary' => 'Số hóa vận hành kho và luồng hàng trong nhà máy.', 'image' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1100&q=85'],
        ]]];

        $team = $industrial->get('team_members');
        $team['label'] = 'Đội ngũ kỹ sư';
        $team['description'] = 'Đội ngũ đồng nhất layout, gồm hình, tên và chức danh.';
        $team['dynamic'] = false;
        $team['settings'] = ['source' => 'custom', 'limit' => 4];
        $team['data']['vi'] = ['title' => 'Đội ngũ kỹ sư có năng lực', 'subtitle' => 'Thành viên chuyên gia', 'description' => 'Những con người tâm huyết, kinh nghiệm và cùng hướng về kết quả cho khách hàng.', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'Anwar Ramadan', 'role' => 'Giám đốc nhân sự', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Osama Bakri', 'role' => 'Giám đốc điều hành', 'image' => 'https://images.unsplash.com/photo-1531123897727-8f129e1688ce?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Sana El-Shamy', 'role' => 'Kỹ sư xây dựng', 'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Jackson Ckumanni', 'role' => 'Quản lý dự án', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=700&q=85'],
        ]]];

        $partners = $logistics->get('partner_logos');
        $partners['label'] = 'Đối tác';
        $partners['description'] = 'Danh sách logo đối tác từ CMS Partners.';
        $partners['dynamic'] = false;
        $partners['settings'] = ['source' => 'custom', 'limit' => 5];
        $partners['data']['vi'] = ['title' => 'Đối tác của chúng tôi', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'Apex Industry'],
            ['name' => 'NovaTech'],
            ['name' => 'Core Manufacturing'],
            ['name' => 'Prime Engineering'],
            ['name' => 'VietWorks'],
        ]]];

        return [$hero, $quality, $about, $feature, $projects, $team, $partners];
    }

    private function xd0322DefaultBlocks(): array
    {
        $base = collect($this->xd321DefaultBlocks())->keyBy('block_type');
        $industrial = collect($this->xd0320DefaultBlocks())->keyBy('block_type');

        $hero = $base->get('hero_slider');
        $hero['label'] = 'Header và hero XD0322';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'xd0322-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Cung cấp giải pháp xây dựng tốt nhất', 'subtitle' => 'Chất lượng - An toàn - Hiệu quả - Chuyên nghiệp', 'description' => 'Thiết kế và thi công trọn gói cho các công trình dân dụng và thương mại.', 'button_label' => 'Liên hệ báo giá', 'content' => ['slides' => [['title' => 'Cung cấp giải pháp xây dựng tốt nhất', 'summary' => 'Dịch vụ thi công trọn gói với chiến lược linh hoạt trong quá trình vận hành và phát triển.', 'button_label' => 'Liên hệ báo giá', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#lien-he']]]];

        $quality = $base->get('featured_categories');
        $quality['label'] = 'Cam kết XD0322';
        $quality['dynamic'] = false;
        $quality['settings'] = ['source' => 'custom', 'limit' => 4];
        $quality['data']['vi'] = ['title' => 'Cam kết chất lượng', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['title' => 'Cam kết chất lượng', 'summary' => 'Kiểm soát kỹ từng hạng mục.', 'icon' => 'fa-regular fa-lightbulb'], ['title' => 'Đúng tiến độ', 'summary' => 'Bảo đảm kế hoạch thi công.', 'icon' => 'fa-solid fa-hand-holding-heart'], ['title' => 'Tận tâm với khách hàng', 'summary' => 'Đồng hành trong từng giai đoạn.', 'icon' => 'fa-solid fa-trowel-bricks'], ['title' => 'Bảo hành dài hạn', 'summary' => 'Chính sách sau bàn giao rõ ràng.', 'icon' => 'fa-solid fa-helmet-safety']]]];

        $about = $base->get('about_experience');
        $about['data']['vi'] = ['title' => 'Chúng tôi dẫn đầu trong lĩnh vực xây dựng', 'subtitle' => 'Về chúng tôi', 'description' => 'XD0322 là đơn vị thiết kế và thi công trọn gói. Chúng tôi giữ vững cam kết về chất lượng, an toàn, hiệu quả và tính chuyên nghiệp trong mỗi công trình.', 'button_label' => 'Xem thêm', 'content' => ['years' => '18+', 'years_label' => 'Năm trong nghề', 'items' => []]];

        $services = $base->get('content_mosaic');
        $services['dynamic'] = false;
        $services['settings'] = ['source' => 'custom', 'limit' => 6];
        $services['data']['vi'] = ['title' => 'Dịch vụ tốt nhất cho bạn', 'subtitle' => 'Dịch vụ của chúng tôi', 'description' => 'Giải pháp thiết kế, thi công và hoàn thiện phù hợp với mọi nhu cầu.', 'button_label' => '', 'content' => ['items' => [
            ['title' => 'Thiết kế kiến trúc', 'summary' => 'Tối ưu công năng, thẩm mỹ và ngân sách ngay từ bản vẽ.'],
            ['title' => 'Thi công nhà phố', 'summary' => 'Quản lý đồng bộ chất lượng, vật tư và tiến độ công trình.'],
            ['title' => 'Xây dựng biệt thự', 'summary' => 'Kiến tạo không gian sống cao cấp theo phong cách riêng.'],
            ['title' => 'Nội thất trọn gói', 'summary' => 'Thiết kế và hoàn thiện nội thất thống nhất với kiến trúc.'],
            ['title' => 'Cải tạo công trình', 'summary' => 'Nâng cấp không gian cũ an toàn, hiệu quả và tiết kiệm.'],
            ['title' => 'Tư vấn giám sát', 'summary' => 'Kiểm soát kỹ thuật và chất lượng trong suốt quá trình thi công.'],
        ]]];

        $projects = $base->get('project_gallery');
        $projects['dynamic'] = false;
        $projects['settings'] = ['source' => 'custom', 'limit' => 6];
        $projects['data']['vi'] = ['title' => 'Dự án của chúng tôi', 'subtitle' => 'Công trình tiêu biểu', 'description' => 'Những công trình chung cư, biệt thự, nhà phố và văn phòng đã hoàn thiện.', 'button_label' => '', 'content' => ['items' => [
            ['title' => 'Biệt thự hiện đại ven đô', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=85'],
            ['title' => 'Nhà phố tối ưu không gian', 'image' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1000&q=85'],
            ['title' => 'Văn phòng sáng tạo', 'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1000&q=85'],
            ['title' => 'Khu căn hộ cao cấp', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1000&q=85'],
            ['title' => 'Không gian nghỉ dưỡng', 'image' => 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1000&q=85'],
            ['title' => 'Showroom thương mại', 'image' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1000&q=85'],
        ]]];

        $products = $base->get('featured_products');
        $products['dynamic'] = false;
        $products['settings'] = ['source' => 'custom', 'limit' => 4];
        $products['data']['vi'] = ['title' => 'Sản phẩm của chúng tôi', 'subtitle' => 'Nội thất và vật liệu hoàn thiện', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['title' => 'Bộ sofa phòng khách', 'price' => 'Liên hệ', 'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Bàn ăn gỗ tự nhiên', 'price' => 'Liên hệ', 'image' => 'https://images.unsplash.com/photo-1617806118233-18e1de247200?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Tủ bếp hiện đại', 'price' => 'Liên hệ', 'image' => 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Đèn trang trí cao cấp', 'price' => 'Liên hệ', 'image' => 'https://images.unsplash.com/photo-1524484485831-a92ffc0de03f?auto=format&fit=crop&w=900&q=85'],
        ]]];

        $team = $industrial->get('team_members');
        $team['dynamic'] = false;
        $team['settings'] = ['source' => 'custom', 'limit' => 4];
        $team['data']['vi'] = ['title' => 'Đội ngũ của chúng tôi', 'subtitle' => 'Tận tâm, chuyên nghiệp', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'Nguyễn Minh Hoàng', 'role' => 'Kiến trúc sư trưởng', 'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Trần Quốc Huy', 'role' => 'Kỹ sư xây dựng', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Lê Thu Hà', 'role' => 'Kiến trúc sư nội thất', 'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=700&q=85'],
            ['name' => 'Phạm Anh Tuấn', 'role' => 'Quản lý dự án', 'image' => 'https://images.unsplash.com/photo-1531384441138-2736e62e0919?auto=format&fit=crop&w=700&q=85'],
        ]]];

        $testimonials = $base->get('testimonials');
        $testimonials['dynamic'] = false;
        $testimonials['settings'] = ['source' => 'custom', 'limit' => 2];
        $testimonials['data']['vi'] = ['title' => 'Nhận xét của khách hàng', 'subtitle' => 'Phản hồi sau thi công', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'Anh Minh', 'role' => 'Chủ nhà tại Hà Nội', 'quote' => 'Đội ngũ làm việc chuyên nghiệp, bám sát thiết kế và bàn giao đúng tiến độ.'],
            ['name' => 'Chị Lan', 'role' => 'Chủ doanh nghiệp', 'quote' => 'Quy trình rõ ràng, vật liệu minh bạch và chất lượng hoàn thiện rất tốt.'],
        ]]];

        $pricing = $base->get('process_steps');
        $pricing['label'] = 'Báo giá thi công';
        $pricing['data']['vi'] = ['title' => 'Báo giá thi công', 'subtitle' => 'Gói dịch vụ', 'description' => 'Các gói thi công linh hoạt phù hợp với quy mô và ngân sách công trình.', 'button_label' => 'Đăng ký ngay', 'content' => ['items' => [['title' => 'Gói cơ bản', 'summary' => '280.000đ/m²'], ['title' => 'Gói nâng cao', 'summary' => '350.000đ/m²'], ['title' => 'Gói cao cấp', 'summary' => '500.000đ/m²']]]];

        $partners = $base->get('partner_logos');
        $partners['dynamic'] = false;
        $partners['settings'] = ['source' => 'custom', 'limit' => 5];
        $partners['data']['vi'] = ['title' => 'Đối tác của chúng tôi', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['name' => 'VietBuild'], ['name' => 'An Cường'], ['name' => 'Hòa Phát'], ['name' => 'Dulux'], ['name' => 'Viglacera'],
        ]]];
        $news = $base->get('bizmax_latest_posts');
        $news['dynamic'] = false;
        $news['settings'] = ['source' => 'custom', 'limit' => 3];
        $news['data']['vi'] = ['title' => 'Bài viết mới nhất', 'subtitle' => 'Tin tức và cập nhật', 'description' => '', 'button_label' => '', 'content' => ['items' => [
            ['title' => '5 lưu ý khi lập ngân sách xây nhà', 'summary' => 'Những khoản chi quan trọng cần chuẩn bị trước khi khởi công.', 'published_at' => '24/07/2026', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Xu hướng kiến trúc bền vững', 'summary' => 'Tối ưu ánh sáng, thông gió và vật liệu thân thiện với môi trường.', 'published_at' => '18/07/2026', 'image' => 'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=900&q=85'],
            ['title' => 'Quy trình giám sát công trình hiệu quả', 'summary' => 'Các mốc kiểm tra giúp bảo đảm chất lượng và tiến độ thi công.', 'published_at' => '10/07/2026', 'image' => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=900&q=85'],
        ]]];

        return [$hero, $quality, $about, $services, $products, $team, $projects, $pricing, $testimonials, $news, $partners];
    }

    private function xd321DefaultBlocks(): array
    {
        $industrial = collect($this->xd0320DefaultBlocks())->keyBy('block_type');
        $interior = collect($this->xd0314DefaultBlocks())->keyBy('block_type');
        $foot = collect($this->foot401DefaultBlocks())->keyBy('block_type');
        $logistics = collect($this->xd0312DefaultBlocks())->keyBy('block_type');

        $hero = $industrial->get('hero_slider');
        $hero['label'] = 'Header và hero logistics XD321';
        $hero['description'] = 'Hero slider cho dịch vụ vận chuyển và logistics.';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'xd321-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Nhanh chóng, an toàn cùng XD321 Cargo', 'subtitle' => 'XD321 Cargo', 'description' => 'Tối ưu mọi hành trình vận chuyển từ nội địa đến quốc tế.', 'button_label' => 'Xem thêm', 'content' => ['slides' => [['title' => 'Nhanh chóng, an toàn cùng XD321 Cargo', 'summary' => 'Tối ưu mọi hành trình vận chuyển từ nội địa đến quốc tế. An toàn và tiết kiệm.', 'button_label' => 'Xem thêm', 'image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#dich-vu']]]];

        $quality = $industrial->get('featured_categories');
        $quality['label'] = 'Cam kết dịch vụ';
        $quality['data']['vi'] = ['title' => 'Cam kết XD321 Cargo', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['title' => 'Đúng tiến độ', 'summary' => 'Theo dõi đơn hàng liên tục.', 'icon' => 'fa-solid fa-clock'], ['title' => 'An toàn hàng hóa', 'summary' => 'Quy trình minh bạch.', 'icon' => 'fa-solid fa-shield-halved'], ['title' => 'Kết nối toàn cầu', 'summary' => 'Mạng lưới quốc tế.', 'icon' => 'fa-solid fa-globe'], ['title' => 'Chi phí tối ưu', 'summary' => 'Báo giá rõ ràng.', 'icon' => 'fa-solid fa-tags']]]];

        $about = $industrial->get('about_experience');
        $about['label'] = 'Giới thiệu XD321 Cargo';
        $about['media'] = ['image' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1100&q=85'];
        $about['data']['vi'] = ['title' => 'Đối tác logistics tin cậy cho doanh nghiệp', 'subtitle' => 'Giới thiệu về XD321 Cargo', 'description' => 'Chúng tôi kết nối thương mại toàn cầu bằng giải pháp vận chuyển đáng tin cậy, minh bạch và linh hoạt. Đội ngũ chuyên nghiệp hỗ trợ từ kho bãi đến giao nhận cuối cùng.', 'button_label' => 'Tư vấn ngay', 'content' => ['years' => '15+', 'years_label' => 'Năm kinh nghiệm', 'items' => [['title' => 'Vận tải đa phương thức'], ['title' => 'Quy trình kiểm soát chặt chẽ'], ['title' => 'Dịch vụ linh hoạt cho từng lô hàng'], ['title' => 'Mạng lưới đối tác toàn cầu']]]];

        $services = $interior->get('content_mosaic');
        $services['label'] = 'Dịch vụ vận chuyển';
        $services['anchor_id'] = 'dich-vu';
        $services['description'] = 'Lấy từ tin tức, sản phẩm, dịch vụ, dự án hoặc nhập tay.';
        $services['settings'] = ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true];
        $services['data']['vi'] = ['title' => 'Dịch vụ vận chuyển', 'subtitle' => 'Kết nối vận tải toàn cầu', 'description' => 'Giải pháp linh hoạt cho hàng hóa đường biển, hàng không và đường bộ.', 'button_label' => '', 'content' => ['items' => []]];

        $solutions = $interior->get('content_mosaic');
        $solutions['block_type'] = 'project_gallery';
        $solutions['label'] = 'Giải pháp logistics';
        $solutions['anchor_id'] = 'giai-phap';
        $solutions['settings'] = ['source' => 'cms_projects', 'limit' => 3, 'featured_only' => true];
        $solutions['data']['vi'] = ['title' => 'Giải pháp logistics hiện đại', 'subtitle' => 'Giải pháp', 'description' => 'Từ lưu kho, xử lý đơn hàng đến điều phối chuỗi cung ứng.', 'button_label' => '', 'content' => ['items' => []]];

        $process = $logistics->get('process_steps');
        $process['label'] = 'Quy trình vận hành';
        $process['anchor_id'] = 'quy-trinh';
        $process['data']['vi'] = ['title' => 'Quy trình đảm bảo vận hành logistics xuyên suốt', 'subtitle' => 'Quy trình làm việc', 'description' => 'Mỗi bước đều được kiểm soát chặt chẽ để đảm bảo tiến độ và tính minh bạch.', 'button_label' => 'Liên hệ ngay', 'content' => ['items' => [['title' => 'Tiếp nhận và lên kế hoạch', 'summary' => 'Phân tích chi tiết lô hàng và xây dựng phương án tối ưu.'], ['title' => 'Điều phối và triển khai', 'summary' => 'Tổ chức vận chuyển, xử lý chứng từ và tối ưu lộ trình.'], ['title' => 'Theo dõi và cập nhật', 'summary' => 'Cập nhật trạng thái liên tục trong thời gian thực.'], ['title' => 'Giao hàng và tối ưu', 'summary' => 'Đánh giá kết quả để nâng cao chất lượng dịch vụ.']]]];

        $products = $foot->get('featured_products');
        $products['label'] = 'Sản phẩm logistics';
        $products['anchor_id'] = 'san-pham';
        $products['data']['vi']['title'] = 'Sản phẩm hỗ trợ logistics';
        $products['data']['vi']['subtitle'] = 'Vật tư đóng gói';

        $testimonials = $industrial->get('team_members');
        $testimonials['block_type'] = 'testimonials';
        $testimonials['label'] = 'Phản hồi khách hàng';
        $testimonials['data']['vi'] = ['title' => 'Khách hàng nói gì về XD321 Cargo', 'subtitle' => 'Phản hồi từ đối tác', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $partners = $industrial->get('partner_logos');
        $partners['label'] = 'Mạng lưới đối tác';
        $news = $foot->get('bizmax_latest_posts');
        $news['label'] = 'Tin tức logistics';
        $news['anchor_id'] = 'tin-tuc';
        $news['settings'] = ['source' => 'cms_posts', 'limit' => 6, 'featured_only' => true];
        $news['data']['vi'] = ['title' => 'Cập nhật tin tức logistics', 'subtitle' => 'Kiến thức và thị trường', 'description' => '', 'button_label' => 'Xem thêm', 'content' => ['items' => []]];

        return [$hero, $quality, $about, $services, $solutions, $process, $products, $testimonials, $partners, $news];
    }

    /** @return array<int, array<string, mixed>> */
    private function nt504DefaultBlocks(): array
    {
        $preview = '/theme-previews/NT504/nt504.png';
        $heading = fn (?string $title = null, ?string $subtitle = null, ?string $description = null, ?string $button = null): array => [
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_label' => $button,
        ];
        $sourceSchema = fn (array $options, int $limit): array => [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $options],
            'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị', 'default' => $limit],
            'category_id' => ['type' => 'select', 'label' => 'Danh mục lọc'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nội dung nổi bật', 'default' => false],
        ];
        $categories = [['value' => 'catalog_categories', 'label' => 'Danh mục sản phẩm']];
        $products = [['value' => 'cms_products', 'label' => 'Sản phẩm']];
        $posts = [['value' => 'cms_posts', 'label' => 'Tin tức']];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero bộ sưu tập màu', 'description' => 'Banner lớn đầu trang kèm lợi ích dịch vụ.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'nt504-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số slide'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tốc độ tự chạy (ms)']], 'data' => ['vi' => array_merge($heading('Sơn nhà đẹp bắt đầu từ một màu sắc đúng', 'BST MÀU SẮC MỚI 2026', 'Bảng màu thời thượng, bền đẹp vượt trội và thân thiện với môi trường.', 'Khám phá bộ sưu tập'), ['content' => ['slides' => [['title' => 'Sơn nhà đẹp bắt đầu từ một màu sắc đúng', 'summary' => 'Bảng màu thời thượng, bền đẹp vượt trội và thân thiện với môi trường.', 'badge' => 'BST MÀU SẮC MỚI 2026', 'button_label' => 'Khám phá bộ sưu tập', 'image' => '/theme-demo/nt504/hero.png', 'link_url' => '#san-pham']]]]), 'en' => $heading('A beautiful home starts with the right colour', 'NEW COLOUR COLLECTION 2026', 'Modern, durable and environmentally mindful colour solutions.', 'Explore the collection')]],
            ['block_type' => 'nt504_spaces', 'label' => 'Màu sơn theo không gian', 'description' => 'Năm không gian sống dạng ảnh.', 'preview_image' => $preview, 'anchor_id' => 'khong-gian', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 5], 'settings_schema' => $sourceSchema($categories, 5), 'data' => ['vi' => $heading('Không gian sống', 'CHỌN MÀU SƠN THEO', 'Khám phá các bảng màu được chuyên gia phối sẵn cho từng không gian trong nhà.'), 'en' => $heading('Living spaces', 'CHOOSE PAINT BY', 'Explore expert-curated palettes for every room.')]],
            ['block_type' => 'nt504_product_categories', 'label' => 'Danh mục sản phẩm lớn', 'description' => 'Bốn nhóm sơn chủ lực.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 4], 'settings_schema' => $sourceSchema($categories, 4), 'data' => ['vi' => $heading('Sản phẩm chất lượng cho mọi công trình', 'DANH MỤC SẢN PHẨM'), 'en' => $heading('Quality products for every project', 'PRODUCT CATEGORIES')]],
            ['block_type' => 'nt504_premium_promo', 'label' => 'Banner ưu đãi cao cấp', 'description' => 'Banner sản phẩm rộng với ba lợi ích.', 'preview_image' => $preview, 'anchor_id' => 'uu-dai-cao-cap', 'settings' => ['background_image' => '/theme-demo/nt504/promo.png'], 'settings_schema' => ['background_image' => ['type' => 'text', 'label' => 'Ảnh nền']], 'data' => ['vi' => $heading('Sắc màu hoàn hảo', 'ƯU ĐÃI CÓ HẠN', 'Khám phá bộ sưu tập sơn cao cấp với công nghệ tiên tiến, mang đến màu sắc bền đẹp và bảo vệ tối ưu cho mọi công trình.', 'Mua ngay'), 'en' => $heading('Perfect colours', 'LIMITED OFFER', 'Premium paint technology for beautiful, durable protection.', 'Shop now')]],
            ['block_type' => 'nt504_category_rail', 'label' => 'Rail danh mục tròn', 'description' => 'Tám danh mục sản phẩm dạng avatar tròn.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 8], 'settings_schema' => $sourceSchema($categories, 8), 'data' => ['vi' => $heading('Danh mục sơn'), 'en' => $heading('Paint categories')]],
            ['block_type' => 'nt504_sale_products', 'label' => 'Sản phẩm khuyến mãi', 'description' => 'Năm sản phẩm ưu đãi nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'khuyen-mai', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 5, 'featured_only' => true], 'settings_schema' => $sourceSchema($products, 5), 'data' => ['vi' => $heading('Sản phẩm khuyến mãi', 'ƯU ĐÃI NỔI BẬT', 'Chọn lựa những sản phẩm chất lượng với mức giá tốt nhất trong tháng này.'), 'en' => $heading('Promotional products', 'FEATURED OFFERS', 'Quality products at this month’s best prices.')]],
            ['block_type' => 'nt504_service_promos', 'label' => 'Ba banner dịch vụ', 'description' => 'Khuyến mãi, tư vấn màu và giao hàng.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'data' => ['vi' => $heading('Dịch vụ nổi bật'), 'en' => $heading('Featured services')]],
            ['block_type' => 'nt504_latest_news', 'label' => 'Tin tức & kiến thức', 'description' => 'Một bài lớn và ba bài tin mới.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4, 'featured_only' => false], 'settings_schema' => $sourceSchema($posts, 4), 'data' => ['vi' => $heading('Cập nhật tin tức mới nhất', 'TIN TỨC & KIẾN THỨC', 'Khám phá xu hướng, mẹo hay và những kiến thức hữu ích về sơn & không gian sống.'), 'en' => $heading('Latest news and ideas', 'NEWS & KNOWLEDGE', 'Colour trends, practical tips and paint expertise.')]],
            ['block_type' => 'nt504_footer', 'label' => 'Footer Wolf Paint', 'description' => 'Thông tin thương hiệu, sản phẩm, hỗ trợ và liên hệ.', 'preview_image' => $preview, 'anchor_id' => 'footer', 'data' => ['vi' => $heading('Wolf Paint', null, 'Giải pháp sơn cao cấp, bền đẹp, an toàn cho mọi công trình.'), 'en' => $heading('Wolf Paint', null, 'Premium, durable and safe paint solutions for every project.')]],
        ];
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
        $hero['label'] = 'Header và hero NT501';
        $hero['description'] = 'Hero slider cho studio thiết kế nội thất.';
        $hero['settings'] = ['source' => 'site_banners', 'placement' => 'nt501-hero-slider', 'limit' => 3, 'autoplay_ms' => 6500];
        $hero['data']['vi'] = ['title' => 'Không gian đẹp, chất lượng bền vững', 'subtitle' => 'NT501 Interior Studio', 'description' => 'Thiết kế và thi công nội thất cho những ngôi nhà mang dấu ấn riêng.', 'button_label' => 'Khám phá dự án', 'content' => ['slides' => [['title' => 'Không gian đẹp, chất lượng bền vững', 'summary' => 'Đồng hành cùng bạn kiến tạo một không gian sống tinh tế và bền vững.', 'button_label' => 'Khám phá dự án', 'image' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=2200&q=90', 'link_url' => '#du-an']]]];

        $about = $interior->get('about_experience');
        $about['label'] = 'Giới thiệu studio';
        $about['description'] = 'Khối giới thiệu nhanh dùng dữ liệu tùy chỉnh.';
        $about['media'] = ['image' => 'https://images.unsplash.com/photo-1618221195710-dd6b41faaea6?auto=format&fit=crop&w=1200&q=90'];
        $about['data']['vi'] = ['title' => 'Kiến tạo không gian sống đầy cảm hứng', 'subtitle' => 'Giới thiệu về NT501', 'description' => 'NT501 kết hợp tư duy thiết kế tinh tế, vật liệu chất lượng và quy trình thi công kỹ lưỡng để biến mỗi ý tưởng thành một không gian đáng sống.', 'button_label' => 'Xem dịch vụ', 'content' => ['items' => []]];

        $showcase = $interior->get('content_mosaic');
        $showcase['label'] = 'Không gian tiêu biểu';
        $showcase['description'] = 'Lấy từ tin tức, sản phẩm, dịch vụ, dự án hoặc nhập tay.';
        $showcase['anchor_id'] = 'khong-gian';
        $showcase['settings'] = ['source' => 'cms_projects', 'limit' => 2, 'featured_only' => true];
        $showcase['data']['vi'] = ['title' => 'Giải pháp cho mọi phong cách sống', 'subtitle' => 'Không gian tiêu biểu', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $projects = $interior->get('content_mosaic');
        $projects['block_type'] = 'project_gallery';
        $projects['label'] = 'Dự án nổi bật';
        $projects['description'] = 'Dự án hiển thị thành thanh trượt ngang.';
        $projects['anchor_id'] = 'du-an';
        $projects['settings'] = ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => true];
        $projects['data']['vi'] = ['title' => 'Những công trình nổi bật', 'subtitle' => 'Dự án đã thực hiện', 'description' => '', 'button_label' => 'Xem tất cả', 'content' => ['items' => []]];

        $services = $interior->get('featured_services');
        $services['label'] = 'Dịch vụ nội thất';
        $services['description'] = 'Lấy từ nhiều nguồn dữ liệu hoặc nhập tay.';
        $services['anchor_id'] = 'dich-vu';
        $services['settings'] = ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true];
        $services['data']['vi'] = ['title' => 'Đồng hành từ ý tưởng đến hoàn thiện', 'subtitle' => 'Dịch vụ của chúng tôi', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $testimonials = $legacy->get('testimonials');
        $testimonials['label'] = 'Cảm nhận khách hàng';
        $testimonials['description'] = 'Phản hồi khách hàng và hình ảnh đại diện.';
        $testimonials['data']['vi'] = ['title' => 'Điều khách hàng nói về chúng tôi', 'subtitle' => 'Cảm nhận khách hàng', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $posts = $foot->get('bizmax_latest_posts');
        $posts['label'] = 'Bài viết nội thất';
        $posts['description'] = 'Lấy từ tin tức, sản phẩm, dịch vụ, dự án hoặc nhập tay.';
        $posts['settings'] = ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => true];
        $posts['data']['vi'] = ['title' => 'Bài viết gần đây', 'subtitle' => 'Tin tức và cập nhật', 'description' => '', 'button_label' => '', 'content' => ['items' => []]];

        $partnerLogos = $partners->get('partner_logos');
        $partnerLogos['label'] = 'Đối tác NT501';

        $stats = $interior->get('featured_categories');
        $stats['label'] = 'Chỉ số năng lực';
        $stats['description'] = 'Ba chỉ số năng lực dùng dữ liệu tùy chỉnh.';
        $stats['data']['vi'] = ['title' => 'Năng lực NT501', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['title' => '10+', 'summary' => 'Năm làm việc'], ['title' => '20', 'summary' => 'Chuyên gia nội thất'], ['title' => '1000', 'summary' => 'Dự án tiềm năng']]]];

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
            ['block_type' => 'landing_contact', 'label' => 'Đặt bàn nhanh', 'description' => 'Khối liên hệ và đặt bàn do FOOT403 thiết kế.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => ['source' => 'custom'], 'data' => ['vi' => ['title' => 'Đặt bàn cho một bữa ăn đáng nhớ', 'subtitle' => 'Liên hệ với Dola', 'description' => 'Để lại thông tin, đội ngũ nhà hàng sẽ liên hệ xác nhận trong thời gian sớm nhất.', 'button_label' => 'Gửi yêu cầu đặt bàn', 'content' => ['address' => '70 Lữ Gia, Phường 15, Quận 11, TP.HCM', 'phone' => '0399162342', 'email' => 'support@htvietnam.vn', 'hours' => '10:00 – 22:30, tất cả các ngày']]]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function xd0314DefaultBlocks(): array
    {
        $sources = [
            ['value' => 'custom', 'label' => 'Nhập thủ công'],
            ['value' => 'cms_posts', 'label' => 'Tin tức'],
            ['value' => 'cms_products', 'label' => 'Sản phẩm'],
            ['value' => 'cms_services', 'label' => 'Dịch vụ'],
            ['value' => 'cms_projects', 'label' => 'Dự án'],
        ];

        return [
            [
                'block_type' => 'hero_slider',
                'label' => 'Header và banner',
                'description' => 'Topbar, header, đăng nhập/đăng ký và hero slider hình ảnh.',
                'preview_image' => '/theme-previews/XD0314/hero-slider.png',
                'anchor_id' => 'top',
                'dynamic' => true,
                'settings' => ['source' => 'site_banners', 'placement' => 'xd0314-hero-slider', 'limit' => 3, 'autoplay_ms' => 6200],
                'settings_schema' => [
                    'placement' => ['type' => 'text', 'label' => 'Vị trí banner'],
                    'limit' => ['type' => 'number', 'label' => 'Số slide'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Think different - do different',
                        'subtitle' => 'Build Bench',
                        'description' => 'Hiện thực hóa ước mơ sở hữu ngôi nhà hoàn hảo của khách hàng bằng kinh nghiệm và sự chuyên nghiệp.',
                        'button_label' => 'Xem thêm',
                        'content' => ['slides' => [
                            ['title' => 'Think different - do different', 'summary' => 'Hiện thực hóa ước mơ sở hữu ngôi nhà hoàn hảo của khách hàng, thổi hồn vào từng công trình bằng kinh nghiệm và sự chuyên nghiệp của chúng tôi.', 'button_label' => 'Xem thêm', 'image' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#gioi-thieu'],
                            ['title' => 'Thiết kế và thi công trọn gói', 'summary' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, mỗi bước đều được quản lý rõ ràng.', 'button_label' => 'Dịch vụ', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85', 'link_url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Think different - do different', 'subtitle' => 'Build Bench', 'description' => 'Professional construction solutions for homes and commercial spaces.', 'button_label' => 'Learn more', 'content' => ['slides' => []]],
                ],
            ],
            [
                'block_type' => 'featured_categories',
                'label' => 'Slide danh mục dịch vụ',
                'description' => 'Danh mục dịch vụ chạy ngang.',
                'preview_image' => '/theme-previews/XD0314/service-category-slider.png',
                'anchor_id' => 'danh-muc-dich-vu',
                'settings' => [],
                'data' => [
                    'vi' => [
                        'title' => 'Danh mục dịch vụ',
                        'subtitle' => 'Dịch vụ',
                        'description' => '',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Thi công ống nước', 'summary' => 'Sửa chữa và thi công ống nước ngầm cho công trình.', 'icon' => '🚰', 'url' => '#dich-vu'],
                            ['title' => 'Sơn sửa công trình', 'summary' => 'Sơn sửa công trình lớn nhỏ đúng tiến độ.', 'icon' => '🎨', 'url' => '#dich-vu'],
                            ['title' => 'Thi công nội thất', 'summary' => 'Thiết kế nội thất đa dạng phong cách.', 'icon' => '🪑', 'url' => '#dich-vu'],
                            ['title' => 'Thi công xây dựng', 'summary' => 'Kiến trúc sư và thợ lành nghề giàu kinh nghiệm.', 'icon' => '🏠', 'url' => '#dich-vu'],
                        ]],
                    ],
                    'en' => ['title' => 'Service categories', 'subtitle' => 'Services', 'description' => '', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'about_experience',
                'label' => 'Giới thiệu công ty',
                'description' => 'Khối giới thiệu nhanh, card năng lực và thống kê nhập tùy chỉnh.',
                'preview_image' => '/theme-previews/XD0314/about-experience.png',
                'anchor_id' => 'gioi-thieu',
                'settings' => [],
                'media' => ['image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1400&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Về công ty',
                        'subtitle' => 'Giới thiệu',
                        'description' => 'Với kinh nghiệm cùng đội ngũ công nhân hàng đầu, chúng tôi đã đạt được nhiều thành công với các công trình trên khắp đất nước.',
                        'button_label' => '',
                        'content' => [
                            'items' => [
                                ['title' => 'Thi công ống nước', 'summary' => 'Thực hiện sửa chữa và thi công ống nước ngầm cho công trình và nhà ở.', 'icon' => '🚰'],
                                ['title' => 'Sơn sửa công trình', 'summary' => 'Nhận và thực hiện các yêu cầu sơn sửa công trình lớn nhỏ đúng tiến độ.', 'icon' => '🎨'],
                                ['title' => 'Thi công nội thất', 'summary' => 'Nhân lực thiết kế nội thất đa dạng với nhiều phong cách.', 'icon' => '🪑'],
                                ['title' => 'Thi công xây dựng', 'summary' => 'Đội ngũ kiến trúc sư, thợ lành nghề sẽ giúp ước mơ của bạn thành hiện thực.', 'icon' => '🏡'],
                            ],
                            'stats' => [
                                ['value' => '316', 'label' => 'Dự án đã hoàn thành', 'icon' => '▥'],
                                ['value' => '761', 'label' => 'Khách hàng hài lòng', 'icon' => '●'],
                                ['value' => '1245', 'label' => 'Công nhân làm việc', 'icon' => '☏'],
                            ],
                        ],
                    ],
                    'en' => ['title' => 'About company', 'subtitle' => 'About', 'description' => 'Experienced teams delivering construction projects nationwide.', 'button_label' => '', 'content' => []],
                ],
            ],
            [
                'block_type' => 'content_mosaic',
                'label' => 'Dự án mới nhất',
                'description' => 'Gallery ảnh có tiêu đề trong ảnh, lấy dữ liệu từ tin tức/sản phẩm/dịch vụ/dự án hoặc nhập tay.',
                'preview_image' => '/theme-previews/XD0314/project-gallery.png',
                'anchor_id' => 'du-an',
                'dynamic' => true,
                'settings' => ['source' => 'cms_projects', 'limit' => 8, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'media' => ['background' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Dự án mới nhất',
                        'subtitle' => '',
                        'description' => '',
                        'button_label' => 'Xem chi tiết',
                        'content' => ['tabs' => [
                            ['label' => 'Mẫu nhà đang hot'], ['label' => 'Mẫu nhà đơn giản đẹp 2019'], ['label' => 'Mẫu nhà cao cấp đẹp 2019'], ['label' => 'Mẫu nhà cấp 4 đẹp 2019'],
                        ], 'items' => [
                            ['title' => 'Mẫu nhà phố hiện đại', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Tòa nhà văn phòng', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Sân vườn trên cao', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Chung cư cao tầng', 'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Latest projects', 'subtitle' => '', 'description' => '', 'button_label' => 'View more', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'faq_showcase',
                'label' => 'Tại sao chọn chúng tôi',
                'description' => 'Khối lý do chọn nhập liệu tùy chỉnh.',
                'preview_image' => '/theme-previews/XD0314/why-choose.png',
                'anchor_id' => 'tai-sao-chon',
                'settings' => [],
                'media' => ['background' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=85'],
                'data' => [
                    'vi' => [
                        'title' => 'Tại sao chọn chúng tôi!',
                        'subtitle' => '',
                        'description' => 'Tiêu chí kinh doanh hàng đầu của chúng tôi là tạo ra sản phẩm độc đáo, tối ưu cho khách hàng và đảm bảo hoàn thành dự án đúng ý tưởng, chất lượng và tiến độ.',
                        'button_label' => '',
                        'content' => ['items' => [
                            ['title' => 'Chất lượng tốt nhất', 'summary' => 'Chất lượng công trình luôn đảm bảo tốt nhất và phù hợp các tiêu chuẩn chung.', 'icon' => '◎'],
                            ['title' => 'Chính trực', 'summary' => 'Đảm bảo tính trung thực và chính trực trong quá trình thiết kế và xây dựng.', 'icon' => '🏆'],
                            ['title' => 'Chiến lược', 'summary' => 'Cung cấp phương án và chiến lược xây dựng đầy đủ cho khách hàng.', 'icon' => '☝'],
                            ['title' => 'Sự an toàn', 'summary' => 'Đặt tính an toàn lên hàng đầu trong quá trình xây dựng.', 'icon' => '●'],
                            ['title' => 'Cộng đồng', 'summary' => 'Công trình phù hợp tiêu chuẩn cộng đồng và bối cảnh xung quanh.', 'icon' => '▰'],
                            ['title' => 'Sự bền vững', 'summary' => 'Công trình được xây dựng chất lượng và bền vững theo thời gian.', 'icon' => '⚙'],
                        ]],
                    ],
                    'en' => ['title' => 'Why choose us', 'subtitle' => '', 'description' => 'Practical construction values for quality, safety and progress.', 'button_label' => '', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'featured_services',
                'label' => 'Dịch vụ của chúng tôi',
                'description' => 'Carousel dịch vụ lấy từ nhiều nguồn hoặc nhập tay.',
                'preview_image' => '/theme-previews/XD0314/featured-services.png',
                'anchor_id' => 'dich-vu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'Danh mục'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Dịch vụ của chúng tôi',
                        'subtitle' => 'Dịch vụ',
                        'description' => '',
                        'button_label' => 'Xem thêm',
                        'content' => ['items' => [
                            ['title' => 'Xây dựng nhà phố theo nhu cầu sử dụng', 'summary' => 'Thiết kế và xây dựng nhà thành phố hiện đại.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Xây dựng nhà phố 2 tầng mái Thái', 'summary' => 'Kiến trúc tinh tế, tối ưu công năng sử dụng.', 'image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=900&q=85'],
                            ['title' => 'Xây dựng biệt thự vườn', 'summary' => 'Không gian sống xanh, tiện nghi và thoải mái.', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=900&q=85'],
                        ]],
                    ],
                    'en' => ['title' => 'Our services', 'subtitle' => 'Services', 'description' => '', 'button_label' => 'Read more', 'content' => ['items' => []]],
                ],
            ],
            [
                'block_type' => 'team_members',
                'label' => 'Đội ngũ',
                'description' => 'Giới thiệu đội ngũ nhân sự, mỗi mục cùng layout với chức danh và tên.',
                'preview_image' => '/theme-previews/XD0314/team-members.png',
                'anchor_id' => 'doi-ngu',
                'dynamic' => true,
                'settings' => ['source' => 'cms_team_members', 'limit' => 5, 'featured_only' => true],
                'settings_schema' => [
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'custom', 'label' => 'Nhập thủ công'], ['value' => 'cms_team_members', 'label' => 'Đội ngũ']]],
                    'limit' => ['type' => 'number', 'label' => 'Số nhân sự'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
                ],
                'data' => [
                    'vi' => [
                        'title' => 'Đội ngũ của chúng tôi',
                        'subtitle' => 'Đội ngũ',
                        'description' => 'Với đội ngũ công nhân lâu năm và kinh nghiệm, chúng tôi đảm bảo mang đến các công trình đạt tiêu chuẩn trong cả thiết kế và xây dựng.',
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
        $hero['data']['vi'] = [
            'title' => 'Logistics thông minh cho chuỗi cung ứng hiện đại',
            'subtitle' => 'Bizgrow Logistics',
            'description' => 'Kết nối kho bãi, vận chuyển và giao nhận bằng một quy trình minh bạch, linh hoạt và tối ưu chi phí.',
            'button_label' => 'Khám phá dịch vụ',
            'content' => ['slides' => []],
        ];

        $about = $blocks->get('bizmax_about');
        $about['data']['vi'] = [
            'title' => 'Kết nối hàng hóa với thị trường trên toàn thế giới',
            'subtitle' => 'Về Bizgrow',
            'description' => 'Bizgrow vận hành mạng lưới kho bãi, vận tải và giao nhận đồng bộ để doanh nghiệp chủ động từ điểm xuất phát đến điểm giao cuối.',
            'button_label' => 'Khám phá Bizgrow',
            'content' => [
                'image_primary' => '',
                'image_secondary' => '',
                'years' => '15+',
                'years_label' => 'Năm kinh nghiệm',
                'progress_label' => 'Khách hàng hài lòng',
                'progress_value' => 98,
            ],
        ];

        $services = $blocks->get('business_service_grid');
        $services['data']['vi'] = [
            'title' => 'Giải pháp vận chuyển cho mọi quy mô doanh nghiệp',
            'subtitle' => 'Dịch vụ logistics',
            'description' => 'Dịch vụ hậu cần đồng bộ từ lưu kho, xử lý đơn hàng đến vận chuyển quốc tế.',
            'button_label' => 'Xem chi tiết',
            'content' => ['items' => []],
        ];

        $process['data']['vi'] = [
            'title' => 'Quy trình làm việc',
            'subtitle' => 'Hành trình đơn hàng',
            'description' => 'Mỗi bước đều được kiểm soát bằng dữ liệu để hàng hóa được xử lý chính xác, an toàn và đúng tiến độ.',
            'button_label' => '',
            'content' => ['items' => [
                ['title' => 'Yêu cầu báo giá', 'description' => 'Tiếp nhận loại hàng, tuyến vận chuyển, khối lượng và thời gian dự kiến.'],
                ['title' => 'Xác nhận phương án', 'description' => 'Thống nhất lịch trình, chi phí, chứng từ và đầu mối theo dõi đơn hàng.'],
                ['title' => 'Lưu kho và xử lý', 'description' => 'Sắp xếp hàng hóa khoa học, kiểm đếm và cập nhật trạng thái bằng dữ liệu số.'],
                ['title' => 'Vận chuyển và bàn giao', 'description' => 'Điều phối giao hàng an toàn, đúng thời gian và cập nhật liên tục đến khi hoàn tất.'],
            ]],
        ];

        $benefits = $blocks->get('bizmax_benefit_panel');
        $benefits['data']['vi'] = [
            'title' => 'Năng lực logistics sẵn sàng đồng hành',
            'subtitle' => 'Quy mô Bizgrow',
            'description' => 'Hệ thống vận hành được xây dựng để mở rộng linh hoạt cùng doanh nghiệp.',
            'button_label' => '',
            'content' => ['image' => '', 'items' => [
                ['title' => '50 cụm kho trên toàn quốc'],
                ['title' => '500 nhân sự vận hành'],
                ['title' => '1.000 xe tải chuyên dụng'],
                ['title' => '5.000 khách hàng tin tưởng'],
            ]],
        ];

        $team = $blocks->get('team_members');
        $team['data']['vi']['title'] = 'Đội ngũ logistics giàu kinh nghiệm';
        $team['data']['vi']['subtitle'] = 'Con người Bizgrow';
        $team['data']['vi']['description'] = 'Những chuyên gia điều phối mang đến sự chủ động và hiệu quả cho chuỗi cung ứng của bạn.';

        $partners = $blocks->get('partner_logos');
        $partners['data']['vi']['title'] = 'Đối tác vận hành tin cậy';
        $partners['data']['vi']['subtitle'] = 'Kết nối toàn cầu';

        $posts = $blocks->get('bizmax_latest_posts');
        $posts['data']['vi']['title'] = 'Góc nhìn mới về logistics và chuỗi cung ứng';
        $posts['data']['vi']['subtitle'] = 'Từ chuyên gia Bizgrow';
        $posts['data']['vi']['button_label'] = 'Đọc thêm';

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
            'title' => 'Kế toán và thuế vững vàng cho doanh nghiệp',
            'subtitle' => 'Tư vấn tài chính chuyên nghiệp',
            'description' => 'Đồng hành từ tổ chức kế toán, kê khai thuế đến quản trị tài chính minh bạch và hiệu quả.',
            'button_label' => 'Nhận tư vấn',
            'content' => ['slides' => []],
        ];
        $blocks[1]['data']['vi'] = [
            'title' => 'Giải pháp tài chính dành cho từng giai đoạn phát triển',
            'subtitle' => 'Dịch vụ kế toán và tư vấn',
            'description' => 'Dữ liệu rõ ràng, tuân thủ đúng và báo cáo dễ hiểu để lãnh đạo chủ động ra quyết định.',
            'button_label' => 'Tìm hiểu thêm',
            'content' => ['items' => []],
        ];
        $blocks[2]['data']['vi'] = [
            'title' => 'Một đội ngũ đáng tin cho những con số quan trọng',
            'subtitle' => 'Về InVess',
            'description' => 'Đội ngũ InVess cung cấp giải pháp kế toán, thuế và tài chính thực tế, rõ ràng, phù hợp với quy mô và mục tiêu của doanh nghiệp.',
            'button_label' => 'Khám phá InVess',
            'content' => [
                'image_primary' => '',
                'image_secondary' => '',
                'years' => '25+',
                'years_label' => 'Năm kinh nghiệm',
                'progress_label' => 'Khách hàng hài lòng',
                'progress_value' => 97,
            ],
        ];
        $blocks[3]['data']['vi'] = [
            'title' => 'Biến dữ liệu tài chính thành lợi thế kinh doanh',
            'subtitle' => 'Giá trị chúng tôi mang lại',
            'description' => 'Mỗi báo cáo không chỉ đúng chuẩn mà còn giúp doanh nghiệp nhìn rõ dòng tiền, rủi ro và cơ hội tăng trưởng.',
            'button_label' => '',
            'content' => ['image' => '', 'items' => [
                ['title' => 'Tuân thủ đúng quy định'],
                ['title' => 'Báo cáo rõ ràng, đúng hạn'],
                ['title' => 'Kiểm soát rủi ro chủ động'],
                ['title' => 'Tư vấn sát hoạt động'],
            ]],
        ];
        $blocks[4]['data']['vi'] = [
            'title' => 'Cách chúng tôi hoạt động',
            'subtitle' => 'Quy trình làm việc',
            'description' => 'Một quy trình tư vấn rõ ràng giúp doanh nghiệp chủ động ở từng quyết định tài chính.',
            'button_label' => '',
            'content' => ['items' => [
                ['title' => 'Tiếp nhận nhu cầu', 'description' => 'Lắng nghe mục tiêu, quy mô vận hành và thu thập thông tin cần thiết.'],
                ['title' => 'Đánh giá hồ sơ', 'description' => 'Phân tích dữ liệu, nhận diện rủi ro và xác định phạm vi công việc.'],
                ['title' => 'Tư vấn giải pháp', 'description' => 'Trình bày kế hoạch minh bạch về đầu việc, chi phí và thời gian triển khai.'],
                ['title' => 'Đồng hành thực hiện', 'description' => 'Theo dõi kết quả, cập nhật định kỳ và hỗ trợ doanh nghiệp kịp thời.'],
            ]],
        ];
        $blocks[5]['data']['vi'] = [
            'title' => 'Sự an tâm được xây dựng từ kết quả thực tế',
            'subtitle' => 'Khách hàng chia sẻ',
            'description' => 'Phản hồi từ những doanh nghiệp đang đồng hành cùng InVess.',
            'button_label' => '',
            'content' => ['items' => [
                ['name' => 'Nguyễn Hoàng Nam', 'role' => 'Giám đốc An Phát', 'quote' => 'Báo cáo được chuẩn hóa rõ ràng, giúp chúng tôi kiểm soát dòng tiền và ra quyết định nhanh hơn.'],
                ['name' => 'Trần Minh Anh', 'role' => 'Nhà sáng lập Nori Studio', 'quote' => 'Đội ngũ tư vấn dễ hiểu, phản hồi nhanh và luôn chủ động nhắc các mốc thuế quan trọng.'],
                ['name' => 'Lê Quốc Huy', 'role' => 'CFO GreenLink', 'quote' => 'InVess không chỉ xử lý số liệu mà còn chỉ ra các điểm cần cải thiện trong vận hành tài chính.'],
            ]],
        ];
        $blocks[6]['data']['vi'] = [
            'title' => 'Chuyên gia đồng hành cùng doanh nghiệp',
            'subtitle' => 'Đội ngũ InVess',
            'description' => 'Kinh nghiệm chuyên môn kết hợp sự thấu hiểu hoạt động kinh doanh thực tế.',
            'button_label' => '',
            'content' => ['items' => [
                ['name' => 'Phạm Thanh Hà', 'role' => 'Chuyên gia tư vấn thuế', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=800&q=85'],
                ['name' => 'Đỗ Minh Quân', 'role' => 'Giám đốc dịch vụ kế toán', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=800&q=85'],
                ['name' => 'Nguyễn Thu Trang', 'role' => 'Chuyên gia tài chính doanh nghiệp', 'image' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=800&q=85'],
            ]],
        ];
        $blocks[7]['data']['vi'] = [
            'title' => 'Trao đổi cùng chuyên gia InVess',
            'subtitle' => 'Bắt đầu từ nhu cầu của bạn',
            'description' => 'Chia sẻ vấn đề doanh nghiệp đang gặp phải. Chúng tôi sẽ đề xuất phạm vi và cách triển khai phù hợp.',
            'button_label' => 'Gửi yêu cầu',
            'content' => [],
        ];
        $blocks[8]['data']['vi'] = [
            'title' => 'Kiến thức tài chính dành cho doanh nghiệp',
            'subtitle' => 'Góc nhìn từ chuyên gia',
            'description' => '',
            'button_label' => 'Đọc thêm',
            'content' => ['items' => [
                ['title' => 'Những mốc thuế doanh nghiệp cần lưu ý', 'summary' => 'Lịch kê khai và các đầu việc quan trọng giúp doanh nghiệp chủ động tuân thủ.', 'image' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?auto=format&fit=crop&w=900&q=85'],
                ['title' => 'Đọc báo cáo dòng tiền theo cách đơn giản', 'summary' => 'Các chỉ số cốt lõi giúp nhà quản lý hiểu sức khỏe tài chính doanh nghiệp.', 'image' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=900&q=85'],
                ['title' => 'Kiểm soát chi phí trong giai đoạn tăng trưởng', 'summary' => 'Cách xây dựng ngân sách và theo dõi chênh lệch mà không làm chậm vận hành.', 'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?auto=format&fit=crop&w=900&q=85'],
            ]],
        ];
        $blocks[9]['data']['vi'] = [
            'title' => 'Đối tác đồng hành',
            'subtitle' => 'Kết nối tin cậy',
            'description' => '',
            'button_label' => '',
            'content' => ['items' => []],
        ];

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
        $blocks[0]['label'] = 'Banner cảnh quan';
        $blocks[0]['description'] = 'Banner giới thiệu dịch vụ thiết kế, thi công và chăm sóc cảnh quan.';
        $blocks[0]['data']['vi'] = [
            'title' => 'Kiến tạo khu vườn xanh cho mọi không gian sống',
            'subtitle' => 'Cảnh quan bền vững',
            'description' => 'Từ ý tưởng đến thi công và chăm sóc định kỳ, Garden Haven đồng hành để mỗi khoảng xanh luôn đẹp, khỏe và giàu sức sống.',
            'button_label' => 'Nhận tư vấn miễn phí',
            'content' => ['slides' => []],
        ];

        $blocks[1]['label'] = 'Dịch vụ cảnh quan';
        $blocks[1]['data']['vi'] = [
            'title' => 'Dịch vụ dành cho khu vườn của bạn',
            'subtitle' => 'Chuyên môn của chúng tôi',
            'description' => 'Giải pháp trọn gói cho nhà ở, biệt thự, văn phòng và không gian thương mại.',
            'button_label' => 'Xem chi tiết',
            'content' => ['items' => []],
        ];

        $blocks[2]['label'] = 'Giới thiệu Garden Haven';
        $blocks[2]['data']['vi'] = [
            'title' => 'Đưa thiên nhiên trở lại gần hơn với cuộc sống',
            'subtitle' => 'Về Garden Haven',
            'description' => 'Chúng tôi kết hợp thẩm mỹ, đặc tính cây trồng và điều kiện khí hậu để tạo nên cảnh quan hài hòa, dễ chăm sóc và bền vững theo thời gian.',
            'button_label' => 'Khám phá câu chuyện',
            'content' => [
                'image_primary' => '',
                'image_secondary' => '',
                'years' => '15+',
                'years_label' => 'Năm kinh nghiệm',
                'progress_label' => 'Khách hàng hài lòng',
                'progress_value' => 96,
            ],
        ];

        $blocks[3]['label'] = 'Lý do chọn Garden Haven';
        $blocks[3]['data']['vi'] = [
            'title' => 'Mỗi công trình là một hệ sinh thái được chăm chút',
            'subtitle' => 'Khác biệt của chúng tôi',
            'description' => 'Quy trình rõ ràng, vật liệu phù hợp và đội ngũ am hiểu cây xanh giúp khu vườn phát triển khỏe mạnh sau bàn giao.',
            'button_label' => '',
            'content' => [
                'image' => '',
                'items' => [
                    ['title' => 'Thiết kế theo hiện trạng'],
                    ['title' => 'Cây giống tuyển chọn'],
                    ['title' => 'Thi công đúng tiến độ'],
                    ['title' => 'Bảo dưỡng tận tâm'],
                ],
            ],
        ];

        $blocks[4]['label'] = 'Dự án cảnh quan';
        $blocks[4]['description'] = 'Thư viện các dự án sân vườn và cảnh quan tiêu biểu.';
        $blocks[4]['data']['vi'] = [
            'title' => 'Những khoảng xanh chúng tôi đã kiến tạo',
            'subtitle' => 'Dự án nổi bật',
            'description' => 'Mỗi dự án được thiết kế theo nhịp sống, kiến trúc và điều kiện tự nhiên riêng.',
            'button_label' => 'Xem dự án',
            'content' => ['items' => []],
        ];

        $blocks[5]['label'] = 'Cảm nhận khách hàng';
        $blocks[5]['data']['vi'] = [
            'title' => 'Niềm vui bắt đầu từ một khu vườn đáng sống',
            'subtitle' => 'Khách hàng chia sẻ',
            'description' => 'Những phản hồi chân thành sau khi không gian xanh được hoàn thiện và đưa vào sử dụng.',
            'button_label' => '',
            'content' => ['items' => [
                ['name' => 'Chị Minh Anh', 'role' => 'Biệt thự Thảo Điền', 'quote' => 'Khu vườn thoáng, nhiều lớp xanh nhưng vẫn rất dễ chăm sóc. Đội ngũ bàn giao đúng những gì đã tư vấn.'],
                ['name' => 'Anh Quốc Huy', 'role' => 'Nhà phố Quận 7', 'quote' => 'Khoảng sân nhỏ được xử lý khéo léo, sáng hơn và trở thành nơi cả gia đình sử dụng mỗi ngày.'],
                ['name' => 'Công ty An Phú', 'role' => 'Cảnh quan văn phòng', 'quote' => 'Quy trình chuyên nghiệp, thi công gọn và kế hoạch bảo dưỡng sau bàn giao rất rõ ràng.'],
            ]],
        ];

        $blocks[6]['label'] = 'Đội ngũ cảnh quan';
        $blocks[6]['data']['vi'] = [
            'title' => 'Đội ngũ hiểu cây, hiểu đất và hiểu không gian',
            'subtitle' => 'Những người làm vườn',
            'description' => 'Kiến trúc sư cảnh quan, kỹ sư cây xanh và đội thi công cùng phối hợp xuyên suốt mỗi dự án.',
            'button_label' => '',
            'content' => ['items' => [
                ['name' => 'Nguyễn Minh Khang', 'role' => 'Kiến trúc sư cảnh quan', 'image' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=800&q=85'],
                ['name' => 'Trần Thanh Hà', 'role' => 'Kỹ sư cây xanh', 'image' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=800&q=85'],
                ['name' => 'Lê Hoàng Nam', 'role' => 'Quản lý thi công', 'image' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=800&q=85'],
            ]],
        ];

        $blocks[7]['label'] = 'Đăng ký tư vấn';
        $blocks[7]['data']['vi'] = [
            'title' => 'Bắt đầu khu vườn trong mơ của bạn',
            'subtitle' => 'Tư vấn cùng chuyên gia',
            'description' => 'Chia sẻ diện tích, phong cách và nhu cầu sử dụng. Chúng tôi sẽ liên hệ để đề xuất hướng triển khai phù hợp.',
            'button_label' => 'Gửi yêu cầu',
            'content' => [],
        ];

        $blocks[8]['label'] = 'Cẩm nang sân vườn';
        $blocks[8]['data']['vi'] = [
            'title' => 'Cảm hứng và kinh nghiệm chăm sóc không gian xanh',
            'subtitle' => 'Cẩm nang Garden Haven',
            'description' => '',
            'button_label' => 'Đọc thêm',
            'content' => ['items' => [
                ['title' => 'Chọn cây phù hợp cho khu vườn nhiều nắng', 'summary' => 'Những nhóm cây bền nắng, dễ phối và phù hợp với khí hậu đô thị.', 'image' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?auto=format&fit=crop&w=900&q=85'],
                ['title' => 'Năm nguyên tắc để sân vườn luôn xanh khỏe', 'summary' => 'Từ đất trồng, tưới nước đến cắt tỉa đúng thời điểm trong năm.', 'image' => 'https://images.unsplash.com/photo-1558904541-efa843a96f01?auto=format&fit=crop&w=900&q=85'],
                ['title' => 'Thiết kế góc thư giãn giữa thiên nhiên', 'summary' => 'Cách kết hợp cây xanh, vật liệu và ánh sáng cho một góc nghỉ ngơi riêng tư.', 'image' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=900&q=85'],
            ]],
        ];

        $blocks[9]['label'] = 'Đối tác đồng hành';
        $blocks[9]['data']['vi'] = [
            'title' => 'Đối tác đồng hành',
            'subtitle' => 'Kết nối bền vững',
            'description' => '',
            'button_label' => '',
            'content' => ['items' => []],
        ];

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
        $sources = [['value' => 'cms_services', 'label' => 'Dịch vụ'], ['value' => 'cms_posts', 'label' => 'Tin tức'], ['value' => 'cms_products', 'label' => 'Sản phẩm'], ['value' => 'cms_projects', 'label' => 'Dự án'], ['value' => 'custom', 'label' => 'Nhập thủ công']];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Header và banner', 'description' => 'Header hai tầng và banner slider.', 'preview_image' => '/theme-previews/XD0306/hero-slider.png', 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'xd0306-hero-slider', 'limit' => 3, 'autoplay_ms' => 6000], 'settings_schema' => [['key' => 'placement', 'label' => 'Vị trí banner', 'type' => 'text', 'default' => 'xd0306-hero-slider'], ['key' => 'limit', 'label' => 'Số slide', 'type' => 'number', 'default' => 3]], 'data' => ['vi' => ['title' => 'Thiết kế website chuyên nghiệp', 'subtitle' => 'Digital agency', 'description' => 'Chúng tôi xây dựng website, thương hiệu và chiến dịch số có hiệu quả.', 'button_label' => 'Liên hệ ngay', 'content' => ['slides' => []]], 'en' => ['title' => 'Professional website design', 'subtitle' => 'Digital agency', 'description' => 'Website, branding and digital campaigns.', 'button_label' => 'Contact us', 'content' => ['slides' => []]]]],
            ['block_type' => 'business_service_grid', 'label' => 'Dịch vụ nổi bật', 'description' => 'Nguồn Dịch vụ, Tin tức, Sản phẩm, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0306/business-service-grid.png', 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 3, 'featured_only' => true], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources], 'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị']], 'data' => ['vi' => ['title' => 'Công ty cổ phần Black', 'subtitle' => 'Về chúng tôi', 'description' => 'Dịch vụ marketing tổng thể giúp doanh nghiệp phát triển trong môi trường số.', 'button_label' => 'Xem thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Black agency', 'subtitle' => 'About us', 'description' => 'Integrated digital marketing services.', 'button_label' => 'Learn more', 'content' => ['items' => []]]]],
            ['block_type' => 'bizmax_about', 'label' => 'Giới thiệu sáng tạo', 'description' => 'Nội dung giới thiệu và hotline tùy chỉnh.', 'preview_image' => '/theme-previews/XD0306/bizmax-about.png', 'anchor_id' => 'gioi-thieu', 'settings' => [], 'data' => ['vi' => ['title' => 'Chúng tôi sáng tạo studio xây dựng thương hiệu', 'subtitle' => 'Chúng tôi là ai', 'description' => 'Đồng hành và phát triển cùng doanh nghiệp bằng giải pháp marketing online, truyền thông và quảng cáo.', 'button_label' => '1900 9477', 'content' => ['years' => '20+', 'years_label' => 'Năm kinh nghiệm', 'progress_label' => 'Khách hàng hài lòng', 'progress_value' => 90]], 'en' => ['title' => 'We build creative brands', 'subtitle' => 'Who we are', 'description' => 'We help businesses grow through digital marketing.', 'button_label' => '1900 9477', 'content' => []]]],
            ['block_type' => 'collection_gallery', 'label' => 'Album hình ảnh', 'description' => 'Nguồn Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0306/collection-gallery.png', 'anchor_id' => 'thu-vien', 'dynamic' => true, 'settings' => ['source' => 'cms_projects', 'limit' => 6], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources], 'limit' => ['type' => 'number', 'label' => 'Số ảnh']], 'data' => ['vi' => ['title' => 'Một số album của chúng tôi', 'subtitle' => 'Hình ảnh', 'description' => '', 'button_label' => 'Xem thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Selected albums', 'subtitle' => 'Gallery', 'description' => '', 'button_label' => 'View more', 'content' => ['items' => []]]]],
            ['block_type' => 'faq_showcase', 'label' => 'Câu hỏi thường gặp', 'description' => 'FAQ tùy chỉnh.', 'preview_image' => '/theme-previews/XD0306/faq-showcase.png', 'anchor_id' => 'faq', 'settings' => [], 'data' => ['vi' => ['title' => 'Faq\'s', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => [['question' => 'Tôi cần chuẩn bị gì? Tiến hành mất bao lâu?', 'answer' => 'Đội ngũ sẽ khảo sát và đề xuất lộ trình phù hợp.'], ['question' => 'Dịch vụ marketing trọn gói bao gồm những gì?', 'answer' => 'Bao gồm chiến lược, nội dung, quảng cáo và đo lường hiệu quả.'], ['question' => 'Tại sao nên chọn dịch vụ marketing trọn gói?', 'answer' => 'Giúp các kênh truyền thông vận hành thống nhất.']]]], 'en' => ['title' => 'FAQ', 'subtitle' => '', 'description' => '', 'button_label' => '', 'content' => ['items' => []]]]],
            ['block_type' => 'bizmax_latest_posts', 'label' => 'Blog của chúng tôi', 'description' => 'Nguồn Tin tức, Sản phẩm, Dịch vụ, Dự án hoặc nhập thủ công.', 'preview_image' => '/theme-previews/XD0306/bizmax-latest-posts.png', 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 5], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => $sources], 'limit' => ['type' => 'number', 'label' => 'Số bài hiển thị']], 'data' => ['vi' => ['title' => 'Blog của chúng tôi', 'subtitle' => '', 'description' => '', 'button_label' => 'Đọc thêm', 'content' => ['items' => []]], 'en' => ['title' => 'Our blog', 'subtitle' => '', 'description' => '', 'button_label' => 'Read more', 'content' => ['items' => []]]]],
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
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => ['custom', 'cms_projects', 'cms_services', 'cms_products', 'cms_posts']],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'category_id' => ['type' => 'number', 'label' => 'ID danh mục khi dùng tin tức/sản phẩm'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
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
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => ['custom', 'cms_team_members']],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
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
                    'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => ['custom', 'cms_testimonials']],
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
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
                    'limit' => ['type' => 'number', 'label' => 'Số mục hiển thị'],
                    'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy nổi bật'],
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
    private function dl750DefaultBlocks(): array
    {
        $preview = '/theme-previews/DL750/preview-dl750.svg';
        $heading = fn (string $title, string $subtitle = '', string $description = '', string $button = ''): array => compact('title', 'subtitle', 'description') + ['button_label' => $button];
        $withItems = fn (array $data, array $items): array => array_merge($data, ['content' => ['items' => $items]]);
        $productSchema = ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_products', 'label' => 'Sản phẩm Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số sản phẩm'], 'category_id' => ['type' => 'select', 'label' => 'Danh mục'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ sản phẩm nổi bật']];
        $categoryFallback = [
            ['title' => 'Lều và mái che', 'summary' => 'Không gian nghỉ giữa thiên nhiên', 'icon' => 'fa-solid fa-campground', 'url' => '#san-pham'],
            ['title' => 'Túi ngủ', 'summary' => 'Ấm áp cho mọi hành trình', 'icon' => 'fa-solid fa-bed', 'url' => '#san-pham'],
            ['title' => 'Đèn dã ngoại', 'summary' => 'Ánh sáng bền bỉ ngoài trời', 'icon' => 'fa-regular fa-lightbulb', 'url' => '#san-pham'],
            ['title' => 'Trang phục', 'summary' => 'Thoải mái và linh hoạt', 'icon' => 'fa-solid fa-shirt', 'url' => '#san-pham'],
            ['title' => 'Dụng cụ đa năng', 'summary' => 'Gọn nhẹ, hữu ích', 'icon' => 'fa-solid fa-screwdriver-wrench', 'url' => '#san-pham'],
            ['title' => 'Đạp xe', 'summary' => 'Khám phá cung đường mới', 'icon' => 'fa-solid fa-bicycle', 'url' => '#dich-vu'],
            ['title' => 'Chèo thuyền', 'summary' => 'Trải nghiệm trên mặt nước', 'icon' => 'fa-solid fa-sailboat', 'url' => '#dich-vu'],
            ['title' => 'Bếp và phụ kiện', 'summary' => 'Bữa ngon giữa thiên nhiên', 'icon' => 'fa-solid fa-fire-burner', 'url' => '#san-pham'],
        ];
        $services = [
            ['title' => 'Thuê lều trại', 'summary' => 'Bộ lều chất lượng, phù hợp từ chuyến đi ngắn đến hành trình dài ngày.', 'image' => 'https://images.unsplash.com/photo-1475483768296-6163e08872a1?auto=format&fit=crop&w=1000&q=85', 'url' => '#lien-he'],
            ['title' => 'Thuê phụ kiện', 'summary' => 'Bếp, đèn, bàn ghế và dụng cụ dã ngoại được chuẩn bị đầy đủ.', 'image' => 'https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?auto=format&fit=crop&w=1000&q=85', 'url' => '#lien-he'],
            ['title' => 'Hành trình trekking', 'summary' => 'Gợi ý cung đường và thiết bị an toàn cho người yêu khám phá.', 'image' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1000&q=85', 'url' => '#lien-he'],
            ['title' => 'Khu vui chơi ngoài trời', 'summary' => 'Hoạt động gắn kết dành cho gia đình và nhóm bạn.', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1000&q=85', 'url' => '#lien-he'],
            ['title' => 'Chèo thuyền thư giãn', 'summary' => 'Tận hưởng mặt nước yên bình với trang bị tiêu chuẩn.', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=1000&q=85', 'url' => '#lien-he'],
            ['title' => 'Xe camping tiện nghi', 'summary' => 'Linh hoạt di chuyển và nghỉ ngơi giữa thiên nhiên.', 'image' => 'https://images.unsplash.com/photo-1525811902-f2342640856e?auto=format&fit=crop&w=1000&q=85', 'url' => '#lien-he'],
        ];
        $products = [
            ['title' => 'Lều dã ngoại Alpine 2P', 'price' => 1850000, 'image' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
            ['title' => 'Túi ngủ Trail Warm', 'price' => 890000, 'image' => 'https://images.unsplash.com/photo-1523987355523-c7b5b0dd90a7?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
            ['title' => 'Đèn trại Forest Light', 'price' => 620000, 'image' => 'https://images.unsplash.com/photo-1527221579996-0de6d1ae2069?auto=format&fit=crop&w=900&q=85', 'url' => '#'],
        ];
        $faq = [
            ['title' => 'Có những hoạt động ngoài trời nào?', 'summary' => 'Bạn có thể trải nghiệm cắm trại, trekking, chèo thuyền và nhiều hoạt động kết nối cùng thiên nhiên.'],
            ['title' => 'Có dịch vụ cho thuê lều không?', 'summary' => 'Có. Các bộ lều được vệ sinh, kiểm tra trước khi bàn giao và có nhiều kích thước lựa chọn.'],
            ['title' => 'Tôi cần chuẩn bị gì cho chuyến đi?', 'summary' => 'Đội ngũ tư vấn sẽ gửi danh sách thiết bị phù hợp theo địa điểm, thời tiết và số người tham gia.'],
            ['title' => 'Có hỗ trợ nhóm gia đình và doanh nghiệp?', 'summary' => 'Có. Chúng tôi thiết kế gói trải nghiệm riêng cho gia đình, trường học và chương trình gắn kết đội ngũ.'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero du lịch dã ngoại', 'description' => 'Banner toàn màn hình lấy từ kho banner.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'dl750-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => 'https://images.unsplash.com/photo-1504851149312-7a075b496cc7?auto=format&fit=crop&w=2200&q=90'], 'data' => ['vi' => $heading('Chạm vào thiên nhiên', 'Hành trình đáng nhớ bắt đầu từ đây', 'Khám phá những trải nghiệm ngoài trời được chuẩn bị chỉn chu và an toàn.', 'Xem dịch vụ'), 'en' => $heading('Reconnect with nature', 'Memorable outdoor journeys begin here')]],
            ['block_type' => 'dl750_categories', 'label' => 'Danh mục nổi bật', 'description' => 'Tám danh mục lấy từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'danh-muc', 'dynamic' => true, 'settings' => ['source' => 'catalog_categories', 'limit' => 8], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'catalog_categories', 'label' => 'Danh mục Catalog']]], 'limit' => ['type' => 'number', 'label' => 'Số danh mục']], 'data' => ['vi' => $withItems($heading('Danh mục nổi bật', 'Trang bị cho mọi chuyến đi'), $categoryFallback), 'en' => $withItems($heading('Featured categories'), $categoryFallback)]],
            ['block_type' => 'dl750_about', 'label' => 'Về chúng tôi', 'description' => 'Giới thiệu thương hiệu và cam kết phục vụ.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'media' => ['image' => 'https://images.unsplash.com/photo-1521336575822-6da63fb45455?auto=format&fit=crop&w=1300&q=85', 'image_secondary' => 'https://images.unsplash.com/photo-1496545672447-f699b503d270?auto=format&fit=crop&w=900&q=85'], 'data' => ['vi' => $withItems($heading('Về chúng tôi', 'Cùng nhau trải nghiệm điều khác biệt', 'Chúng tôi cung cấp thiết bị, dịch vụ và giải pháp phù hợp để mỗi hành trình ngoài trời trở nên nhẹ nhàng, an toàn và đáng nhớ.', 'Khám phá thêm'), [['title' => 'Sản phẩm chất lượng', 'icon' => 'fa-solid fa-award'], ['title' => 'Giá cả minh bạch', 'icon' => 'fa-solid fa-tags'], ['title' => 'Phục vụ chu đáo', 'icon' => 'fa-solid fa-hands-holding-circle'], ['title' => 'Hỗ trợ hành trình', 'icon' => 'fa-solid fa-truck-fast']]), 'en' => $heading('About us', 'Experience the outdoors differently')]],
            ['block_type' => 'dl750_services', 'label' => 'Dịch vụ cung cấp', 'description' => 'Sáu dịch vụ lấy từ CMS Services.', 'preview_image' => $preview, 'anchor_id' => 'dich-vu', 'dynamic' => true, 'settings' => ['source' => 'cms_services', 'limit' => 6, 'featured_only' => false], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_services', 'label' => 'Dịch vụ CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số dịch vụ'], 'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ dịch vụ nổi bật']], 'data' => ['vi' => $withItems($heading('Dịch vụ cung cấp', 'Từ trang bị đến trải nghiệm trọn gói'), $services), 'en' => $withItems($heading('Our services'), $services)]],
            ['block_type' => 'dl750_reasons', 'label' => 'Lý do lựa chọn', 'description' => 'Ba giá trị nổi bật và hotline động.', 'preview_image' => $preview, 'anchor_id' => 'ly-do', 'data' => ['vi' => $withItems($heading('Lý do chọn chúng tôi', 'Chuyên nghiệp trong từng chi tiết', 'Trải nghiệm gần thiên nhiên, trang bị tiện nghi và đội ngũ hỗ trợ tận tâm.'), [['title' => 'Hoạt động ngoài trời', 'summary' => 'Những trải nghiệm giúp bạn kết nối sâu hơn với thiên nhiên.', 'icon' => 'fa-solid fa-campground'], ['title' => 'Thể thao trên mặt nước', 'summary' => 'Hoạt động thư giãn với trang bị được kiểm tra an toàn.', 'icon' => 'fa-solid fa-person-swimming'], ['title' => 'Phụ kiện dã ngoại', 'summary' => 'Danh mục thiết bị đa dạng, phù hợp nhiều nhu cầu.', 'icon' => 'fa-solid fa-fire']]), 'en' => $heading('Why choose us')]],
            ['block_type' => 'dl750_products', 'label' => 'Sản phẩm nổi bật', 'description' => 'Sản phẩm dã ngoại lấy từ Catalog.', 'preview_image' => $preview, 'anchor_id' => 'san-pham', 'dynamic' => true, 'settings' => ['source' => 'cms_products', 'limit' => 6, 'featured_only' => true, 'feature_image' => 'https://images.unsplash.com/photo-1496545672447-f699b503d270?auto=format&fit=crop&w=1200&q=85'], 'settings_schema' => array_merge($productSchema, ['feature_image' => ['type' => 'image', 'label' => 'Ảnh chiến dịch']]), 'data' => ['vi' => $withItems($heading('Sản phẩm nổi bật', 'Trang bị đáng tin cậy cho mọi hành trình'), $products), 'en' => $withItems($heading('Featured products'), $products)]],
            ['block_type' => 'dl750_gallery', 'label' => 'Hình ảnh hoạt động', 'description' => 'Bộ ảnh hoạt động ngoài trời.', 'preview_image' => $preview, 'anchor_id' => 'thu-vien', 'data' => ['vi' => $withItems($heading('Hình ảnh hoạt động', 'Khoảnh khắc giữa thiên nhiên'), [['title' => 'Cắm trại ven hồ', 'image' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=85'], ['title' => 'Bạn bè cùng khám phá', 'image' => 'https://images.unsplash.com/photo-1527631746610-bca00a040d60?auto=format&fit=crop&w=1400&q=85'], ['title' => 'Bữa tối giữa rừng', 'image' => 'https://images.unsplash.com/photo-1475483768296-6163e08872a1?auto=format&fit=crop&w=1200&q=85']]), 'en' => $heading('Outdoor moments')]],
            ['block_type' => 'dl750_news', 'label' => 'Tin tức', 'description' => 'Bốn bài viết mới từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'tin-tuc', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 4], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Bài viết CMS']]], 'limit' => ['type' => 'number', 'label' => 'Số bài viết']], 'data' => ['vi' => $heading('Tin tức', 'Kinh nghiệm dã ngoại và câu chuyện hành trình'), 'en' => $heading('News and stories')]],
            ['block_type' => 'dl750_faq', 'label' => 'Câu hỏi thường gặp', 'description' => 'Khối FAQ dạng accordion.', 'preview_image' => $preview, 'anchor_id' => 'faq', 'media' => ['image' => 'https://images.unsplash.com/photo-1551632811-561732d1e306?auto=format&fit=crop&w=1200&q=85'], 'data' => ['vi' => $withItems($heading('Câu hỏi thường gặp', 'Giải đáp nhanh trước chuyến đi', '', 'Đăng ký nhận tư vấn'), $faq), 'en' => $withItems($heading('Frequently asked questions'), $faq)]],
            ['block_type' => 'dl750_partners', 'label' => 'Đối tác', 'description' => 'Logo đối tác lấy từ CMS Partners.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 6], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_partners', 'label' => 'Đối tác CMS'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => $heading('Đối tác', 'Những thương hiệu cùng đồng hành'), 'en' => $heading('Partners')]],
        ];
    }

    private function bds702DefaultBlocks(): array
    {
        $preview = '/theme-previews/BDS702/preview-bds702.svg';
        $listingSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'real_estate_listings', 'label' => 'Tin bất động sản']]],
            'limit' => ['type' => 'number', 'label' => 'Số dự án hiển thị'],
            'category_id' => ['type' => 'number', 'label' => 'Loại hình bất động sản'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy dự án nổi bật'],
        ];
        $postSchema = [
            'source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_posts', 'label' => 'Bài viết CMS']]],
            'limit' => ['type' => 'number', 'label' => 'Số hoạt động hiển thị'],
            'featured_only' => ['type' => 'boolean', 'label' => 'Chỉ lấy bài nổi bật'],
        ];
        $heading = fn (string $title, string $subtitle = '', string $description = '', string $button = ''): array => compact('title', 'subtitle', 'description') + ['button_label' => $button];
        $withItems = fn (array $data, array $items): array => array_merge($data, ['content' => ['items' => $items]]);
        $projectFallback = [
            ['title' => 'Không gian sống xanh giữa lòng đô thị', 'summary' => 'Quy hoạch đồng bộ, tiện ích hiện đại và kết nối thuận tiện.', 'image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=85', 'location' => 'Khu đô thị trung tâm', 'area' => 180, 'bedrooms' => 3, 'bathrooms' => 2, 'url' => '#lien-he'],
            ['title' => 'Căn hộ thanh lịch bên công viên', 'summary' => 'Thiết kế tối ưu ánh sáng tự nhiên và trải nghiệm sống riêng tư.', 'image' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=85', 'location' => 'Khu dân cư mới', 'area' => 145, 'bedrooms' => 3, 'bathrooms' => 2, 'url' => '#lien-he'],
            ['title' => 'Biệt thự sân vườn cao cấp', 'summary' => 'Nơi tận hưởng sự yên tĩnh, sang trọng và gần gũi thiên nhiên.', 'image' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=85', 'location' => 'Khu đô thị sinh thái', 'area' => 320, 'bedrooms' => 5, 'bathrooms' => 4, 'url' => '#lien-he'],
        ];

        return [
            ['block_type' => 'hero_slider', 'label' => 'Hero dự án bất động sản', 'description' => 'Banner lớn lấy từ kho banner của website.', 'preview_image' => $preview, 'anchor_id' => 'top', 'dynamic' => true, 'settings' => ['source' => 'site_banners', 'placement' => 'bds702-hero-slider', 'limit' => 3, 'autoplay_ms' => 5600], 'settings_schema' => ['placement' => ['type' => 'text', 'label' => 'Placement banner'], 'limit' => ['type' => 'number', 'label' => 'Số banner'], 'autoplay_ms' => ['type' => 'number', 'label' => 'Tự chuyển (ms)']], 'media' => ['image' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=2200&q=90'], 'data' => ['vi' => $heading('Kiến tạo không gian sống bền vững', 'Kiến trúc tinh hoa · Hài hòa cuộc sống', 'Những công trình được phát triển theo tiêu chuẩn hiện đại và bền vững cùng thời gian.', 'Khám phá dự án'), 'en' => $heading('Creating sustainable living spaces', 'Refined architecture · Harmonious living')]],
            ['block_type' => 'bds702_intro', 'label' => 'Giới thiệu dự án', 'description' => 'Ảnh tổng mặt bằng, nội dung giới thiệu và bốn chỉ số nổi bật.', 'preview_image' => $preview, 'anchor_id' => 'gioi-thieu', 'media' => ['image' => 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1400&q=85'], 'data' => ['vi' => $withItems($heading('Giới thiệu', 'Một không gian sống được quy hoạch chỉn chu', 'Từ cảnh quan đến tiện ích, mỗi chi tiết đều hướng đến trải nghiệm sống cân bằng và giá trị lâu dài.'), [['title' => 'Quy hoạch đồng bộ', 'summary' => 'Không gian được tổ chức khoa học'], ['title' => 'Giá trị bền vững', 'summary' => 'Tối ưu hiệu quả đầu tư dài hạn'], ['title' => 'Mảng xanh rộng mở', 'summary' => 'Hài hòa cùng thiên nhiên'], ['title' => 'Kết nối thuận tiện', 'summary' => 'Dễ dàng tiếp cận tiện ích thiết yếu']]), 'en' => $heading('Introduction', 'A thoughtfully planned living environment')]],
            ['block_type' => 'bds702_featured_projects', 'label' => 'Dự án tiêu biểu', 'description' => 'Sáu dự án lấy từ module Bất động sản.', 'preview_image' => $preview, 'anchor_id' => 'du-an', 'dynamic' => true, 'settings' => ['source' => 'real_estate_listings', 'limit' => 6, 'featured_only' => false], 'settings_schema' => $listingSchema, 'data' => ['vi' => $withItems($heading('Dự án tiêu biểu', 'Những lựa chọn nổi bật dành cho khách hàng'), array_merge($projectFallback, $projectFallback)), 'en' => $withItems($heading('Featured projects'), $projectFallback)]],
            ['block_type' => 'bds702_investment_activities', 'label' => 'Hoạt động đầu tư', 'description' => 'Ba nội dung tư vấn và hoạt động đầu tư lấy từ CMS.', 'preview_image' => $preview, 'anchor_id' => 'hoat-dong', 'dynamic' => true, 'settings' => ['source' => 'cms_posts', 'limit' => 3, 'featured_only' => false, 'background_image' => 'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?auto=format&fit=crop&w=2200&q=85'], 'settings_schema' => array_merge($postSchema, ['background_image' => ['type' => 'image', 'label' => 'Ảnh nền']]), 'data' => ['vi' => $withItems($heading('Hoạt động đầu tư', 'Giải pháp chuyên nghiệp cho từng nhu cầu', 'Đồng hành từ tư vấn, thiết kế đến triển khai dự án.'), [['title' => 'Tư vấn phát triển dự án', 'image' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=900&q=85'], ['title' => 'Thiết kế không gian sống', 'image' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=900&q=85'], ['title' => 'Quản lý đầu tư bền vững', 'image' => 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=900&q=85']]), 'en' => $heading('Investment activities')]],
            ['block_type' => 'bds702_recommended_projects', 'label' => 'Dự án phù hợp', 'description' => 'Ba dự án gợi ý lấy từ module Bất động sản.', 'preview_image' => $preview, 'anchor_id' => 'de-xuat', 'dynamic' => true, 'settings' => ['source' => 'real_estate_listings', 'limit' => 3, 'featured_only' => true], 'settings_schema' => $listingSchema, 'data' => ['vi' => $withItems($heading('Dự án tốt cho bạn', 'Không gian phù hợp với phong cách sống riêng'), $projectFallback), 'en' => $withItems($heading('Projects for you'), $projectFallback)]],
            ['block_type' => 'bds702_consultation', 'label' => 'Đăng ký tư vấn', 'description' => 'Form liên hệ gửi về hệ thống CMS.', 'preview_image' => $preview, 'anchor_id' => 'lien-he', 'settings' => ['background_image' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?auto=format&fit=crop&w=2200&q=85'], 'settings_schema' => ['background_image' => ['type' => 'image', 'label' => 'Ảnh nền']], 'data' => ['vi' => $heading('Đăng ký tư vấn miễn phí', 'Chuyên viên sẽ liên hệ trong thời gian sớm nhất', 'Hãy để lại thông tin và nhu cầu, đội ngũ tư vấn sẽ đồng hành cùng bạn.', 'Gửi đăng ký'), 'en' => $heading('Free consultation', 'Our consultant will contact you shortly')]],
            ['block_type' => 'bds702_partners', 'label' => 'Đối tác chiến lược', 'description' => 'Logo đối tác lấy trực tiếp từ CMS Partners.', 'preview_image' => $preview, 'anchor_id' => 'doi-tac', 'dynamic' => true, 'settings' => ['source' => 'cms_partners', 'limit' => 8], 'settings_schema' => ['source' => ['type' => 'select', 'label' => 'Nguồn dữ liệu', 'options' => [['value' => 'cms_partners', 'label' => 'Đối tác CMS'], ['value' => 'custom', 'label' => 'Nhập thủ công']]], 'limit' => ['type' => 'number', 'label' => 'Số đối tác']], 'data' => ['vi' => $heading('Đối tác chiến lược', 'Cùng kiến tạo những giá trị bền vững'), 'en' => $heading('Strategic partners')]],
        ];
    }

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
