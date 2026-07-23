@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? 'admin@htvietnam.vn'));
    $address = trim((string) ($branding['support_location'] ?? '196 NguyÃ¡Â»â€¦n Ã„ÂÃƒÂ¬nh ChiÃ¡Â»Æ’u, QuÃ¡ÂºÂ­n 3, TP.HCM'));
    $productItems = collect($products ?? []);
    $categoryItems = collect($searchCategories ?? []);
    $filters = $searchFilters ?? [];
    $currentCategory = (string) ($filters['category'] ?? '');
    $searchQuery = (string) ($searchQuery ?? '');
    $formatCurrency = fn ($value) => $value === null || (float) $value <= 0 ? 'LiÃƒÂªn hÃ¡Â»â€¡' : number_format((float) $value, 0, ',', '.').'Ã„â€˜';

    $localizeMenuUrl = static fn (?string $href): string => \App\Support\FrontendRouteUrl::localized($href);

    $normalizeNavItem = function (array $item) use (&$normalizeNavItem, $localizeMenuUrl): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
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
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chÃ¡Â»Â§', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chÃ¡Â»Â§',
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m', 'san pham', 'products', 'product'], true);
    });

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems->map(function (array $item) use ($productNavigationItems): array {
                $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
                if (in_array($label, ['sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                    $item['children'] = $productNavigationItems->all();
                }
                return $item;
            })->values();
        } else {
            $navArray = $navItems->values()->all();
            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chÃ¡Â»Â§', 'home'], true));
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [[
                'label' => app()->getLocale() === 'en' ? 'Products' : 'SÃ¡ÂºÂ£n phÃ¡ÂºÂ©m',
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ]]);
            $navItems = collect($navArray);
        }
    }

    $currentUrl = rtrim(url()->current(), '/');
    $navItems = $navItems->map(function (array $item) use ($currentUrl): array {
        $href = (string) ($item['href'] ?? '#');
        $absoluteHref = str_starts_with($href, 'http') ? rtrim($href, '/') : rtrim(url($href), '/');
        $item['active'] = $href !== '#' && ($absoluteHref === $currentUrl || (($item['active'] ?? false) && request()->routeIs('site.catalog.*')));
        return $item;
    })->values();
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-xd0314-search';
@endphp

@extends('theme-xd0314::layout')

@section('title'){{ $searchQuery !== '' ? 'TÃƒÂ¬m kiÃ¡ÂºÂ¿m: '.$searchQuery : 'TÃƒÂ¬m kiÃ¡ÂºÂ¿m sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' }} | {{ $logoAlt }}@endsection

