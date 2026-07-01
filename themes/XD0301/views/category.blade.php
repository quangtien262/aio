@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? 'admin@htvietnam.vn'));
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM'));
    $categoryDescription = trim((string) ($category->description ?? ''));
    $productItems = collect($products ?? []);
    $childCategoryItems = collect($childCategories ?? []);
    $sidebarItems = collect($catalogTreeCategories ?? $sidebarCategories ?? []);
    $formatCurrency = fn ($value) => $value === null || (float) $value <= 0 ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $renderCategoryTree = function ($items, int $level = 0) use (&$renderCategoryTree): string {
        return collect($items)->map(function (array $item) use (&$renderCategoryTree, $level): string {
            $children = collect($item['children'] ?? []);
            $classes = trim('xd-side-link level-'.$level.' '.(($item['active'] ?? false) ? 'is-active' : '').' '.(($item['current'] ?? false) ? 'is-current' : ''));
            $html = '<div class="xd-side-node level-'.$level.'">';
            $html .= '<a class="'.e($classes).'" href="'.e((string) ($item['url'] ?? '#')).'">';
            $html .= '<span>'.e((string) ($item['label'] ?? $item['name'] ?? 'Danh mục')).'</span>';
            $html .= '<small>'.(int) ($item['count'] ?? 0).'</small>';
            $html .= '</a>';

            if ($children->isNotEmpty()) {
                $html .= '<div class="xd-side-children">'.$renderCategoryTree($children->all(), $level + 1).'</div>';
            }

            return $html.'</div>';
        })->implode('');
    };

    $localizeMenuUrl = function (?string $href): string {
        $href = trim((string) $href);

        if ($href === '' || $href === '#' || str_starts_with($href, '#') || preg_match('/^(https?:)?\/\//i', $href) || preg_match('/^(mailto|tel):/i', $href)) {
            return $href !== '' ? $href : '#';
        }

        $parts = parse_url($href) ?: [];
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            return route('site.home').$query.$fragment;
        }

        $segments = explode('/', $path);
        $knownLocales = \App\Support\FrontendLocalization::knownLocaleCodes();

        if (! in_array($segments[0] ?? '', $knownLocales, true)) {
            array_unshift($segments, app()->getLocale());
        }

        return url('/'.implode('/', $segments)).$query.$fragment;
    };

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
    $aboutUrl = url('/'.app()->getLocale().'/gioi-thieu');
    $contactUrl = url('/'.app()->getLocale().'/lien-he');
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chủ', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chủ',
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
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productCategories
                    ->map(fn ($item): array => [
                        'label' => (string) $item->name,
                        'href' => route('site.catalog.category', ['slug' => $item->slug]),
                        'target' => '_self',
                        'active' => false,
                        'children' => $item->children
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

                    if (in_array($label, ['sáº£n pháº©m', 'sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                        $item['children'] = $productNavigationItems->all();
                    }

                    return $item;
                })
                ->values();
        } else {
            $productMenuItem = [
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chá»§', 'trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
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
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $category->name }} | {{ $logoAlt }}</title>
    <style>
        :root{--lime:#bdd400;--lime-dark:#8fa900;--ink:#26384a;--muted:#74808a;--line:#e6ebe8;--bg:#fbfcfa;--panel:#fff;--shadow:0 22px 55px rgba(28,45,60,.13);--font:"Montserrat","Segoe UI",Arial,sans-serif}
        *{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--bg);font-family:var(--font);font-size:16px;line-height:1.7}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}button,input,select{font:inherit}.xd-container{width:min(1540px,calc(100% - 56px));margin:0 auto}
        .xd-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);box-shadow:0 10px 30px rgba(16,29,40,.08);backdrop-filter:blur(14px)}.xd-header-inner{display:flex;align-items:center;justify-content:space-between;min-height:102px;gap:34px}.xd-logo{display:inline-flex;align-items:center;gap:11px;font-size:34px;font-weight:900;letter-spacing:-.06em;color:var(--ink)}.xd-logo-image{display:block;width:auto;max-width:156px;height:64px;object-fit:contain}.xd-logo-mark{position:relative;width:38px;height:50px;background:linear-gradient(135deg,var(--lime),#d9ec27);clip-path:polygon(50% 0,100% 22%,100% 100%,0 100%,0 22%)}.xd-logo-mark:before,.xd-logo-mark:after{content:"";position:absolute;background:#fff}.xd-logo-mark:before{left:9px;bottom:7px;width:20px;height:30px}.xd-logo-mark:after{left:14px;top:17px;width:10px;height:7px;box-shadow:0 11px 0 #fff}.xd-logo span{color:var(--lime)}
        .xd-nav{display:flex;align-items:center;justify-content:center;gap:0;min-width:0;flex:1}.xd-nav-item{position:relative}.xd-nav-link{display:inline-flex;align-items:center;gap:8px;padding:39px 21px;color:#344354;font-size:15px;font-weight:850;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}.xd-nav-caret{color:var(--lime-dark);font-size:12px;transition:transform .18s ease}.xd-nav-link.is-active,.xd-nav-link:hover,.xd-nav-item:hover>.xd-nav-link,.xd-nav-item:focus-within>.xd-nav-link{color:var(--lime-dark)}.xd-nav-item:hover>.xd-nav-link .xd-nav-caret,.xd-nav-item:focus-within>.xd-nav-link .xd-nav-caret{transform:rotate(180deg)}.xd-dropdown{position:absolute;top:100%;left:0;z-index:90;min-width:250px;padding:12px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateY(12px);transition:.18s}.xd-nav-item:hover>.xd-dropdown,.xd-nav-item:focus-within>.xd-dropdown{opacity:1;visibility:visible;transform:translateY(0)}.xd-dropdown-link{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 14px;border-left:3px solid transparent;color:#53606b;font-size:14px;font-weight:800;line-height:1.35}.xd-dropdown-link:hover{background:#f7f9ee;border-left-color:var(--lime);color:var(--ink)}
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-cart-link{display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}.xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.xd-cart-link:hover{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}
        .xd-page-main{padding:0 0 88px}.xd-hero{position:relative;min-height:360px;display:grid;align-items:end;overflow:hidden;background:linear-gradient(135deg,#0c1a22,#26384a)}.xd-hero:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(8,18,25,.85),rgba(8,18,25,.45)),url("{{ $category->image_url ?: 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1600&q=80' }}") center/cover;filter:saturate(.9)}.xd-hero:after{content:"";position:absolute;inset:auto 0 0;height:42%;background:linear-gradient(0deg,rgba(8,18,25,.76),transparent)}.xd-hero-inner{position:relative;z-index:1;padding:90px 0 70px;color:#fff}.xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:22px;color:rgba(255,255,255,.72);font-size:14px;font-weight:800}.xd-breadcrumb a:hover{color:var(--lime)}.xd-kicker{position:relative;display:inline-block;margin:0 0 18px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-hero h1{max-width:760px;margin:0;font-size:clamp(42px,6vw,86px);line-height:.98;letter-spacing:-.065em}.xd-hero p{max-width:760px;margin:22px 0 0;color:rgba(255,255,255,.82);font-size:20px;font-weight:650}
        .xd-catalog{display:grid;grid-template-columns:320px minmax(0,1fr);gap:34px;margin-top:42px}.xd-sidebar{display:grid;gap:18px;align-content:start}.xd-panel,.xd-product-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.07)}.xd-panel{padding:24px}.xd-panel h2,.xd-panel h3{margin:0 0 16px;font-size:22px;letter-spacing:-.025em}.xd-side-list{display:grid;gap:6px}.xd-side-node{display:grid;gap:6px}.xd-side-children{display:grid;gap:6px;margin-left:14px;padding-left:14px;border-left:1px dashed #dfe7db}.xd-side-link{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 14px;border:1px solid var(--line);color:#53606b;font-weight:850;line-height:1.35}.xd-side-link.level-0{color:var(--ink);background:#fff;font-weight:950}.xd-side-link.level-1{font-size:14px}.xd-side-link.level-2,.xd-side-link.level-3{font-size:13px;padding:10px 12px}.xd-side-link.is-active,.xd-side-link:hover{border-color:var(--lime);background:#f8faed;color:var(--ink)}.xd-side-link.is-current{box-shadow:inset 4px 0 0 var(--lime)}.xd-side-link small{flex:0 0 auto;color:var(--muted);font-weight:750}.xd-promo{background:#13232d;color:#fff;border-color:#13232d}.xd-promo p{margin:0;color:rgba(255,255,255,.74)}.xd-toolbar{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:20px}.xd-toolbar h2{margin:0;font-size:38px;line-height:1.08;letter-spacing:-.045em}.xd-toolbar p{margin:8px 0 0;color:var(--muted);font-weight:750}.xd-sort{height:46px;min-width:190px;border:1px solid var(--line);background:#fff;color:var(--ink);padding:0 14px;font-weight:800}
        .xd-child-cats{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px}.xd-child-cats a{display:inline-flex;align-items:center;min-height:38px;padding:0 14px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--muted);font-size:13px;font-weight:900;text-transform:uppercase}.xd-child-cats a:hover{border-color:var(--lime);color:var(--ink)}
        .xd-product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}.xd-product-card{display:grid;grid-template-rows:auto 1fr;overflow:hidden;border-radius:22px;background:linear-gradient(180deg,#fff,#fbfcfa);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}.xd-product-card:hover{transform:translateY(-5px);border-color:rgba(189,212,0,.7);box-shadow:var(--shadow)}.xd-product-image{position:relative;display:block;aspect-ratio:1/1;background:radial-gradient(circle at 50% 34%,#fff 0,#f5f8f2 46%,#e9efe8 100%);overflow:hidden}.xd-product-image:after{content:"";position:absolute;left:22px;right:22px;bottom:17px;height:18px;border-radius:50%;background:rgba(38,56,74,.14);filter:blur(10px)}.xd-product-image img{position:relative;z-index:1;width:100%;height:100%;padding:20px;object-fit:contain;transition:transform .35s ease}.xd-product-card:hover img{transform:scale(1.035)}.xd-product-tag{position:absolute;z-index:2;left:16px;top:16px;max-width:calc(100% - 32px);padding:7px 11px;border-radius:999px;background:rgba(16,29,40,.88);color:#fff;font-size:11px;font-weight:950;line-height:1.25;text-transform:uppercase;box-shadow:0 10px 22px rgba(16,29,40,.18)}.xd-product-body{display:grid;align-content:start;gap:14px;padding:22px}.xd-product-body h3{margin:0;font-size:20px;line-height:1.28;letter-spacing:-.025em}.xd-price-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:2px}.xd-price{color:#9a6a3e;font-size:23px;font-weight:950;letter-spacing:-.04em}.xd-old-price{color:#9aa3a9;text-decoration:line-through}.xd-discount{display:inline-flex;align-items:center;height:26px;padding:0 8px;border-radius:999px;background:var(--ink);color:#fff;font-size:12px;font-weight:900}.xd-empty{padding:42px;background:#fff;border:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-catalog{grid-template-columns:1fr}.xd-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.xd-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-container{width:min(100% - 24px,1540px)}.xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}.xd-logo-image{max-width:132px;height:52px}.xd-header-actions{margin-left:auto;gap:8px}.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-hotline{min-height:42px;padding:0 14px;border-radius:999px;font-size:0}.xd-hotline:after{content:"{{ $hotline }}";font-size:13px}.xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px}.xd-nav{flex:0 0 100%;gap:8px;overflow-x:auto;padding:8px 0 2px}.xd-nav::-webkit-scrollbar{display:none}.xd-nav-item{flex:0 0 auto}.xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;font-size:12px}.xd-hero{min-height:330px}.xd-hero-inner{padding:58px 0 46px}.xd-hero h1{font-size:40px}.xd-hero p{font-size:16px}.xd-toolbar{display:grid}.xd-sort{width:100%}.xd-product-grid,.xd-footer-grid{grid-template-columns:1fr}.xd-footer{padding:52px 0 42px}.xd-footer h3{font-size:24px}.xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}.xd-newsletter{display:grid;border-radius:16px;overflow:hidden}.xd-newsletter input{min-height:52px}.xd-newsletter button{width:100%;min-height:52px}}
    </style>
</head>
<body>
    <div id="top" class="xd-page">
        <header class="xd-header">
            <div class="xd-container xd-header-inner">
                <a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt }} trang chủ">
                    @if ($logoUrl !== '')
                        <img class="xd-logo-image" src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
                    @else
                        <i class="xd-logo-mark" aria-hidden="true"></i><b>Ar<span>kit</span></b>
                    @endif
                </a>
                <nav class="xd-nav" aria-label="Menu chính">
                    @foreach ($navItems as $item)
                        <div class="xd-nav-item {{ !empty($item['children']) ? 'has-children' : '' }}">
                            <a class="xd-nav-link {{ ($item['active'] ?? false) ? 'is-active' : '' }}" href="{{ $item['href'] }}" target="{{ $item['target'] ?? '_self' }}">
                                <span>{{ $item['label'] }}</span>
                                @if (!empty($item['children']))
                                    <span class="xd-nav-caret" aria-hidden="true">&#9662;</span>
                                @endif
                            </a>
                            @if (!empty($item['children']))
                                <div class="xd-dropdown" role="menu">
                                    @foreach (collect($item['children'])->take(10) as $child)
                                        <a class="xd-dropdown-link" href="{{ $child['href'] ?? '#' }}" target="{{ $child['target'] ?? '_self' }}" role="menuitem">{{ $child['label'] ?? 'Menu' }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
                <div class="xd-header-actions">
                    <a class="xd-hotline" href="tel:{{ $phoneHref }}"><span aria-hidden="true">&#9742;</span> {{ $hotline }}</a>
                    <a class="xd-cart-link" href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng" title="Giỏ hàng">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6h15l-1.5 8.5H8L6 3H3"/><circle cx="9" cy="20" r="1.7"/><circle cx="18" cy="20" r="1.7"/></svg>
                    </a>
                    @if (auth('customer')->check())
                        <a class="xd-login-button" href="{{ route('customer.account') }}">Tài khoản</a>
                    @else
                        <button type="button" class="xd-login-button" data-xd-auth-open="login">Đăng nhập</button>
                    @endif
                </div>
            </div>
        </header>

        <main class="xd-page-main">
            <section class="xd-hero">
                <div class="xd-container xd-hero-inner">
                    <div class="xd-breadcrumb">
                        <a href="{{ route('site.home') }}">Trang chủ</a>
                        <span>/</span>
                        @if ($category->parent)
                            <a href="{{ route('site.catalog.category', ['slug' => $category->parent->slug]) }}">{{ $category->parent->name }}</a>
                            <span>/</span>
                        @endif
                        <strong>{{ $category->name }}</strong>
                    </div>
                    <span class="xd-kicker">Danh mục sản phẩm</span>
                    <h1>{{ $category->name }}</h1>
                    <p>{{ $categoryDescription !== '' ? $categoryDescription : 'Danh sách sản phẩm được sắp theo nhóm nhu cầu để khách hàng chọn nhanh vật tư, nội thất và giải pháp phù hợp cho công trình.' }}</p>
                </div>
            </section>

            <section class="xd-container xd-catalog">
                <aside class="xd-sidebar">
                    <div class="xd-panel">
                        <h2>Danh mục sản phẩm</h2>
                        <div class="xd-side-list">
                            @if ($sidebarItems->isNotEmpty())
                                {!! $renderCategoryTree($sidebarItems->all()) !!}
                            @else
                                <a class="xd-side-link is-active" href="{{ route('site.catalog.category', ['slug' => $category->slug]) }}">
                                    <span>{{ $category->name }}</span>
                                    <small>{{ $productItems->count() }}</small>
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="xd-panel xd-promo">
                        <h3>Tư vấn chọn đúng vật tư</h3>
                        <p>Gửi nhu cầu công trình để đội ngũ XD0301 gợi ý nhóm sản phẩm và phương án triển khai phù hợp.</p>
                    </div>
                </aside>

                <div class="xd-main">
                    <div class="xd-toolbar">
                        <div>
                            <span class="xd-kicker">Catalog</span>
                            <h2>{{ $productItems->count() }} sản phẩm đang hiển thị</h2>
                            <p>Lọc nhanh theo danh mục con hoặc xem chi tiết từng sản phẩm.</p>
                        </div>
                        <form method="GET">
                            <select class="xd-sort" name="sort" onchange="this.form.submit()">
                                @foreach (($sortOptions ?? []) as $option)
                                    <option value="{{ $option['value'] }}" {{ ($filters['sort'] ?? 'default') === $option['value'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </form>
                    </div>

                    @if ($childCategoryItems->isNotEmpty())
                        <div class="xd-child-cats">
                            @foreach ($childCategoryItems as $child)
                                <a href="{{ $child['url'] ?? '#' }}">{{ $child['name'] ?? 'Danh mục' }}</a>
                            @endforeach
                        </div>
                    @endif

                    @if ($productItems->isNotEmpty())
                        <div class="xd-product-grid">
                            @foreach ($productItems as $product)
                                <a class="xd-product-card" href="{{ $product['url'] ?? '#' }}">
                                    <span class="xd-product-image">
                                        <img src="{{ $product['image'] ?? 'https://picsum.photos/seed/xd0301-product/720/540' }}" alt="{{ $product['title'] ?? '' }}">
                                        <span class="xd-product-tag">{{ $product['tag'] ?? $category->name }}</span>
                                    </span>
                                    <span class="xd-product-body">
                                        <h3>{{ $product['title'] ?? 'Sản phẩm' }}</h3>
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
                    @else
                        <div class="xd-empty">Chưa có sản phẩm nào trong danh mục này.</div>
                    @endif
                </div>
            </section>
        </main>

        @include('theme-xd0301::partials.footer', ['footerNewsletterSource' => 'theme-footer-xd0301-category'])
        @include('theme-xd0301::partials.auth-modal')
    </div>
</body>
</html>
