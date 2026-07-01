@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? 'admin@htvietnam.vn'));
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM'));
    $gallery = $productGallery ?? [];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/xd0301-product/1200/900');
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $detailHtml = trim((string) ($productModel->detail_content ?? ''));
    $seoTitle = trim((string) ($productModel->meta_title ?? '')) ?: ($product['title'] ?? $logoAlt);
    $seoDescription = trim((string) ($productModel->meta_description ?? '')) ?: trim((string) ($productModel->short_description ?? ''));
    $seoKeywords = trim((string) ($productModel->meta_keywords ?? ''));
    $usageTermsList = $usageTerms ?? [];
    $relatedProducts = $relatedProducts ?? [];
    $discount = (int) ($product['discount'] ?? 0);
    $isOutOfStock = $productModel->stock !== null && (int) $productModel->stock <= 0;
    $formatCurrency = fn ($value) => $value === null || (float) $value <= 0 ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    if ($highlights === [] && filled($productModel->short_description)) {
        $highlights = [trim((string) $productModel->short_description)];
    }

    if ($detailParagraphsList === []) {
        $detailParagraphsList = [
            $productModel->detail_content ?: ($productModel->short_description ?: 'Thông tin sản phẩm đang được cập nhật.'),
        ];
    }

    if ($detailHtml === '') {
        $detailHtml = collect($detailParagraphsList)
            ->map(fn ($paragraph): string => '<p>'.e((string) $paragraph).'</p>')
            ->implode('');
    }

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
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
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
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }} | {{ $logoAlt }}</title>
    @if ($seoDescription !== '')
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    @if ($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <style>
        :root{--lime:#bdd400;--lime-dark:#8fa900;--ink:#26384a;--muted:#74808a;--line:#e6ebe8;--bg:#fbfcfa;--shadow:0 22px 55px rgba(28,45,60,.13);--font:"Montserrat","Segoe UI",Arial,sans-serif}
        *{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--bg);font-family:var(--font);font-size:16px;line-height:1.75}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}button,input,select{font:inherit}.xd-container{width:min(1540px,calc(100% - 56px));margin:0 auto}
        .xd-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);box-shadow:0 10px 30px rgba(16,29,40,.08);backdrop-filter:blur(14px)}.xd-header-inner{display:flex;align-items:center;justify-content:space-between;min-height:102px;gap:34px}.xd-logo{display:inline-flex;align-items:center;gap:11px;font-size:34px;font-weight:900;letter-spacing:-.06em;color:var(--ink)}.xd-logo-image{display:block;width:auto;max-width:156px;height:64px;object-fit:contain}.xd-logo-mark{position:relative;width:38px;height:50px;background:linear-gradient(135deg,var(--lime),#d9ec27);clip-path:polygon(50% 0,100% 22%,100% 100%,0 100%,0 22%)}.xd-logo-mark:before,.xd-logo-mark:after{content:"";position:absolute;background:#fff}.xd-logo-mark:before{left:9px;bottom:7px;width:20px;height:30px}.xd-logo-mark:after{left:14px;top:17px;width:10px;height:7px;box-shadow:0 11px 0 #fff}.xd-logo span{color:var(--lime)}
        .xd-nav{display:flex;align-items:center;justify-content:center;gap:0;min-width:0;flex:1}.xd-nav-item{position:relative}.xd-nav-link{display:inline-flex;align-items:center;gap:8px;padding:39px 21px;color:#344354;font-size:15px;font-weight:850;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}.xd-nav-caret{color:var(--lime-dark);font-size:12px;transition:transform .18s ease}.xd-nav-link.is-active,.xd-nav-link:hover,.xd-nav-item:hover>.xd-nav-link,.xd-nav-item:focus-within>.xd-nav-link{color:var(--lime-dark)}.xd-nav-item:hover>.xd-nav-link .xd-nav-caret,.xd-nav-item:focus-within>.xd-nav-link .xd-nav-caret{transform:rotate(180deg)}.xd-dropdown{position:absolute;top:100%;left:0;z-index:90;min-width:250px;padding:12px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateY(12px);transition:.18s}.xd-nav-item:hover>.xd-dropdown,.xd-nav-item:focus-within>.xd-dropdown{opacity:1;visibility:visible;transform:translateY(0)}.xd-dropdown-link{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 14px;border-left:3px solid transparent;color:#53606b;font-size:14px;font-weight:800;line-height:1.35}.xd-dropdown-link:hover{background:#f7f9ee;border-left-color:var(--lime);color:var(--ink)}
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-cart-link{display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}.xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.xd-cart-link:hover{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap;cursor:pointer}.xd-login-button:hover{border-color:var(--lime);color:var(--lime-dark)}
        .xd-page-main{padding:46px 0 88px}.xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:28px;color:var(--muted);font-size:14px;font-weight:750}.xd-breadcrumb a:hover{color:var(--lime-dark)}.xd-product-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(420px,.75fr);gap:42px;align-items:start}.xd-gallery-panel,.xd-info-panel,.xd-panel{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.07)}.xd-gallery-stage{height:min(680px,60vw);min-height:420px;background:#eef2ef;overflow:hidden}.xd-gallery-stage img{width:100%;height:100%;object-fit:cover}.xd-thumbs{display:flex;gap:12px;overflow:auto;padding:14px}.xd-thumb{flex:0 0 92px;width:92px;height:74px;padding:0;border:2px solid transparent;background:#eef2ef;cursor:pointer}.xd-thumb.is-active{border-color:var(--lime)}.xd-thumb img{width:100%;height:100%;object-fit:cover}.xd-info-panel{padding:44px}.xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-info-panel h1{margin:0 0 18px;font-size:clamp(38px,4vw,64px);line-height:1.06;letter-spacing:-.055em}.xd-summary{margin:0 0 24px;color:var(--muted);font-size:19px;font-weight:600}.xd-product-meta{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px}.xd-chip{display:inline-flex;align-items:center;min-height:36px;padding:0 14px;border:1px solid var(--line);border-radius:999px;background:#fbfcfa;color:var(--muted);font-size:13px;font-weight:850;text-transform:uppercase}.xd-price-box{padding:24px 0;margin-bottom:22px;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.xd-price-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.xd-price{color:#9a6a3e;font-size:42px;font-weight:950;letter-spacing:-.04em}.xd-old-price{color:#9aa3a9;font-size:24px;text-decoration:line-through}.xd-discount{display:inline-flex;align-items:center;height:30px;padding:0 10px;border-radius:999px;background:var(--ink);color:#fff;font-weight:900}.xd-purchase-form{display:grid;gap:18px}.xd-quantity{display:flex;align-items:center;gap:12px;color:var(--muted);font-weight:850}.xd-quantity select{height:44px;min-width:86px;border:1px solid var(--line);padding:0 12px;background:#fff}.xd-cta-row{display:flex;flex-wrap:wrap;gap:12px}.xd-btn{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:0 24px;border:0;border-radius:3px;font-weight:950;text-transform:uppercase;cursor:pointer}.xd-btn-primary{background:var(--lime);color:#fff;box-shadow:0 15px 30px rgba(189,212,0,.28)}.xd-btn-dark{background:var(--ink);color:#fff}.xd-btn-outline{background:#fff;color:var(--ink);border:1px solid var(--line)}.xd-btn:hover{transform:translateY(-1px)}.xd-stock{color:var(--muted);font-size:14px;font-weight:800}.xd-content-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:34px;margin-top:42px}.xd-panel{padding:34px}.xd-panel h2,.xd-panel h3{margin:0 0 18px;font-size:30px;line-height:1.2;letter-spacing:-.035em}.xd-rich{color:#465461;font-size:18px}.xd-rich p{margin:0 0 16px}.xd-list{margin:0;padding:0;list-style:none;display:grid;gap:12px}.xd-list li{position:relative;padding-left:24px;color:#465461;font-weight:650}.xd-list li:before{content:"";position:absolute;left:0;top:.65em;width:9px;height:9px;background:var(--lime)}.xd-side-stack{display:grid;gap:18px}.xd-related{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;margin-top:42px}.xd-related-card{background:#fff;border:1px solid var(--line);box-shadow:0 12px 34px rgba(16,29,40,.06);overflow:hidden}.xd-related-card img{width:100%;height:220px;object-fit:cover}.xd-related-card div{padding:18px}.xd-related-card h3{margin:0 0 10px;font-size:17px;line-height:1.35;text-transform:uppercase}.xd-related-price{color:#9a6a3e;font-size:20px;font-weight:950}.xd-section-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-top:64px}.xd-section-head h2{margin:0;font-size:40px;line-height:1.1;letter-spacing:-.04em}.xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}
        .xd-rich figure{margin:22px 0}.xd-rich img{max-width:100%;height:auto;border-radius:14px;box-shadow:0 18px 38px rgba(16,29,40,.12)}
        .xd-cart-message{margin-bottom:24px;border-left:5px solid var(--lime);font-weight:850}.xd-cart-message.is-error{border-left-color:#dc3545;background:#fff8f8;color:#9b1c31}.xd-out-of-stock{display:grid;gap:14px;margin-top:6px}.xd-stock-alert{padding:16px 18px;border:1px solid #f1c9cf;background:#fff8f8;color:#9b1c31;font-weight:850}.xd-btn[disabled]{opacity:.55;cursor:not-allowed;transform:none}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-product-hero,.xd-content-grid{grid-template-columns:1fr}.xd-related,.xd-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){body{font-size:15px}.xd-container{width:min(100% - 24px,1540px)}.xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}.xd-logo-image{max-width:132px;height:52px}.xd-header-actions{width:auto;gap:8px;margin-left:auto}.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-hotline{min-height:42px;padding:0 14px;border-radius:999px;font-size:0}.xd-hotline:after{content:"{{ $hotline }}";font-size:13px}.xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px}.xd-nav{flex:0 0 100%;display:flex;gap:8px;overflow-x:auto;padding:8px 0 2px}.xd-nav::-webkit-scrollbar{display:none}.xd-nav-item{flex:0 0 auto}.xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;font-size:12px}.xd-page-main{padding:28px 0 54px}.xd-gallery-stage{height:360px;min-height:0}.xd-info-panel,.xd-panel{padding:24px}.xd-info-panel h1{font-size:34px}.xd-price{font-size:32px}.xd-cta-row{display:grid}.xd-btn{width:100%}.xd-related,.xd-footer-grid{grid-template-columns:1fr}.xd-footer{padding:52px 0 42px}.xd-footer h3{font-size:24px}.xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}.xd-newsletter{display:grid;border-radius:16px;overflow:hidden}.xd-newsletter input{min-height:52px}.xd-newsletter button{width:100%;min-height:52px}}
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
            <div class="xd-container">
                @if (session('cart_success'))
                    <div class="xd-panel xd-cart-message">{{ session('cart_success') }}</div>
                @endif
                @if (session('cart_error') || $errors->has('cart'))
                    <div class="xd-panel xd-cart-message is-error">{{ session('cart_error') ?: $errors->first('cart') }}</div>
                @endif

                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a>
                    @if ($productModel->category?->parent)
                        <span>/</span>
                        <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->parent->slug]) }}">{{ $productModel->category->parent->name }}</a>
                    @endif
                    @if ($productModel->category)
                        <span>/</span>
                        <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->slug]) }}">{{ $productModel->category->name }}</a>
                    @endif
                    <span>/</span>
                    <span>{{ $product['title'] }}</span>
                </nav>

                <section class="xd-product-hero">
                    <div class="xd-gallery-panel">
                        <div class="xd-gallery-stage">
                            <img id="xd-product-main-image" src="{{ $primaryImage }}" alt="{{ $product['title'] }}">
                        </div>
                        @if (count($gallery) > 1)
                            <div class="xd-thumbs" aria-label="Gallery ảnh sản phẩm">
                                @foreach ($gallery as $index => $image)
                                    <button type="button" class="xd-thumb {{ $index === 0 ? 'is-active' : '' }}" data-xd-thumb data-image-url="{{ $image['url'] }}" data-image-alt="{{ $image['alt'] }}">
                                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <article class="xd-info-panel">
                        <span class="xd-kicker">{{ $productModel->category?->name ?: 'Sản phẩm' }}</span>
                        <h1>{{ $product['title'] }}</h1>
                        <p class="xd-summary">{!! nl2br(e($productModel->short_description ?: 'Giải pháp vật tư và nội thất cho công trình hiện đại.')) !!}</p>

                        <div class="xd-product-meta">
                            @if ($productModel->sku)
                                <span class="xd-chip">SKU {{ $productModel->sku }}</span>
                            @endif
                            <span class="xd-chip">Tồn kho {{ number_format((int) ($product['meta'] ?? 0), 0, ',', '.') }}</span>
                            @if ($productModel->category)
                                <span class="xd-chip">{{ $productModel->category->name }}</span>
                            @endif
                        </div>

                        <div class="xd-price-box">
                            <div class="xd-price-row">
                                <span class="xd-price">{{ $formatCurrency($product['price'] ?? null) }}</span>
                                @if (($product['old_price'] ?? null) !== null)
                                    <span class="xd-old-price">{{ $formatCurrency($product['old_price']) }}</span>
                                @endif
                                @if ($discount > 0)
                                    <span class="xd-discount">-{{ $discount }}%</span>
                                @endif
                            </div>
                        </div>

                        @if ($isOutOfStock)
                            <div class="xd-out-of-stock">
                                <div class="xd-stock-alert">Sản phẩm hiện đã hết hàng. Vui lòng liên hệ hotline {{ $hotline }} để được tư vấn.</div>
                                <div class="xd-cta-row">
                                    <button type="button" class="xd-btn xd-btn-dark" disabled>Tạm hết hàng</button>
                                    <a class="xd-btn xd-btn-primary" href="tel:{{ $phoneHref }}">Liên hệ ngay</a>
                                    @if (!empty($themeShellData['customer_auth']['is_authenticated']))
                                        <form method="POST" action="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">
                                            @csrf
                                            <button type="submit" class="xd-btn xd-btn-outline">{{ !empty($isFavorite) ? 'Đã lưu' : 'Lưu yêu thích' }}</button>
                                        </form>
                                    @else
                                        <button type="button" class="xd-btn xd-btn-outline" data-xd-auth-open="login">Đăng nhập để lưu</button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <form class="xd-purchase-form" method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}">
                                @csrf
                                <input type="hidden" name="quantity" value="1">
                                <div class="xd-cta-row">
                                    <button type="submit" class="xd-btn xd-btn-primary" formaction="{{ route('site.cart.buy_now', ['slug' => $productModel->slug]) }}">Mua ngay</button>
                                    <button type="submit" class="xd-btn xd-btn-dark">Thêm vào giỏ</button>
                                    @if (!empty($themeShellData['customer_auth']['is_authenticated']))
                                        <button type="submit" class="xd-btn xd-btn-outline" formaction="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">{{ !empty($isFavorite) ? 'Đã lưu' : 'Lưu yêu thích' }}</button>
                                    @else
                                        <button type="button" class="xd-btn xd-btn-outline" data-xd-auth-open="login">Đăng nhập để lưu</button>
                                    @endif
                                </div>
                                <div class="xd-stock">Tư vấn kỹ thuật qua hotline {{ $hotline }} trước khi đặt hàng số lượng lớn.</div>
                            </form>
                        @endif
                    </article>
                </section>

                <section class="xd-content-grid">
                    <article class="xd-panel">
                        <h2>Thông tin chi tiết</h2>
                        <div class="xd-rich">
                            {!! $detailHtml !!}
                        </div>
                    </article>

                    <aside class="xd-side-stack">
                        <section class="xd-panel">
                            <h3>Điểm nổi bật</h3>
                            <ul class="xd-list">
                                @forelse ($highlights as $item)
                                    <li>{{ $item }}</li>
                                @empty
                                    <li>Phù hợp cho công trình dân dụng và thương mại.</li>
                                    <li>Dễ phối hợp với quy trình tư vấn, báo giá và thi công.</li>
                                @endforelse
                            </ul>
                        </section>
                        @if ($usageTermsList !== [])
                            <section class="xd-panel">
                                <h3>Lưu ý sử dụng</h3>
                                <ul class="xd-list">
                                    @foreach ($usageTermsList as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </aside>
                </section>

                @if (!empty($relatedProducts))
                    <div class="xd-section-head">
                        <div>
                            <span class="xd-kicker">Gợi ý thêm</span>
                            <h2>Sản phẩm liên quan</h2>
                        </div>
                    </div>
                    <section class="xd-related">
                        @foreach (collect($relatedProducts)->take(4) as $related)
                            <article class="xd-related-card">
                                <a href="{{ $related['url'] ?? '#' }}">
                                    <img src="{{ $related['image'] ?? 'https://picsum.photos/seed/xd0301-related/640/420' }}" alt="{{ $related['title'] ?? 'Sản phẩm' }}">
                                    <div>
                                        <h3>{{ $related['title'] ?? 'Sản phẩm' }}</h3>
                                        <span class="xd-related-price">{{ $formatCurrency($related['price'] ?? null) }}</span>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </section>
                @endif
            </div>
        </main>

        @include('theme-xd0301::partials.footer', ['footerNewsletterSource' => 'theme-footer-xd0301-product'])
        @include('theme-xd0301::partials.auth-modal')
    </div>

    <script>
        document.querySelectorAll('[data-xd-thumb]').forEach((button) => {
            button.addEventListener('click', () => {
                const image = document.getElementById('xd-product-main-image');
                if (!image) return;
                image.src = button.dataset.imageUrl || image.src;
                image.alt = button.dataset.imageAlt || image.alt;
                document.querySelectorAll('[data-xd-thumb]').forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
            });
        });
    </script>
</body>
</html>