@push('head')
    <style>
        .xd-cart-link{display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}
        .xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .xd-cart-link:hover{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}
        .xd-hero{position:relative;padding:92px 0 76px;overflow:hidden;background:linear-gradient(135deg,rgba(8,18,25,.93),rgba(38,56,74,.84)),url("https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=80") center/cover;color:#fff}
        .xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:22px;color:rgba(255,255,255,.72);font-size:14px;font-weight:800}
        .xd-breadcrumb a:hover{color:var(--lime)}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 18px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-hero h1{max-width:820px;margin:0;font-size:clamp(42px,6vw,82px);line-height:.98;letter-spacing:-.065em}
        .xd-hero p{max-width:760px;margin:22px 0 0;color:rgba(255,255,255,.82);font-size:20px;font-weight:650}
        .xd-search-shell{display:grid;grid-template-columns:320px minmax(0,1fr);gap:34px;padding:42px 0 88px}
        .xd-panel,.xd-product-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.07)}
        .xd-panel{padding:24px}
        .xd-panel h2,.xd-panel h3{margin:0 0 16px;font-size:22px;letter-spacing:-.025em}
        .xd-filter-form{display:grid;gap:12px}
        .xd-filter-form input,.xd-filter-form select{width:100%;height:48px;border:1px solid var(--line);background:#fff;color:var(--ink);padding:0 14px;font-weight:750}
        .xd-filter-form button{height:48px;border:0;background:var(--lime);color:#fff;font-weight:950;text-transform:uppercase;cursor:pointer}
        .xd-side-list{display:grid;gap:8px;margin-top:18px}
        .xd-side-link{display:flex;justify-content:space-between;gap:12px;padding:13px 14px;border:1px solid var(--line);color:#53606b;font-weight:850}
        .xd-side-link.is-active,.xd-side-link:hover{border-color:var(--lime);background:#f8faed;color:var(--ink)}
        .xd-promo{margin-top:18px;background:#13232d;color:#fff;border-color:#13232d}
        .xd-promo p{margin:0;color:rgba(255,255,255,.74)}
        .xd-toolbar{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}
        .xd-toolbar h2{margin:0;font-size:38px;line-height:1.08;letter-spacing:-.045em}
        .xd-toolbar p{margin:8px 0 0;color:var(--muted);font-weight:750}
        .xd-sort{height:46px;min-width:190px;border:1px solid var(--line);background:#fff;color:var(--ink);padding:0 14px;font-weight:800}
        .xd-product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
        .xd-product-card{display:grid;grid-template-rows:auto 1fr;overflow:hidden;border-radius:22px;background:linear-gradient(180deg,#fff,#fbfcfa);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
        .xd-product-card:hover{transform:translateY(-5px);border-color:rgba(189,212,0,.7);box-shadow:var(--shadow)}
        .xd-product-image{position:relative;display:block;aspect-ratio:1/1;background:radial-gradient(circle at 50% 34%,#fff 0,#f5f8f2 46%,#e9efe8 100%);overflow:hidden}
        .xd-product-image:after{content:"";position:absolute;left:22px;right:22px;bottom:17px;height:18px;border-radius:50%;background:rgba(38,56,74,.14);filter:blur(10px)}
        .xd-product-image img{position:relative;z-index:1;width:100%;height:100%;padding:20px;object-fit:contain;transition:transform .35s ease}
        .xd-product-card:hover img{transform:scale(1.035)}
        .xd-product-tag{position:absolute;z-index:2;left:16px;top:16px;max-width:calc(100% - 32px);padding:7px 11px;border-radius:999px;background:rgba(16,29,40,.88);color:#fff;font-size:11px;font-weight:950;line-height:1.25;text-transform:uppercase;box-shadow:0 10px 22px rgba(16,29,40,.18)}
        .xd-product-body{display:grid;align-content:start;gap:14px;padding:22px}
        .xd-product-body h3{margin:0;font-size:20px;line-height:1.28;letter-spacing:-.025em}
        .xd-price-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:2px}
        .xd-price{color:#9a6a3e;font-size:23px;font-weight:950;letter-spacing:-.04em}
        .xd-old-price{color:#9aa3a9;text-decoration:line-through}
        .xd-discount{display:inline-flex;align-items:center;height:26px;padding:0 8px;border-radius:999px;background:var(--ink);color:#fff;font-size:12px;font-weight:900}
        .xd-empty{padding:42px;background:#fff;border:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-pagination{display:flex;justify-content:space-between;gap:16px;margin-top:28px}
        .xd-pagination a,.xd-pagination span{display:inline-flex;align-items:center;min-height:42px;padding:0 16px;border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:900}
        .xd-pagination span{opacity:.45}
        @media (max-width:1180px){.xd-search-shell{grid-template-columns:1fr}.xd-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-hero{padding:58px 0 46px}.xd-hero h1{font-size:40px}.xd-hero p{font-size:16px}.xd-toolbar{display:grid}.xd-sort{width:100%}}
    </style>
@endpush

@section('content')
        <main>
            <section class="xd-hero">
                <div class="xd-container">
                    <div class="xd-breadcrumb">
                        <a href="{{ route('site.home') }}">Trang chÃ¡Â»Â§</a>
                        <span>/</span>
                        <strong>TÃƒÂ¬m kiÃ¡ÂºÂ¿m</strong>
                    </div>
                    <span class="xd-kicker">Catalog</span>
                    <h1>{{ $searchQuery !== '' ? 'KÃ¡ÂºÂ¿t quÃ¡ÂºÂ£ cho "'.$searchQuery.'"' : 'TÃƒÂ¬m kiÃ¡ÂºÂ¿m sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' }}</h1>
                    <p>Tra cÃ¡Â»Â©u nhanh theo tÃ¡Â»Â« khÃƒÂ³a, danh mÃ¡Â»Â¥c vÃƒÂ  sÃ¡ÂºÂ¯p xÃ¡ÂºÂ¿p theo nhu cÃ¡ÂºÂ§u mua hÃƒÂ ng cÃ¡Â»Â§a khÃƒÂ¡ch.</p>
                </div>
            </section>

            <section class="xd-container xd-search-shell">
                <aside>
                    <div class="xd-panel">
                        <h2>BÃ¡Â»â„¢ lÃ¡Â»Âc</h2>
                        <form class="xd-filter-form" method="GET" action="{{ route('site.catalog.search') }}">
                            <input type="search" name="q" value="{{ $searchQuery }}" placeholder="TÃƒÂ¬m sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m, mÃƒÂ£ SKU...">
                            <select name="category">
                                <option value="">TÃ¡ÂºÂ¥t cÃ¡ÂºÂ£ danh mÃ¡Â»Â¥c</option>
                                @foreach ($categoryItems as $category)
                                    <option value="{{ $category->slug }}" {{ $currentCategory === $category->slug ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            <select name="sort">
                                <option value="default" {{ ($filters['sort'] ?? 'default') === 'default' ? 'selected' : '' }}>MÃ¡Â»â€ºi nhÃ¡ÂºÂ¥t</option>
                                <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>GiÃƒÂ¡ tÃ„Æ’ng dÃ¡ÂºÂ§n</option>
                                <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>GiÃƒÂ¡ giÃ¡ÂºÂ£m dÃ¡ÂºÂ§n</option>
                                <option value="bestseller" {{ ($filters['sort'] ?? '') === 'bestseller' ? 'selected' : '' }}>BÃƒÂ¡n chÃ¡ÂºÂ¡y</option>
                            </select>
                            <button type="submit">LÃ¡Â»Âc sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m</button>
                        </form>
                    </div>
                    <div class="xd-panel">
                        <h3>Danh mÃ¡Â»Â¥c</h3>
                        <div class="xd-side-list">
                            <a class="xd-side-link {{ $currentCategory === '' ? 'is-active' : '' }}" href="{{ route('site.catalog.search', request()->except(['category', 'page'])) }}">
                                <span>TÃ¡ÂºÂ¥t cÃ¡ÂºÂ£</span>
                                <small>{{ (int) ($resultCount ?? $productItems->count()) }}</small>
                            </a>
                            @foreach ($categoryItems as $category)
                                <a class="xd-side-link {{ $currentCategory === $category->slug ? 'is-active' : '' }}" href="{{ route('site.catalog.search', array_merge(request()->except(['page']), ['category' => $category->slug])) }}">
                                    <span>{{ $category->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="xd-panel xd-promo">
                        <h3>CÃ¡ÂºÂ§n tÃ†Â° vÃ¡ÂºÂ¥n nhanh?</h3>
                        <p>GÃ¡Â»Â­i tÃ¡Â»Â« khÃƒÂ³a hoÃ¡ÂºÂ·c gÃ¡Â»Âi hotline Ã„â€˜Ã¡Â»Æ’ Ã„â€˜Ã¡Â»â„¢i ngÃ…Â© XD0314 gÃ¡Â»Â£i ÃƒÂ½ nhÃƒÂ³m sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m phÃƒÂ¹ hÃ¡Â»Â£p cho cÃƒÂ´ng trÃƒÂ¬nh.</p>
                    </div>
                </aside>

                <div>
                    <div class="xd-toolbar">
                        <div>
                            <span class="xd-kicker">KÃ¡ÂºÂ¿t quÃ¡ÂºÂ£</span>
                            <h2>{{ (int) ($resultCount ?? $productItems->count()) }} sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m phÃƒÂ¹ hÃ¡Â»Â£p</h2>
                            <p>{{ $searchQuery !== '' ? 'Ã„Âang lÃ¡Â»Âc theo tÃ¡Â»Â« khÃƒÂ³a: '.$searchQuery : 'Danh sÃƒÂ¡ch sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m Ã„â€˜ang Ã„â€˜Ã†Â°Ã¡Â»Â£c hiÃ¡Â»Æ’n thÃ¡Â»â€¹ theo bÃ¡Â»â„¢ lÃ¡Â»Âc hiÃ¡Â»â€¡n tÃ¡ÂºÂ¡i.' }}</p>
                        </div>
                        <form method="GET" action="{{ route('site.catalog.search') }}">
                            @foreach (request()->except(['sort', 'page']) as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <select class="xd-sort" name="sort" onchange="this.form.submit()">
                                <option value="default" {{ ($filters['sort'] ?? 'default') === 'default' ? 'selected' : '' }}>MÃ¡Â»â€ºi nhÃ¡ÂºÂ¥t</option>
                                <option value="price_asc" {{ ($filters['sort'] ?? '') === 'price_asc' ? 'selected' : '' }}>GiÃƒÂ¡ tÃ„Æ’ng dÃ¡ÂºÂ§n</option>
                                <option value="price_desc" {{ ($filters['sort'] ?? '') === 'price_desc' ? 'selected' : '' }}>GiÃƒÂ¡ giÃ¡ÂºÂ£m dÃ¡ÂºÂ§n</option>
                                <option value="bestseller" {{ ($filters['sort'] ?? '') === 'bestseller' ? 'selected' : '' }}>BÃƒÂ¡n chÃ¡ÂºÂ¡y</option>
                            </select>
                        </form>
                    </div>

                    @if ($productItems->isNotEmpty())
                        <div class="xd-product-grid">
                            @foreach ($productItems as $product)
                                <a class="xd-product-card" href="{{ $product['url'] ?? '#' }}">
                                    <span class="xd-product-image">
                                        <img src="{{ $product['image'] ?? 'https://picsum.photos/seed/XD0314-product/720/540' }}" alt="{{ $product['title'] ?? '' }}">
                                        <span class="xd-product-tag">{{ $product['tag'] ?? 'SÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' }}</span>
                                    </span>
                                    <span class="xd-product-body">
                                        <h3>{{ $product['title'] ?? 'SÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' }}</h3>
                                        <span class="xd-price-row">
                                            <strong class="xd-price">{{ $formatCurrency($product['price'] ?? null) }}</strong>
                                            @if (!empty($product['old_price']))
                                                <span class="xd-old-price">{{ $formatCurrency($product['old_price']) }}</span>
                                            @endif
                                            @if (!empty($product['discount']))
                                                <span class="xd-discount">-{{ $product['discount'] }}%</span>
                                            @endif
                                        </span>
                                    </span>
                                </a>
                            @endforeach
                        </div>

                        @if (($pagination ?? null) && method_exists($pagination, 'previousPageUrl'))
                            <div class="xd-pagination">
                                @if ($pagination->previousPageUrl())
                                    <a href="{{ $pagination->previousPageUrl() }}">Trang trÃ†Â°Ã¡Â»â€ºc</a>
                                @else
                                    <span>Trang trÃ†Â°Ã¡Â»â€ºc</span>
                                @endif
                                @if ($pagination->nextPageUrl())
                                    <a href="{{ $pagination->nextPageUrl() }}">Trang sau</a>
                                @else
                                    <span>Trang sau</span>
                                @endif
                            </div>
                        @endif
                    @else
                        <div class="xd-empty">ChÃ†Â°a tÃƒÂ¬m thÃ¡ÂºÂ¥y sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m phÃƒÂ¹ hÃ¡Â»Â£p. HÃƒÂ£y thÃ¡Â»Â­ tÃ¡Â»Â« khÃƒÂ³a khÃƒÂ¡c hoÃ¡ÂºÂ·c bÃ¡Â»Â bÃ¡Â»â€ºt bÃ¡Â»â„¢ lÃ¡Â»Âc.</div>
                    @endif
                </div>
            </section>
        </main>
@endsection

