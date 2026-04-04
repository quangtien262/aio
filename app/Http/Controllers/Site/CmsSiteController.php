<?php

namespace App\Http\Controllers\Site;

use App\Core\Themes\ThemeRegistry;
use App\Models\CmsMenu;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\SiteProfile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CmsSiteController
{
    public function __construct(
        private readonly ThemeRegistry $themeRegistry,
    ) {
    }

    public function home(): View
    {
        $siteProfile = SiteProfile::query()->first();
        $activeTheme = $this->resolveActiveTheme($siteProfile);

        if ($themeHomeView = $this->resolveThemeHomeView($activeTheme)) {
            return view($themeHomeView, [
                'siteProfile' => $siteProfile,
                'activeTheme' => $activeTheme,
                'menus' => $this->resolveMenus(),
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
            'menus' => $this->resolveMenus(),
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
            'menus' => $this->resolveMenus(),
            'isPreview' => false,
            'pageTitle' => $title,
            'pageDescription' => $description,
        ]);
    }

    private function resolveMenus(): array
    {
        return CmsMenu::query()->get()->groupBy('location')->map(fn ($items) => $items->first()?->items ?? [])->all();
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
}
