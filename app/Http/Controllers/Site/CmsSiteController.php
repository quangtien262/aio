<?php

namespace App\Http\Controllers\Site;

use App\Core\Themes\ThemeDemoContentGenerator;
use App\Core\Themes\ThemeRegistry;
use App\Core\Themes\ThemeTranslationService;
use App\Mail\ContactInquiryMail;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\CmsCategory;
use App\Models\CmsFeaturedCategory;
use App\Models\CmsMedia;
use App\Models\CmsSidePromo;
use App\Models\Customer;
use App\Models\CustomerFavorite;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsProject;
use App\Models\CmsProjectCategory;
use App\Models\CmsService;
use App\Models\CmsServiceCategory;
use App\Models\ContactInquiry;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\SiteBanner;
use App\Models\SiteProfile;
use App\Support\FrontendLocalization;
use App\Support\FrontendRouteUrl;
use App\Support\BusinessContentTranslationService;
use App\Support\LandingPages\LandingPageBuilder;
use App\Support\Localization\CmsPageLocalization;
use App\Support\Localization\LandingPageLocalization;
use App\Support\Localization\LocaleContext;
use App\Support\Localization\LocalizedContentRepository;
use App\Support\OrderConfirmationSender;
use App\Support\SiteContext;
use App\Support\StorefrontCart;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CmsSiteController
{
    private const DEFAULT_BRAND_ASSET = 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png';
    private const DEFAULT_WEBSITE_KEY = 'website-main';

    public function __construct(
        private readonly ThemeRegistry $themeRegistry,
        private readonly ThemeDemoContentGenerator $themeDemoContentGenerator,
        private readonly ThemeTranslationService $themeTranslationService,
        private readonly BusinessContentTranslationService $businessContentTranslationService,
        private readonly StorefrontCart $storefrontCart,
        private readonly OrderConfirmationSender $orderConfirmationSender,
        private readonly LandingPageBuilder $landingPageBuilder,
        private readonly CmsPageLocalization $cmsPageLocalization,
        private readonly LocalizedContentRepository $localizedContent,
        private readonly LocaleContext $localeContext,
        private readonly LandingPageLocalization $landingLocalization,
    ) {
    }

    public function home(): View
    {
        $siteProfile = $this->localizeSiteProfile($this->currentSiteProfile());
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $themeKey = (string) ($activeTheme['key'] ?? '');
        $menus = $this->resolveMenus($websiteKey);

        if ($themeHomeView = $this->resolveThemeHomeView($activeTheme)) {
            $landingPage = $this->landingPageBuilder->resolveHome($websiteKey, $themeKey);
            $landingViewData = $landingPage
                ? $this->landingPageBuilder->viewData($landingPage, app()->getLocale(), FrontendLocalization::defaultLocale())
                : [];
            $landingTranslation = $landingPage?->data
                ->filter(fn ($data): bool => $data->isPublishedTranslation())
                ->firstWhere('locale', $this->currentLocale())
                ?? $landingPage?->data
                    ->filter(fn ($data): bool => $data->isPublishedTranslation())
                    ->firstWhere('locale', FrontendLocalization::fallbackLocale());
            $localizedSeo = $landingPage && $landingTranslation
                ? $this->landingLocalization->seo($landingPage, $landingTranslation)
                : [];

            return view($themeHomeView, array_merge([
                'siteProfile' => $siteProfile,
                'activeTheme' => $activeTheme,
                'menus' => $menus,
                'themeHomeData' => $this->resolveThemeHomeData($siteProfile, $activeTheme, $menus),
                'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
                'canonicalUrl' => data_get($localizedSeo, 'canonical_url'),
                'hreflangUrls' => data_get($localizedSeo, 'alternates', []),
                'resolvedContentLocale' => data_get($localizedSeo, 'resolved_locale'),
            ], $landingViewData));
        }

        $page = CmsPage::query()->with('featuredMedia')->where('slug', 'home')->where('status', 'published')->first();

        if ($page) {
            return $this->renderContent('page', $page, [
                'siteProfile' => $siteProfile,
                'activeTheme' => $activeTheme,
                'latestPosts' => CmsPost::query()->where('status', 'published')->latest('publish_at')->take(3)->get(),
            ]);
        }

        return view('site', [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
        ]);
    }

    public function page(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');

        if (in_array($slug, ['contact', 'lien-he'], true)) {
            return to_route('site.contact', ['locale' => $this->currentLocale()], 301);
        }

        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $resolution = $this->cmsPageLocalization->resolvePublic(
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        if ($resolution->usedFallback || $resolution->redirectPath !== null) {
            return redirect()->to(
                FrontendRouteUrl::page(
                    $resolution->translation->slug,
                    $resolution->translation->locale,
                ),
                $resolution->usedFallback ? 302 : 301,
            );
        }

        return $this->renderContent('page', $resolution->page, [
            'localizedSeo' => $this->cmsPageLocalization->seo(
                $resolution->page,
                $resolution->translation,
            ),
        ]);
    }

    public function landing(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');
        $siteProfile = $this->localizeSiteProfile($this->currentSiteProfile());
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $themeKey = (string) ($activeTheme['key'] ?? '');
        $themeHomeView = $this->resolveThemeHomeView($activeTheme);

        abort_if($themeHomeView === null, 404);

        $canPreviewDraft = auth('admin')->check() && $request->query('mod') === 'admin';
        $resolution = $canPreviewDraft
            ? null
            : $this->landingLocalization->resolvePublic(
                $websiteKey,
                $themeKey,
                $this->currentLocale(),
                $slug,
            );
        $landingPage = $canPreviewDraft
            ? $this->landingPageBuilder->resolveBySlug($websiteKey, $slug, $themeKey, false)
            : ($resolution['page'] ?? null);

        abort_if($landingPage === null || $landingPage->is_home, 404);

        if (
            ! $canPreviewDraft
            && ($resolution['used_fallback'] || $resolution['redirect_to'] !== null)
        ) {
            return redirect()->route('site.landing.show', [
                'locale' => $resolution['resolved_locale'],
                'slug' => $resolution['translation']->slug,
            ], $resolution['redirect_to'] !== null ? 301 : 302);
        }

        $menus = $this->resolveMenus($websiteKey);
        $localizedSeo = ! $canPreviewDraft
            ? $this->landingLocalization->seo(
                $landingPage,
                $resolution['translation'],
            )
            : [];

        return view($themeHomeView, array_merge([
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeHomeData' => $this->resolveThemeHomeData($siteProfile, $activeTheme, $menus),
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'canonicalUrl' => data_get($localizedSeo, 'canonical_url'),
            'hreflangUrls' => data_get($localizedSeo, 'alternates', []),
            'resolvedContentLocale' => data_get($localizedSeo, 'resolved_locale'),
        ], $this->landingPageBuilder->viewData($landingPage, app()->getLocale(), FrontendLocalization::defaultLocale())));
    }

    public function switchThemePreset(Request $request): RedirectResponse
    {
        abort_unless(app()->environment('local') || $request->user('admin') !== null, 403);

        $preset = (string) $request->route('preset');

        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $themeKey = $this->resolveServiceThemeKey($activeTheme);

        abort_unless($themeKey !== null, 404);
        abort_unless($this->themeRegistry->all()->firstWhere('key', $themeKey) !== null, 404);

        $servicePresetKeys = collect($this->themeDemoContentGenerator->servicePresets())
            ->pluck('key')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->all();

        abort_unless(in_array($preset, $servicePresetKeys, true), 404);

        try {
            $this->storefrontCart->clear();
            $this->themeDemoContentGenerator->generate($themeKey, $preset);
        } catch (InvalidArgumentException) {
            abort(422, 'Preset demo content không hợp lệ.');
        }

        return to_route('site.home', ['locale' => $this->currentLocale()])
            ->with('cart_success', 'Đã chuyển preset demo storefront.');
    }

    public function postsIndex(Request $request): View|RedirectResponse
    {
        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) ($request->route('slug') ?? $request->query('category', '')));
        $categoryResolution = $categorySlug !== ''
            ? $this->localizedContent->resolvePublishedBySlug(
                'cms_category',
                $websiteKey,
                $this->currentLocale(),
                $categorySlug,
            )
            : null;

        if ($categorySlug !== '') {
            abort_if($categoryResolution === null, 404);

            if ($categoryResolution['used_fallback'] || $categoryResolution['redirect_to'] !== null) {
                return redirect()->to(
                    FrontendRouteUrl::blogCategory(
                        $categoryResolution['model']->slug,
                        $categoryResolution['resolved_locale'],
                    ),
                    $categoryResolution['redirect_to'] !== null ? 301 : 302,
                );
            }
        }

        $postsQuery = CmsPost::query()->with(['category', 'featuredMedia'])->where('status', 'published');
        $this->applyWebsiteScope($postsQuery, $websiteKey);

        if ($search !== '') {
            $postsQuery->where(function (EloquentBuilder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('excerpt', 'like', '%'.$search.'%')
                    ->orWhere('body', 'like', '%'.$search.'%');
            });
        }

        if ($categoryResolution !== null) {
            $postsQuery->whereHas('category', function (EloquentBuilder $query) use ($categoryResolution, $websiteKey): void {
                $this->applyWebsiteScope($query, $websiteKey);
                $query->whereKey($categoryResolution['model']->getKey());
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
        $postCategories = $postCategories->map(
            fn (CmsCategory $category): CmsCategory => $this->localizedContent->localize(
                $category,
                'cms_category',
                $this->currentLocale(),
                $websiteKey,
            ),
        );
        $currentPostCategory = $categoryResolution !== null
            ? $postCategories->firstWhere('id', $categoryResolution['model']->getKey())
            : null;


        return $this->renderListing('posts', $currentPostCategory?->name ?? 'Tin tức', $currentPostCategory?->description ?? 'Danh sách bài viết đã xuất bản.', $posts, [
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

    public function post(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');
        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'cms_post',
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        /** @var CmsPost $post */
        $post = $resolution['model'];
        $post->loadMissing(['category', 'featuredMedia']);

        if ($resolution['used_fallback'] || $resolution['redirect_to'] !== null) {
            return redirect()->to(
                FrontendRouteUrl::post($post->slug, $resolution['resolved_locale']),
                $resolution['redirect_to'] !== null ? 301 : 302,
            );
        }

        return $this->renderContent('post', $post, [
            'siteProfile' => $siteProfile,
            'localizedSeo' => $this->localizedContentSeo(
                'cms_post',
                $post,
                $resolution['resolved_locale'],
            ),
        ]);
    }

    public function servicesIndex(Request $request): View|RedirectResponse
    {
        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) ($request->route('slug') ?? $request->query('category', '')));
        $categoryResolution = $categorySlug !== ''
            ? $this->localizedContent->resolvePublishedBySlug(
                'cms_service_category',
                $websiteKey,
                $this->currentLocale(),
                $categorySlug,
            )
            : null;

        if ($categorySlug !== '') {
            abort_if($categoryResolution === null, 404);

            if ($categoryResolution['used_fallback'] || $categoryResolution['redirect_to'] !== null) {
                return redirect()->to(
                    FrontendRouteUrl::serviceCategory(
                        $categoryResolution['model']->slug,
                        $categoryResolution['resolved_locale'],
                    ),
                    $categoryResolution['redirect_to'] !== null ? 301 : 302,
                );
            }
        }

        $servicesQuery = CmsService::query()
            ->with(['category', 'featuredImage'])
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('updated_at');
        $this->applyWebsiteScope($servicesQuery, $websiteKey);

        if ($search !== '') {
            $servicesQuery->where(function (EloquentBuilder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        if ($categoryResolution !== null) {
            $servicesQuery->whereHas('category', function (EloquentBuilder $query) use ($categoryResolution): void {
                $query->whereKey($categoryResolution['model']->getKey());
            });
        }

        if (
            strtoupper((string) ($activeTheme['key'] ?? '')) === 'XD0301'
            && $categorySlug !== ''
            && $search === ''
        ) {
            $singleServiceCandidates = (clone $servicesQuery)
                ->with(['category', 'images', 'featuredImage'])
                ->limit(2)
                ->get();

            if ($singleServiceCandidates->count() === 1) {
                return $this->renderContent('service', $singleServiceCandidates->first(), [
                    'siteProfile' => $siteProfile,
                    'activeTheme' => $activeTheme,
                    'menus' => $menus,
                ]);
            }
        }

        $services = $servicesQuery->paginate(12)->withQueryString();
        $currentServiceCategory = null;

        if ($categoryResolution !== null) {
            $categoryQuery = CmsServiceCategory::query()->whereKey($categoryResolution['model']->getKey());
            $this->applyWebsiteScope($categoryQuery, $websiteKey);
            $currentServiceCategory = $categoryQuery->first();

            if ($currentServiceCategory !== null) {
                /** @var CmsServiceCategory $currentServiceCategory */
                $currentServiceCategory = $this->localizedContent->localize(
                    $currentServiceCategory,
                    'cms_service_category',
                    $this->currentLocale(),
                    $websiteKey,
                );
            }
        }

        return $this->renderListing('services', $currentServiceCategory?->name ?? 'Dịch vụ', $currentServiceCategory?->description ?? 'Danh sách dịch vụ đã xuất bản.', $services, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'serviceFilters' => [
                'q' => $search,
                'category' => $categorySlug,
            ],
        ]);
    }

    public function service(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');
        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);

        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'cms_service',
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        /** @var CmsService $service */
        $service = $resolution['model'];
        $service->loadMissing(['category', 'images', 'featuredImage']);

        if ($resolution['used_fallback'] || $resolution['redirect_to'] !== null) {
            return redirect()->to(
                FrontendRouteUrl::service($service->slug, $resolution['resolved_locale']),
                $resolution['redirect_to'] !== null ? 301 : 302,
            );
        }

        return $this->renderContent('service', $service, [
            'siteProfile' => $siteProfile,
            'localizedSeo' => $this->localizedContentSeo(
                'cms_service',
                $service,
                $resolution['resolved_locale'],
            ),
        ]);
    }

    public function projectsIndex(Request $request): View|RedirectResponse
    {
        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);
        $search = trim((string) $request->query('q', ''));
        $categorySlug = trim((string) ($request->route('slug') ?? $request->query('category', '')));
        $categoryResolution = $categorySlug !== ''
            ? $this->localizedContent->resolvePublishedBySlug(
                'cms_project_category',
                $websiteKey,
                $this->currentLocale(),
                $categorySlug,
            )
            : null;

        if ($categorySlug !== '') {
            abort_if($categoryResolution === null, 404);

            if ($categoryResolution['used_fallback'] || $categoryResolution['redirect_to'] !== null) {
                return redirect()->to(
                    FrontendRouteUrl::projectCategory(
                        $categoryResolution['model']->slug,
                        $categoryResolution['resolved_locale'],
                    ),
                    $categoryResolution['redirect_to'] !== null ? 301 : 302,
                );
            }
        }

        $projectsQuery = CmsProject::query()
            ->with(['category', 'images', 'featuredImage'])
            ->where('status', 'published')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest('updated_at');
        $this->applyWebsiteScope($projectsQuery, $websiteKey);

        if ($search !== '') {
            $projectsQuery->where(function (EloquentBuilder $query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        if ($categoryResolution !== null) {
            $projectsQuery->whereHas('category', function (EloquentBuilder $query) use ($categoryResolution): void {
                $query->whereKey($categoryResolution['model']->getKey());
            });
        }

        $projects = $projectsQuery->paginate(12)->withQueryString();
        $currentProjectCategory = null;

        if ($categoryResolution !== null) {
            $categoryQuery = CmsProjectCategory::query()->whereKey($categoryResolution['model']->getKey());
            $this->applyWebsiteScope($categoryQuery, $websiteKey);
            $currentProjectCategory = $categoryQuery->first();

            if ($currentProjectCategory !== null) {
                /** @var CmsProjectCategory $currentProjectCategory */
                $currentProjectCategory = $this->localizedContent->localize(
                    $currentProjectCategory,
                    'cms_project_category',
                    $this->currentLocale(),
                    $websiteKey,
                );
            }
        }

        return $this->renderListing('projects', $currentProjectCategory?->name ?? 'Dự án', $currentProjectCategory?->description ?? 'Danh sách dự án đã xuất bản.', $projects, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'projectFilters' => [
                'q' => $search,
                'category' => $categorySlug,
            ],
        ]);
    }

    public function project(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');
        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);

        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'cms_project',
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        /** @var CmsProject $project */
        $project = $resolution['model'];
        $project->loadMissing(['category', 'images', 'featuredImage']);

        if ($resolution['used_fallback'] || $resolution['redirect_to'] !== null) {
            return redirect()->to(
                FrontendRouteUrl::project($project->slug, $resolution['resolved_locale']),
                $resolution['redirect_to'] !== null ? 301 : 302,
            );
        }

        $recentProjectsQuery = CmsProject::query()
            ->with(['category', 'featuredImage'])
            ->where('status', 'published')
            ->whereKeyNot($project->getKey())
            ->latest('updated_at');
        $this->applyWebsiteScope($recentProjectsQuery, $websiteKey);

        $recentProjects = $recentProjectsQuery
            ->take(10)
            ->get()
            ->map(fn (CmsProject $recentProject): CmsProject => $this->localizeProjectModel($recentProject, $websiteKey));

        return $this->renderContent('project', $project, [
            'siteProfile' => $siteProfile,
            'recentProjects' => $recentProjects,
            'localizedSeo' => $this->localizedContentSeo(
                'cms_project',
                $project,
                $resolution['resolved_locale'],
            ),
        ]);
    }

    public function contact(): View
    {
        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);

        $query = CmsPage::query()
            ->with('featuredMedia')
            ->where('status', 'published')
            ->whereIn('slug', ['contact', 'lien-he']);
        $this->applyWebsiteScope($query, $websiteKey);

        $page = $query
            ->orderByRaw("CASE WHEN slug = 'contact' THEN 0 WHEN slug = 'lien-he' THEN 1 ELSE 2 END")
            ->first();

        if ($page === null) {
            $page = new CmsPage([
                'slug' => 'contact',
                'status' => 'published',
                'title' => 'Liên hệ',
                'excerpt' => 'Kết nối với đội ngũ tư vấn để nhận hỗ trợ phù hợp với nhu cầu của bạn.',
                'body' => '',
                'meta_title' => 'Liên hệ',
                'meta_description' => 'Thông tin liên hệ và biểu mẫu gửi yêu cầu tư vấn.',
            ]);
        }

        return $this->renderContent('contact', $page, [
            'siteProfile' => $siteProfile,
        ]);
    }

    public function submitContact(Request $request): RedirectResponse|JsonResponse
    {
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'source' => ['nullable', 'string', 'in:contact,quote_modal'],
            'subject' => ['nullable', 'string', 'max:150'],
            'route_summary' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $siteContext = app(SiteContext::class);
        $site = $siteContext->site();
        $websiteKey = $siteContext->websiteKey();
        $submittedHost = strtolower($request->getHost());
        $siteProfile = $this->currentSiteProfile();
        $branding = array_merge([
            'company_name' => $siteProfile?->site_name ?? 'AIO Website',
            'support_hotline' => '1900 6760',
            'support_email' => config('mail.from.address', 'cs@aio.local'),
            'support_location' => 'Hà Nội',
        ], $siteProfile?->branding ?? []);

        $source = (string) ($payload['source'] ?? 'contact');

        $payload['subject'] = trim((string) ($payload['subject'] ?? '')) !== ''
            ? (string) $payload['subject']
            : ($source === 'quote_modal' ? 'Yeu cau bao gia tu website' : 'Yeu cau tu van tu website');
        $payload['submitted_at'] = now()->toDateTimeString();
        $payload['page_url'] = $request->headers->get('referer', $request->fullUrl());
        $payload['website_key'] = $websiteKey;
        $payload['submitted_host'] = $submittedHost;

        $order = null;

        if ($source === 'quote_modal') {
            $order = Order::query()->create([
                'order_code' => 'QTE'.now()->format('ymdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
                'website_key' => $websiteKey,
                'customer_id' => $request->user('customer')?->id,
                'status' => 'pending',
                'customer_name' => $payload['name'],
                'customer_phone' => $payload['phone'] ?? null,
                'customer_email' => $payload['email'],
                'delivery_address' => trim((string) ($payload['route_summary'] ?? $payload['page_url'] ?? 'Bao gia tu website')),
                'note' => trim("Yeu cau bao gia tu menu\nLộ trình: ".($payload['route_summary'] ?? '-') ."\n\n".($payload['message'] ?? '') ."\n\nTrang gui: ".($payload['page_url'] ?? '-')),
                'payment_method' => 'quote_request',
                'payment_label' => 'Yêu cầu báo giá',
                'subtotal' => 0,
                'item_count' => 0,
                'placed_at' => now(),
            ]);
        }

        ContactInquiry::query()->create([
            'site_id' => $site?->id,
            'website_key' => $websiteKey,
            'submitted_host' => $submittedHost,
            'customer_id' => $request->user('customer')?->id,
            'order_id' => $order?->id,
            'source' => $source,
            'status' => 'new',
            'name' => $payload['name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'] ?? null,
            'subject' => $payload['subject'],
            'route_summary' => $payload['route_summary'] ?? null,
            'message' => $payload['message'],
            'page_url' => $payload['page_url'],
            'locale' => app()->getLocale(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1024, ''),
            'submitted_at' => now(),
        ]);

        Mail::to($branding['support_email'])
            ->queue((new ContactInquiryMail($payload, $branding))->onQueue('mail'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $source === 'quote_modal'
                    ? 'Yêu cầu báo giá đã được gửi và lưu thành công.'
                    : 'Yêu cầu liên hệ đã được gửi thành công.',
                'data' => [
                    'email' => $payload['email'],
                    'subject' => $payload['subject'],
                ],
            ]);
        }

        return app(\Illuminate\Routing\Redirector::class)->to($request->headers->get('referer', route('site.home')))
            ->with('contact_status', 'Đã gửi yêu cầu liên hệ. Chúng tôi sẽ phản hồi trong thời gian sớm nhất.');
    }

    public function category(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');

        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'catalog_category',
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        /** @var CatalogCategory $resolvedCategory */
        $resolvedCategory = $resolution['model'];

        if ($resolution['used_fallback'] || $resolution['redirect_to'] !== null) {
            return redirect()->to(
                FrontendRouteUrl::category($resolvedCategory->slug, $resolution['resolved_locale']),
                $resolution['redirect_to'] !== null ? 301 : 302,
            );
        }

        $categoryQuery = CatalogCategory::query()->with(['parent', 'children' => function ($query) use ($websiteKey): void {
            $this->applyWebsiteScope($query, $websiteKey);
            $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
        }]);
        $this->applyWebsiteScope($categoryQuery, $websiteKey);

        $category = $categoryQuery->whereKey($resolvedCategory->getKey())->where('is_active', true)->firstOrFail();
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
            'catalogTreeCategories' => $this->resolveCatalogCategoryTreeItems($category, $websiteKey),
            'products' => $products->map(fn (CatalogProduct $product): array => $this->mapProductCard($product, (string) ($activeTheme['key'] ?? 'SHOP601')))->all(),
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

    private function resolveCatalogCategoryTreeItems(CatalogCategory $currentCategory, string $websiteKey): array
    {
        $query = CatalogCategory::query()
            ->where('is_active', true)
            ->withCount(['products' => function (EloquentBuilder $query) use ($websiteKey): void {
                $query->where('is_active', true);
                $this->applyWebsiteScope($query, $websiteKey);
            }])
            ->orderBy('sort_order')
            ->orderBy('name');
        $this->applyWebsiteScope($query, $websiteKey);

        $categories = $query->get();
        $childrenByParent = $categories->groupBy(fn (CatalogCategory $category): string => $category->parent_id === null ? 'root' : (string) $category->parent_id);

        $buildTree = function (?int $parentId) use (&$buildTree, $childrenByParent, $currentCategory, $websiteKey): array {
            $key = $parentId === null ? 'root' : (string) $parentId;

            return collect($childrenByParent->get($key, collect()))
                ->map(function (CatalogCategory $category) use (&$buildTree, $currentCategory, $websiteKey): array {
                    $children = $buildTree((int) $category->id);
                    $directCount = (int) ($category->products_count ?? 0);
                    $totalCount = $directCount + collect($children)->sum(fn (array $child): int => (int) ($child['count'] ?? 0));
                    $isCurrent = (int) $category->id === (int) $currentCategory->id;
                    $hasActiveChild = collect($children)->contains(fn (array $child): bool => (bool) ($child['active'] ?? false));

                    return [
                        'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $category->id), $category->name),
                        'url' => $this->categoryUrl((string) $category->slug),
                        'count' => $totalCount,
                        'active' => $isCurrent || $hasActiveChild,
                        'current' => $isCurrent,
                        'children' => $children,
                    ];
                })
                ->values()
                ->all();
        };

        return $buildTree(null);
    }

    public function product(Request $request): View|RedirectResponse
    {
        $slug = (string) $request->route('slug');

        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'catalog_product',
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        /** @var CatalogProduct $product */
        $product = $resolution['model'];
        $product->loadMissing(['category.parent', 'images']);

        if ($resolution['used_fallback'] || $resolution['redirect_to'] !== null) {
            return redirect()->to(
                FrontendRouteUrl::product($product->slug, $resolution['resolved_locale']),
                $resolution['redirect_to'] !== null ? 301 : 302,
            );
        }

        return $this->renderProductDetailView(
            $product,
            false,
            $this->localizedContentSeo(
                'catalog_product',
                $product,
                $resolution['resolved_locale'],
            ),
        );
    }

    public function previewProduct(Request $request): View
    {
        abort_unless(in_array('cms.product.view', $request->user('admin')?->permissions() ?? [], true), 403);

        $product = CatalogProduct::query()->findOrFail((string) $request->route('product'));

        $product = $this->resolveProductPreviewModel((string) $product->getKey(), true);

        return $this->renderProductDetailView($product, true);
    }

    private function renderProductDetailView(
        CatalogProduct $product,
        bool $isPreview,
        array $localizedSeo = [],
    ): View
    {
        $siteProfile = $this->currentSiteProfile();
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
            'product' => $this->mapProductCard($product, (string) ($activeTheme['key'] ?? 'SHOP601')),
            'productModel' => $product,
            'productGallery' => $this->resolveProductGallery($product),
            'productHighlights' => $this->splitTextLines($product->highlights),
            'usageTerms' => $this->splitTextLines($product->usage_terms),
            'usageLocationLines' => $this->splitTextLines($product->usage_location),
            'detailParagraphs' => $this->splitTextParagraphs($product->detail_content),
            'relatedProducts' => $relatedProducts->map(fn (CatalogProduct $item): array => $this->mapProductCard($item, (string) ($activeTheme['key'] ?? 'SHOP601')))->all(),
            'canonicalUrl' => data_get($localizedSeo, 'canonical_url'),
            'hreflangUrls' => data_get($localizedSeo, 'alternates', []),
            'resolvedContentLocale' => data_get($localizedSeo, 'resolved_locale'),
            'isFavorite' => in_array($product->id, $favoriteProductIds, true),
            'isPreview' => $isPreview,
        ]);
    }

    public function searchProducts(Request $request): View
    {
        $siteProfile = $this->currentSiteProfile();
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
            $matchingProductIds = $this->resolveAccentInsensitiveProductIds($search, $websiteKey, true);

            $baseProductsQuery->where(function (EloquentBuilder $query) use ($search, $matchingProductIds): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('short_description', 'like', '%'.$search.'%')
                    ->orWhere('detail_content', 'like', '%'.$search.'%');

                if ($matchingProductIds !== []) {
                    $query->orWhereIn('id', $matchingProductIds);
                }
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
            'products' => $products->map(fn (CatalogProduct $product): array => $this->mapProductCard($product, (string) ($activeTheme['key'] ?? 'SHOP601')))->all(),
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
        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $productsQuery = CatalogProduct::query()->with('category')->where('is_active', true);
        $this->applyWebsiteScope($productsQuery, $websiteKey);

        $matchingProductIds = $this->resolveAccentInsensitiveProductIds($search, $websiteKey, false);

        $productsQuery->where(function (EloquentBuilder $query) use ($search, $matchingProductIds): void {
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('sku', 'like', '%'.$search.'%')
                ->orWhere('short_description', 'like', '%'.$search.'%');

            if ($matchingProductIds !== []) {
                $query->orWhereIn('id', $matchingProductIds);
            }
        });

        $products = $productsQuery
            ->orderByDesc('is_featured')
            ->orderByDesc('sold_count')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $products->map(function (CatalogProduct $product): array {
                $websiteKey = $this->resolveWebsiteKey($this->currentSiteProfile());
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

    public function cart(Request $request): View|JsonResponse
    {
        $cartSummary = $this->storefrontCart->summary();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'data' => [
                    'cart_summary' => $cartSummary,
                    'has_items' => $this->storefrontCart->hasItems(),
                    'checkout_url' => route('site.checkout.index'),
                    'cart_url' => route('site.cart.index'),
                ],
            ]);
        }

        $siteProfile = $this->currentSiteProfile();
        $activeTheme = $this->resolveActiveTheme($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $menus = $this->resolveMenus($websiteKey);

        return $this->renderThemeCatalogView('cart', $activeTheme, [
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => array_replace($this->resolveThemeShellData($siteProfile, $activeTheme, $menus), [
                'cart_summary' => $cartSummary,
            ]),
            'checkoutMode' => $request->boolean('checkout'),
        ]);
    }

    public function addToCart(Request $request): JsonResponse|RedirectResponse
    {
        $slug = (string) $request->route('slug');

        $product = $this->resolvePurchasableProduct($slug);
        $quantity = $this->validateCartQuantity($request, $product);

        $item = $this->storefrontCart->add($product, $quantity);
        $summary = $this->storefrontCart->summary();
        $message = 'Đã thêm '.$quantity.' sản phẩm vào giỏ hàng.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'item' => $item,
                    'cart_summary' => $summary,
                    'cart_url' => route('site.cart.index'),
                    'checkout_url' => route('site.checkout.index'),
                ],
            ]);
        }

        return back()->with('cart_success', $message);
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

    public function updateCartItem(Request $request): JsonResponse|RedirectResponse
    {
        $productId = (int) $request->route('productId');

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $item = $this->storefrontCart->update($productId, (int) $validated['quantity']);

        abort_if($item === null, 404);

        $message = 'Đã cập nhật số lượng sản phẩm trong giỏ hàng.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'item' => $item,
                    'cart_summary' => $this->storefrontCart->summary(),
                ],
            ]);
        }

        return back()->with('cart_success', $message);
    }

    public function removeCartItem(Request $request): JsonResponse|RedirectResponse
    {
        $productId = (int) $request->route('productId');

        $this->storefrontCart->remove($productId);

        $message = 'Đã xóa sản phẩm khỏi giỏ hàng.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => $message,
                'data' => [
                    'cart_summary' => $this->storefrontCart->summary(),
                ],
            ]);
        }

        return back()->with('cart_success', $message);
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

        $siteProfile = $this->currentSiteProfile();
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
            'website_key' => app(SiteContext::class)->websiteKey(),
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

    public function checkoutSuccess(Request $request): View
    {
        $order = Order::query()->findOrFail((string) $request->route('order'));

        abort_unless(auth('customer')->id() === $order->customer_id, 403);

        $siteProfile = $this->currentSiteProfile();
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

    public function previewPage(Request $request): View
    {
        abort_unless(in_array('cms.publish', $request->user('admin')?->permissions() ?? [], true), 403);

        $page = CmsPage::query()
            ->with(['featuredMedia', 'translations'])
            ->findOrFail((string) $request->route('page'));
        $resolution = $this->cmsPageLocalization->resolvePreview(
            $page,
            (string) $request->route('locale'),
        );

        return $this->renderContent('page', $resolution->page, [
            'isPreview' => true,
            'localizedSeo' => $this->cmsPageLocalization->seo(
                $resolution->page,
                $resolution->translation,
            ),
        ]);
    }

    public function previewPost(Request $request): View
    {
        abort_unless(in_array('cms.publish', $request->user('admin')?->permissions() ?? [], true), 403);

        $post = CmsPost::query()->findOrFail((string) $request->route('post'));

        return $this->renderContent('post', $post->load(['category', 'featuredMedia']), ['isPreview' => true]);
    }

    private function renderContent(string $contentType, object $entry, array $extra = []): View
    {
        $siteProfile = $this->localizeSiteProfile($extra['siteProfile'] ?? $this->currentSiteProfile());
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $activeTheme = $extra['activeTheme'] ?? $this->resolveActiveTheme($siteProfile);
        $menus = $extra['menus'] ?? $this->resolveMenus($websiteKey);
        $viewName = $this->resolveThemeCmsView($activeTheme, $contentType) ?? 'site-cms';

        if (
            $entry instanceof CmsPage
            && ! $entry->relationLoaded('currentTranslation')
        ) {
            $entry = $this->localizePageModel($entry, $websiteKey);
        }

        if (
            $entry instanceof CmsPage
            && $entry->relationLoaded('featuredMedia')
            && $entry->featuredMedia
        ) {
            $entry->setRelation(
                'featuredMedia',
                $this->localizeMediaModel($entry->featuredMedia, $websiteKey),
            );
        }

        if ($entry instanceof CmsPost) {
            $entry = $this->localizePostModel($entry, $websiteKey);
        }

        if ($entry instanceof CmsService) {
            $entry = $this->localizeServiceModel($entry, $websiteKey);
        }

        if ($entry instanceof CmsProject) {
            $entry = $this->localizeProjectModel($entry, $websiteKey);
        }

        if ($contentType === 'page' && ! array_key_exists('latestPosts', $extra)) {
            $extra['latestPosts'] = CmsPost::query()->with('featuredMedia')->where('status', 'published')->latest('publish_at')->take(3)->get()
                ->map(fn (CmsPost $post): CmsPost => $this->localizePostModel($post, $websiteKey));
        }

        if ($contentType === 'post' && $entry instanceof CmsPost && ! array_key_exists('relatedPosts', $extra)) {
            $isDn302 = strtoupper((string) data_get($activeTheme, 'key')) === 'DN302';
            $extra['relatedPosts'] = $this->resolveRelatedPosts($entry, $siteProfile, $isDn302 ? 10 : 3, ! $isDn302);
        }

        if ($contentType === 'service' && $entry instanceof CmsService && ! array_key_exists('latestServices', $extra)) {
            $extra['latestServices'] = $this->resolveLatestServices($siteProfile, $entry, 15);
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
            'pageKeywords' => $entry->meta_keywords ?? null,
            'canonicalUrl' => data_get($extra, 'localizedSeo.canonical_url'),
            'hreflangUrls' => data_get($extra, 'localizedSeo.alternates', []),
            'resolvedContentLocale' => data_get($extra, 'localizedSeo.resolved_locale'),
        ], $extra));
    }

    private function renderListing(string $contentType, string $title, string $description, mixed $items, array $extra = []): View
    {
        $siteProfile = $this->localizeSiteProfile($extra['siteProfile'] ?? $this->currentSiteProfile());
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $activeTheme = $extra['activeTheme'] ?? $this->resolveActiveTheme($siteProfile);
        $themeKey = (string) ($activeTheme['key'] ?? 'SHOP601');
        $menus = $extra['menus'] ?? $this->resolveMenus($websiteKey);
        $viewName = $this->resolveThemeCmsView($activeTheme, $contentType) ?? 'site-cms';

        if (is_object($items) && method_exists($items, 'getCollection') && method_exists($items, 'setCollection')) {
            $items->setCollection($items->getCollection()->map(fn (mixed $item): mixed => match (true) {
                $item instanceof CmsPost => $this->localizePostModel($item, $websiteKey),
                $item instanceof CmsService => $this->localizeServiceModel($item, $websiteKey),
                $item instanceof CmsProject => $this->localizeProjectModel($item, $websiteKey),
                default => $item,
            }));
        } elseif ($items instanceof Collection) {
            $items = $items->map(fn (mixed $item): mixed => match (true) {
                $item instanceof CmsPost => $this->localizePostModel($item, $websiteKey),
                $item instanceof CmsService => $this->localizeServiceModel($item, $websiteKey),
                $item instanceof CmsProject => $this->localizeProjectModel($item, $websiteKey),
                default => $item,
            });
        }

        if ($contentType === 'posts' && ! array_key_exists('latestPosts', $extra)) {
            $latestPostsQuery = CmsPost::query()->where('status', 'published')->latest('publish_at');
            $this->applyWebsiteScope($latestPostsQuery, $websiteKey);

            $extra['latestPosts'] = $latestPostsQuery
                ->take(3)
                ->get()
                ->map(fn (CmsPost $post): CmsPost => $this->localizePostModel($post, $websiteKey));
        }

        return view($viewName, array_merge([
            'contentType' => $contentType,
            'listingItems' => $items,
            'siteProfile' => $siteProfile,
            'activeTheme' => $activeTheme,
            'menus' => $menus,
            'themeShellData' => $this->resolveThemeShellData($siteProfile, $activeTheme, $menus),
            'isPreview' => false,
            'pageTitle' => $contentType === 'posts' ? $this->themeText('menu.default.blog', $title, $themeKey) : $title,
            'pageDescription' => $contentType === 'posts'
                ? $this->themeText('cms.posts.description', $description, $themeKey)
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
            ->map(function (Collection $items) use ($websiteKey): array {
                $menu = $items->first();

                if (! $menu instanceof CmsMenu) {
                    return [];
                }

                /** @var CmsMenu $localized */
                $localized = $this->localizedContent->localize(
                    $menu,
                    'cms_menu',
                    $this->currentLocale(),
                    $websiteKey,
                );

                return $localized->items ?? [];
            })
            ->all();
    }

    private function resolveActiveTheme(?SiteProfile $siteProfile): ?array
    {
        $themeKey = app(SiteContext::class)->themeKey() ?: $siteProfile?->active_theme_key;

        if (! $themeKey) {
            return null;
        }

        /** @var array<string, mixed>|null $activeTheme */
        $activeTheme = $this->themeRegistry->all()->firstWhere('key', strtoupper((string) $themeKey));

        return $activeTheme;
    }

    private function currentSiteProfile(): ?SiteProfile
    {
        return app(SiteContext::class)->profile()
            ?? SiteProfile::query()->first();
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

    private function resolveThemeCmsView(?array $activeTheme, string $contentType = 'page'): ?string
    {
        $themeKey = strtolower((string) ($activeTheme['key'] ?? ''));

        if ($themeKey === '') {
            return null;
        }

        $viewKey = match ($contentType) {
            'services' => 'services',
            'service' => 'service',
            'projects' => 'projects',
            'project' => 'project',
            'posts' => 'news',
            'post' => 'news-detail',
            'contact' => 'contact',
            default => 'cms',
        };

        $viewFactory = app(\Illuminate\Contracts\View\Factory::class);
        $viewName = "theme-{$themeKey}::{$viewKey}";

        if ($viewFactory->exists($viewName)) {
            return $viewName;
        }

        $fallbackView = "theme-{$themeKey}::cms";

        return $viewFactory->exists($fallbackView) ? $fallbackView : null;
    }

    private function resolveRelatedPosts(CmsPost $post, ?SiteProfile $siteProfile, int $limit = 3, bool $preferSameCategory = true): Collection
    {
        $websiteKey = (string) ($post->website_key ?: $this->resolveWebsiteKey($siteProfile));

        if (! $preferSameCategory) {
            $latestQuery = CmsPost::query()
                ->with(['category', 'featuredMedia'])
                ->where('status', 'published')
                ->whereKeyNot($post->id)
                ->latest('publish_at');
            $this->applyWebsiteScope($latestQuery, $websiteKey);

            return $latestQuery->take($limit)->get()
                ->map(fn (CmsPost $item): CmsPost => $this->localizePostModel($item, $websiteKey))
                ->values();
        }

        $sameCategoryQuery = CmsPost::query()
            ->with(['category', 'featuredMedia'])
            ->where('status', 'published')
            ->whereKeyNot($post->id)
            ->latest('publish_at');
        $this->applyWebsiteScope($sameCategoryQuery, $websiteKey);

        if ($post->category_id !== null) {
            $sameCategoryQuery->where('category_id', $post->category_id);
        }

        $sameCategory = $sameCategoryQuery->take($limit)->get();

        if ($sameCategory->count() >= $limit) {
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
            ->concat($fallbackQuery->take($limit - $sameCategory->count())->get())
            ->map(fn (CmsPost $item): CmsPost => $this->localizePostModel($item, $websiteKey))
            ->values();
    }

    private function resolveLatestServices(?SiteProfile $siteProfile, ?CmsService $currentService = null, int $limit = 15): Collection
    {
        $websiteKey = (string) ($currentService?->website_key ?: $this->resolveWebsiteKey($siteProfile));

        $query = CmsService::query()
            ->with('featuredImage')
            ->where('status', 'published')
            ->latest('publish_at')
            ->latest('updated_at');
        $this->applyWebsiteScope($query, $websiteKey);

        if ($currentService?->getKey() !== null) {
            $query->whereKeyNot($currentService->getKey());
        }

        return $query
            ->take($limit)
            ->get()
            ->map(fn (CmsService $service): CmsService => $this->localizeServiceModel($service, $websiteKey))
            ->values();
    }

    private function resolveThemeHomeData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        $themeKey = (string) ($activeTheme['key'] ?? '');

        if ($this->isServiceThemeKey($themeKey)) {
            return $this->resolveServiceThemeHomeData($siteProfile, $activeTheme, $menus);
        }

        if (! $this->isCommerceThemeKey($themeKey)) {
            return [];
        }

        return $this->resolveCommerceThemeHomeData($siteProfile, $activeTheme, $menus);
    }

    private function resolveCommerceThemeHomeData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        $themeKey = (string) ($activeTheme['key'] ?? 'SHOP601');

        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $shellData = $this->resolveThemeShellData($siteProfile, $activeTheme, $menus);

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
        $sideBanners = $this->resolveSidePromos($websiteKey, $themeKey);
        $featuredProducts = $this->resolveFeaturedProducts($websiteKey);
        $latestPosts = $this->resolveLatestPostHighlights($websiteKey);

        return [
            ...$shellData,
            'hero_banner' => $heroBanner,
            'hero_slides' => $this->resolveHeroSlides($websiteKey, $themeKey),
            'fashion_banner_slides' => $this->resolveFashionBannerSlides($websiteKey, $themeKey),
            'side_banners' => $sideBanners,
            'secondary_side_promos' => $this->resolveSidePromos($websiteKey, $themeKey, 'home-secondary-side-promos'),
            'featured_products' => $featuredProducts,
            'featured_title' => collect($featuredProducts)->contains(fn (array $product): bool => (bool) ($product['is_featured'] ?? false))
                ? $this->themeText('theme.fallback.featured_products', 'Sản phẩm nổi bật', $themeKey)
                : $this->themeText('theme.fallback.latest_products', 'Sản phẩm mới nhất', $themeKey),
            'sections' => $this->resolveSections($parentCategories, $websiteKey, $themeKey),
            'fashion_category_tiles' => $this->resolveFashionCategoryTiles($parentCategories, $websiteKey),
            'featured_categories' => $this->resolveFeaturedCategories($websiteKey, $parentCategories, 'home-featured-categories', true, $themeKey),
            'footer_featured_categories' => $this->resolveFeaturedCategories($websiteKey, $parentCategories, 'footer-featured-categories', false, $themeKey),
            'brand_highlights' => $this->resolveFeaturedCategories($websiteKey, $parentCategories, 'home-featured-categories', true, $themeKey),
            'hero_slide_defaults' => $this->resolveCommerceHeroSlideDefaults($websiteKey, $themeKey),
            'footer_columns' => $this->resolveCommerceFooterColumns($websiteKey, $themeKey),
            'company_footer' => $this->resolveCommerceCompanyFooter($websiteKey, $themeKey),
            'latest_posts_section' => [
                'kicker' => $this->themeBlockText($websiteKey, $themeKey, 'latest_posts.kicker', 'Fashion journal'),
                'title' => $this->themeBlockText($websiteKey, $themeKey, 'latest_posts.title', 'Tin mới từ shop'),
                'summary' => $this->themeBlockText(
                    $websiteKey,
                    $themeKey,
                    'latest_posts.summary',
                    'Cập nhật lookbook, cách phối đồ và các ghi chú vận hành mới nhất từ CMS.'
                ),
            ],
            'latest_posts' => $latestPosts,
        ];
    }

    private function resolveServiceThemeHomeData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $shellData = $this->resolveThemeShellData($siteProfile, $activeTheme, $menus);
        $themeKey = (string) ($activeTheme['key'] ?? 'SER0101');

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

        $parentCategories = $parentCategories->take(6)->get();
        $featuredServices = $this->resolveFeaturedProducts($websiteKey);
        $latestPosts = $this->resolveLatestPostHighlights($websiteKey);

        return [
            ...$shellData,
            'hero_banner' => $this->resolveHeroBanner($websiteKey, $themeKey),
            'hero_slides' => $this->resolveHeroSlides($websiteKey, $themeKey),
            'side_banners' => $this->resolveSidePromos($websiteKey, $themeKey),
            'secondary_side_promos' => $this->resolveSidePromos($websiteKey, $themeKey, 'home-secondary-side-promos'),
            'service_categories' => $this->resolveServiceCategories($parentCategories, $websiteKey),
            'featured_services' => $featuredServices,
            'featured_title' => $this->themeText('theme.fallback.featured_services', 'Goi dich vu noi bat', $themeKey),
            'service_routes' => $this->resolveServiceRoutes($featuredServices),
            'service_metrics' => $this->resolveServiceMetrics($websiteKey, $themeKey),
            'quote_panel' => $this->resolveServiceQuotePanel($websiteKey, $themeKey),
            'service_highlights' => $this->resolveServiceHighlights($parentCategories),
            'footer_featured_categories' => $this->resolveFeaturedCategories($websiteKey, $parentCategories, 'footer-featured-categories', false),
            'latest_posts_section' => [
                'kicker' => $this->themeBlockText($websiteKey, $themeKey, 'latest_posts.kicker', 'Tin mới'),
                'title' => $this->themeBlockText($websiteKey, $themeKey, 'latest_posts.title', 'Tin mới'),
                'summary' => $this->themeBlockText(
                    $websiteKey,
                    $themeKey,
                    'latest_posts.summary',
                    'Khối này lấy bài viết mới nhất từ CMS. Sếp có thể đổi đoạn mô tả này trong phần nội dung website để phù hợp với từng preset hoặc thương hiệu.'
                ),
            ],
            'latest_posts' => $latestPosts,
        ];
    }

    private function resolveHeroSlides(string $websiteKey, string $themeKey): array
    {
        $query = SiteBanner::query()
            ->where('is_active', true)
            ->where('placement', 'hero-slider')
            ->where(function (EloquentBuilder $builder) use ($themeKey): void {
                $builder->where('theme_key', $themeKey)->orWhereNull('theme_key');
            })
            ->orderByRaw('CASE WHEN theme_key = ? THEN 0 ELSE 1 END', [$themeKey])
            ->orderBy('sort_order')
            ->orderBy('id');
        $this->applyWebsiteScope($query, $websiteKey);

        $items = $query->take(8)->get()->map(function (SiteBanner $banner) use ($websiteKey, $themeKey): array {
            $eyebrowKey = sprintf('site_banner.%d.metadata.eyebrow', $banner->id);
            $titleKey = sprintf('site_banner.%d.title', $banner->id);
            $summaryKey = sprintf('site_banner.%d.metadata.summary', $banner->id);
            $badgeKey = sprintf('site_banner.%d.badge', $banner->id);
            $ctaKey = sprintf('site_banner.%d.metadata.button_label', $banner->id);

            return [
                'image' => $banner->image_url,
                'alt' => $this->contentText($websiteKey, $titleKey, $banner->title ?? $this->themeText('theme.fallback.hero_title', 'Hero slide', $themeKey)),
                'kicker' => $this->contentText($websiteKey, $eyebrowKey, (string) data_get($banner->metadata, 'eyebrow', '')),
                'eyebrow' => $this->contentText($websiteKey, $eyebrowKey, (string) data_get($banner->metadata, 'eyebrow', '')),
                'title' => $this->contentText($websiteKey, $titleKey, $banner->title ?? ''),
                'summary' => $this->contentText($websiteKey, $summaryKey, (string) data_get($banner->metadata, 'summary', $banner->subtitle ?? '')),
                'badge' => $this->contentText($websiteKey, $badgeKey, $banner->badge ?? ''),
                'cta' => $this->contentText($websiteKey, $ctaKey, (string) data_get($banner->metadata, 'button_label', 'Mua ngay')),
                'position' => (string) data_get($banner->metadata, 'image_position', 'center'),
                'show_caption' => (bool) data_get($banner->metadata, 'show_caption', true),
                'link_url' => $banner->link_url ? $this->localizedUrl((string) $banner->link_url, route('site.catalog.search')) : route('site.catalog.search'),
                'translation_keys' => [
                    'eyebrow' => $eyebrowKey,
                    'title' => $titleKey,
                    'summary' => $summaryKey,
                    'badge' => $badgeKey,
                    'cta' => $ctaKey,
                ],
                'edit_fields' => [
                    ['key' => $eyebrowKey, 'label' => 'Nhãn slide', 'group' => 'content', 'entity' => 'banner'],
                    ['key' => $titleKey, 'label' => 'Tiêu đề slide', 'group' => 'content', 'entity' => 'banner'],
                    ['key' => $summaryKey, 'label' => 'Mô tả slide', 'group' => 'content', 'entity' => 'banner'],
                    ['key' => $badgeKey, 'label' => 'Badge slide', 'group' => 'content', 'entity' => 'banner'],
                    ['key' => $ctaKey, 'label' => 'CTA slide', 'group' => 'content', 'entity' => 'banner'],
                ],
            ];
        })->values()->all();

        if ($items !== []) {
            return $items;
        }

        if (! $this->isServiceThemeKey($themeKey)) {
            return [];
        }

        return [
            [
                'image' => asset('theme-demo/service/ser-tour-ops-hero.svg'),
                'alt' => 'Tour ops và vận hành tuyến',
                'kicker' => 'Tour ops',
                'eyebrow' => 'Tour ops',
                'title' => 'Vận hành tour đoàn gọn và rõ',
                'summary' => 'Nhìn nhanh block vận hành, hỗ trợ chốt tuyến tour và shuttle nội đô trong cùng một màn hình.',
                'badge' => '',
                'cta' => 'Xem chi tiết',
                'position' => 'center',
            ],
            [
                'image' => asset('theme-demo/service/service-banner-1.svg'),
                'alt' => 'Đưa đón sân bay đúng giờ',
                'kicker' => 'Airport pickup',
                'eyebrow' => 'Airport pickup',
                'title' => 'Đưa đón đúng giờ cho khách lẻ và đoàn nhỏ',
                'summary' => 'Phù hợp cho lead hotline, chuyển đổi nhanh và các tuyến pickup cần thông tin rõ ràng.',
                'badge' => '',
                'cta' => 'Xem chi tiết',
                'position' => 'left center',
            ],
            [
                'image' => asset('theme-demo/service/service-banner-2.svg'),
                'alt' => 'City transfer và VIP charter',
                'kicker' => 'City transfer',
                'eyebrow' => 'City transfer',
                'title' => 'Banner phụ cho tuyến nội đô và VIP charter',
                'summary' => 'Giữ nhịp visual đều tay hơn bộ card cũ nhưng vẫn đồng nhất tông màu của theme.',
                'badge' => '',
                'cta' => 'Xem chi tiết',
                'position' => 'left center',
            ],
            [
                'image' => asset('theme-demo/service/service-hero-main.svg'),
                'alt' => 'Hero điều phối xe dịch vụ',
                'kicker' => 'Điều phối nhanh',
                'eyebrow' => 'Điều phối nhanh',
                'title' => 'Đi xe sân bay và shuttle doanh nghiệp',
                'summary' => 'Tập trung lead nóng, lịch xe trong ngày và nhu cầu đặt chuyến gấp của doanh nghiệp.',
                'badge' => '',
                'cta' => 'Xem chi tiết',
                'position' => 'right center',
                'show_caption' => false,
            ],
        ];
    }

    private function resolveFashionBannerSlides(string $websiteKey, string $themeKey): array
    {
        $query = SiteBanner::query()
            ->where('is_active', true)
            ->whereIn('placement', ['home-fashion-banner', 'hero-slider', 'hero-main'])
            ->where(function (EloquentBuilder $builder) use ($themeKey): void {
                $builder->where('theme_key', $themeKey)->orWhereNull('theme_key');
            })
            ->orderByRaw('CASE WHEN theme_key = ? THEN 0 ELSE 1 END', [$themeKey])
            ->orderByRaw("CASE placement WHEN 'home-fashion-banner' THEN 0 WHEN 'hero-slider' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('id');
        $this->applyWebsiteScope($query, $websiteKey);

        return $query->take(6)->get()->map(function (SiteBanner $banner) use ($websiteKey, $themeKey): array {
            $titleKey = sprintf('site_banner.%d.title', $banner->id);

            return [
                'image' => $banner->image_url,
                'title' => $this->contentText($websiteKey, $titleKey, $banner->title ?? $this->themeText('theme.fallback.hero_title', 'Hero slide', $themeKey)),
                'link_url' => $banner->link_url ? $this->localizedUrl((string) $banner->link_url, route('site.catalog.search')) : route('site.catalog.search'),
                'target' => '_self',
                'source' => 'site_banners',
            ];
        })->values()->all();
    }

    private function resolveThemeShellData(?SiteProfile $siteProfile, ?array $activeTheme, array $menus): array
    {
        $siteProfile = $this->localizeSiteProfile($siteProfile);
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $themeKey = (string) ($activeTheme['key'] ?? 'SHOP601');
        $themePalette = $this->resolveThemePalette($siteProfile, $themeKey);
        $branding = array_merge([
            'company_name' => $siteProfile?->site_name ?? 'AIO Website',
            'logo_url' => '',
            'favicon_url' => '',
            'primary_color' => '#ef2b2d',
            'support_hotline' => '',
            'support_email' => '',
            'support_location' => '',
        ], $siteProfile?->branding ?? []);

        if ($this->isCommerceThemeKey($themeKey)) {
            $branding = $this->resolveCommerceBranding($siteProfile, $branding, $websiteKey, $themeKey);
        } elseif (! $this->isDemoPresetBranding($branding)) {
            if ($themePalette !== []) {
                $branding = array_merge($branding, $themePalette);
            }

            foreach (['company_name', 'slogan', 'support_location'] as $field) {
                if (filled($branding[$field] ?? null)) {
                    $branding[$field] = $this->contentText($websiteKey, sprintf('branding.%s', $field), (string) $branding[$field]);
                }
            }
        } elseif ($themePalette !== []) {
            $branding = array_merge($branding, $themePalette);
        }

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
            'top_menu' => $this->resolveTopMenuItems($menus, $themeKey),
            'product_menu' => $this->resolveProductMenuItems($menus, $parentCategories),
            'fashion_category_tiles' => $this->resolveFashionCategoryTiles($parentCategories, $websiteKey),
            'featured_categories' => $this->resolveFeaturedCategories($websiteKey, $parentCategories, 'home-featured-categories', true, $themeKey),
            'footer_featured_categories' => $this->resolveFeaturedCategories($websiteKey, $parentCategories, 'footer-featured-categories', false, $themeKey),
            'featured_products' => $this->resolveFeaturedProducts($websiteKey),
            'latest_posts_section' => [
                'kicker' => $this->themeBlockText($websiteKey, $themeKey, 'latest_posts.kicker', 'Fashion journal'),
                'title' => $this->themeBlockText($websiteKey, $themeKey, 'latest_posts.title', 'Tin mới từ shop'),
                'summary' => $this->themeBlockText(
                    $websiteKey,
                    $themeKey,
                    'latest_posts.summary',
                    'Cập nhật lookbook, cách phối đồ và các ghi chú vận hành mới nhất từ CMS.'
                ),
            ],
            'latest_posts' => $this->resolveLatestPostHighlights($websiteKey),
            'preset_switcher' => $this->resolvePresetSwitcher($siteProfile, $activeTheme),
            'fashion_banner_slides' => $this->resolveFashionBannerSlides($websiteKey, $themeKey),
            'side_banners' => $this->resolveSidePromos($websiteKey, $themeKey),
            'secondary_side_promos' => $this->resolveSidePromos($websiteKey, $themeKey, 'home-secondary-side-promos'),
            'footer_columns' => $this->resolveCommerceFooterColumns($websiteKey, $themeKey),
            'company_footer' => $this->resolveCommerceCompanyFooter($websiteKey, $themeKey),
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
        $siteContext = app(SiteContext::class);

        if ($siteContext->site() !== null) {
            return $siteContext->websiteKey();
        }

        $branding = $siteProfile?->branding ?? [];

        return (string) ($branding['website_key'] ?? $siteContext->websiteKey() ?? self::DEFAULT_WEBSITE_KEY);
    }

    private function resolvePresetSwitcher(?SiteProfile $siteProfile, ?array $activeTheme): array
    {
        $themeKey = (string) ($activeTheme['key'] ?? '');
        $enabled = $this->isServiceThemeKey($themeKey) && (app()->environment('local') || auth('admin')->check());

        if (! $enabled) {
            return [
                'enabled' => false,
                'current_key' => null,
                'current_label' => null,
                'options' => [],
            ];
        }

        $branding = (array) ($siteProfile?->branding ?? []);
        $currentKey = $this->resolveCurrentServicePresetKey($branding);
        $servicePresets = collect($this->themeDemoContentGenerator->servicePresets());
        $currentPreset = $servicePresets->firstWhere('key', $currentKey);

        return [
            'enabled' => true,
            'current_key' => $currentKey,
            'current_label' => is_array($currentPreset) ? ($currentPreset['label'] ?? null) : null,
            'options' => $servicePresets->map(fn (array $preset): array => [
                'key' => $preset['key'],
                'label' => $preset['label'],
                'description' => $preset['description'],
                'is_active' => $preset['key'] === $currentKey,
                'switch_url' => route('site.theme.preset.switch', [
                    'locale' => $this->currentLocale(),
                    'preset' => $preset['key'],
                ]),
            ])->all(),
        ];
    }

    private function resolveCurrentServicePresetKey(array $branding): ?string
    {
        $presetKey = $branding['demo_preset_key'] ?? null;

        if (is_string($presetKey) && $presetKey !== '') {
            return $presetKey;
        }

        $companyName = trim((string) ($branding['company_name'] ?? ''));

        if ($companyName === '') {
            return null;
        }

        $matchedPreset = collect($this->themeDemoContentGenerator->servicePresets())
            ->first(fn (array $preset): bool => ($preset['label'] ?? null) === $companyName || ($preset['company_name'] ?? null) === $companyName);

        return is_array($matchedPreset) ? (string) ($matchedPreset['key'] ?? '') : null;
    }

    private function applyWebsiteScope($query, string $websiteKey): void
    {
        unset($query, $websiteKey);
    }

    private function resolveTopMenuItems(array $menus, ?string $themeKey = null): array
    {
        $websiteKey = $this->resolveWebsiteKey($this->currentSiteProfile());
        $locationCandidates = strtoupper((string) $themeKey) === 'DN302'
            ? ['primary', 'primary-navigation']
            : ['primary-navigation', 'primary'];
        $resolvedLocation = collect($locationCandidates)
            ->first(fn (string $location): bool => ! empty($menus[$location] ?? []))
            ?? $locationCandidates[0];
        $items = collect($menus[$resolvedLocation] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        if ($this->isLandingHybridThemeKey($themeKey) && ($items->isEmpty() || $this->shouldUseLandingHybridTopMenuFallback($items->all()))) {
            return [
                ['label' => 'Dịch vụ', 'url' => route('site.services.index'), 'target' => '_self'],
                ['label' => 'Tin tức', 'url' => route('site.blog.index'), 'target' => '_self'],
                ['label' => 'Giới thiệu', 'url' => FrontendRouteUrl::page('gioi-thieu', $this->currentLocale()), 'target' => '_self'],
                ['label' => 'Liên hệ', 'url' => route('site.contact'), 'target' => '_self'],
            ];
        }

        if ($items->isEmpty() || ($this->isCommerceThemeKey($themeKey) && $this->shouldUseCommerceTopMenuFallback($items->all()))) {
            $resolvedThemeKey = $this->isCommerceThemeKey($themeKey) ? (string) $themeKey : 'SHOP601';

            return [
                ['label' => $this->themeText('menu.default.blog', 'Tin tức', $resolvedThemeKey), 'url' => route('site.blog.index'), 'target' => '_self'],
                ['label' => $this->themeText('menu.default.about', 'Giới thiệu', $resolvedThemeKey), 'url' => $this->localizedStaticPageUrl('gioi-thieu'), 'target' => '_self'],
                ['label' => $this->themeText('menu.default.contact', 'Liên hệ', $resolvedThemeKey), 'url' => route('site.contact'), 'target' => '_self'],
            ];
        }

        $fallbackUrl = $this->isCommerceThemeKey($themeKey) ? route('site.home') : null;
        $normalizeItems = function (array $menuItems, string $baseKey) use (&$normalizeItems, $websiteKey, $fallbackUrl): array {
            return collect($menuItems)
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values()
                ->map(function (array $item, int $index) use (&$normalizeItems, $websiteKey, $fallbackUrl, $baseKey): array {
                    $itemKey = "{$baseKey}.{$index}";

                    return [
                        'label' => $this->contentText($websiteKey, "{$itemKey}.label", (string) ($item['label'] ?? '')),
                        'url' => $this->localizedUrl((string) ($item['url'] ?? '#'), $fallbackUrl),
                        'target' => $item['target'] ?? '_self',
                        'children' => $normalizeItems(is_array($item['children'] ?? null) ? $item['children'] : [], "{$itemKey}.children"),
                    ];
                })
                ->all();
        };

        return $normalizeItems($items->all(), 'cms_menu.'.$resolvedLocation);
    }

    private function resolveProductMenuItems(array $menus, Collection $parentCategories): array
    {
        $websiteKey = $this->resolveWebsiteKey($this->currentSiteProfile());
        $configured = collect($menus['product-navigation'] ?? [])->filter(fn (mixed $item): bool => is_array($item))->values();
        $validCategorySlugs = $parentCategories
            ->flatMap(fn (CatalogCategory $parent): array => array_merge([$parent->slug], $parent->children->pluck('slug')->all()))
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->values()
            ->all();

        if ($configured->isNotEmpty()) {
            $normalizeItems = function (array $menuItems, string $baseKey = 'cms_menu.product-navigation') use (&$normalizeItems, $websiteKey): array {
                return collect($menuItems)
                    ->filter(fn (mixed $item): bool => is_array($item))
                    ->values()
                    ->map(function (array $item, int $index) use (&$normalizeItems, $websiteKey, $baseKey): array {
                        $itemKey = "{$baseKey}.{$index}";

                        return [
                            'label' => $this->contentText($websiteKey, "{$itemKey}.label", (string) ($item['label'] ?? '')),
                            'url' => $this->localizedUrl((string) ($item['url'] ?? '#'), route('site.catalog.search')),
                            'target' => $item['target'] ?? '_self',
                            'children' => $normalizeItems(is_array($item['children'] ?? null) ? $item['children'] : [], "{$itemKey}.children"),
                        ];
                    })
                    ->values()
                    ->all();
            };

            return $configured->map(function (array $item, int $index) use ($validCategorySlugs, $websiteKey, $normalizeItems): array {
                $children = $normalizeItems(is_array($item['children'] ?? null) ? $item['children'] : [], sprintf('cms_menu.product-navigation.%d.children', $index));
                $resolvedUrl = $this->localizedUrl((string) ($item['url'] ?? '#'), route('site.catalog.search'));

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

    private function resolveFashionCategoryTiles(Collection $parentCategories, string $websiteKey): array
    {
        return $parentCategories
            ->take(8)
            ->values()
            ->map(function (CatalogCategory $parent, int $index) use ($websiteKey): array {
                $categoryIds = $parent->children->pluck('id')->prepend($parent->id)->all();
                $productsQuery = CatalogProduct::query()
                    ->where('is_active', true)
                    ->whereIn('catalog_category_id', $categoryIds);
                $this->applyWebsiteScope($productsQuery, $websiteKey);

                return [
                    'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $parent->id), $parent->name),
                    'url' => $this->categoryUrl($parent->slug),
                    'target' => '_self',
                    'icon' => ['✦', '◐', '◇', '◌', '✧', '◆', '○', '●'][$index % 8],
                    'image' => $parent->image_url,
                    'products_count' => $productsQuery->count(),
                    'children' => $parent->children->take(4)->map(fn (CatalogCategory $child): array => [
                        'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $child->id), $child->name),
                        'url' => $this->categoryUrl($child->slug),
                        'target' => '_self',
                    ])->values()->all(),
                ];
            })
            ->filter(fn (array $category): bool => (int) ($category['products_count'] ?? 0) > 0 || ($category['children'] ?? []) !== [])
            ->values()
            ->all();
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
                'summary' => $this->themeText('theme.fallback.hero_summary', 'Tạo data test từ trang quản lý theme để đổ nội dung thật cho SHOP601.', $themeKey),
                'badge' => $this->themeText('theme.fallback.hero_badge', 'Chỉ từ 199K', $themeKey),
                'cta' => $this->themeText('theme.fallback.hero_cta', 'Mua ngay', $themeKey),
                'image' => 'https://picsum.photos/seed/shop601-default-hero/960/520',
                'link_url' => route('site.catalog.search'),
                'edit_fields' => [
                    ['slot' => 'eyebrow', 'key' => 'theme.fallback.hero_eyebrow', 'label' => 'Hero eyebrow', 'group' => 'static'],
                    ['slot' => 'title', 'key' => 'theme.fallback.hero_title', 'label' => 'Tiêu đề hero', 'group' => 'static'],
                    ['slot' => 'summary', 'key' => 'theme.fallback.hero_summary', 'label' => 'Mô tả hero', 'group' => 'static'],
                    ['slot' => 'badge', 'key' => 'theme.fallback.hero_badge', 'label' => 'Badge hero', 'group' => 'static'],
                    ['slot' => 'cta', 'key' => 'theme.fallback.hero_cta', 'label' => 'CTA hero', 'group' => 'static'],
                ],
            ];
        }

        return [
            'eyebrow' => $this->contentText($websiteKey, sprintf('site_banner.%d.metadata.eyebrow', $banner->id), (string) data_get($banner->metadata, 'eyebrow', 'Flash sale')),
            'title' => $this->contentText($websiteKey, sprintf('site_banner.%d.title', $banner->id), $banner->title ?? 'Deal nổi bật'),
            'summary' => $this->contentText($websiteKey, sprintf('site_banner.%d.metadata.summary', $banner->id), (string) data_get($banner->metadata, 'summary', $banner->subtitle ?? '')),
            'badge' => $this->contentText($websiteKey, sprintf('site_banner.%d.badge', $banner->id), $banner->badge ?? 'Ưu đãi hot'),
            'cta' => $this->contentText($websiteKey, sprintf('site_banner.%d.metadata.button_label', $banner->id), (string) data_get($banner->metadata, 'button_label', 'Mua ngay')),
            'image' => $banner->image_url,
            'link_url' => $banner->link_url ? $this->localizedUrl((string) $banner->link_url, route('site.catalog.search')) : route('site.catalog.search'),
            'edit_fields' => [
                ['slot' => 'eyebrow', 'key' => sprintf('site_banner.%d.metadata.eyebrow', $banner->id), 'label' => 'Hero eyebrow', 'group' => 'content', 'entity' => 'banner'],
                ['slot' => 'title', 'key' => sprintf('site_banner.%d.title', $banner->id), 'label' => 'Tiêu đề hero', 'group' => 'content', 'entity' => 'banner'],
                ['slot' => 'summary', 'key' => sprintf('site_banner.%d.metadata.summary', $banner->id), 'label' => 'Mô tả hero', 'group' => 'content', 'entity' => 'banner'],
                ['slot' => 'badge', 'key' => sprintf('site_banner.%d.badge', $banner->id), 'label' => 'Badge hero', 'group' => 'content', 'entity' => 'banner'],
                ['slot' => 'cta', 'key' => sprintf('site_banner.%d.metadata.button_label', $banner->id), 'label' => 'CTA hero', 'group' => 'content', 'entity' => 'banner'],
            ],
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
            'link_url' => $banner->link_url ? $this->localizedUrl((string) $banner->link_url, route('site.catalog.search')) : route('site.catalog.search'),
        ])->all();

        if ($items !== []) {
            return $items;
        }

        return [
            ['title' => $this->themeText('theme.fallback.side_banner_voucher', 'Voucher cuối tuần', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_voucher_subtitle', 'Ưu đãi theo preset', $themeKey), 'image' => 'https://picsum.photos/seed/shop601-default-side-1/360/180', 'link_url' => route('site.catalog.search')],
            ['title' => $this->themeText('theme.fallback.side_banner_hot', 'Hot trend', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_hot_subtitle', 'Block phụ 2', $themeKey), 'image' => 'https://picsum.photos/seed/shop601-default-side-2/360/180', 'link_url' => route('site.catalog.search')],
            ['title' => $this->themeText('theme.fallback.side_banner_top', 'Top sản phẩm', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_top_subtitle', 'Block phụ 3', $themeKey), 'image' => 'https://picsum.photos/seed/shop601-default-side-3/360/180', 'link_url' => route('site.catalog.search')],
            ['title' => $this->themeText('theme.fallback.side_banner_combo', 'Combo mới', $themeKey), 'subtitle' => $this->themeText('theme.fallback.side_banner_combo_subtitle', 'Block phụ 4', $themeKey), 'image' => 'https://picsum.photos/seed/shop601-default-side-4/360/180', 'link_url' => route('site.catalog.search')],
        ];
    }

    private function resolveSidePromos(string $websiteKey, string $themeKey, string $location = 'home-hero-side-promos'): array
    {
        $query = CmsSidePromo::query()
            ->where('location', $location)
            ->orderByDesc('id');

        $this->applyWebsiteScope($query, $websiteKey);

        $group = $query->first();

        if ($group && is_array($group->items) && $group->items !== []) {
            $items = collect($group->items)
                ->sortBy(fn (array $item, int $index): int => (int) ($item['sort_order'] ?? $index))
                ->values()
                ->map(function (array $item, int $index) use ($websiteKey, $group): array {
                    return [
                        'badge' => $this->contentText($websiteKey, sprintf('cms_side_promo.%s.%d.badge', $group->location, $index), (string) ($item['badge'] ?? '')),
                        'title' => $this->contentText($websiteKey, sprintf('cms_side_promo.%s.%d.title', $group->location, $index), (string) ($item['title'] ?? '')),
                        'subtitle' => $this->contentText($websiteKey, sprintf('cms_side_promo.%s.%d.subtitle', $group->location, $index), (string) ($item['subtitle'] ?? '')),
                        'cta_label' => $this->contentText($websiteKey, sprintf('cms_side_promo.%s.%d.cta_label', $group->location, $index), (string) ($item['cta_label'] ?? '')),
                        'image' => (string) ($item['image'] ?? ''),
                        'sort_order' => (int) ($item['sort_order'] ?? $index),
                        'link_url' => filled($item['url'] ?? $item['custom_url'] ?? null)
                            ? $this->localizedUrl((string) ($item['url'] ?? $item['custom_url']), route('site.catalog.search'))
                            : route('site.catalog.search'),
                        'target' => (string) ($item['target'] ?? '_self'),
                    ];
                })
                ->filter(fn (array $item): bool => filled($item['title']) && filled($item['image']))
                ->values()
                ->all();

            if ($items !== []) {
                return $items;
            }
        }

        return $this->resolveSideBanners($websiteKey, $themeKey);
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

    private function resolveSections(Collection $parentCategories, string $websiteKey, string $themeKey = 'SHOP601'): array
    {
        $tabs = $this->commerceThemeDefaults($themeKey)['section_tabs'];

        return $parentCategories->map(function (CatalogCategory $parent, int $index) use ($websiteKey, $themeKey, $tabs): array {
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
                'tabs' => $tabs,
                'filters' => $parent->children->take(4)->map(fn (CatalogCategory $child): array => [
                    'label' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $child->id), $child->name),
                    'url' => $this->categoryUrl($child->slug),
                ])->all(),
                'items' => $productsQuery->take(8)->get()->map(fn (CatalogProduct $product): array => $this->mapProductCard($product, $themeKey))->all(),
            ];
        })->filter(fn (array $section): bool => $section['items'] !== [])->values()->all();
    }

    private function resolveFeaturedCategories(string $websiteKey, Collection $parentCategories, string $location = 'home-featured-categories', bool $fallbackToBrandHighlights = true, string $themeKey = 'SHOP601'): array
    {
        $query = CmsFeaturedCategory::query()
            ->where('location', $location)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
        $this->applyWebsiteScope($query, $websiteKey);

        $record = $query->first();
        $items = collect($record?->items ?? [])->values()->filter(fn (mixed $item): bool => is_array($item) && filled($item['label'] ?? null));

        if ($items->isNotEmpty()) {
            return $items->map(function (array $item, int $index) use ($websiteKey, $location, $themeKey): array {
                return [
                    'name' => $this->contentText($websiteKey, sprintf('cms_featured_category.%s.%d.label', $location, $index), (string) ($item['label'] ?? '')),
                    'url' => $this->localizedUrl((string) ($item['url'] ?? '#'), route('site.catalog.search')),
                    'target' => $item['target'] ?? '_self',
                    'tone' => $this->resolveFeaturedCategoryTone($index, $themeKey),
                ];
            })->all();
        }

        return $fallbackToBrandHighlights ? $this->resolveBrandHighlights($parentCategories, $themeKey) : [];
    }

    private function resolveBrandHighlights(Collection $parentCategories, string $themeKey = 'SHOP601'): array
    {
        $websiteKey = $this->resolveWebsiteKey($this->currentSiteProfile());

        return $parentCategories->take(5)->values()->map(fn (CatalogCategory $category, int $index): array => [
            'name' => $this->contentText($websiteKey, sprintf('catalog_category.%d.name', $category->id), $category->name),
            'url' => $this->categoryUrl($category->slug),
            'target' => '_self',
            'tone' => $this->resolveFeaturedCategoryTone($index, $themeKey),
        ])->all();
    }

    private function resolveFeaturedCategoryTone(int $index, string $themeKey = 'SHOP601'): string
    {
        $tones = $this->commerceThemeDefaults($themeKey)['featured_tones'];

        return $tones[$index % count($tones)];
    }

    private function resolveServiceCategories(Collection $parentCategories, string $websiteKey): array
    {
        return $parentCategories->map(function (CatalogCategory $parent) use ($websiteKey): array {
            $resolvedWebsiteKey = $websiteKey;

            return [
                'name' => $parent->name,
                'url' => $this->categoryUrl($parent->slug),
                'summary' => $this->contentText(
                    $resolvedWebsiteKey,
                    sprintf('catalog_category.%d.description', $parent->id),
                    (string) ($parent->description ?? '')
                ),
                'children' => $parent->children->map(fn (CatalogCategory $child): array => [
                    'name' => $this->contentText($resolvedWebsiteKey, sprintf('catalog_category.%d.name', $child->id), $child->name),
                    'url' => $this->categoryUrl($child->slug),
                ])->all(),
            ];
        })->values()->all();
    }

    private function resolveServiceRoutes(array $featuredServices): array
    {
        return collect($featuredServices)->take(6)->values()->map(function (array $service): array {
            return [
                'title' => $service['title'] ?? '',
                'summary' => $service['tag'] ?? '',
                'price' => $service['price'] ?? null,
                'url' => $service['url'] ?? '#',
            ];
        })->all();
    }

    private function resolveServiceMetrics(string $websiteKey, string $themeKey): array
    {
        $categoryCountQuery = CatalogCategory::query()->where('is_active', true);
        $productCountQuery = CatalogProduct::query()->where('is_active', true);
        $postCountQuery = CmsPost::query()->where('status', 'published');

        $this->applyWebsiteScope($categoryCountQuery, $websiteKey);
        $this->applyWebsiteScope($productCountQuery, $websiteKey);
        $this->applyWebsiteScope($postCountQuery, $websiteKey);

        return [
            $this->resolveServiceMetricEntry($websiteKey, $themeKey, 0, (string) max(12, $categoryCountQuery->count() * 2), '+', 'gói dịch vụ và tuyến tham khảo'),
            $this->resolveServiceMetricEntry($websiteKey, $themeKey, 1, (string) max(24, $productCountQuery->count()), '+', 'mẫu nội dung catalog đang hiển thị'),
            $this->resolveServiceMetricEntry($websiteKey, $themeKey, 2, (string) max(3, $postCountQuery->count()), '+', 'bài viết hướng dẫn và cẩm nang'),
            $this->resolveServiceMetricEntry($websiteKey, $themeKey, 3, '24', '/7', 'hỗ trợ lead demo trong giao diện'),
        ];
    }

    private function resolveServiceMetricEntry(string $websiteKey, string $themeKey, int $index, string $defaultValue, string $defaultSuffix, string $defaultLabel): array
    {
        return [
            'value' => $this->themeBlockText($websiteKey, $themeKey, sprintf('service_metrics.%d.value', $index), $defaultValue),
            'suffix' => $this->themeBlockText($websiteKey, $themeKey, sprintf('service_metrics.%d.suffix', $index), $defaultSuffix),
            'label' => $this->themeBlockText($websiteKey, $themeKey, sprintf('service_metrics.%d.label', $index), $defaultLabel),
        ];
    }

    private function resolveServiceQuotePanel(string $websiteKey, string $themeKey): array
    {
        $defaults = [
            [
                'title' => 'Loại xe phổ biến',
                'summary' => '4 chỗ, 7 chỗ, 16 chỗ, 29 chỗ, 45 chỗ, shuttle, cargo nhẹ.',
            ],
            [
                'title' => 'Thông tin cần có',
                'summary' => 'Số khách, điểm đón, điểm đến, ngày đi và số điện thoại liên hệ.',
            ],
            [
                'title' => 'Cam kết phản hồi',
                'summary' => 'CTA xuất hiện xuyên suốt từ trang chủ, trang danh mục, trang chi tiết và bước gửi yêu cầu.',
            ],
            [
                'title' => 'Kênh ưu tiên',
                'summary' => 'Hotline, email, trang liên hệ và form lưu nhu cầu để điều phối viên gọi lại.',
            ],
        ];

        return [
            'badge' => $this->themeBlockText($websiteKey, $themeKey, 'quote_panel.badge', 'Báo giá trong ngày'),
            'items' => collect($defaults)->map(function (array $item, int $index) use ($websiteKey, $themeKey): array {
                return [
                    'title' => $this->themeBlockText($websiteKey, $themeKey, sprintf('quote_panel.items.%d.title', $index), $item['title']),
                    'summary' => $this->themeBlockText($websiteKey, $themeKey, sprintf('quote_panel.items.%d.summary', $index), $item['summary']),
                ];
            })->all(),
        ];
    }

    private function resolveCommerceHeroSlideDefaults(string $websiteKey, string $themeKey): array
    {
        $defaults = $this->commerceThemeDefaults($themeKey);

        return [
            'eyebrow' => $this->themeBlockText($websiteKey, $themeKey, 'hero_slide.eyebrow', $defaults['hero_slide']['eyebrow']),
            'badge' => $this->themeBlockText($websiteKey, $themeKey, 'hero_slide.badge', $defaults['hero_slide']['badge']),
            'cta' => $this->themeBlockText($websiteKey, $themeKey, 'hero_slide.cta', $defaults['hero_slide']['cta']),
        ];
    }

    private function resolveCommerceFooterColumns(string $websiteKey, string $themeKey): array
    {
        $defaults = $this->commerceThemeDefaults($themeKey)['footer_columns'];

        return collect($defaults)->map(function (array $column, int $index) use ($websiteKey, $themeKey): array {
            return [
                'title' => $this->themeBlockText($websiteKey, $themeKey, sprintf('footer.columns.%d.title', $index), $column['title']),
                'links' => collect($column['links'])->map(
                    fn (string $link, int $linkIndex): string => $this->themeBlockText(
                        $websiteKey,
                        $themeKey,
                        sprintf('footer.columns.%d.links.%d', $index, $linkIndex),
                        $link,
                    ),
                )->all(),
            ];
        })->all();
    }

    private function resolveCommerceCompanyFooter(string $websiteKey, string $themeKey): array
    {
        $defaults = $this->commerceThemeDefaults($themeKey)['company_footer'];

        return [
            'address_line_1' => $this->themeBlockText($websiteKey, $themeKey, 'company_footer.address_line_1', $defaults['address_line_1']),
            'address_line_2' => $this->themeBlockText($websiteKey, $themeKey, 'company_footer.address_line_2', $defaults['address_line_2']),
        ];
    }

    private function resolveCommerceBranding(?SiteProfile $siteProfile, array $branding, string $websiteKey, string $themeKey): array
    {
        $defaults = $this->commerceThemeDefaults($themeKey)['branding'];
        $themePalette = $this->resolveThemePalette($siteProfile, $themeKey);

        if ($this->shouldUseCommerceBrandingFallback($siteProfile, $branding)) {
            $branding = array_merge($defaults, $branding);
        }

        if ($themePalette !== []) {
            $branding = array_merge($branding, $themePalette);
        }

        foreach (['company_name', 'slogan', 'support_location'] as $field) {
            if (filled($branding[$field] ?? null)) {
                $branding[$field] = $this->contentText($websiteKey, sprintf('branding.%s', $field), (string) $branding[$field]);
            }
        }

        return $branding;
    }

    private function resolveThemePalette(?SiteProfile $siteProfile, string $themeKey): array
    {
        $themePalettes = $siteProfile?->theme_palettes ?? [];
        $palette = $themePalettes[strtoupper($themeKey)] ?? null;

        return is_array($palette) ? $palette : [];
    }

    private function commerceThemeDefaults(string $themeKey): array
    {
        return match (strtoupper($themeKey)) {
            default => [
                'hero_slide' => [
                    'eyebrow' => 'Ưu đãi nổi bật',
                    'badge' => 'Khám phá ngay',
                    'cta' => 'Xem ngay',
                ],
                'footer_columns' => [
                    ['title' => 'Trợ giúp', 'links' => ['Chính sách giao hàng', 'Cách thức thanh toán', 'Hotdeal E-voucher', 'Membership']],
                    ['title' => 'Giới thiệu', 'links' => ['Về chúng tôi', 'Liên hệ', 'Chính sách bảo mật', 'Quy chế hoạt động']],
                    ['title' => 'Hợp tác', 'links' => ['Thẻ quà tặng', 'Liên hệ hợp tác', 'Tuyển dụng', 'Thông tin báo chí']],
                ],
                'company_footer' => [
                    'address_line_1' => '332 Lũy Bán Bích, Phường Hòa Thạnh, Quận Tân Phú, TP.HCM',
                    'address_line_2' => 'Chi nhánh Hà Nội: Tầng 3, CT2 Ban Cơ Yếu Chính Phủ, Thanh Xuân',
                ],
                'branding' => [
                    'company_name' => 'AIO Commerce',
                    'slogan' => 'Deal ngon mỗi ngày, mua nhanh giá tốt',
                    'logo_url' => self::DEFAULT_BRAND_ASSET,
                    'favicon_url' => self::DEFAULT_BRAND_ASSET,
                    'primary_color' => '#ef2b2d',
                    'support_hotline' => '1900 6760 / 0354.466.968',
                    'support_email' => 'support@htvietnam.vn',
                    'support_location' => 'TP.HCM & Hà Nội',
                ],
                'section_tabs' => ['Mới nhất', 'Bán chạy', 'Giá tốt'],
                'featured_tones' => ['#101828', '#8f5f00', '#1c8c64', '#a66900', '#0d9488'],
            ],
        };
    }

    private function shouldUseCommerceBrandingFallback(?SiteProfile $siteProfile, array $branding): bool
    {
        if ($this->isDemoPresetBranding($branding)) {
            return true;
        }

        if (($siteProfile?->website_type ?? null) !== 'ecommerce') {
            return true;
        }

        $signals = [
            $siteProfile?->site_name,
            $branding['company_name'] ?? null,
            $branding['slogan'] ?? null,
            $branding['support_location'] ?? null,
        ];

        return collect($signals)
            ->filter(fn (mixed $value): bool => filled($value))
            ->contains(fn (mixed $value): bool => preg_match('/viet\s*tour|tour|coach|airport|shuttle|fleet|route|bao\s*gia|dua\s*don|van\s*hanh|charter/i', (string) $value) === 1);
    }

    private function shouldUseCommerceTopMenuFallback(array $items): bool
    {
        return collect($items)->contains(function (mixed $item): bool {
            if (! is_array($item)) {
                return false;
            }

            $signals = [
                $item['label'] ?? null,
                $item['url'] ?? null,
            ];

            return collect($signals)
                ->filter(fn (mixed $value): bool => filled($value))
                ->contains(function (mixed $value): bool {
                    $normalizedValue = $this->normalizeSearchText((string) $value);

                    return preg_match('/bao\s*gia|fleet|airport|shuttle|charter|tour|coach|xe|tuyen|route|demo\s*ser010[01]/', $normalizedValue) === 1;
                });
        });
    }

    private function shouldUseLandingHybridTopMenuFallback(array $items): bool
    {
        return collect($items)->contains(function (mixed $item): bool {
            if (! is_array($item)) {
                return false;
            }

            $url = trim((string) ($item['url'] ?? $item['href'] ?? ''));

            if ($url === '' || $url === '#') {
                return true;
            }

            $normalizedUrl = $this->normalizeSearchText($url);

            return preg_match('/demo[-_\s]*th\d+|demo[-_\s]*ser\d+/', $normalizedUrl) === 1;
        });
    }

    private function resolveServiceThemeKey(?array $activeTheme): ?string
    {
        $themeKey = strtoupper((string) ($activeTheme['key'] ?? ''));

        return $this->isServiceThemeKey($themeKey) ? $themeKey : null;
    }

    private function isServiceThemeKey(?string $themeKey): bool
    {
        return strtoupper((string) $themeKey) === 'SER0101';
    }

    private function isCommerceThemeKey(?string $themeKey): bool
    {
        return in_array(strtoupper((string) $themeKey), ['SHOP601', 'SHOP602', 'NT502'], true);
    }

    private function isLandingHybridThemeKey(?string $themeKey): bool
    {
        return in_array(strtoupper((string) $themeKey), ['XD0301'], true);
    }

    private function themeBlockText(string $websiteKey, string $themeKey, string $blockKey, ?string $default): string
    {
        return $this->contentText(
            $websiteKey,
            app(\App\Support\ThemeBlockRegistry::class)->contentKey($themeKey, $blockKey),
            $default,
        );
    }

    private function resolveServiceHighlights(Collection $parentCategories): array
    {
        $defaults = [
            ['title' => 'Báo giá nhanh', 'summary' => 'Nổi bật CTA gọi hotline, nhận lịch trình và yêu cầu tư vấn ngay trên hero.'],
            ['title' => 'Fleet rõ ràng', 'summary' => 'Danh mục và gói dịch vụ hiển thị theo số chỗ, nhu cầu và hành trình để ra quyết định nhanh hơn.'],
            ['title' => 'Nội dung tạo trust', 'summary' => 'Có sẵn block chỉ số vận hành, bài viết hướng dẫn và vị trí liên hệ cho doanh nghiệp dịch vụ.'],
        ];

        if ($parentCategories->isEmpty()) {
            return $defaults;
        }

        return $parentCategories->take(3)->values()->map(function (CatalogCategory $category, int $index) use ($defaults): array {
            return [
                'title' => $category->name,
                'summary' => $category->children->pluck('name')->take(3)->implode(', ') ?: $defaults[$index]['summary'],
            ];
        })->all();
    }

    private function resolveLatestPostHighlights(string $websiteKey): array
    {
        $query = CmsPost::query()->with('featuredMedia')->where('status', 'published')->latest('publish_at');
        $this->applyWebsiteScope($query, $websiteKey);

        return $query->take(3)->get()->map(function (CmsPost $post) use ($websiteKey): array {
            $post = $this->localizePostModel($post, $websiteKey);

            return [
                'title' => $post->title,
                'excerpt' => $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 180, '...'),
                'url' => route('site.blog.show', ['slug' => $post->slug]),
                'image' => $post->featuredMedia?->url,
                'published_at' => $post->publish_at?->format('d/m/Y'),
            ];
        })->all();
    }

    private function mapProductCard(CatalogProduct $product, ?string $themeKey = null): array
    {
        $websiteKey = $this->resolveWebsiteKey($this->currentSiteProfile());
        $resolvedThemeKey = $themeKey ?: 'SHOP601';
        $product = $this->localizeProductModel($product, $websiteKey);
        $originalPrice = $product->original_price !== null ? (float) $product->original_price : null;
        $price = (float) $product->price;
        $discount = ($originalPrice !== null && $originalPrice > 0 && $originalPrice > $price)
            ? (int) round((($originalPrice - $price) / $originalPrice) * 100)
            : 0;

        $highlightLines = $this->splitTextLines($product->highlights);
        $usageTermLines = $this->splitTextLines($product->usage_terms);
        $usageLocationLines = $this->splitTextLines($product->usage_location);

        return [
            'title' => $product->name,
            'price' => $price,
            'old_price' => $originalPrice,
            'discount' => $discount,
            'image' => $this->resolveProductPrimaryImage($product),
            'tag' => $product->category?->name ?: $this->themeText('theme.fallback.new_product', 'Sản phẩm mới', $resolvedThemeKey),
            'meta' => $product->stock,
            'sku' => $product->sku,
            'summary' => $product->short_description,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'meta_keywords' => $product->meta_keywords,
            'sold_count' => (int) ($product->sold_count ?? 0),
            'deal_end_at' => $product->deal_end_at?->toIso8601String(),
            'highlights' => $highlightLines,
            'usage_terms_lines' => $usageTermLines,
            'usage_location_lines' => $usageLocationLines,
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

        return $galleryImage ?: 'https://picsum.photos/seed/shop601-product-fallback/640/420';
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
            $images = collect(['https://picsum.photos/seed/shop601-product-fallback/960/720']);
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

    /**
     * @return array<int, int>
     */
    private function resolveAccentInsensitiveProductIds(string $search, string $websiteKey, bool $includeDetailContent): array
    {
        $normalizedSearch = $this->normalizeSearchText($search);

        if ($normalizedSearch === '') {
            return [];
        }

        $query = CatalogProduct::query()
            ->select(['id', 'name', 'sku', 'short_description', 'detail_content'])
            ->where('is_active', true);
        $this->applyWebsiteScope($query, $websiteKey);

        return $query->get()
            ->filter(function (CatalogProduct $product) use ($normalizedSearch, $includeDetailContent): bool {
                $haystacks = [
                    $product->name,
                    $product->sku,
                    $product->short_description,
                ];

                if ($includeDetailContent) {
                    $haystacks[] = $product->detail_content;
                }

                foreach ($haystacks as $haystack) {
                    if (str_contains($this->normalizeSearchText((string) $haystack), $normalizedSearch)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private function normalizeSearchText(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
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
        $viewFactory = app(\Illuminate\Contracts\View\Factory::class);

        if ($themeKey !== '') {
            $viewName = "theme-{$themeKey}::{$viewKey}";

            if ($viewFactory->exists($viewName)) {
                return view($viewName, $data);
            }
        }

        foreach (['theme-shop601'] as $fallbackNamespace) {
            $fallbackView = "{$fallbackNamespace}::{$viewKey}";

            if ($viewFactory->exists($fallbackView)) {
                return view($fallbackView, $data);
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
        $normalizedSlug = trim($slug, '/');
        $resolvedSlug = CmsPage::query()
            ->where('status', 'published')
            ->where(function (EloquentBuilder $query) use ($normalizedSlug): void {
                $query
                    ->where('slug', $normalizedSlug)
                    ->orWhere('slug', 'like', '%-'.$normalizedSlug);
            })
            ->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', [$normalizedSlug])
            ->orderBy('id')
            ->value('slug') ?: $normalizedSlug;

        return FrontendRouteUrl::page($resolvedSlug, $this->currentLocale());
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

    private function localizedUrl(string $url, ?string $anchorFallback = null): string
    {
        $value = trim($url);

        if ($value === '') {
            return $anchorFallback ?? '#';
        }

        if (str_starts_with($value, '#')) {
            return $anchorFallback ?? $value;
        }

        $localePattern = implode('|', array_map(fn (string $locale): string => preg_quote($locale, '/'), FrontendLocalization::supportedLocales()));
        if ($anchorFallback !== null && preg_match('/^\/?(?:'.$localePattern.')?\/?#/i', $value) === 1) {
            return $anchorFallback;
        }

        if (preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\//i', $value) || preg_match('/^(mailto:|tel:|javascript:)/i', $value)) {
            return $value;
        }

        return FrontendRouteUrl::localized($value, $this->currentLocale());
    }

    private function currentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * @return array{canonical_url: string, alternates: array<string, string>, resolved_locale: string}
     */
    private function localizedContentSeo(
        string $resourceType,
        object $model,
        string $resolvedLocale,
    ): array {
        $websiteKey = (string) ($model->website_key ?? self::DEFAULT_WEBSITE_KEY);
        $alternates = $this->localizedContent
            ->publicTranslations($resourceType, (string) $model->getKey(), $websiteKey)
            ->filter(fn ($translation): bool => filled($translation->slug))
            ->mapWithKeys(fn ($translation): array => [
                $translation->locale => $this->localizedContentUrl(
                    $resourceType,
                    (string) $translation->slug,
                    (string) $translation->locale,
                ),
            ])
            ->all();
        $currentSlug = (string) ($model->slug ?? '');
        $canonical = $this->localizedContentUrl(
            $resourceType,
            $currentSlug,
            $resolvedLocale,
        );
        $defaultLocale = $this->localeContext->defaultLocale($websiteKey);

        if (isset($alternates[$defaultLocale])) {
            $alternates['x-default'] = $alternates[$defaultLocale];
        }

        return [
            'canonical_url' => $canonical,
            'alternates' => $alternates,
            'resolved_locale' => $resolvedLocale,
        ];
    }

    private function localizedContentUrl(
        string $resourceType,
        string $slug,
        string $locale,
    ): string {
        return match ($resourceType) {
            'cms_post' => FrontendRouteUrl::post($slug, $locale),
            'cms_service' => FrontendRouteUrl::service($slug, $locale),
            'cms_project' => FrontendRouteUrl::project($slug, $locale),
            'catalog_product' => FrontendRouteUrl::product($slug, $locale),
            'catalog_category' => FrontendRouteUrl::category($slug, $locale),
            'cms_category' => FrontendRouteUrl::blogCategory($slug, $locale),
            'cms_service_category' => FrontendRouteUrl::serviceCategory($slug, $locale),
            'cms_project_category' => FrontendRouteUrl::projectCategory($slug, $locale),
            default => FrontendRouteUrl::home($locale),
        };
    }

    private function themeText(string $key, string $default, ?string $themeKey = null): string
    {
        return $this->themeTranslationService->bladeText($themeKey ?: 'SHOP601', $this->currentLocale(), $key, $default);
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
        /** @var SiteProfile $localized */
        $localized = $this->localizedContent->localize(
            $siteProfile,
            'site_profile',
            $this->currentLocale(),
            $websiteKey,
        );
        $localized->site_name = $this->contentText($websiteKey, 'site_profile.site_name', $localized->site_name);

        $branding = $localized->branding ?? [];
        if (! $this->isDemoPresetBranding($branding)) {
            foreach (['company_name', 'slogan', 'support_location'] as $field) {
                if (filled($branding[$field] ?? null)) {
                    $branding[$field] = $this->contentText($websiteKey, sprintf('branding.%s', $field), (string) $branding[$field]);
                }
            }
        }

        $localized->setAttribute('branding', $branding);

        return $localized;
    }

    private function localizeCategoryModel(CatalogCategory $category, string $websiteKey): CatalogCategory
    {
        /** @var CatalogCategory $localized */
        $localized = $this->localizedContent->localize(
            $category,
            'catalog_category',
            $this->currentLocale(),
            $websiteKey,
        );

        if ($category->relationLoaded('parent') && $category->parent) {
            $localized->setRelation('parent', $this->localizeCategoryModel($category->parent, $websiteKey));
        }

        if ($category->relationLoaded('children')) {
            $localized->setRelation('children', $category->children->map(fn (CatalogCategory $child): CatalogCategory => $this->localizeCategoryModel($child, $websiteKey)));
        }

        return $localized;
    }

    private function isDemoPresetBranding(array $branding): bool
    {
        return filled($branding['demo_preset_key'] ?? null);
    }

    private function localizeProductModel(CatalogProduct $product, string $websiteKey): CatalogProduct
    {
        /** @var CatalogProduct $localized */
        $localized = $this->localizedContent->localize(
            $product,
            'catalog_product',
            $this->currentLocale(),
            $websiteKey,
        );

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
        /** @var CmsPost $localized */
        $localized = $this->localizedContent->localize(
            $post,
            'cms_post',
            $this->currentLocale(),
            $websiteKey,
        );

        if ($post->relationLoaded('category') && $post->category) {
            /** @var CmsCategory $localizedCategory */
            $localizedCategory = $this->localizedContent->localize(
                $post->category,
                'cms_category',
                $this->currentLocale(),
                $websiteKey,
            );
            $localized->setRelation('category', $localizedCategory);
        }

        if ($post->relationLoaded('featuredMedia') && $post->featuredMedia) {
            $localized->setRelation(
                'featuredMedia',
                $this->localizeMediaModel($post->featuredMedia, $websiteKey),
            );
        }

        return $localized;
    }

    private function localizeMediaModel(
        CmsMedia $media,
        string $websiteKey,
    ): CmsMedia {
        /** @var CmsMedia $localized */
        $localized = $this->localizedContent->localize(
            $media,
            'cms_media',
            $this->currentLocale(),
            $websiteKey,
        );

        return $localized;
    }

    private function localizeServiceModel(CmsService $service, string $websiteKey): CmsService
    {
        /** @var CmsService $localized */
        $localized = $this->localizedContent->localize(
            $service,
            'cms_service',
            $this->currentLocale(),
            $websiteKey,
        );
        $localized->excerpt = $localized->summary;
        $localized->body = $localized->content;

        return $localized;
    }

    private function localizeProjectModel(CmsProject $project, string $websiteKey): CmsProject
    {
        /** @var CmsProject $localized */
        $localized = $this->localizedContent->localize(
            $project,
            'cms_project',
            $this->currentLocale(),
            $websiteKey,
        );
        $localized->excerpt = $localized->summary;
        $localized->body = $localized->content;

        if ($project->relationLoaded('category') && $project->category) {
            /** @var CmsProjectCategory $localizedCategory */
            $localizedCategory = $this->localizedContent->localize(
                $project->category,
                'cms_project_category',
                $this->currentLocale(),
                $websiteKey,
            );
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
        $siteProfile = $this->currentSiteProfile();
        $websiteKey = $this->resolveWebsiteKey($siteProfile);
        $resolution = $this->localizedContent->resolvePublishedBySlug(
            'catalog_product',
            $websiteKey,
            $this->currentLocale(),
            $slug,
        );

        abort_if($resolution === null, 404);

        /** @var CatalogProduct $product */
        $product = $resolution['model'];

        return $product;
    }

    private function resolveProductPreviewModel(string $identifier, bool $allowInactive): CatalogProduct
    {
        $siteProfile = $this->currentSiteProfile();
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
            throw ValidationException::withMessages([
                'cart' => 'Sản phẩm hiện đã hết hàng.',
            ]);
        }

        if ($product->stock !== null) {
            $quantity = min($quantity, max(1, (int) $product->stock));
        }

        return $quantity;
    }
}
