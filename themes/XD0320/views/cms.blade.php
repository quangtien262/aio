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
        .xd-page-main{padding:76px 0 90px}
        .xd-cms-hero{display:grid;grid-template-columns:minmax(0,.75fr) minmax(340px,.45fr);gap:48px;align-items:end;margin-bottom:54px;padding:56px;border:1px solid var(--line);background:#fff;box-shadow:0 20px 55px rgba(28,45,60,.08)}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-cms-hero h1{margin:0;color:var(--ink);font-size:clamp(42px,5vw,72px);line-height:1.08;letter-spacing:-.055em}
        .xd-cms-hero p{margin:18px 0 0;color:var(--muted);font-size:20px;font-weight:550}
        .xd-cms-stats{display:grid;gap:12px;color:#fff;background:var(--ink);padding:26px 30px}
        .xd-cms-stats strong{font-size:46px;line-height:1}
        .xd-cms-stats span{color:rgba(255,255,255,.75);font-weight:800;text-transform:uppercase}
        .xd-services-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:34px}
        .xd-service-card{background:#fff;box-shadow:0 5px 20px rgba(16,29,40,.08);transition:.25s}
        .xd-service-card:hover{transform:translateY(-8px);box-shadow:var(--shadow)}
        .xd-service-image{display:block;height:300px;overflow:hidden;background:#eef2ef}
        .xd-service-image img{width:100%;height:100%;object-fit:cover;transition:.4s}
        .xd-service-card:hover img{transform:scale(1.05)}
        .xd-service-body{padding:36px 38px 40px}
        .xd-service-card h2,.xd-service-card h3{margin:0 0 14px;font-size:22px;line-height:1.32;letter-spacing:.015em;text-transform:uppercase}
        .xd-service-card p{margin:0 0 26px;color:var(--muted);font-size:17px}
        .xd-text-link{color:var(--lime-dark);font-weight:900;text-transform:uppercase}
        .xd-detail{display:grid;grid-template-columns:minmax(0,.85fr) minmax(300px,.35fr);gap:44px}
        .xd-detail-card,.xd-side-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}
        .xd-detail-card{overflow:hidden}
        .xd-detail-image{width:100%;max-height:520px;object-fit:cover}
        .xd-detail-body{padding:44px 52px}
        .xd-detail-body h1{margin:0 0 18px;font-size:clamp(38px,4vw,62px);line-height:1.1;letter-spacing:-.05em}
        .xd-detail-summary{margin:0 0 28px;color:var(--muted);font-size:20px}
        .xd-rich-content{color:#465461;font-size:18px}
        .xd-rich-content :first-child{margin-top:0}
        .xd-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:28px}
        .xd-gallery figure{margin:0}
        .xd-gallery img{width:100%;height:210px;object-fit:cover}
        .xd-gallery figcaption{margin-top:8px;color:var(--muted);font-size:14px}
        .xd-side-card{padding:28px}
        .xd-side-card h3{margin:0 0 18px;font-size:24px}
        .xd-side-card a{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-side-card a:hover{color:var(--lime-dark)}
        .xd-contact-page{display:grid;grid-template-columns:minmax(0,.9fr) minmax(420px,.72fr);gap:34px;align-items:stretch}
        .xd-contact-panel,.xd-contact-form-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}
        .xd-contact-panel{padding:44px 48px;background:linear-gradient(135deg,#fff 0%,#f7faee 100%)}
        .xd-contact-panel h2,.xd-contact-form-card h2{margin:0 0 18px;font-size:34px;line-height:1.15;letter-spacing:-.04em}
        .xd-contact-panel p{margin:0 0 26px;color:var(--muted);font-size:18px;font-weight:600}
        .xd-contact-methods{display:grid;gap:16px;margin:0;padding:0;list-style:none}
        .xd-contact-method{display:grid;grid-template-columns:54px minmax(0,1fr);gap:16px;align-items:center;padding:18px;border:1px solid rgba(38,56,74,.1);background:#fff}
        .xd-contact-icon{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;background:var(--ink);color:#fff;font-size:22px;font-weight:900}
        .xd-contact-method small{display:block;color:var(--lime-dark);font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase}
        .xd-contact-method a,.xd-contact-method span{color:var(--ink);font-size:18px;font-weight:850;overflow-wrap:anywhere}
        .xd-contact-note{margin-top:24px;padding:20px 22px;background:var(--ink);color:#fff}
        .xd-contact-note strong{display:block;margin-bottom:6px;color:var(--lime)}
        .xd-contact-note span{color:rgba(255,255,255,.78);font-weight:650}
        .xd-contact-form-card{padding:44px 48px}
        .xd-contact-form{display:grid;gap:16px}
        .xd-contact-field{display:grid;gap:8px}
        .xd-contact-field label{font-size:13px;font-weight:950;letter-spacing:.04em;text-transform:uppercase}
        .xd-contact-field input,.xd-contact-field textarea{width:100%;border:1px solid var(--line);border-radius:0;background:#fbfcfa;color:var(--ink);font:inherit;font-weight:650;outline:0;transition:.2s}
        .xd-contact-field input{height:56px;padding:0 18px}
        .xd-contact-field textarea{min-height:150px;padding:16px 18px;resize:vertical}
        .xd-contact-field input:focus,.xd-contact-field textarea:focus{border-color:var(--lime);box-shadow:0 0 0 4px rgba(189,212,0,.14)}
        .xd-contact-submit{display:inline-flex;align-items:center;justify-content:center;width:max-content;min-height:58px;padding:0 30px;border:0;background:var(--lime);color:#fff;box-shadow:0 15px 30px rgba(189,212,0,.28);font:inherit;font-weight:950;text-transform:uppercase;cursor:pointer}
        .xd-contact-submit:hover{transform:translateY(-1px)}
        .xd-contact-alert{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(143,169,0,.25);background:#f7fae5;color:var(--lime-dark);font-weight:850}
        .xd-contact-errors{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(180,35,24,.22);background:#fff4f2;color:#b42318;font-weight:800}
        .xd-contact-errors ul{margin:6px 0 0;padding-left:18px}
        @media (max-width:1180px){.xd-cms-hero,.xd-detail,.xd-contact-page{grid-template-columns:1fr}}
        @media (max-width:640px){.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-page-main{padding:38px 0 56px}.xd-cms-hero{padding:30px 22px;margin-bottom:26px}.xd-cms-hero h1{font-size:36px}.xd-cms-hero p{font-size:16px}.xd-service-card{border-radius:18px;overflow:hidden}.xd-service-image{height:215px}.xd-service-body{padding:26px 22px}.xd-service-card h2,.xd-service-card h3{font-size:19px}.xd-detail-body{padding:28px 22px}.xd-detail-body h1{font-size:34px}.xd-detail-summary,.xd-rich-content{font-size:16px}.xd-contact-panel,.xd-contact-form-card{padding:28px 22px}.xd-contact-panel h2,.xd-contact-form-card h2{font-size:28px}.xd-contact-method{grid-template-columns:44px minmax(0,1fr);padding:14px}.xd-contact-icon{width:44px;height:44px;font-size:18px}.xd-contact-method a,.xd-contact-method span{font-size:15px}.xd-contact-submit{width:100%}}

        .xd20-project-page{padding:40px 0 64px;background:#f5f5f4;color:#202325}
        .xd20-project-page .xd-container{width:min(1240px,calc(100% - 48px));margin-inline:auto}
        .xd20-project-page .xd-cms-hero{display:flex;align-items:center;justify-content:space-between;gap:28px;margin:0 0 28px;padding:0 0 28px;border-bottom:1px solid #dfe1e2}
        .xd20-project-page .xd-kicker{display:block;color:#c82716;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;margin-bottom:12px}
        .xd20-project-page h1{font-size:clamp(28px,3vw,42px);line-height:1.2;margin:0;font-weight:800;color:#202325}
        .xd20-project-page .xd-cms-hero p{font-size:15px;line-height:1.6;color:#62686d;margin:12px 0 0}
        .xd20-project-page .xd-cms-stats{display:flex;align-items:center;gap:12px;flex-shrink:0;padding:14px 20px;border-left:3px solid #eb2916;background:#fff}
        .xd20-project-page .xd-cms-stats strong{font-size:28px;color:#202325}
        .xd20-project-page .xd-cms-stats span{font-size:12px;color:#62686d;max-width:100px;line-height:1.5}
        .xd20-project-page .xd-services-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
        .xd20-project-page .xd-service-card{display:flex;flex-direction:column;min-width:0;background:#fff;border:1px solid #e2e3e4;border-radius:8px;overflow:hidden;transition:box-shadow .2s,border-color .2s}
        .xd20-project-page .xd-service-card:hover{border-color:#c7cace;box-shadow:0 8px 24px #2023250d}
        .xd20-project-page .xd-service-image{display:block;aspect-ratio:16/9;overflow:hidden;background:#e8e9e9}
        .xd20-project-page .xd-service-image img{display:block;width:100%;height:100%;object-fit:cover}
        .xd20-project-page .xd-service-body{display:flex;flex:1;flex-direction:column;padding:22px}
        .xd20-project-page h2{font-size:21px;line-height:1.4;margin:0 0 10px;font-weight:700}
        .xd20-project-page h2 a{color:#202325;text-decoration:none}
        .xd20-project-page h2 a:hover{color:#c82716}
        .xd20-project-page .xd-service-body p{font-size:14px;line-height:1.7;color:#62686d;margin:0 0 20px;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
        .xd20-project-page .xd-text-link{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:auto;padding-top:14px;border-top:1px solid #eceeed;color:#b72516;font-size:13px;font-weight:700;text-decoration:none}
        .xd20-project-page .xd-text-link:after{content:'↗';font-size:20px}
        .xd20-project-page a:focus-visible{outline:3px solid #eb2916;outline-offset:-3px}
        @media(max-width:900px){.xd20-project-page .xd-services-list{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:600px){.xd20-project-page{padding:28px 0 40px}.xd20-project-page .xd-container{width:calc(100% - 32px)}.xd20-project-page .xd-cms-hero{align-items:flex-start;flex-direction:column;gap:16px}.xd20-project-page .xd-cms-stats{padding:8px 14px}.xd20-project-page .xd-cms-stats strong{font-size:22px}.xd20-project-page .xd-cms-stats span{max-width:none}.xd20-project-page .xd-services-list{grid-template-columns:1fr;gap:20px}.xd20-project-page .xd-service-body{padding:20px}}

.xd20-project-page .xd-service-image{height:auto}.xd20-project-page .xd-service-card{box-shadow:none;transform:none}.xd20-project-page h2{text-transform:none;letter-spacing:normal}.xd20-project-page .xd-cms-hero{background:transparent;box-shadow:none;border-width:0 0 1px}.xd20-project-page .xd-project-count{font-size:13px;color:#62686d;white-space:nowrap}.xd20-project-page .xd-empty{grid-column:1/-1;padding:32px;background:#fff;border:1px solid #e2e3e4;border-radius:8px;color:#62686d}
    </style>
@endpush

@section('content')
<main class="xd-page-main {{ isset($listingItems) ? 'xd20-project-page' : '' }}">
            <div class="xd-container">
                    @if (isset($listingItems))
                    <header class="xd-cms-hero">
                        <h1>{{ $pageTitle ?? (app()->getLocale() === 'vi' ? 'Dự án' : 'Projects') }}</h1>
                        <span class="xd-project-count">{{ $listingItems->count() }} {{ app()->getLocale() === 'vi' ? 'dự án đang hiển thị' : 'projects displayed' }}</span>
                    </header>
                    <section class="xd-services-list">
                        @forelse ($listingItems as $project)
                            <article class="xd-service-card">
                                @if ($project->featuredImage?->image_url)
                                    <a class="xd-service-image" href="{{ route('site.projects.show', ['slug' => $project->slug]) }}"><img src="{{ $project->featuredImage->image_url }}" alt="{{ $project->title }}" loading="lazy"></a>
                                @endif
                                <div class="xd-service-body"><h2><a href="{{ route('site.projects.show', ['slug' => $project->slug]) }}">{{ $project->title }}</a></h2><p>{{ $project->summary }}</p></div>
                            </article>
                        @empty
                            <p class="xd-empty">{{ app()->getLocale() === 'vi' ? 'Chưa có dự án được xuất bản.' : 'No projects published yet.' }}</p>
                        @endforelse
                    </section>
                    @if (method_exists($listingItems, 'links')){{ $listingItems->links() }}@endif
                    @else
                    <section class="xd-detail-card">
                        <div class="xd-detail-body">
                            <span class="xd-kicker">{{ strtoupper($contentType ?? 'PAGE') }}</span>
                            <h1>{{ $entry->title }}</h1>
                            @if (!empty($entry->excerpt))
                                <p class="xd-detail-summary">{{ $entry->excerpt }}</p>
                            @endif
                            <div class="xd-rich-content">
                                {!! ($entry->body ?? $entry->content) ?: '<p>Nội dung đang được cập nhật.</p>' !!}
                            </div>
                        </div>
                    </section>
                    @endif
            </div>
</main>
@endsection
