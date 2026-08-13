<?php

namespace App\Core\Themes;

use App\Models\CatalogProduct;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPartner;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsService;
use App\Models\CmsTestimonial;
use App\Models\LandingPage;
use App\Models\LandingPageBlock;
use App\Models\LandingPageBlockData;
use App\Models\SiteProfile;
use App\Models\ThemeDemoRecord;
use App\Support\FrontendRouteUrl;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\SiteContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThemeDemoWebsiteFinalizer
{
    private const SUPPLEMENTAL_PRESET_PREFIX = 'website-shell:';

    /**
     * @var array<class-string<Model>, string>
     */
    private const SUPPLEMENTAL_MODELS = [
        CmsTestimonial::class => 'testimonials',
        CmsPartner::class => 'partners',
        CmsMenu::class => 'menus',
        CmsPage::class => 'pages',
        LandingPage::class => 'landing_pages',
    ];

    public function __construct(
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly SiteContext $siteContext,
    ) {}

    /**
     * Remove only the records added by this finalization layer.
     *
     * @return array<string, int>
     */
    public function purge(?string $themeKey = null): array
    {
        $query = ThemeDemoRecord::query()
            ->where('preset_key', 'like', self::SUPPLEMENTAL_PRESET_PREFIX.'%');

        if (filled($themeKey)) {
            $query->where('theme_key', strtoupper(trim((string) $themeKey)));
        }

        $records = $query->get();
        $counts = array_fill_keys(array_values(self::SUPPLEMENTAL_MODELS), 0);

        foreach (self::SUPPLEMENTAL_MODELS as $modelClass => $countKey) {
            $ids = $records
                ->where('model_type', $modelClass)
                ->pluck('model_id')
                ->filter()
                ->values()
                ->all();

            if ($ids === []) {
                continue;
            }

            $counts[$countKey] = $modelClass::query()->whereKey($ids)->count();
            $modelClass::query()->whereKey($ids)->delete();
        }

        if ($records->isNotEmpty()) {
            ThemeDemoRecord::query()->whereKey($records->pluck('id')->all())->delete();
        }

        return $counts;
    }

    /**
     * Complete a provider-specific or generic demo with a usable website shell.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    public function finalize(string $themeKey, string $presetKey, array $result): array
    {
        return DB::transaction(function () use ($themeKey, $presetKey, $result): array {
            $themeKey = strtoupper(trim($themeKey));
            $websiteKey = $this->siteContext->websiteKey();
            $profile = SiteProfile::query()->first();
            $landingResult = $this->resolveDemoLandingPage($themeKey, $presetKey, $websiteKey);
            $aboutResult = $this->resolveAboutPage($themeKey, $presetKey, $profile);
            $menuCreated = $this->normalizePrimaryMenu(
                $themeKey,
                $presetKey,
                $profile,
                $aboutResult['page'],
                $landingResult['page'],
            );
            $cmsSections = $this->normalizeCmsSections(
                $themeKey,
                $presetKey,
                $profile,
                $landingResult['page'],
                $landingResult['is_demo_owned'],
            );

            $additions = [
                'pages' => $aboutResult['created'] ? 1 : 0,
                'menus' => $menuCreated ? 1 : 0,
                'landing_pages' => $landingResult['created'] ? 1 : 0,
                'testimonials' => $cmsSections['testimonials'],
                'partners' => $cmsSections['partners'],
            ];

            foreach ($additions as $key => $count) {
                if ($count < 1) {
                    continue;
                }

                data_set(
                    $result,
                    'counts.'.$key,
                    (int) data_get($result, 'counts.'.$key, 0) + $count,
                );
            }

            $result['website_shell'] = [
                'about_page' => $aboutResult['page']->slug,
                'primary_menu' => true,
                'testimonials_source' => $cmsSections['has_testimonial_blocks']
                    ? 'cms_testimonials'
                    : null,
                'partners_source' => $cmsSections['has_partner_blocks']
                    ? 'cms_partners'
                    : null,
            ];

            return $result;
        });
    }

    /**
     * @return array{page:?LandingPage,created:bool,is_demo_owned:bool}
     */
    private function resolveDemoLandingPage(
        string $themeKey,
        string $presetKey,
        string $websiteKey,
    ): array {
        if (! $this->landingPageBuilder->supportsTheme($themeKey)) {
            return ['page' => null, 'created' => false, 'is_demo_owned' => false];
        }

        $existing = LandingPage::query()
            ->where('website_key', $websiteKey)
            ->where('theme_key', $themeKey)
            ->where('is_home', true)
            ->first();
        $page = $this->landingPageBuilder->resolveHome($websiteKey, $themeKey, true);
        $created = $existing === null && $page !== null;

        if ($created) {
            $this->recordSupplemental($page, $themeKey, $presetKey);
        }

        $isDemoOwned = $page !== null
            && ThemeDemoRecord::query()
                ->where('theme_key', $themeKey)
                ->where('model_type', LandingPage::class)
                ->where('model_id', $page->getKey())
                ->exists();

        return [
            'page' => $page?->loadMissing('blocks.data'),
            'created' => $created,
            'is_demo_owned' => $isDemoOwned,
        ];
    }

    /**
     * @return array{page:CmsPage,created:bool}
     */
    private function resolveAboutPage(
        string $themeKey,
        string $presetKey,
        ?SiteProfile $profile,
    ): array {
        $page = CmsPage::query()->where('slug', 'gioi-thieu')->first();

        if ($page === null) {
            $ownedPageIds = ThemeDemoRecord::query()
                ->where('theme_key', $themeKey)
                ->where('model_type', CmsPage::class)
                ->pluck('model_id');

            $page = CmsPage::query()
                ->whereKey($ownedPageIds)
                ->where('slug', 'like', '%gioi-thieu')
                ->latest('id')
                ->first();
        }

        if ($page !== null) {
            return ['page' => $page, 'created' => false];
        }

        $companyName = trim((string) data_get($profile?->branding, 'company_name'))
            ?: trim((string) $profile?->site_name)
            ?: 'doanh nghiệp';
        $websiteType = (string) ($profile?->website_type ?: 'business');
        $description = $this->aboutDescription($websiteType, $companyName);
        $page = CmsPage::query()->create([
            'title' => 'Giới thiệu '.$companyName,
            'slug' => 'gioi-thieu',
            'status' => 'published',
            'excerpt' => $description,
            'body' => '<h2>Về '.$companyName.'</h2><p>'.$description.'</p><p>Chúng tôi đặt chất lượng, sự minh bạch và trải nghiệm khách hàng làm nền tảng cho mọi sản phẩm và dịch vụ.</p>',
            'meta_title' => 'Giới thiệu | '.$companyName,
            'meta_description' => $description,
            'template' => 'default',
            'publish_at' => now(),
        ]);
        $this->recordSupplemental($page, $themeKey, $presetKey);

        return ['page' => $page, 'created' => true];
    }

    private function aboutDescription(string $websiteType, string $companyName): string
    {
        return match ($websiteType) {
            'real_estate' => $companyName.' cung cấp thông tin bất động sản minh bạch và giải pháp tư vấn phù hợp cho nhu cầu an cư, đầu tư.',
            'ecommerce' => $companyName.' tuyển chọn sản phẩm chất lượng, chính sách rõ ràng và dịch vụ hỗ trợ tận tâm.',
            'service' => $companyName.' cung cấp giải pháp chuyên nghiệp, linh hoạt và đồng hành cùng khách hàng trong suốt quá trình triển khai.',
            default => $companyName.' phát triển sản phẩm và dịch vụ dựa trên chất lượng, uy tín và giá trị bền vững.',
        };
    }

    private function normalizePrimaryMenu(
        string $themeKey,
        string $presetKey,
        ?SiteProfile $profile,
        CmsPage $aboutPage,
        ?LandingPage $landingPage,
    ): bool {
        $ownedMenuIds = ThemeDemoRecord::query()
            ->where('theme_key', $themeKey)
            ->where('model_type', CmsMenu::class)
            ->pluck('model_id');
        $menu = CmsMenu::query()
            ->whereKey($ownedMenuIds)
            ->whereIn('location', ['primary-navigation', 'primary'])
            ->latest('updated_at')
            ->latest('id')
            ->first();

        if ($menu === null) {
            $userMenu = CmsMenu::query()
                ->whereIn('location', ['primary-navigation', 'primary'])
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($userMenu !== null) {
                return false;
            }

            $menu = CmsMenu::query()->create([
                'name' => $themeKey.' Primary Navigation',
                'location' => $themeKey === 'DN302' ? 'primary' : 'primary-navigation',
                'items' => [],
            ]);
            $this->recordSupplemental($menu, $themeKey, $presetKey);
            $created = true;
        } else {
            $created = false;
        }

        // NEWS88 ships a category-led editorial menu whose labels and targets
        // are part of its localized demo contract. Do not replace that owned
        // menu with the generic business/ecommerce navigation sequence.
        if ($themeKey === 'NEWS88' && collect($menu->items)->isNotEmpty()) {
            return $created;
        }

        $menu->forceFill([
            'items' => $this->primaryMenuItems(
                $themeKey,
                (string) ($profile?->website_type ?: 'business'),
                $aboutPage,
                $landingPage,
            ),
        ])->save();

        return $created;
    }

    /**
     * @return list<array{label:string,url:string,target:string}>
     */
    private function primaryMenuItems(
        string $themeKey,
        string $websiteType,
        CmsPage $aboutPage,
        ?LandingPage $landingPage,
    ): array {
        $capabilities = $this->contentCapabilities($themeKey, $landingPage);
        $links = [
            'home' => [
                'label' => 'Trang chủ',
                'url' => FrontendRouteUrl::homePath(),
                'link_type' => 'home',
            ],
            'about' => [
                'label' => 'Giới thiệu',
                'url' => FrontendRouteUrl::pagePath($aboutPage->slug),
                'link_type' => 'page',
                'link_value' => (string) $aboutPage->id,
                'resource_type' => 'cms_page',
                'resource_id' => (string) $aboutPage->id,
            ],
            'products' => [
                'label' => 'Sản phẩm',
                'url' => FrontendRouteUrl::catalogSearchPath(),
                'link_type' => 'catalog-index',
            ],
            'services' => [
                'label' => 'Dịch vụ',
                'url' => FrontendRouteUrl::servicesPath(),
                'link_type' => 'service-index',
            ],
            'projects' => [
                'label' => 'Dự án',
                'url' => FrontendRouteUrl::projectsPath(),
                'link_type' => 'project-index',
            ],
            'listings' => [
                'label' => 'Tin rao',
                'url' => FrontendRouteUrl::realEstatePath(),
                'link_type' => 'real-estate-index',
            ],
            'news' => [
                'label' => 'Tin tức',
                'url' => FrontendRouteUrl::blogPath(),
                'link_type' => 'post-index',
            ],
            'contact' => [
                'label' => 'Liên hệ',
                'url' => FrontendRouteUrl::contactPath(),
                'link_type' => 'contact',
            ],
        ];
        $order = match ($websiteType) {
            'real_estate' => ['home', 'listings', 'projects', 'news', 'about', 'contact'],
            'ecommerce' => ['home', 'about', 'products', 'services', 'news', 'contact'],
            default => ['home', 'about', 'services', 'projects', 'products', 'news', 'contact'],
        };

        return collect($order)
            ->filter(function (string $key) use ($capabilities, $websiteType): bool {
                if (in_array($key, ['home', 'about', 'contact'], true)) {
                    return true;
                }

                if ($key === 'listings') {
                    return $websiteType === 'real_estate';
                }

                return $capabilities[$key] ?? false;
            })
            ->map(fn (string $key): array => [
                ...$links[$key],
                'target' => '_self',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{products:bool,services:bool,projects:bool,news:bool}
     */
    private function contentCapabilities(string $themeKey, ?LandingPage $landingPage): array
    {
        $blockTypes = $landingPage?->blocks
            ->pluck('block_type')
            ->map(fn (mixed $type): string => strtolower((string) $type))
            ->all() ?? [];
        $contains = static fn (array $needles): bool => collect($blockTypes)
            ->contains(fn (string $type): bool => Str::contains($type, $needles));

        return [
            'products' => $this->hasThemeCompatibleContent(
                $themeKey,
                CatalogProduct::class,
                'is_active',
                true,
            )
                || $contains(['product', 'catalog']),
            'services' => $this->hasThemeCompatibleContent(
                $themeKey,
                CmsService::class,
                'status',
                'published',
            )
                || $contains(['service']),
            'projects' => $this->hasThemeCompatibleContent(
                $themeKey,
                CmsProject::class,
                'status',
                'published',
            )
                || $contains(['project']),
            'news' => $this->hasThemeCompatibleContent(
                $themeKey,
                CmsPost::class,
                'status',
                'published',
            )
                || $contains(['post', 'news', 'blog']),
        ];
    }

    /**
     * User content is reusable across themes. Demo content owned by another
     * theme must not influence the current theme's navigation profile.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function hasThemeCompatibleContent(
        string $themeKey,
        string $modelClass,
        string $field,
        mixed $value,
    ): bool {
        $excludedIds = ThemeDemoRecord::query()
            ->where('model_type', $modelClass)
            ->where('theme_key', '!=', $themeKey)
            ->select('model_id');
        $query = $modelClass::query()->where($field, $value);

        return $query
            ->whereNotIn($query->getModel()->getQualifiedKeyName(), $excludedIds)
            ->exists();
    }

    /**
     * @return array{
     *     testimonials:int,
     *     partners:int,
     *     has_testimonial_blocks:bool,
     *     has_partner_blocks:bool
     * }
     */
    private function normalizeCmsSections(
        string $themeKey,
        string $presetKey,
        ?SiteProfile $profile,
        ?LandingPage $landingPage,
        bool $isDemoOwned,
    ): array {
        $empty = [
            'testimonials' => 0,
            'partners' => 0,
            'has_testimonial_blocks' => false,
            'has_partner_blocks' => false,
        ];

        if ($landingPage === null || ! $isDemoOwned) {
            return $empty;
        }

        $testimonialBlocks = $landingPage->blocks
            ->filter(fn (LandingPageBlock $block): bool => $this->isTestimonialBlock($block->block_type));
        $partnerBlocks = $landingPage->blocks
            ->filter(fn (LandingPageBlock $block): bool => $this->isPartnerBlock($block->block_type));

        $this->setCmsSource($testimonialBlocks, 'cms_testimonials');
        $this->setCmsSource($partnerBlocks, 'cms_partners');

        return [
            'testimonials' => $this->seedTestimonials(
                $themeKey,
                $presetKey,
                $profile,
                $testimonialBlocks,
            ),
            'partners' => $this->seedPartners(
                $themeKey,
                $presetKey,
                $profile,
                $partnerBlocks,
            ),
            'has_testimonial_blocks' => $testimonialBlocks->isNotEmpty(),
            'has_partner_blocks' => $partnerBlocks->isNotEmpty(),
        ];
    }

    /**
     * @param  Collection<int, LandingPageBlock>  $blocks
     */
    private function setCmsSource(Collection $blocks, string $source): void
    {
        $blocks->each(function (LandingPageBlock $block) use ($source): void {
            $block->forceFill([
                'settings' => [
                    ...(array) $block->settings,
                    'source' => $source,
                    'featured_only' => (bool) data_get($block->settings, 'featured_only', true),
                ],
            ])->save();
        });
    }

    /**
     * @param  Collection<int, LandingPageBlock>  $blocks
     */
    private function seedTestimonials(
        string $themeKey,
        string $presetKey,
        ?SiteProfile $profile,
        Collection $blocks,
    ): int {
        if ($blocks->isEmpty()) {
            return 0;
        }

        $target = $this->targetCount($blocks, 3);
        $ownedCount = $this->ownedModelCount($themeKey, CmsTestimonial::class);
        $missing = max(0, $target - $ownedCount);

        if ($missing === 0) {
            return 0;
        }

        $company = trim((string) data_get($profile?->branding, 'company_name'))
            ?: trim((string) $profile?->site_name)
            ?: 'thương hiệu';
        $fallbacks = $this->fallbackItems($blocks);
        $defaults = [
            ['name' => 'Minh Anh', 'role' => 'Khách hàng', 'quote' => 'Đội ngũ tư vấn rõ ràng, hỗ trợ tận tâm và mang lại trải nghiệm tốt hơn mong đợi.'],
            ['name' => 'Thu Hà', 'role' => 'Khách hàng thân thiết', 'quote' => 'Chất lượng chỉn chu, quy trình minh bạch và phản hồi rất nhanh khi tôi cần hỗ trợ.'],
            ['name' => 'Hoàng Nam', 'role' => 'Đối tác doanh nghiệp', 'quote' => 'Một đơn vị đáng tin cậy, làm việc chuyên nghiệp và luôn giữ đúng cam kết.'],
        ];
        $images = [
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=500&q=85',
            'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=500&q=85',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=500&q=85',
        ];

        for ($index = 0; $index < $missing; $index++) {
            $item = (array) ($fallbacks->get($index) ?? $defaults[$index % count($defaults)]);
            [$nameFromTitle, $roleFromTitle] = $this->testimonialIdentityFromTitle(
                (string) ($item['title'] ?? ''),
            );
            $name = trim((string) ($item['name'] ?? $nameFromTitle))
                ?: $defaults[$index % count($defaults)]['name'];
            $role = trim((string) ($item['role'] ?? $roleFromTitle))
                ?: $defaults[$index % count($defaults)]['role'];
            $quote = trim((string) (
                $item['quote']
                ?? $item['summary']
                ?? $item['description']
                ?? $defaults[$index % count($defaults)]['quote']
            ));
            $image = $this->itemImage($item) ?: $images[$index % count($images)];
            $testimonial = CmsTestimonial::query()->create([
                'name' => $name,
                'role' => $role,
                'company' => $company,
                'quote' => $quote,
                'image_url' => $image,
                'image_alt' => $name,
                'link_url' => FrontendRouteUrl::contactPath(),
                'status' => 'published',
                'publish_at' => now(),
                'is_featured' => true,
                'sort_order' => $ownedCount + $index,
            ]);
            $this->recordSupplemental($testimonial, $themeKey, $presetKey);
        }

        return $missing;
    }

    /**
     * @param  Collection<int, LandingPageBlock>  $blocks
     */
    private function seedPartners(
        string $themeKey,
        string $presetKey,
        ?SiteProfile $profile,
        Collection $blocks,
    ): int {
        if ($blocks->isEmpty()) {
            return 0;
        }

        $target = $this->targetCount($blocks, 6);
        $ownedCount = $this->ownedModelCount($themeKey, CmsPartner::class);
        $missing = max(0, $target - $ownedCount);

        if ($missing === 0) {
            return 0;
        }

        $company = trim((string) data_get($profile?->branding, 'company_name'))
            ?: trim((string) $profile?->site_name)
            ?: 'thương hiệu';
        $fallbacks = $this->fallbackItems($blocks);
        $defaultNames = [
            'Đối tác chiến lược',
            'Đối tác công nghệ',
            'Đối tác vận hành',
            'Đối tác phân phối',
            'Đối tác tài chính',
            'Đối tác truyền thông',
        ];

        for ($index = 0; $index < $missing; $index++) {
            $item = (array) ($fallbacks->get($index) ?? []);
            $title = trim((string) ($item['title'] ?? $item['name'] ?? ''))
                ?: $defaultNames[$index % count($defaultNames)];
            $image = $this->itemImage($item)
                ?: '/theme-demo/dn202/partner-'.str_pad((string) (($index % 6) + 1), 2, '0', STR_PAD_LEFT).'.svg';
            $partner = CmsPartner::query()->create([
                'title' => $title,
                'slug' => Str::slug(strtolower($themeKey).'-'.$title.'-'.($ownedCount + $index + 1)),
                'description' => trim((string) ($item['description'] ?? $item['summary'] ?? 'Đối tác đồng hành cùng '.$company.'.')),
                'image_url' => $image,
                'image_alt' => $title,
                'link_url' => FrontendRouteUrl::homePath(),
                'status' => 'published',
                'publish_at' => now(),
                'is_featured' => true,
                'sort_order' => $ownedCount + $index,
            ]);
            $this->recordSupplemental($partner, $themeKey, $presetKey);
        }

        return $missing;
    }

    /**
     * @param  Collection<int, LandingPageBlock>  $blocks
     */
    private function targetCount(Collection $blocks, int $default): int
    {
        return max(
            1,
            min(
                12,
                (int) ($blocks
                    ->map(fn (LandingPageBlock $block): int => (int) data_get($block->settings, 'limit', $default))
                    ->max() ?: $default),
            ),
        );
    }

    /**
     * @param  Collection<int, LandingPageBlock>  $blocks
     * @return Collection<int, array<string, mixed>>
     */
    private function fallbackItems(Collection $blocks): Collection
    {
        return $blocks
            ->flatMap(function (LandingPageBlock $block): array {
                /** @var LandingPageBlockData|null $data */
                $data = $block->data->firstWhere('locale', 'vi') ?? $block->data->first();
                $content = json_decode((string) $data?->content, true);

                if (! is_array($content)) {
                    return [];
                }

                foreach (['items', 'testimonials', 'partners', 'reviews'] as $key) {
                    if (is_array($content[$key] ?? null)) {
                        return $content[$key];
                    }
                }

                return [];
            })
            ->filter(fn (mixed $item): bool => is_array($item))
            ->unique(fn (array $item): string => (string) json_encode(
                $item,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ))
            ->values();
    }

    /**
     * @return array{0:string,1:string}
     */
    private function testimonialIdentityFromTitle(string $title): array
    {
        $parts = preg_split('/\s*[·|\-]\s*/u', trim($title), 2) ?: [];

        return [
            trim((string) ($parts[0] ?? '')),
            trim((string) ($parts[1] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemImage(array $item): ?string
    {
        foreach (['image', 'image_url', 'avatar', 'thumbnail', 'logo'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function ownedModelCount(string $themeKey, string $modelClass): int
    {
        $ids = ThemeDemoRecord::query()
            ->where('theme_key', $themeKey)
            ->where('model_type', $modelClass)
            ->pluck('model_id');

        return $modelClass::query()->whereKey($ids)->count();
    }

    private function isTestimonialBlock(string $blockType): bool
    {
        $blockType = strtolower($blockType);

        return Str::contains($blockType, 'testimonial')
            || $blockType === 'ec902_video_reviews';
    }

    private function isPartnerBlock(string $blockType): bool
    {
        return Str::contains(strtolower($blockType), 'partner');
    }

    private function recordSupplemental(
        Model $model,
        string $themeKey,
        string $presetKey,
    ): void {
        ThemeDemoRecord::query()->updateOrCreate(
            [
                'model_type' => $model::class,
                'model_id' => $model->getKey(),
            ],
            [
                'theme_key' => $themeKey,
                'preset_key' => self::SUPPLEMENTAL_PRESET_PREFIX.$presetKey,
            ],
        );
    }
}
