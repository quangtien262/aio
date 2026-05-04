<?php

namespace App\Http\Controllers\Site;

use App\Core\Themes\ThemeRegistry;
use App\Core\Themes\ThemeTranslationService;
use App\Mail\ContactInquiryMail;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Support\FrontendLocalization;
use App\Support\BusinessContentTranslationService;
use App\Support\OrderConfirmationSender;
use App\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class CmsSiteController
{
    private const DEFAULT_BRAND_ASSET = 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png';
    private const DEFAULT_WEBSITE_KEY = 'website-main';

    public function __construct(
        private readonly ThemeRegistry $themeRegistry,
        private readonly ThemeTranslationService $themeTranslationService,
        private readonly BusinessContentTranslationService $businessContentTranslationService,
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

    public function page(Request $request): View
    {
        $slug = (string) $request->route('slug');

        $page = CmsPage::query()->with('featuredMedia')->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return $this->renderContent('page', $page);
    }

    public function postsIndex(Request $request): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));

        $postsQuery = CmsPost::query()->with(['category', 'featuredMedia'])->where('status', 'published');
        $this->applyWebsiteScope($postsQuery, $websiteKey);

        if ($search !== '') {
            $postsQuery->where(function (EloquentBuilder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('excerpt', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        if ($categorySlug !== '') {
            $postsQuery->whereHas('category', function (EloquentBuilder $query) use ($categorySlug, $websiteKey): void {
                $this->applyWebsiteScope($query, $websiteKey);
                $query->where('slug', $categorySlug);
            });
        }

        $posts = $postsQuery->latest('publish_at')->paginate(10)->withQueryString();

        $postCategories = CmsCategory::query()
            ->whereHas('posts', function (EloquentBuilder $query) use ($websiteKey): void {
                $query->where('status', 'published');
                $this->applyWebsiteScope($query, $websiteKey);
            })
            ->orderBy('name');
        $this->applyWebsiteScope($postCategories, $websiteKey);

        $postCategories = $postCategories->get();
        $postCategories = $postCategories->map(function (CmsCategory $category) use ($websiteKey): CmsCategory {
            $localized = clone $category;
            $localized->name = $this->contentText($websiteKey, sprintf('cms_category.%d.name', $category->id), $category->name);

            return $localized;
        });

        return $this->renderListing('posts', 'Tin tức', 'Danh sách bài viết đã xuất bản.', $posts, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'postFilters' => [
                'q' => $search,
                'category' => $categorySlug,
            ],
            'postCategories' => $postCategories,
        ]);
    }

    public function post(Request $request): View
    {
        $slug = (string) $request->route('slug');

        $post = CmsPost::query()->with(['category', 'featuredMedia'])->where('slug', $slug)->where('status', 'published')->firstOrFail();

        return $this->renderContent('post', $post);
    }

    public function submitContact(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $siteProfile = SiteProfile::query()->first();
        $branding = array_merge([
            'company_name' => $siteProfile?->site_name ?? 'AIO Website',
            'support_hotline' => '1900 6760',
            'support_email' => config('mail.from.address', 'cs@aio.local'),
            'support_location' => 'Hà Nội',
        ], $siteProfile?->branding ?? []);

        $payload['subject'] = trim((string) ($payload['subject'] ?? '')) !== ''
            ? (string) $payload['subject']
            : 'Yeu cau tu van tu website';
        $payload['submitted_at'] = now()->toDateTimeString();
        $payload['page_url'] = $request->headers->get('referer', $request->fullUrl());

        Mail::to($branding['support_email'])
            ->queue((new ContactInquiryMail($payload, $branding))->onQueue('mail'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Yeu cau lien he da duoc gui thanh cong.',
                'data' => [
                    'email' => $payload['email'],
                    'subject' => $payload['subject'],
                ],
            ]);
        }

        return app(\Illuminate\Routing\Redirector::class)->to($request->headers->get('referer', route('site.home')))
            ->with('contact_status', 'Đã gửi yêu cầu liên hệ. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
    }

    public function category(Request $request): View
    {
        $slug = (string) $request->route('slug');

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
        $category = $this->localizeCategoryModel($category, $websiteKey);

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
                'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $item->id), $item->name),
                'url' => $this->categoryUrl($item->slug),
                'count' => $productCountQuery->count(),
                'active' => $item->id === $currentCategory->id,
            ];
        })->all();
    }

    public function product(Request $request): View
    {
        $slug = (string) $request->route('slug');

        $product = $this->resolveProductPreviewModel($slug, false);

        return $this->renderProductDetailView($product, false);
    }

    public function previewProduct(Request $request, CatalogProduct $product): View
    {
        abort_unless(in_array('cms.product.view', $request->user('admin')?->permissions() ?? [], true), 403);

        $product = $this->resolveProductPreviewModel((string) $product->getKey(), true);

        return $this->renderProductDetailView($product, true);
    }

    private function renderProductDetailView(CatalogProduct $product, bool $isPreview): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);
        $product = $this->localizeProductModel($product, $websiteKey);

        $relatedProductsQuery = CatalogProduct::query()->with(['category', 'images'])->where('is_active', true)->where('id', '!=', $product->id);
        $this->applyWebsiteScope($relatedProductsQuery, $websiteKey);

        if ($product->catalog_category_id !== null) {
            $relatedProductsQuery->where('catalog_category_id', $product->catalog_category_id);
        }

        $relatedProducts = $relatedProductsQuery->latest('created_at')->take(8)->get();
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();
        $favoriteProductIds = $customer
            ? CustomerFavorite::query()->where('customer_id', $customer->id)->pluck('catalog_product_id')->all()
            : [];

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
            'isFavorite' => in_array($product->id, $favoriteProductIds, true),
            'isPreview' => $isPreview,
        ]);
    }

    public function searchProducts(Request $request): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) $request->query('category', ''));
        $sort = (string) $request->query('sort', 'default');
        $allowedSorts = ['default', 'newest', 'price_asc', 'price_desc', 'bestseller'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'default';
        }

        $baseProductsQuery = CatalogProduct::query()->with(['category', 'images'])->where('is_active', true);
        $this->applyWebsiteScope($baseProductsQuery, $websiteKey);

        if ($search !== '') {
            $baseProductsQuery->where(function (EloquentBuilder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhere('detail_content', 'like', '%'.$search.'%');
            });
        }

        if ($categorySlug !== '') {
            $baseProductsQuery->whereHas('category', function (EloquentBuilder $query) use ($categorySlug, $websiteKey): void {
                $this->applyWebsiteScope($query, $websiteKey);
                $query->where('slug', $categorySlug);
            });
        }

        $availableMinPrice = (int) floor((float) ((clone $baseProductsQuery)->min('price') ?? 0));
        $availableMaxPrice = (int) ceil((float) ((clone $baseProductsQuery)->max('price') ?? 0));

        $selectedMinPrice = $request->filled('min_price') ? (int) $request->query('min_price') : $availableMinPrice;
        $selectedMaxPrice = $request->filled('max_price') ? (int) $request->query('max_price') : $availableMaxPrice;

        if ($availableMaxPrice > 0) {
            $selectedMinPrice = max($availableMinPrice, min($selectedMinPrice, $availableMaxPrice));
            $selectedMaxPrice = max($availableMinPrice, min($selectedMaxPrice, $availableMaxPrice));
        } else {
            $selectedMinPrice = 0;
            $selectedMaxPrice = 0;
        }

        if ($selectedMinPrice > $selectedMaxPrice) {
            [$selectedMinPrice, $selectedMaxPrice] = [$selectedMaxPrice, $selectedMinPrice];
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

        $products = $productsQuery->paginate(24)->withQueryString();

        $searchCategories = CatalogCategory::query()
            ->where('is_active', true)
            ->whereHas('products', function (EloquentBuilder $query) use ($websiteKey): void {
                $query->where('is_active', true);
                $this->applyWebsiteScope($query, $websiteKey);
            })
            ->orderBy('sort_order')
            ->orderBy('name');
        $this->applyWebsiteScope($searchCategories, $websiteKey);

        $searchCategories = $searchCategories->get(['id', 'name', 'slug'])
            ->map(fn (CatalogCategory $category): CatalogCategory => $this->localizeCategoryModel($category, $websiteKey));

        return $this->renderThemeCatalogView('search', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'searchQuery' => $search,
            'products' => $products->map(fn (CatalogProduct $product): array => $this->mapProductCard($product))->all(),
            'pagination' => $products,
            'resultCount' => $products->total(),
            'searchCategories' => $searchCategories,
            'searchFilters' => [
                'q' => $search,
                'category' => $categorySlug,
                'sort' => $sort,
                'min_price' => $selectedMinPrice,
                'max_price' => $selectedMaxPrice,
                'available_min_price' => $availableMinPrice,
                'available_max_price' => $availableMaxPrice,
            ],
        ]);
    }

    public function searchProductSuggestions(Request $request): JsonResponse
    {
        $siteProfile = SiteProfile::query()->first();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $productsQuery = CatalogProduct::query()->with('category')->where('is_active', true);
        $this->applyWebsiteScope($productsQuery, $websiteKey);

        $productsQuery->where(function (EloquentBuilder $query) use ($search): void {
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('short_description', 'like', '%'.$search.'%');
        });

        $products = $productsQuery
            ->orderByDesc('is_featured')
            ->orderByDesc('sold_count')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $products->map(function (CatalogProduct $product): array {
                $websiteKey = $this->resolveWebsiteKey(SiteProfile::query()->first());
                $product = $this->localizeProductModel($product, $websiteKey);

                return [
                    'label' => $product->name,
                    'value' => $product->name,
                    'sku' => $product->sku,
                    'category' => $product->category?->name,
                    'price' => (float) $product->price,
                    'price_label' => number_format((float) $product->price, 0, ',', '.').'đ',
                    'url' => $this->productUrl($product->slug ?: (string) $product->id),
                ];
            })->values()->all(),
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

    public function addToCart(Request $request): RedirectResponse
    {
        $slug = (string) $request->route('slug');

        $product = $this->resolvePurchasableProduct($slug);
        $quantity = $this->validateCartQuantity($request, $product);

        $this->storefrontCart->add($product, $quantity);

        return back()->with('cart_success', 'Đã thêm '.$quantity.' sản phẩm vào giỏ hàng.');
    }

    public function buyNow(Request $request): RedirectResponse
    {
        $slug = (string) $request->route('slug');

        $product = $this->resolvePurchasableProduct($slug);
        $quantity = $this->validateCartQuantity($request, $product);

        $this->storefrontCart->add($product, $quantity);

        if (! $request->user('customer')) {
            return to_route('site.cart.index')
                ->with('cart_success', 'Sản phẩm đã được thêm vào giỏ. Vui lòng đăng nhập để tiếp tục đặt hàng.')
                ->with('open_auth_modal', 'login')
                ->with('post_login_redirect', route('site.checkout.index'));
        }

        return to_route('site.checkout.index')
            ->with('cart_success', 'Đã thêm sản phẩm vào giỏ và chuyển bạn tới bước thanh toán.');
    }

    public function updateCartItem(Request $request): RedirectResponse
    {
        $productId = (int) $request->route('productId');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $item = $this->storefrontCart->update($productId, (int) $validated['quantity']);

        abort_if($item === null, 404);

        return back()->with('cart_success', 'Đã cập nhật số lượng sản phẩm trong giỏ hàng.');
    }

    public function removeCartItem(Request $request): RedirectResponse
    {
        $productId = (int) $request->route('productId');

        $this->storefrontCart->remove($productId);

        return back()->with('cart_success', 'Đã xóa sản phẩm khỏi giỏ hàng.');
    }

    public function checkout(Request $request): View|RedirectResponse
    {
        if (! $this->storefrontCart->hasItems()) {
            return to_route('site.cart.index')->with('cart_success', 'Giỏ hàng đang trống, chưa thể thanh toán.');
        }

        if (! $request->user('customer')) {
            return to_route('site.cart.index')
                ->with('cart_success', 'Vui lòng đăng nhập để tiếp tục thanh toán.')
                ->with('open_auth_modal', 'login')
                ->with('post_login_redirect', route('site.checkout.index'));
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

        if (! $request->user('customer')) {
            return to_route('site.cart.index')
                ->with('cart_success', 'Vui lòng đăng nhập để hoàn tất đặt hàng.')
                ->with('open_auth_modal', 'login')
                ->with('post_login_redirect', route('site.checkout.index'));
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'delivery_address' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'in:cod,bank_transfer,pickup'],
        ]);

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
        abort_unless(auth('customer')->id() === $order->customer_id, 403);

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
        $siteProfile = $this->localizeSiteProfile($extra['siteProfile'] ?? SiteProfile::query()->first());
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $activeTheme = $extra['activeTheme'] ?? $this->resolveActiveTheme($siteProfile);
        $menus = $extra['menus'] ?? $this->resolveMenus($websiteKey);
        $viewName = $this->resolveThemeCmsView($activeTheme) ?? 'site-cms';

        if ($entry instanceof CmsPage) {
            $entry = $this->localizePageModel($entry, $websiteKey);
        }

        if ($entry instanceof CmsPost) {
            $entry = $this->localizePostModel($entry, $websiteKey);
        }

        if ($contentType === 'page' && ! array_key_exists('latestPosts', $extra)) {
            $extra['latestPosts'] = CmsPost::query()->where('status', 'published')->latest('publish_at')->take(3)->get()
                ->map(fn (CmsPost $post): CmsPost => $this->localizePostModel($post, $websiteKey));
        }

        if ($contentType === 'post' && $entry instanceof CmsPost && ! array_key_exists('relatedPosts', $extra)) {
            $extra['relatedPosts'] = $this->resolveRelatedPosts($entry, $siteProfile);
        }

        return view($viewName, array_merge([
            'contentType' => $contentType,
            'entry' => $entry,
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'isPreview' => false,
            'pageTitle' => $entry->meta_title ?: $entry->title,
            'pageDescription' => $entry->meta_description ?: ($entry->excerpt ?? null),
        ], $extra));
    }

    private function renderListing(string $contentType, string $title, string $description, mixed $items, array $extra = []): View
    {
        $siteProfile = $this->localizeSiteProfile($extra['siteProfile'] ?? SiteProfile::query()->first());
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $activeTheme = $extra['activeTheme'] ?? $this->resolveActiveTheme($siteProfile);
        $menus = $extra['menus'] ?? $this->resolveMenus($websiteKey);
        $viewName = $this->resolveThemeCmsView($activeTheme) ?? 'site-cms';

        if (is_object($items) && method_exists($items, 'getCollection') && method_exists($items, 'setCollection')) {
            $items->setCollection($items->getCollection()->map(fn (CmsPost $post): CmsPost => $this->localizePostModel($post, $websiteKey)));
        } elseif ($items instanceof Collection) {
            $items = $items->map(fn (CmsPost $post): CmsPost => $this->localizePostModel($post, $websiteKey));
        }

        return view($viewName, array_merge([
            'contentType' => $contentType,
            'listingItems' => $items,
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'isPreview' => false,
            'pageTitle' => $contentType === 'posts' ? $this->themeText('menu.default.blog', $title, 'TH0001') : $title,
            'pageDescription' => $contentType === 'posts'
                ? $this->themeText('cms.posts.description', $description, 'TH0001')
                : $description,
        ], $extra));
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

        return app(\Illuminate\Contracts\View\Factory::class)->exists($viewName) ? $viewName : null;
    }

    private function resolveThemeCmsView(?array $activeTheme): ?string
    {
        $themeKey = strtolower((string) ($activeTheme['key'] ?? ''));

        if ($themeKey === '') {
            return null;
        }

        $viewName = "theme-{$themeKey}::cms";

        return app(\Illuminate\Contracts\View\Factory::class)->exists($viewName) ? $viewName : null;
    }

    private function resolveRelatedPosts(CmsPost $post, ?SiteProfile $siteProfile): Collection
    {
        $websiteKey = (string) ($post->website_key ?: $this->resolveWebsiteKey($siteProfile));

        $sameCategoryQuery = CmsPost::query()
            ->with(['category', 'featuredMedia'])
            ->where('status', 'published')
            ->whereKeyNot($post->id)
            ->latest('publish_at');
        $this->applyWebsiteScope($sameCategoryQuery, $websiteKey);

        if ($post->category_id !== null) {
            $sameCategoryQuery->where('category_id', $post->category_id);
        }

        $sameCategory = $sameCategoryQuery->take(3)->get();

        if ($sameCategory->count() >= 3) {
            return $sameCategory->map(fn (CmsPost $item): CmsPost => $this->localizePostModel($item, $websiteKey));
        }

        $fallbackQuery = CmsPost::query()
            ->with(['category', 'featuredMedia'])
            ->where('status', 'published')
            ->whereKeyNot($post->id)
            ->whereNotIn('id', $sameCategory->pluck('id'))
            ->latest('publish_at');
        $this->applyWebsiteScope($fallbackQuery, $websiteKey);

        return $sameCategory
            ->concat($fallbackQuery->take(3 - $sameCategory->count())->get())
            ->map(fn (CmsPost $item): CmsPost => $this->localizePostModel($item, $websiteKey))
            ->values();
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
                ? $this->themeText('theme.fallback.featured_products', 'Sản phẩm nổi bật', $themeKey)
                : $this->themeText('theme.fallback.latest_products', 'Sản phẩm mới nhất', $themeKey),
            'sections' => $sections,
            'brand_highlights' => $this->resolveBrandHighlights($parentCategories),
        ];
    }

    private function resolveThemeShellData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        $siteProfile = $this->localizeSiteProfile($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $branding = array_merge([
            'company_name' => $siteProfile?->site_name ?? 'AIO Website',
            'logo_url' => self::DEFAULT_BRAND_ASSET,
            'favicon_url' => self::DEFAULT_BRAND_ASSET,
            'primary_color' => '#ef2b2d',
            'support_hotline' => '1900 6760',
            'support_email' => config('mail.from.address', 'cs@aio.local'),
            'support_location' => 'Hà Nội',
        ], $siteProfile?->branding ?? []);

        foreach (['company_name', 'slogan', 'support_location'] as $field) {
            if (filled($branding[$field] ?? null)) {
                $branding[$field] = $this->contentText($websiteKey, sprintf('branding.%s', $field), (string) $branding[$field]);
            }
        }

        $themeKey = (string) ($activeTheme['key'] ?? 'TH0001');
        /** @var Customer|null $customer */
        $customer = auth('customer')->user();
        $isSubscribed = $customer
            ? NewsletterSubscriber::query()->where(function ($query) use ($customer): void {
                $query->where('customer_id', $customer->id);

                if (filled($customer->email)) {
                    $query->orWhere('email', $customer->email);
                }
            })->exists()
            : false;

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
            'customer_auth' => [
                'is_authenticated' => $customer !== null,
                'customer' => $customer ? [
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                ] : null,
                'login_url' => route('customer.auth.login'),
                'register_url' => route('customer.auth.register'),
                'account_url' => route('customer.account'),
                'logout_url' => route('customer.auth.logout'),
            ],
            'newsletter' => [
                'is_subscribed' => $isSubscribed,
            ],
        ];
    }

    private function resolveWebsiteKey(?SiteProfile $siteProfile): string
    {
        $branding = $siteProfile?->branding ?? [];

        return (string) ($branding['website_key'] ?? self::DEFAULT_WEBSITE_KEY);
    }

    private function applyWebsiteScope($query, string $websiteKey): void
    {
        unset($query, $websiteKey);
    }

    private function resolveTopMenuItems(array $menus): array
    {
        $websiteKey = $this->resolveWebsiteKey(SiteProfile::query()->first());
        $items = collect($menus['primary-navigation'] ?? $menus['primary'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        if ($items->isEmpty()) {
            return [
                ['label' => $this->themeText('menu.default.blog', 'Tin tức', 'TH0001'), 'url' => route('site.blog.index'), 'target' => '_self'],
                ['label' => $this->themeText('menu.default.about', 'Giới thiệu', 'TH0001'), 'url' => $this->localizedStaticPageUrl('gioi-thieu'), 'target' => '_self'],
                ['label' => $this->themeText('menu.default.contact', 'Liên hệ', 'TH0001'), 'url' => $this->localizedStaticPageUrl('lien-he'), 'target' => '_self'],
            ];
        }

        return $items->values()->map(fn (array $item, int $index): array => [
            'label' => $this->contentText($websiteKey, sprintf('cms_menu.primary-navigation.%d.label', $index), (string) ($item['label'] ?? '')),
            'url' => $this->localizedUrl((string) ($item['url'] ?? '#')),
            'target' => $item['target'] ?? '_self',
        ])->all();
    }

    private function resolveProductMenuItems(array $menus, Collection $parentCategories): array
    {
        $websiteKey = $this->resolveWebsiteKey(SiteProfile::query()->first());
        $configured = collect($menus['product-navigation'] ?? [])->filter(fn (mixed $item): bool => is_array($item))->values();
        $validCategorySlugs = $parentCategories
            ->flatMap(fn (CatalogCategory $parent): array => array_merge([$parent->slug], $parent->children->pluck('slug')->all()))
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->values()
            ->all();

        if ($configured->isNotEmpty()) {
            return $configured->map(function (array $item, int $index) use ($validCategorySlugs, $websiteKey): array {
                $children = collect($item['children'] ?? [])->filter(fn (mixed $child): bool => is_array($child))->values()->map(fn (array $child, int $childIndex): array => [
                    'label' => $this->contentText($websiteKey, sprintf('cms_menu.product-navigation.%d.children.%d.label', $index, $childIndex), (string) ($child['label'] ?? '')),
                    'url' => $this->localizedUrl((string) ($child['url'] ?? '#')),
                    'target' => $child['target'] ?? '_self',
                ])->values()->all();
                $resolvedUrl = $this->localizedUrl((string) ($item['url'] ?? '#'));

                if ($this->hasMissingCategorySlug($resolvedUrl, $validCategorySlugs) && ($children[0]['url'] ?? null)) {
                    $resolvedUrl = $children[0]['url'];
                }

                return [
                    'label' => $this->contentText($websiteKey, sprintf('cms_menu.product-navigation.%d.label', $index), (string) ($item['label'] ?? 'Danh mục')),
                    'url' => $resolvedUrl,
                    'target' => $item['target'] ?? '_self',
                    'icon' => $item['icon'] ?? ($index === 0 ? '🔥' : '▣'),
                    'highlight' => (bool) ($item['highlight'] ?? false),
                    'children' => $children,
                ];
            })->all();
        }

        return $parentCategories->map(function (CatalogCategory $parent, int $index) use ($websiteKey): array {
            return [
                'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $parent->id), $parent->name),
                'url' => $this->categoryUrl($parent->slug),
                'target' => '_self',
                'icon' => $index === 0 ? '🔥' : '▣',
                'highlight' => $index === 0,
                'children' => $parent->children->map(fn (CatalogCategory $child): array => [
                    'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $child->id), $child->name),
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
                'eyebrow' => $this->themeText('theme.fallback.hero_eyebrow', 'Flash sale', $themeKey),
                'title' => $this->themeText('theme.fallback.hero_title', 'Deal sốc cho sản phẩm mới', $themeKey),
                'summary' => $this->themeText('theme.fallback.hero_summary', 'Tạo data test từ trang quản lý theme để đổ nội dung thật cho TH0001.', $themeKey),
                'badge' => $this->themeText('theme.fallback.hero_badge', 'Chỉ từ 199K', $themeKey),
                'cta' => $this->themeText('theme.fallback.hero_cta', 'Mua ngay', $themeKey),
                'image' => 'https://picsum.photos/seed/th0001-default-hero/960/520',
                'link_url' => '#featured',
            ];
        }

        return [
            'eyebrow' => $this->contentText($websiteKey, sprintf('site_banner.%d.metadata.eyebrow', $banner->id), (string) data_get($banner->metadata, 'eyebrow', 'Flash sale')),
            'title' => $this->contentText($websiteKey, sprintf('site_banner.%d.title', $banner->id), $banner->title ?? 'Deal nổi bật'),
            'summary' => $this->contentText($websiteKey, sprintf('site_banner.%d.metadata.summary', $banner->id), (string) data_get($banner->metadata, 'summary', $banner->subtitle ?? '')),
            'badge' => $this->contentText($websiteKey, sprintf('site_banner.%d.badge', $banner->id), $banner->badge ?? 'Ưu đãi hot'),
            'cta' => $this->contentText($websiteKey, sprintf('site_banner.%d.metadata.button_label', $banner->id), (string) data_get($banner->metadata, 'button_label', 'Mua ngay')),
            'image' => $banner->image_url,
            'link_url' => $this->localizedUrl((string) ($banner->link_url ?: '#featured')),
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
            'title' => $this->contentText($websiteKey, sprintf('site_banner.%d.title', $banner->id), $banner->title ?? $this->themeText('theme.fallback.side_banner_title', 'Banner phụ', $themeKey)),
            'subtitle' => $this->contentText($websiteKey, sprintf('site_banner.%d.subtitle', $banner->id), $banner->subtitle ?? ''),
            'image' => $banner->image_url,
            'link_url' => $this->localizedUrl((string) ($banner->link_url ?: '#featured')),
        ])->all();

        if ($items !== []) {
            return $items;
        }

        return [
            ['title' => $this->themeText('theme.fallback.side_banner_voucher', 'Voucher cuối tuần', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_voucher_subtitle', 'Ưu đãi theo preset', $themeKey), 'image' => 'https://picsum.photos/seed/th0001-default-side-1/360/180', 'link_url' => '#featured'],
            ['title' => $this->themeText('theme.fallback.side_banner_hot', 'Hot trend', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_hot_subtitle', 'Block phụ 2', $themeKey), 'image' => 'https://picsum.photos/seed/th0001-default-side-2/360/180', 'link_url' => '#featured'],
            ['title' => $this->themeText('theme.fallback.side_banner_top', 'Top sản phẩm', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_top_subtitle', 'Block phụ 3', $themeKey), 'image' => 'https://picsum.photos/seed/th0001-default-side-3/360/180', 'link_url' => '#featured'],
            ['title' => $this->themeText('theme.fallback.side_banner_combo', 'Combo mới', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_combo_subtitle', 'Block phụ 4', $themeKey), 'image' => 'https://picsum.photos/seed/th0001-default-side-4/360/180', 'link_url' => '#featured'],
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
                'title' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $parent->id), $parent->name),
                'slug' => $parent->slug,
                'url' => $this->categoryUrl($parent->slug),
                'tabs' => ['Mới nhất', 'Bán chạy', 'Giá tốt'],
                'filters' => $parent->children->take(4)->map(fn (CatalogCategory $child): string => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $child->id), $child->name))->all(),
                'items' => $productsQuery->take(8)->get()->map(fn (CatalogProduct $product): array => $this->mapProductCard($product))->all(),
            ];
        })->filter(fn (array $section): bool => $section['items'] !== [])->values()->all();
    }

    private function resolveBrandHighlights(Collection $parentCategories): array
    {
        $tones = ['#101828', '#8f5f00', '#1c8c64', '#a66900', '#0d9488'];
        $websiteKey = $this->resolveWebsiteKey(SiteProfile::query()->first());

        return $parentCategories->take(5)->values()->map(fn (CatalogCategory $category, int $index): array => [
            'name' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $category->id), $category->name),
            'tone' => $tones[$index % count($tones)],
        ])->all();
    }

    private function mapProductCard(CatalogProduct $product): array
    {
        $websiteKey = $this->resolveWebsiteKey(SiteProfile::query()->first());
        $product = $this->localizeProductModel($product, $websiteKey);
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
            'tag' => $product->category?->name ?: $this->themeText('theme.fallback.new_product', 'Sản phẩm mới', 'TH0001'),
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

            if (app(\Illuminate\Contracts\View\Factory::class)->exists($viewName)) {
                return view($viewName, $data);
            }
        }

        abort(404);
    }

    private function categoryUrl(string $slug): string
    {
        return route('site.catalog.category', ['slug' => $slug]);
    }

    private function productUrl(string $slug): string
    {
        return route('site.catalog.product', ['slug' => $slug]);
    }

    private function localizedStaticPageUrl(string $slug): string
    {
        return url('/'.$this->currentLocale().'/'.ltrim($slug, '/'));
    }

    private function hasMissingCategorySlug(string $url, array $validSlugs): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($path === '') {
            return false;
        }

        $segments = explode('/', $path);

        if (FrontendLocalization::isSupported($segments[0] ?? null)) {
            array_shift($segments);
        }

        if (! in_array($segments[0] ?? null, FrontendLocalization::segmentValues('category'), true)) {
            return false;
        }

        $slug = $segments[1] ?? null;

        return ! is_string($slug) || ! in_array($slug, $validSlugs, true);
    }

    private function localizedUrl(string $url): string
    {
        $value = trim($url);

        if ($value === '') {
            return '#';
        }

        if (str_starts_with($value, '#') || preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\//i', $value) || preg_match('/^(mailto:|tel:|javascript:)/i', $value)) {
            return $value;
        }

        $trimmed = ltrim($value, '/');

        if ($trimmed === '') {
            return route('site.home');
        }

        $segments = explode('/', $trimmed);

        if (FrontendLocalization::isSupported($segments[0] ?? null)) {
            return url('/'.$trimmed);
        }

        return url('/'.$this->currentLocale().'/'.$trimmed);
    }

    private function currentLocale(): string
    {
        return app()->getLocale();
    }

    private function themeText(string $key, string $default, ?string $themeKey = null): string
    {
        return $this->themeTranslationService->bladeText($themeKey ?: 'TH0001', $this->currentLocale(), $key, $default);
    }

    private function contentText(string $websiteKey, string $key, ?string $default): string
    {
        return $this->businessContentTranslationService->text($websiteKey, $key, $default);
    }

    private function localizeSiteProfile(?SiteProfile $siteProfile): ?SiteProfile
    {
        if (! $siteProfile) {
            return null;
        }

        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $localized = clone $siteProfile;
        $localized->site_name = $this->contentText($websiteKey, 'site_profile.site_name', $siteProfile->site_name);

        $branding = $siteProfile->branding ?? [];
        foreach (['company_name', 'slogan', 'support_location'] as $field) {
            if (filled($branding[$field] ?? null)) {
                $branding[$field] = $this->contentText($websiteKey, sprintf('branding.%s', $field), (string) $branding[$field]);
            }
        }

        $localized->setAttribute('branding', $branding);

        return $localized;
    }

    private function localizeCategoryModel(CatalogCategory $category, string $websiteKey): CatalogCategory
    {
        $localized = clone $category;
        $localized->name = $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $category->id), $category->name);
        $localized->description = $this->contentText($websiteKey, sprintf('catalog_category.%d.description', $category->id), $category->description);

        if ($category->relationLoaded('parent') && $category->parent) {
            $localized->setRelation('parent', $this->localizeCategoryModel($category->parent, $websiteKey));
        }

        if ($category->relationLoaded('children')) {
            $localized->setRelation('children', $category->children->map(fn (CatalogCategory $child): CatalogCategory => $this->localizeCategoryModel($child, $websiteKey)));
        }

        return $localized;
    }

    private function localizeProductModel(CatalogProduct $product, string $websiteKey): CatalogProduct
    {
        $localized = clone $product;
        $localized->name = $this->contentText($websiteKey, sprintf('catalog_product.%d.name', $product->id), $product->name);
        $localized->short_description = $this->contentText($websiteKey, sprintf('catalog_product.%d.short_description', $product->id), $product->short_description);
        $localized->detail_content = $this->contentText($websiteKey, sprintf('catalog_product.%d.detail_content', $product->id), $product->detail_content);
        $localized->highlights = $this->contentText($websiteKey, sprintf('catalog_product.%d.highlights', $product->id), $product->highlights);
        $localized->usage_terms = $this->contentText($websiteKey, sprintf('catalog_product.%d.usage_terms', $product->id), $product->usage_terms);
        $localized->usage_location = $this->contentText($websiteKey, sprintf('catalog_product.%d.usage_location', $product->id), $product->usage_location);

        if ($product->relationLoaded('category') && $product->category) {
            $localized->setRelation('category', $this->localizeCategoryModel($product->category, $websiteKey));
        }

        return $localized;
    }

    private function localizePageModel(CmsPage $page, string $websiteKey): CmsPage
    {
        $localized = clone $page;
        $localized->title = $this->contentText($websiteKey, sprintf('cms_page.%d.title', $page->id), $page->title);
        $localized->excerpt = $this->contentText($websiteKey, sprintf('cms_page.%d.excerpt', $page->id), $page->excerpt);
        $localized->body = $this->contentText($websiteKey, sprintf('cms_page.%d.body', $page->id), $page->body);
        $localized->meta_title = $this->contentText($websiteKey, sprintf('cms_page.%d.meta_title', $page->id), $page->meta_title);
        $localized->meta_description = $this->contentText($websiteKey, sprintf('cms_page.%d.meta_description', $page->id), $page->meta_description);

        return $localized;
    }

    private function localizePostModel(CmsPost $post, string $websiteKey): CmsPost
    {
        $localized = clone $post;
        $localized->title = $this->contentText($websiteKey, sprintf('cms_post.%d.title', $post->id), $post->title);
        $localized->excerpt = $this->contentText($websiteKey, sprintf('cms_post.%d.excerpt', $post->id), $post->excerpt);
        $localized->body = $this->contentText($websiteKey, sprintf('cms_post.%d.body', $post->id), $post->body);
        $localized->meta_title = $this->contentText($websiteKey, sprintf('cms_post.%d.meta_title', $post->id), $post->meta_title);
        $localized->meta_description = $this->contentText($websiteKey, sprintf('cms_post.%d.meta_description', $post->id), $post->meta_description);

        if ($post->relationLoaded('category') && $post->category) {
            $localizedCategory = clone $post->category;
            $localizedCategory->name = $this->contentText($websiteKey, sprintf('cms_category.%d.name', $post->category->id), $post->category->name);
            $localizedCategory->description = $this->contentText($websiteKey, sprintf('cms_category.%d.description', $post->category->id), $post->category->description);
            $localizedCategory->meta_title = $this->contentText($websiteKey, sprintf('cms_category.%d.meta_title', $post->category->id), $post->category->meta_title);
            $localizedCategory->meta_description = $this->contentText($websiteKey, sprintf('cms_category.%d.meta_description', $post->category->id), $post->category->meta_description);
            $localized->setRelation('category', $localizedCategory);
        }

        return $localized;
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

    private function resolveProductPreviewModel(string $identifier, bool $allowInactive): CatalogProduct
    {
        $siteProfile = SiteProfile::query()->first();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $query = CatalogProduct::query()->with(['category.parent', 'images']);
        $this->applyWebsiteScope($query, $websiteKey);

        if ($allowInactive && ctype_digit($identifier)) {
            return $query->whereKey((int) $identifier)->firstOrFail();
        }

        return $query->where('slug', $identifier)->where('is_active', true)->firstOrFail();
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
