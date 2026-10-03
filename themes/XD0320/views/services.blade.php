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
        .xd20-service-page{padding:40px 0 64px;background:#f5f5f4;color:#202325}
        .xd20-service-page .xd-container{width:min(1240px,calc(100% - 48px));margin-inline:auto}
        .xd20-service-page .xd-cms-hero{display:flex;align-items:center;justify-content:space-between;gap:28px;margin:0 0 28px;padding:0 0 28px;border-bottom:1px solid #dfe1e2}
        .xd20-service-page .xd-kicker{display:block;color:#c82716;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;margin-bottom:12px}
        .xd20-service-page h1{font-size:clamp(28px,3vw,42px);line-height:1.2;margin:0;font-weight:800;color:#202325}
        .xd20-service-page .xd-cms-hero p{font-size:15px;line-height:1.6;color:#62686d;margin:12px 0 0}
        .xd20-service-page .xd-cms-stats{display:flex;align-items:center;gap:12px;flex-shrink:0;padding:14px 20px;border-left:3px solid #eb2916;background:#fff}
        .xd20-service-page .xd-cms-stats strong{font-size:28px;color:#202325}
        .xd20-service-page .xd-cms-stats span{font-size:12px;color:#62686d;max-width:100px;line-height:1.5}
        .xd20-service-page .xd-services-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
        .xd20-service-page .xd-service-card{display:flex;flex-direction:column;min-width:0;background:#fff;border:1px solid #e2e3e4;border-radius:8px;overflow:hidden;transition:box-shadow .2s,border-color .2s}
        .xd20-service-page .xd-service-card:hover{border-color:#c7cace;box-shadow:0 8px 24px #2023250d}
        .xd20-service-page .xd-service-image{display:block;aspect-ratio:16/9;overflow:hidden;background:#e8e9e9}
        .xd20-service-page .xd-service-image img{display:block;width:100%;height:100%;object-fit:cover}
        .xd20-service-page .xd-service-body{display:flex;flex:1;flex-direction:column;padding:22px}
        .xd20-service-page h2{font-size:21px;line-height:1.4;margin:0 0 10px;font-weight:700}
        .xd20-service-page h2 a{color:#202325;text-decoration:none}
        .xd20-service-page h2 a:hover{color:#c82716}
        .xd20-service-page .xd-service-body p{font-size:14px;line-height:1.7;color:#62686d;margin:0 0 20px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
        .xd20-service-page .xd-text-link{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:auto;padding-top:14px;border-top:1px solid #eceeed;color:#b72516;font-size:13px;font-weight:700;text-decoration:none}
        .xd20-service-page .xd-text-link:after{content:'↗';font-size:20px}
        .xd20-service-page a:focus-visible{outline:3px solid #eb2916;outline-offset:-3px}
        @media(max-width:900px){.xd20-service-page .xd-services-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:600px){.xd20-service-page{padding:28px 0 40px}.xd20-service-page .xd-container{width:calc(100% - 32px)}.xd20-service-page .xd-cms-hero{align-items:flex-start;flex-direction:column;gap:16px}.xd20-service-page .xd-cms-stats{padding:8px 14px}.xd20-service-page .xd-cms-stats strong{font-size:22px}.xd20-service-page .xd-cms-stats span{max-width:none}.xd20-service-page .xd-services-list{grid-template-columns:1fr;gap:20px}.xd20-service-page .xd-service-body{padding:20px}}
    </style>
@endpush

@section('content')
<main class="xd-page-main xd20-service-page">
            <div class="xd-container">
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.f7d5c6216287dbca', 'Dịch vụ') }}</span>
                            <h1>{{ $pageTitle ?? (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.f7d5c6216287dbca', 'Dịch vụ')) }}</h1>
                            <p>{{ $pageDescription ?? (app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.54d378d9d1314481', 'Danh sách dịch vụ thiết kế và thi công nổi bật.')) }}</p>
                        </div>
                        <div class="xd-cms-stats">
                            <strong>{{ collect($listingItems ?? [])->count() }}</strong>
                            <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.82fb2e9e0ebce8c4', 'Dịch vụ đang hiển thị') }}</span>
                        </div>
                    </section>

                    <section class="xd-services-list">
                        @forelse ($listingItems as $service)
                            @php
                                $serviceUrl = route('site.services.show', ['slug' => $service->slug]);
                                $image = $service->featuredImage?->image_url;
                                $alt = $service->featuredImage?->alt_text ?: $service->title;
                            @endphp
                            <article class="xd-service-card">
                                <a class="xd-service-image" href="{{ $serviceUrl }}" aria-label="{{ $service->title }}">
                                    @if ($image)
                                        <img loading="lazy" src="{{ $image }}" alt="{{ $alt }}">
                                    @endif
                                </a>
                                <div class="xd-service-body">
                                    <h2><a href="{{ $serviceUrl }}">{{ $service->title }}</a></h2>
                                    <p>{{ $service->summary ?: \Illuminate\Support\Str::limit(strip_tags($service->content ?? ''), 150) }}</p>
                                    <a class="xd-text-link" href="{{ $serviceUrl }}">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.32919a31e26bbb86', 'Tìm hiểu ngay') }}</a>
                                </div>
                            </article>
                        @empty
                            <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.f2320a5706a9550e', 'Chưa có dịch vụ nào được xuất bản.') }}</p>
                        @endforelse
                    </section>
            </div>
</main>
@endsection
