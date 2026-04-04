<?php

namespace App\Http\Controllers\Site;

use App\Core\Themes\ThemeRegistry;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\Customer;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\Order;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Support\OrderConfirmationSender;
use App\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CmsSiteController
{
    private const DEFAULT_BRAND_ASSET = 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png';
    private const DEFAULT_WEBSITE_KEY = 'website-main';

    public function __construct(
        private readonly ThemeRegistry $themeRegistry,
        private readonly StorefrontCart $storefrontCart,
        private readonly OrderConfirmationSender $orderConfirmationSender,
    ) {
    }

    public function home(): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $menus = $this->resolveMenus($this->resolveWebsiteKey($siteProfile));

        if ($themeHomeView = $this->resolveThemeHomeView($activeTheme)) {
            return view($themeHomeView, [
                'siteProfile' => $siteProfile,
                'activeTheme' => $activeTheme,
                'menus' => $menus,
                'themeHomeData' => $this->resolveThemeHomeData($siteProfile, $activeTheme, $menus),
            ]);
        }

        $page = CmsPage::query()->with('featuredMedia')->where('slug', 'home')->where('status', 'published')->first();

        if ($page) {
            return $this->renderContent('page', $page, [
                'siteProfile' => $siteProfile,
                'activeTheme' => $activeTheme,
                'latestPosts' => CmsPost::query()->where('status', 'published')->latest('publish_at')->take(3)->get(),
            ]);
        }

        return view('site');
    }

    public function page(string $slug): View
    {
        $page = CmsPage::query()->with('featuredMedia')->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return $this->renderContent('page', $page);
    }

    public function postsIndex(): View
    {
        $posts = CmsPost::query()->with(['category', 'featuredMedia'])->where('status', 'published')->latest('publish_at')->paginate(10);

        return $this->renderListing('posts', 'Tin tức', 'Danh sách bài viết đã xuất bản.', $posts);
    }

    public function post(string $slug): View
    {
        $post = CmsPost::query()->with(['category', 'featuredMedia'])->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return $this->renderContent('post', $post);
    }

    public function category(Request $request, string $slug): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        $categoryQuery = CatalogCategory::query()->with(['parent', 'children' => function ($query) use ($websiteKey): void {
            $this->applyWebsiteScope($query, $websiteKey);
            $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
        }]);
        $this->applyWebsiteScope($categoryQuery, $websiteKey);

        $category = $categoryQuery->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $sidebarRootCategory = $this->resolveTopAncestorCategory($category, $websiteKey);
        $sidebarCategories = $this->resolveCategorySidebarItems($sidebarRootCategory, $category, $websiteKey);

        $categoryIds = $category->children->pluck('id')->prepend($category->id)->all();
        $baseProductsQuery = CatalogProduct::query()->with(['category', 'images'])->where('is_active', true)->whereIn('catalog_category_id', $categoryIds);
        $this->applyWebsiteScope($baseProductsQuery, $websiteKey);

        $availableMinPrice = (int) floor((float) ((clone $baseProductsQuery)->min('price') ?? 0));
        $availableMaxPrice = (int) ceil((float) ((clone $baseProductsQuery)->max('price') ?? 0));

        $selectedMinPrice = $request->filled('min_price') ? (int) $request->query('min_price') : $availableMinPrice;
        $selectedMaxPrice = $request->filled('max_price') ? (int) $request->query('max_price') : $availableMaxPrice;

        if ($availableMaxPrice > 0) {
            $selectedMinPrice = max($availableMinPrice, min($selectedMinPrice, $availableMaxPrice));
            $selectedMaxPrice = max($availableMinPrice, min($selectedMaxPrice, $availableMaxPrice));
        }

        if ($selectedMinPrice > $selectedMaxPrice) {
            [$selectedMinPrice, $selectedMaxPrice] = [$selectedMaxPrice, $selectedMinPrice];
        }

        $sort = (string) $request->query('sort', 'default');
        $allowedSorts = ['default', 'bestseller', 'price_asc', 'price_desc', 'newest'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'default';
        }

        $productsQuery = clone $baseProductsQuery;

        if ($availableMaxPrice > 0) {
            $productsQuery->whereBetween('price', [$selectedMinPrice, $selectedMaxPrice]);
        }

        match ($sort) {
            'bestseller' => $productsQuery->orderByDesc('sold_count')->orderByDesc('created_at'),
            'price_asc' => $productsQuery->orderBy('price')->orderByDesc('created_at'),
            'price_desc' => $productsQuery->orderByDesc('price')->orderByDesc('created_at'),
            'newest', 'default' => $productsQuery->latest('created_at'),
        };

        $products = $productsQuery->take(24)->get();

        return $this->renderThemeCatalogView('category', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'category' => $category,
            'sidebarCategories' => $sidebarCategories,
            'products' => $products->map(fn (CatalogProduct $product): array => $this->mapProductCard($product))->all(),
            'childCategories' => $category->children->map(fn (CatalogCategory $child): array => [
                'name' => $child->name,
                'slug' => $child->slug,
                'url' => $this->categoryUrl($child->slug),
            ])->all(),
            'filters' => [
                'sort' => $sort,
                'available_min_price' => $availableMinPrice,
                'available_max_price' => $availableMaxPrice,
                'selected_min_price' => $selectedMinPrice,
                'selected_max_price' => $selectedMaxPrice,
            ],
        ]);
    }

    private function resolveTopAncestorCategory(CatalogCategory $category, string $websiteKey): CatalogCategory
    {
        $current = $category;

        while ($current->parent_id) {
            $parentQuery = CatalogCategory::query()->whereKey($current->parent_id)->where('is_active', true);
            $this->applyWebsiteScope($parentQuery, $websiteKey);

            $parent = $parentQuery->first();

            if (! $parent) {
                break;
            }

            $current = $parent;
        }

        return $current->load(['children' => function ($query) use ($websiteKey): void {
            $this->applyWebsiteScope($query, $websiteKey);
            $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
        }]);
    }

    private function resolveCategorySidebarItems(CatalogCategory $sidebarRootCategory, CatalogCategory $currentCategory, string $websiteKey): array
    {
        $items = $sidebarRootCategory->children->isNotEmpty()
            ? $sidebarRootCategory->children
            : collect([$sidebarRootCategory]);

        return $items->map(function (CatalogCategory $item) use ($currentCategory, $websiteKey): array {
            $categoryIds = $item->children()->pluck('id')->prepend($item->id)->all();
            $productCountQuery = CatalogProduct::query()->where('is_active', true)->whereIn('catalog_category_id', $categoryIds);
            $this->applyWebsiteScope($productCountQuery, $websiteKey);

            return [
                'label' => $item->name,
                'url' => $this->categoryUrl($item->slug),
                'count' => $productCountQuery->count(),
                'active' => $item->id === $currentCategory->id,
            ];
        })->all();
    }

    public function product(string $slug): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        $productQuery = CatalogProduct::query()->with(['category.parent', 'images']);
        $this->applyWebsiteScope($productQuery, $websiteKey);

        $product = $productQuery->where('slug', $slug)->where('is_active', true)->firstOrFail();

        $relatedProductsQuery = CatalogProduct::query()->with(['category', 'images'])->where('is_active', true)->where('id', '!=', $product->id);
        $this->applyWebsiteScope($relatedProductsQuery, $websiteKey);

        if ($product->catalog_category_id !== null) {
            $relatedProductsQuery->where('catalog_category_id', $product->catalog_category_id);
        }

        $relatedProducts = $relatedProductsQuery->latest('created_at')->take(8)->get();

        return $this->renderThemeCatalogView('product', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'product' => $this->mapProductCard($product),
            'productModel' => $product,
            'productGallery' => $this->resolveProductGallery($product),
            'productHighlights' => $this->splitTextLines($product->highlights),
            'usageTerms' => $this->splitTextLines($product->usage_terms),
            'usageLocationLines' => $this->splitTextLines($product->usage_location),
            'detailParagraphs' => $this->splitTextParagraphs($product->detail_content),
            'relatedProducts' => $relatedProducts->map(fn (CatalogProduct $item): array => $this->mapProductCard($item))->all(),
        ]);
    }

    public function cart(Request $request): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        return $this->renderThemeCatalogView('cart', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'checkoutMode' => $request->boolean('checkout'),
        ]);
    }

    public function addToCart(Request $request, string $slug): RedirectResponse
    {
        $product = $this->resolvePurchasableProduct($slug);
        $quantity = $this->validateCartQuantity($request, $product);

        $this->storefrontCart->add($product, $quantity);

        return back()->with('cart_success', 'Đã thêm '.$quantity.' sản phẩm vào giỏ hàng.');
    }

    public function buyNow(Request $request, string $slug): RedirectResponse
    {
        $product = $this->resolvePurchasableProduct($slug);
        $quantity = $this->validateCartQuantity($request, $product);

        $this->storefrontCart->add($product, $quantity);

        return to_route('site.checkout.index')
            ->with('cart_success', 'Đã thêm sản phẩm vào giỏ và chuyển bạn tới bước thanh toán.');
    }

    public function updateCartItem(Request $request, int $productId): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $item = $this->storefrontCart->update($productId, (int) $validated['quantity']);

        abort_if($item === null, 404);

        return back()->with('cart_success', 'Đã cập nhật số lượng sản phẩm trong giỏ hàng.');
    }

    public function removeCartItem(int $productId): RedirectResponse
    {
        $this->storefrontCart->remove($productId);

        return back()->with('cart_success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        if (! $this->storefrontCart->hasItems()) {
            return to_route('site.cart.index')->with('cart_success', 'Giỏ hàng đang trống, chưa thể thanh toán.');
        }

        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        return $this->renderThemeCatalogView('checkout', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'checkoutForm' => $this->resolveCheckoutFormDefaults($request),
            'paymentMethods' => $this->paymentMethodOptions(),
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        if (! $this->storefrontCart->hasItems()) {
            return to_route('site.cart.index')->with('cart_success', 'Giỏ hàng đang trống, chưa thể thanh toán.');
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'delivery_address' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cod,bank_transfer,pickup'],
        ]);

        $siteProfile = SiteProfile::query()->first();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $cartSummary = $this->storefrontCart->summary();
        /** @var Customer|null $customer */
        $customer = $request->user('customer');

        $order = Order::query()->create([
            'order_code' => 'AIO'.now()->format('ymdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            'customer_id' => $customer?->id,
            'status' => 'placed',
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['customer_phone'],
            'customer_email' => $validated['customer_email'] ?? null,
            'delivery_address' => $validated['delivery_address'],
            'note' => $validated['note'] ?? null,
            'payment_method' => $validated['payment_method'],
            'payment_label' => $this->paymentMethodOptions()[$validated['payment_method']]['label'] ?? $validated['payment_method'],
            'subtotal' => $cartSummary['subtotal'],
            'item_count' => $cartSummary['count'],
            'placed_at' => now(),
            'website_key' => $websiteKey,
            'owner_key' => 'owner-system',
            'tenant_key' => 'tenant-a',
        ]);

        $order->items()->createMany(collect($cartSummary['items'])->map(function (array $item): array {
            return [
                'catalog_product_id' => $item['product_id'] ?? null,
                'product_name' => $item['title'] ?? 'Sản phẩm',
                'product_slug' => $item['slug'] ?? null,
                'sku' => $item['sku'] ?? null,
                'unit_price' => (float) ($item['price'] ?? 0),
                'original_price' => $item['old_price'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'line_total' => ((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 1)),
                'image_url' => $item['image'] ?? null,
            ];
        })->all());

        $this->storefrontCart->clear();
        $this->orderConfirmationSender->send($order->load('items'));

        return to_route('site.checkout.success', ['order' => $order->id])->with('cart_success', 'Đơn hàng đã được ghi nhận thành công.');
    }

    public function checkoutSuccess(Order $order): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        return $this->renderThemeCatalogView('checkout-success', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'order' => $order->load('items'),
        ]);
    }

    public function previewPage(Request $request, CmsPage $page): View
    {
        abort_unless(in_array('cms.publish', $request->user('admin')?->permissions() ?? [], true), 403);

        return $this->renderContent('page', $page->load('featuredMedia'), ['isPreview' => true]);
    }

    public function previewPost(Request $request, CmsPost $post): View
    {
        abort_unless(in_array('cms.publish', $request->user('admin')?->permissions() ?? [], true), 403);

        return $this->renderContent('post', $post->load(['category', 'featuredMedia']), ['isPreview' => true]);
    }

    private function renderContent(string $contentType, object $entry, array $extra = []): View
    {
        $siteProfile = $extra['siteProfile'] ?? SiteProfile::query()->first();
        $activeTheme = $extra['activeTheme'] ?? $this->resolveActiveTheme($siteProfile);

        return view('site-cms', array_merge([
            'contentType' => $contentType,
            'entry' => $entry,
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $this->resolveMenus($this->resolveWebsiteKey($siteProfile)),
            'isPreview' => false,
            'pageTitle' => $entry->meta_title ?: $entry->title,
            'pageDescription' => $entry->meta_description ?: ($entry->excerpt ?? null),
        ], $extra));
    }

    private function renderListing(string $contentType, string $title, string $description, mixed $items): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);

        return view('site-cms', [
            'contentType' => $contentType,
            'listingItems' => $items,
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $this->resolveMenus($this->resolveWebsiteKey($siteProfile)),
            'isPreview' => false,
            'pageTitle' => $title,
            'pageDescription' => $description,
        ]);
    }

    private function resolveMenus(?string $websiteKey = null): array
    {
        $query = CmsMenu::query()->orderByDesc('updated_at')->orderByDesc('id');

        if ($websiteKey !== null) {
            $this->applyWebsiteScope($query, $websiteKey);
        }

        return $query->get()
            ->groupBy('location')
            ->map(fn (Collection $items): array => $items->first()?->items ?? [])
            ->all();
    }

    private function resolveActiveTheme(?SiteProfile $siteProfile): ?array
    {
        if (! $siteProfile?->active_theme_key) {
            return null;
        }

        /** @var array<string, mixed>|null $activeTheme */
        $activeTheme = $this->themeRegistry->all()->firstWhere('key', $siteProfile->active_theme_key);

        return $activeTheme;
    }

    private function resolveThemeHomeView(?array $activeTheme): ?string
    {
        $themeKey = strtolower((string) ($activeTheme['key'] ?? ''));

        if ($themeKey === '') {
            return null;
        }

        $viewName = "theme-{$themeKey}::home";

        return view()->exists($viewName) ? $viewName : null;
    }

    private function resolveThemeHomeData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        if (($activeTheme['key'] ?? null) !== 'TH0001') {
            return [];
        }

        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $shellData = $this->resolveThemeShellData($siteProfile, $activeTheme, $menus);
        $themeKey = (string) ($activeTheme['key'] ?? 'TH0001');

        $parentCategories = CatalogCategory::query()
            ->with(['children' => function ($query) use ($websiteKey): void {
                $this->applyWebsiteScope($query, $websiteKey);
                $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
        $this->applyWebsiteScope($parentCategories, $websiteKey);

        $parentCategories = $parentCategories->take(10)->get();

        $heroBanner = $this->resolveHeroBanner($websiteKey, $themeKey);
        $sideBanners = $this->resolveSideBanners($websiteKey, $themeKey);
        $featuredProducts = $this->resolveFeaturedProducts($websiteKey);
        $sections = $this->resolveSections($parentCategories, $websiteKey);

        return [
            ...$shellData,
            'hero_banner' => $heroBanner,
            'side_banners' => $sideBanners,
            'featured_products' => $featuredProducts,
            'featured_title' => collect($featuredProducts)->contains(fn (array $product): bool => (bool) ($product['is_featured'] ?? false))
                ? 'Sản phẩm nổi bật'
                : 'Sản phẩm mới nhất',
            'sections' => $sections,
            'brand_highlights' => $this->resolveBrandHighlights($parentCategories),
        ];
    }

    private function resolveThemeShellData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        $branding = array_merge([
            'company_name' => $siteProfile?->site_name ?? 'AIO Website',
            'logo_url' => self::DEFAULT_BRAND_ASSET,
            'favicon_url' => self::DEFAULT_BRAND_ASSET,
            'primary_color' => '#ef2b2d',
        ], $siteProfile?->branding ?? []);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $themeKey = (string) ($activeTheme['key'] ?? 'TH0001');

        $parentCategories = CatalogCategory::query()
            ->with(['children' => function ($query) use ($websiteKey): void {
                $this->applyWebsiteScope($query, $websiteKey);
                $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
            }])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
        $this->applyWebsiteScope($parentCategories, $websiteKey);

        $parentCategories = $parentCategories->take(10)->get();

        return [
            'branding' => $branding,
            'top_menu' => $this->resolveTopMenuItems($menus),
            'product_menu' => $this->resolveProductMenuItems($menus, $parentCategories),
            'side_banners' => $this->resolveSideBanners($websiteKey, $themeKey),
            'cart_summary' => $this->storefrontCart->summary(),
        ];
    }

    private function resolveWebsiteKey(?SiteProfile $siteProfile): string
    {
        $branding = $siteProfile?->branding ?? [];

        return (string) ($branding['website_key'] ?? self::DEFAULT_WEBSITE_KEY);
    }

    private function applyWebsiteScope($query, string $websiteKey): void
    {
        $query->where(function ($builder) use ($websiteKey): void {
            $builder->where('website_key', $websiteKey)
                ->orWhereNull('website_key');
        });
    }

    private function resolveTopMenuItems(array $menus): array
    {
        $items = collect($menus['primary-navigation'] ?? $menus['primary'] ?? [])
            ->filter(function (mixed $item): bool {
                if (! is_array($item)) {
                    return false;
                }

                $label = mb_strtolower((string) ($item['label'] ?? ''));
                $url = mb_strtolower((string) ($item['url'] ?? ''));

                return str_contains($label, 'tin')
                    || str_contains($label, 'giới')
                    || str_contains($label, 'gioi')
                    || str_contains($label, 'liên')
                    || str_contains($label, 'lien')
                    || str_contains($url, 'tin-tuc')
                    || str_contains($url, 'gioi-thieu')
                    || str_contains($url, 'lien-he');
            })
            ->values();

        if ($items->isEmpty()) {
            return [
                ['label' => 'Tin tức', 'url' => '/tin-tuc', 'target' => '_self'],
                ['label' => 'Giới thiệu', 'url' => '/gioi-thieu', 'target' => '_self'],
                ['label' => 'Liên hệ', 'url' => '/lien-he', 'target' => '_self'],
            ];
        }

        return $items->all();
    }

    private function resolveProductMenuItems(array $menus, Collection $parentCategories): array
    {
        $configured = collect($menus['product-navigation'] ?? [])->filter(fn (mixed $item): bool => is_array($item))->values();

        if ($configured->isNotEmpty()) {
            return $configured->map(function (array $item, int $index): array {
                return [
                    'label' => $item['label'] ?? 'Danh mục',
                    'url' => $item['url'] ?? '#',
                    'target' => $item['target'] ?? '_self',
                    'icon' => $item['icon'] ?? ($index === 0 ? '🔥' : '▣'),
                    'highlight' => (bool) ($item['highlight'] ?? false),
                    'children' => collect($item['children'] ?? [])->filter(fn (mixed $child): bool => is_array($child))->values()->all(),
                ];
            })->all();
        }

        return $parentCategories->map(function (CatalogCategory $parent, int $index): array {
            return [
                'label' => $parent->name,
                'url' => $this->categoryUrl($parent->slug),
                'target' => '_self',
                'icon' => $index === 0 ? '🔥' : '▣',
                'highlight' => $index === 0,
                'children' => $parent->children->map(fn (CatalogCategory $child): array => [
                    'label' => $child->name,
                    'url' => $this->categoryUrl($child->slug),
                    'target' => '_self',
                ])->all(),
            ];
        })->all();
    }

    private function resolveHeroBanner(string $websiteKey, string $themeKey): array
    {
        $query = SiteBanner::query()
            ->where('is_active', true)
            ->where('placement', 'hero-main')
            ->where(function (EloquentBuilder $builder) use ($themeKey): void {
                $builder->where('theme_key', $themeKey)->orWhereNull('theme_key');
            })
            ->orderByRaw('CASE WHEN theme_key = ? THEN 0 ELSE 1 END', [$themeKey])
            ->orderBy('sort_order');
        $this->applyWebsiteScope($query, $websiteKey);

        $banner = $query->first();

        if (! $banner) {
            return [
                'eyebrow' => 'Flash sale',
                'title' => 'Deal sốc cho sản phẩm mới',
                'summary' => 'Tạo data test từ trang quản lý theme để đổ nội dung thật cho TH0001.',
                'badge' => 'Chỉ từ 199K',
                'cta' => 'Mua ngay',
                'image' => 'https://picsum.photos/seed/th0001-default-hero/960/520',
                'link_url' => '#featured',
            ];
        }

        return [
            'eyebrow' => data_get($banner->metadata, 'eyebrow', 'Flash sale'),
            'title' => $banner->title ?? 'Deal nổi bật',
            'summary' => data_get($banner->metadata, 'summary', $banner->subtitle ?? ''),
            'badge' => $banner->badge ?? 'Ưu đãi hot',
            'cta' => data_get($banner->metadata, 'button_label', 'Mua ngay'),
            'image' => $banner->image_url,
            'link_url' => $banner->link_url ?: '#featured',
        ];
    }

    private function resolveSideBanners(string $websiteKey, string $themeKey): array
    {
        $query = SiteBanner::query()
            ->where('is_active', true)
            ->where('placement', 'hero-side')
            ->where(function (EloquentBuilder $builder) use ($themeKey): void {
                $builder->where('theme_key', $themeKey)->orWhereNull('theme_key');
            })
            ->orderByRaw('CASE WHEN theme_key = ? THEN 0 ELSE 1 END', [$themeKey])
            ->orderBy('sort_order');
        $this->applyWebsiteScope($query, $websiteKey);

        $items = $query->take(4)->get()->map(fn (SiteBanner $banner): array => [
            'title' => $banner->title ?? 'Banner phụ',
            'subtitle' => $banner->subtitle ?? '',
            'image' => $banner->image_url,
            'link_url' => $banner->link_url ?: '#featured',
        ])->all();

        if ($items !== []) {
            return $items;
        }

        return [
            ['title' => 'Voucher cuối tuần', 'subtitle' => 'Ưu đãi theo preset', 'image' => 'https://picsum.photos/seed/th0001-default-side-1/360/180', 'link_url' => '#featured'],
            ['title' => 'Hot trend', 'subtitle' => 'Block phụ 2', 'image' => 'https://picsum.photos/seed/th0001-default-side-2/360/180', 'link_url' => '#featured'],
            ['title' => 'Top sản phẩm', 'subtitle' => 'Block phụ 3', 'image' => 'https://picsum.photos/seed/th0001-default-side-3/360/180', 'link_url' => '#featured'],
            ['title' => 'Combo mới', 'subtitle' => 'Block phụ 4', 'image' => 'https://picsum.photos/seed/th0001-default-side-4/360/180', 'link_url' => '#featured'],
        ];
    }

    private function resolveFeaturedProducts(string $websiteKey): array
    {
        $featuredQuery = CatalogProduct::query()->with(['category', 'images'])->where('is_active', true)->where('is_featured', true)->latest('created_at');
        $this->applyWebsiteScope($featuredQuery, $websiteKey);

        $items = $featuredQuery->take(8)->get();

        if ($items->isEmpty()) {
            $fallbackQuery = CatalogProduct::query()->with(['category', 'images'])->where('is_active', true)->latest('created_at');
            $this->applyWebsiteScope($fallbackQuery, $websiteKey);
            $items = $fallbackQuery->take(8)->get();
        }

        return $items->map(fn (CatalogProduct $product): array => $this->mapProductCard($product))->all();
    }

    private function resolveSections(Collection $parentCategories, string $websiteKey): array
    {
        return $parentCategories->map(function (CatalogCategory $parent, int $index) use ($websiteKey): array {
            $categoryIds = $parent->children->pluck('id')->prepend($parent->id)->all();
            $productsQuery = CatalogProduct::query()->with(['category', 'images'])
                ->where('is_active', true)
                ->whereIn('catalog_category_id', $categoryIds)
                ->latest('created_at');
            $this->applyWebsiteScope($productsQuery, $websiteKey);

            return [
                'theme' => $index % 2 === 0 ? 'lime' : 'pink',
                'title' => $parent->name,
                'slug' => $parent->slug,
                'tabs' => ['Mới nhất', 'Bán chạy', 'Giá tốt'],
                'filters' => $parent->children->take(4)->pluck('name')->all(),
                'items' => $productsQuery->take(8)->get()->map(fn (CatalogProduct $product): array => $this->mapProductCard($product))->all(),
            ];
        })->filter(fn (array $section): bool => $section['items'] !== [])->values()->all();
    }

    private function resolveBrandHighlights(Collection $parentCategories): array
    {
        $tones = ['#101828', '#8f5f00', '#1c8c64', '#a66900', '#0d9488'];

        return $parentCategories->take(5)->values()->map(fn (CatalogCategory $category, int $index): array => [
            'name' => $category->name,
            'tone' => $tones[$index % count($tones)],
        ])->all();
    }

    private function mapProductCard(CatalogProduct $product): array
    {
        $originalPrice = $product->original_price !== null ? (float) $product->original_price : null;
        $price = (float) $product->price;
        $discount = ($originalPrice !== null && $originalPrice > 0 && $originalPrice > $price)
            ? (int) round((($originalPrice - $price) / $originalPrice) * 100)
            : 0;

        return [
            'title' => $product->name,
            'price' => $price,
            'old_price' => $originalPrice,
            'discount' => $discount,
            'image' => $this->resolveProductPrimaryImage($product),
            'tag' => $product->category?->name ?: 'Sản phẩm mới',
            'meta' => $product->stock,
            'is_featured' => $product->is_featured,
            'url' => $this->productUrl($product->slug ?: (string) $product->id),
        ];
    }

    private function resolveProductPrimaryImage(CatalogProduct $product): string
    {
        if (filled($product->image_url)) {
            return (string) $product->image_url;
        }

        $galleryImage = $product->relationLoaded('images') ? $product->images->first()?->image_url : $product->images()->orderBy('sort_order')->value('image_url');

        return $galleryImage ?: 'https://picsum.photos/seed/th0001-product-fallback/640/420';
    }

    private function resolveProductGallery(CatalogProduct $product): array
    {
        $images = collect([$product->image_url])
            ->merge($product->relationLoaded('images') ? $product->images->pluck('image_url') : $product->images()->orderBy('sort_order')->pluck('image_url'))
            ->map(fn ($image): string => trim((string) $image))
            ->filter(fn (string $image): bool => $image !== '')
            ->unique()
            ->values();

        if ($images->isEmpty()) {
            $images = collect(['https://picsum.photos/seed/th0001-product-fallback/960/720']);
        }

        return $images->map(fn (string $image, int $index): array => [
            'url' => $image,
            'alt' => $product->name.' '.($index + 1),
        ])->all();
    }

    private function splitTextLines(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) ($value ?? '')) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => $line !== '')
            ->values()
            ->all();
    }

    private function splitTextParagraphs(?string $value): array
    {
        return collect(preg_split('/(?:\r\n|\r|\n){2,}/', trim((string) ($value ?? ''))) ?: [])
            ->map(fn (string $paragraph): string => trim(preg_replace('/\r\n|\r|\n/', ' ', $paragraph) ?? $paragraph))
            ->filter(fn (string $paragraph): bool => $paragraph !== '')
            ->values()
            ->all();
    }

    private function renderThemeCatalogView(string $viewKey, ?array $activeTheme, array $data): View
    {
        $themeKey = strtolower((string) ($activeTheme['key'] ?? ''));

        if ($themeKey !== '') {
            $viewName = "theme-{$themeKey}::{$viewKey}";

            if (view()->exists($viewName)) {
                return view($viewName, $data);
            }
        }

        abort(404);
    }

    private function categoryUrl(string $slug): string
    {
        return '/danh-muc/'.$slug;
    }

    private function productUrl(string $slug): string
    {
        return '/san-pham/'.$slug;
    }

    /**
     * @return array<string, array{label: string, hint: string}>
     */
    private function paymentMethodOptions(): array
    {
        return [
            'cod' => [
                'label' => 'Thanh toán khi xác nhận',
                'hint' => 'Nhân viên sẽ liên hệ lại để chốt đơn và hướng dẫn hoàn tất.',
            ],
            'bank_transfer' => [
                'label' => 'Chuyển khoản ngân hàng',
                'hint' => 'Sau khi đặt hàng, hệ thống sẽ hiển thị thông tin để bạn chuyển khoản xác nhận.',
            ],
            'pickup' => [
                'label' => 'Nhận mã / nhận tại cửa hàng',
                'hint' => 'Phù hợp với deal E-Voucher hoặc nhận trực tiếp tại điểm bán.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveCheckoutFormDefaults(Request $request): array
    {
        /** @var Customer|null $customer */
        $customer = $request->user('customer');

        return [
            'customer_name' => old('customer_name', $customer?->name ?? ''),
            'customer_phone' => old('customer_phone', $customer?->phone ?? ''),
            'customer_email' => old('customer_email', $customer?->email ?? ''),
            'delivery_address' => old('delivery_address', ''),
            'note' => old('note', ''),
            'payment_method' => old('payment_method', 'cod'),
        ];
    }

    private function resolvePurchasableProduct(string $slug): CatalogProduct
    {
        $siteProfile = SiteProfile::query()->first();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $query = CatalogProduct::query()->where('slug', $slug)->where('is_active', true);
        $this->applyWebsiteScope($query, $websiteKey);

        return $query->firstOrFail();
    }

    private function validateCartQuantity(Request $request, CatalogProduct $product): int
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $quantity = (int) $validated['quantity'];

        if ($product->stock !== null && (int) $product->stock <= 0) {
            abort(422, 'Sản phẩm hiện đã hết hàng.');
        }

        if ($product->stock !== null) {
            $quantity = min($quantity, max(1, (int) $product->stock));
        }

        return $quantity;
    }
}
