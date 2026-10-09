@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));

    $localizeMenuUrl = static fn (?string $href): string => \App\Support\FrontendRouteUrl::localized($href);

    $repairXdLabel = static function (string $label): string {
        $label = trim($label);

        return strtr($label, [
            'Trang chủ' => 'Trang chủ',
            'TRANG CHÁ»§' => 'TRANG CHỦ',
            'trang chủ' => 'trang chủ',
            'Sản phẩm' => 'Sản phẩm',
            'SảN PHÁº©M' => 'SẢN PHẨM',
            'SÁº£N PHÁº©M' => 'SẢN PHẨM',
            'sản phẩm' => 'sản phẩm',
            'sản phẩm' => 'sản phẩm',
            'Sản phẩm' => 'Sản phẩm',
            'Tài khoản' => 'Tài khoản',
            'Tà I KHOảN' => 'TÀI KHOẢN',
        ]);
    };

    $normalizeNavItem = function (array $item) use (&$normalizeNavItem, $localizeMenuUrl, $repairXdLabel): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => $repairXdLabel((string) ($item['label'] ?? $item['title'] ?? 'Menu')),
            'href' => $localizeMenuUrl($href),
            'target' => $item['target'] ?? '_self',
            'active' => false,
            'children' => collect($item['children'] ?? [])
                ->filter(fn ($child): bool => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))
                ->map(fn (array $child): array => $normalizeNavItem($child))
                ->values()
                ->all(),
        ];
    };

    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $homeUrl = route('site.home');
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chủ', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.4c23dc9bef7f79b4', 'Trang chủ'),
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

    if (false && ! $hasProductItem && \Illuminate\Support\Facades\Schema::hasTable('catalog_categories') && \Illuminate\Support\Facades\Schema::hasTable('catalog_products')) {
        $productCategories = \App\Models\CatalogCategory::query()
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->withCount(['products' => fn ($productQuery) => $productQuery->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category): bool => (int) $category->products_count > 0 || $category->children->contains(fn ($child): bool => (int) $child->products_count > 0))
            ->take(8)
            ->values();

        if ($productCategories->isNotEmpty()) {
            $productMenuItem = [
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productCategories
                    ->map(fn ($category): array => [
                        'label' => (string) $category->name,
                        'href' => route('site.catalog.category', ['slug' => $category->slug]),
                        'target' => '_self',
                        'active' => false,
                        'children' => $category->children
                            ->filter(fn ($child): bool => (int) $child->products_count > 0)
                            ->take(8)
                            ->map(fn ($child): array => [
                                'label' => (string) $child->name,
                                'href' => route('site.catalog.category', ['slug' => $child->slug]),
                                'target' => '_self',
                                'active' => false,
                                'children' => [],
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
            $navItems = collect($navArray);
        }
    }

    $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems
                ->map(function (array $item) use ($productNavigationItems): array {
                    $label = mb_strtolower(trim((string) ($item['label'] ?? '')));

                    if (in_array($label, ['sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                        $item['children'] = $productNavigationItems->all();
                    }

                    return $item;
                })
                ->values();
        } else {
            $productMenuItem = [
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
            $navItems = collect($navArray);
        }
    }

    $currentUrl = rtrim(url()->current(), '/');
    $navItems = $navItems->map(function (array $item) use ($currentUrl): array {
        $href = (string) ($item['href'] ?? '#');
        $absoluteHref = str_starts_with($href, 'http') ? rtrim($href, '/') : rtrim(url($href), '/');
        $item['active'] = $href !== '#' && $absoluteHref === $currentUrl;

        return $item;
    })->values();

    $isServiceListing = ($contentType ?? null) === 'services';
    $isServiceDetail = ($contentType ?? null) === 'service';
    $isPostListing = ($contentType ?? null) === 'posts';
    $entrySlug = (string) ($entry->slug ?? '');
    $isContactPage = ! $isServiceListing
        && ! $isServiceDetail
        && ! $isPostListing
        && in_array($entrySlug, ['lien-he', 'contact'], true);
    $title = $pageTitle ?? ($entry->title ?? data_get($siteProfile, 'site_name', 'Arkit'));
    $description = $pageDescription ?? ($entry->excerpt ?? '');
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-xd0320-cms';
@endphp

@extends('theme-xd0320::layout')

@section('title', $title)

@if (!empty($description))
    @push('head')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif

@push('head')
    <style>
.xd20-service-detail{background:#f5f5f4;color:#202325;padding:36px 0 64px}.xd20-service-detail .xd-container{width:min(1240px,calc(100% - 48px));margin:auto}.xd20-service-detail .xd-detail{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:28px;align-items:start}.xd20-service-detail .xd-detail-card{min-width:0;background:#fff;border:1px solid #e2e3e4;border-radius:8px;overflow:hidden}.xd20-service-detail .xd-detail-body{padding:28px 32px}.xd20-service-detail .xd-kicker{display:block;margin-bottom:12px;color:#c82716;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.1em}.xd20-service-detail h1{font-size:clamp(28px,3vw,40px);line-height:1.2;margin:0 0 16px;color:#202325}.xd20-service-detail .xd-detail-summary{color:#62686d;font-size:16px;line-height:1.7;margin:0}.xd20-service-detail .xd-detail-image{display:block;width:100%;max-height:360px;object-fit:cover}.xd20-service-detail .xd-rich-content{font-size:16px;line-height:1.8;color:#424b51;overflow-wrap:anywhere}.xd20-service-detail .xd-rich-content h2{font-size:24px;line-height:1.4;color:#202325;margin:24px 0 12px}.xd20-service-detail .xd-rich-content h3{font-size:20px}.xd20-service-detail .xd-rich-content p{margin:0 0 16px}.xd20-service-detail .xd-rich-content img,.xd20-service-detail .xd-rich-content iframe{max-width:100%}.xd20-service-detail .xd-rich-content table{display:block;max-width:100%;overflow:auto}.xd20-service-detail .xd-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-top:24px}.xd20-service-detail .xd-gallery figure{margin:0}.xd20-service-detail .xd-gallery img{width:100%;height:auto;border-radius:4px}.xd20-service-detail .xd-gallery figcaption{font-size:13px;color:#62686d;margin-top:8px}
.xd20-latest-services{background:#fff;border:1px solid #e2e3e4;border-radius:8px;padding:24px}.xd20-latest-services h2{font-size:22px;margin:0 0 8px;padding-bottom:16px;border-bottom:2px solid #eb2916;color:#202325}.xd20-latest-service{display:flex;gap:12px;align-items:center;padding:16px 0;border-bottom:1px solid #eceeed;text-decoration:none;color:#202325}.xd20-latest-service:last-child{border-bottom:0;padding-bottom:0}.xd20-latest-service img{width:72px;height:64px;object-fit:cover;border-radius:4px;flex-shrink:0}.xd20-latest-service span{font-size:15px;font-weight:700;line-height:1.5}.xd20-latest-service:hover{color:#c82716}.xd20-service-detail a:focus-visible{outline:3px solid #eb2916;outline-offset:3px}
@media(max-width:900px){.xd20-service-detail .xd-detail{grid-template-columns:1fr}.xd20-latest-services{max-width:none}}@media(max-width:600px){.xd20-service-detail{padding:24px 0 40px}.xd20-service-detail .xd-container{width:calc(100% - 32px)}.xd20-service-detail .xd-detail-body{padding:22px}.xd20-service-detail .xd-detail-image{max-height:240px}.xd20-service-detail .xd-gallery{grid-template-columns:1fr}.xd20-latest-services{padding:22px}}
    </style>
@endpush

@section('content')
<main class="xd-page-main xd20-service-detail">
            <div class="xd-container">
                    @php
                        $featuredImage = $entry->featuredImage?->image_url;
                        $featuredAlt = $entry->featuredImage?->alt_text ?: $entry->title;
                    @endphp
                    <section class="xd-detail">
                        <article class="xd-detail-card">
                            <div class="xd-detail-body">
                                <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.c88c165a29889115', 'Dịch vụ') }}</span>
                                <h1>{{ $entry->title }}</h1>
                                @if (!empty($entry->excerpt))
                                    <p class="xd-detail-summary">{{ $entry->excerpt }}</p>
                                @endif
                            </div>
                            @if ($featuredImage)
                                <img class="xd-detail-image" src="{{ $featuredImage }}" alt="{{ $featuredAlt }}">
                            @endif
                            <div class="xd-detail-body">
                                <div class="xd-rich-content">
                                    {!! $entry->body ?: '<p>Nội dung đang được cập nhật.</p>' !!}
                                </div>

                                @if (!empty($entry->images) && $entry->images->count() > 1)
                                    <div class="xd-gallery">
                                        @foreach ($entry->images as $image)
                                            <figure>
                                                <img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}">
                                                @if (!empty($image->caption))
                                                    <figcaption>{{ $image->caption }}</figcaption>
                                                @endif
                                            </figure>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                        @if(collect($latestServices ?? [])->isNotEmpty())
                        <aside class="xd20-latest-services" aria-labelledby="xd20-latest-title">
                            <h2 id="xd20-latest-title">{{ app()->getLocale() === 'vi' ? 'Dịch vụ mới nhất' : 'Latest services' }}</h2>
                            @foreach(collect($latestServices)->take(5) as $latestService)
                            <a class="xd20-latest-service" href="{{ route('site.services.show', ['slug' => $latestService->slug]) }}">
                                @if($latestService->featuredImage?->image_url)
                                <img loading="lazy" src="{{ $latestService->featuredImage->image_url }}" alt="">
                                @endif
                                <span>{{ $latestService->title }}</span>
                            </a>
                            @endforeach
                        </aside>
                        @endif
                    </section>
            </div>
</main>
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections'])
@endsection
