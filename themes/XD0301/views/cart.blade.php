@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342')) ?: '0399162342';
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $cartSummary = (array) data_get($shell, 'cart_summary', ['count' => 0, 'unique_count' => 0, 'subtotal' => 0, 'items' => []]);
    $cartItems = array_values((array) ($cartSummary['items'] ?? []));
    $customerAuth = (array) data_get($shell, 'customer_auth', [
        'is_authenticated' => auth('customer')->check(),
        'customer' => auth('customer')->user(),
    ]);
    $formatCurrency = function ($value): string {
        if ($value === null || (float) $value <= 0) {
            return 'Liên hệ';
        }

        return number_format((float) $value, 0, ',', '.').'đ';
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
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chủ', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chủ',
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
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems->map(function (array $item) use ($productNavigationItems): array {
                $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
                if (in_array($label, ['sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                    $item['children'] = $productNavigationItems->all();
                }

                return $item;
            })->values();
        } else {
            $navArray = $navItems->values()->all();
            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [[
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
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
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Giỏ hàng | {{ $logoAlt }}</title>
    <style>
        :root{--lime:#bdd400;--lime-dark:#8fa900;--ink:#26384a;--muted:#74808a;--line:#e6ebe8;--bg:#fbfcfa;--dark:#10202a;--shadow:0 22px 55px rgba(28,45,60,.13);--font:"Montserrat","Segoe UI",Arial,sans-serif}
        *{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--bg);font-family:var(--font);font-size:16px;line-height:1.7}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}button,input,select{font:inherit}.xd-container{width:min(1540px,calc(100% - 56px));margin:0 auto}
        .xd-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);box-shadow:0 10px 30px rgba(16,29,40,.08);backdrop-filter:blur(14px)}.xd-header-inner{display:flex;align-items:center;justify-content:space-between;min-height:102px;gap:34px}.xd-logo{display:inline-flex;align-items:center;gap:11px;font-size:34px;font-weight:900;letter-spacing:-.06em;color:var(--ink)}.xd-logo-image{display:block;width:auto;max-width:156px;height:64px;object-fit:contain}.xd-logo-mark{position:relative;width:38px;height:50px;background:linear-gradient(135deg,var(--lime),#d9ec27);clip-path:polygon(50% 0,100% 22%,100% 100%,0 100%,0 22%)}.xd-logo-mark:before,.xd-logo-mark:after{content:"";position:absolute;background:#fff}.xd-logo-mark:before{left:9px;bottom:7px;width:20px;height:30px}.xd-logo-mark:after{left:14px;top:17px;width:10px;height:7px;box-shadow:0 11px 0 #fff}.xd-logo span{color:var(--lime)}
        .xd-nav{display:flex;align-items:center;justify-content:center;gap:0;min-width:0;flex:1}.xd-nav-item{position:relative}.xd-nav-link{display:inline-flex;align-items:center;gap:8px;padding:39px 21px;color:#344354;font-size:15px;font-weight:850;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}.xd-nav-caret{color:var(--lime-dark);font-size:12px;transition:transform .18s ease}.xd-nav-link.is-active,.xd-nav-link:hover,.xd-nav-item:hover>.xd-nav-link,.xd-nav-item:focus-within>.xd-nav-link{color:var(--lime-dark)}.xd-nav-item:hover>.xd-nav-link .xd-nav-caret,.xd-nav-item:focus-within>.xd-nav-link .xd-nav-caret{transform:rotate(180deg)}.xd-dropdown{position:absolute;top:100%;left:0;z-index:90;min-width:250px;padding:12px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateY(12px);transition:.18s}.xd-nav-item:hover>.xd-dropdown,.xd-nav-item:focus-within>.xd-dropdown{opacity:1;visibility:visible;transform:translateY(0)}.xd-dropdown-link{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 14px;border-left:3px solid transparent;color:#53606b;font-size:14px;font-weight:800;line-height:1.35}.xd-dropdown-link:hover{background:#f7f9ee;border-left-color:var(--lime);color:var(--ink)}
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-cart-link{position:relative;display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}.xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.xd-cart-link:hover,.xd-cart-link.is-active{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}.xd-cart-count{position:absolute;right:-7px;top:-7px;display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--dark);color:#fff;font-size:11px;font-weight:950}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}
        .xd-page-main{padding:54px 0 88px;background:linear-gradient(180deg,#fff 0,#fbfcfa 52%,#fff 100%)}.xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:24px;color:var(--muted);font-size:14px;font-weight:800}.xd-breadcrumb a:hover{color:var(--lime-dark)}.xd-cart-heading{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:24px;margin-bottom:32px}.xd-kicker{position:relative;display:inline-block;margin:0 0 16px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-cart-heading h1{margin:0;font-size:clamp(42px,5vw,72px);line-height:1;letter-spacing:-.06em}.xd-cart-heading p{margin:14px 0 0;color:var(--muted);font-size:19px;font-weight:700}.xd-cart-badge{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border:1px solid rgba(189,212,0,.55);background:#f8faed;color:var(--lime-dark);font-weight:950;text-transform:uppercase}
        .xd-alert{margin-bottom:18px;padding:14px 18px;border-left:5px solid var(--lime);background:#f8faed;color:var(--ink);font-weight:800}.xd-alert.is-error{border-left-color:#d94b5b;background:#fff3f4;color:#9e2d3b}.xd-cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:30px;align-items:start}.xd-panel{background:#fff;border:1px solid var(--line);box-shadow:var(--shadow)}.xd-cart-list{display:grid;gap:14px}.xd-cart-item{display:grid;grid-template-columns:154px minmax(0,1fr) 230px;gap:20px;padding:18px;border:1px solid var(--line);background:#fff;box-shadow:0 16px 38px rgba(16,29,40,.06)}.xd-cart-thumb{display:block;aspect-ratio:1/1;background:radial-gradient(circle at 50% 34%,#fff 0,#f5f8f2 48%,#e9efe8 100%);overflow:hidden}.xd-cart-thumb img{width:100%;height:100%;padding:14px;object-fit:contain}.xd-cart-copy h2{margin:0 0 10px;font-size:24px;line-height:1.25;letter-spacing:-.035em}.xd-cart-meta{display:flex;flex-wrap:wrap;gap:8px}.xd-chip{display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border:1px solid var(--line);border-radius:999px;background:#fff;color:#66717a;font-size:12px;font-weight:900;text-transform:uppercase}.xd-cart-actions{display:grid;gap:12px;align-content:start;text-align:right}.xd-price{color:#9a6a3e;font-size:24px;font-weight:950;letter-spacing:-.035em}.xd-item-total{color:var(--ink);font-weight:950}.xd-quantity-form{display:flex;justify-content:flex-end;gap:8px}.xd-quantity-form input{width:74px;height:42px;border:1px solid var(--line);padding:0 10px;text-align:center;font-weight:850}.xd-small-button,.xd-remove-button{height:42px;border:0;padding:0 14px;font-weight:950;cursor:pointer}.xd-small-button{background:var(--ink);color:#fff}.xd-remove-button{background:#fff1f1;color:#c23b48;border:1px solid #ffd2d7}.xd-summary{position:sticky;top:126px;padding:28px}.xd-summary h2{margin:0 0 8px;font-size:30px;line-height:1.15;letter-spacing:-.035em}.xd-summary p{margin:0 0 22px;color:var(--muted);font-weight:700}.xd-summary-row{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px solid var(--line);font-weight:800}.xd-summary-row strong{font-size:20px}.xd-summary-total strong{font-size:28px;color:#9a6a3e}.xd-button{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:56px;margin-top:18px;padding:0 22px;border:0;background:var(--lime);color:#fff;font-weight:950;text-transform:uppercase;box-shadow:0 18px 30px rgba(189,212,0,.28);cursor:pointer}.xd-button.is-dark{background:var(--dark);box-shadow:none}.xd-button.is-ghost{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none}.xd-empty{display:grid;place-items:center;min-height:320px;padding:52px;text-align:center;background:#fff;border:1px dashed rgba(38,56,74,.25);box-shadow:var(--shadow)}.xd-empty h2{margin:0 0 10px;font-size:36px;letter-spacing:-.04em}.xd-empty p{max-width:620px;margin:0 auto 22px;color:var(--muted);font-weight:700}
        .xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-cart-layout{grid-template-columns:1fr}.xd-summary{position:static}.xd-cart-item{grid-template-columns:130px minmax(0,1fr)}.xd-cart-actions{grid-column:1/-1;text-align:left}.xd-quantity-form{justify-content:flex-start}.xd-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-container{width:min(100% - 24px,1540px)}.xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}.xd-logo-image{max-width:132px;height:52px}.xd-header-actions{margin-left:auto;gap:8px}.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-hotline{min-height:42px;padding:0 14px;border-radius:999px;font-size:0}.xd-hotline:after{content:"{{ $hotline }}";font-size:13px}.xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px}.xd-nav{flex:0 0 100%;gap:8px;overflow-x:auto;padding:8px 0 2px}.xd-nav::-webkit-scrollbar{display:none}.xd-nav-item{flex:0 0 auto}.xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;font-size:12px}.xd-page-main{padding:34px 0 54px}.xd-cart-heading{grid-template-columns:1fr}.xd-cart-heading h1{font-size:42px}.xd-cart-item{grid-template-columns:96px minmax(0,1fr);gap:14px;padding:14px}.xd-cart-copy h2{font-size:19px}.xd-price{font-size:21px}.xd-footer-grid{grid-template-columns:1fr}.xd-footer{padding:52px 0 42px}.xd-footer h3{font-size:24px}.xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}.xd-newsletter{display:grid;border-radius:16px;overflow:hidden}.xd-newsletter input{min-height:52px}.xd-newsletter button{width:100%;min-height:52px}}
    </style>
</head>
<body>
    <div id="top" class="xd-page xd-cart-page">
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
                    <a class="xd-cart-link is-active" href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng" title="Giỏ hàng">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6h15l-1.5 8.5H8L6 3H3"/><circle cx="9" cy="20" r="1.7"/><circle cx="18" cy="20" r="1.7"/></svg>
                        @if ((int) ($cartSummary['count'] ?? 0) > 0)
                            <span class="xd-cart-count">{{ (int) ($cartSummary['count'] ?? 0) }}</span>
                        @endif
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
                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a>
                    <span>/</span>
                    <strong>Giỏ hàng</strong>
                </nav>

                <div class="xd-cart-heading">
                    <div>
                        <span class="xd-kicker">Checkout</span>
                        <h1>Giỏ hàng của bạn</h1>
                        <p>Kiểm tra sản phẩm, số lượng và chuyển sang bước đặt hàng khi thông tin đã đúng.</p>
                    </div>
                    <span class="xd-cart-badge">{{ (int) ($cartSummary['count'] ?? 0) }} sản phẩm</span>
                </div>

                @if (session('cart_success'))
                    <div class="xd-alert">{{ session('cart_success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="xd-alert is-error">{{ $errors->first() }}</div>
                @endif

                @if (! empty($cartItems))
                    <section class="xd-cart-layout">
                        <div class="xd-cart-list">
                            @foreach ($cartItems as $item)
                                @php
                                    $quantity = max(1, (int) ($item['quantity'] ?? 1));
                                    $price = (float) ($item['price'] ?? 0);
                                    $itemSubtotal = $price * $quantity;
                                    $itemUrl = (string) ($item['url'] ?? '#');
                                    $itemImage = trim((string) ($item['image'] ?? ''));
                                    $itemCategory = trim((string) ($item['category'] ?? $item['category_name'] ?? ''));
                                @endphp
                                <article class="xd-cart-item">
                                    <a class="xd-cart-thumb" href="{{ $itemUrl }}">
                                        @if ($itemImage !== '')
                                            <img src="{{ $itemImage }}" alt="{{ $item['title'] ?? 'Sản phẩm' }}">
                                        @endif
                                    </a>
                                    <div class="xd-cart-copy">
                                        <h2><a href="{{ $itemUrl }}">{{ $item['title'] ?? 'Sản phẩm' }}</a></h2>
                                        <div class="xd-cart-meta">
                                            @if ($itemCategory !== '')
                                                <span class="xd-chip">{{ $itemCategory }}</span>
                                            @endif
                                            <span class="xd-chip">Tồn kho {{ $item['stock'] ?? 'không giới hạn' }}</span>
                                            @if (! empty($item['sku']))
                                                <span class="xd-chip">SKU {{ $item['sku'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="xd-cart-actions">
                                        <div>
                                            <div class="xd-price">{{ $formatCurrency($item['price'] ?? null) }}</div>
                                            <div class="xd-item-total">Tạm tính: {{ $formatCurrency($itemSubtotal) }}</div>
                                        </div>
                                        <form method="POST" action="{{ route('site.cart.update', ['productId' => $item['product_id']]) }}" class="xd-quantity-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="number" name="quantity" min="1" max="{{ $item['stock'] ?? 999 }}" value="{{ $quantity }}" aria-label="Số lượng">
                                            <button type="submit" class="xd-small-button">Cập nhật</button>
                                        </form>
                                        <form method="POST" action="{{ route('site.cart.remove', ['productId' => $item['product_id']]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="xd-remove-button">Xóa khỏi giỏ</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <aside class="xd-panel xd-summary">
                            <h2>Tóm tắt đơn hàng</h2>
                            <p>Đơn hàng sẽ được xác nhận lại trước khi thi công hoặc giao sản phẩm.</p>
                            <div class="xd-summary-row">
                                <span>Số sản phẩm</span>
                                <strong>{{ (int) ($cartSummary['count'] ?? 0) }}</strong>
                            </div>
                            <div class="xd-summary-row">
                                <span>Mặt hàng khác nhau</span>
                                <strong>{{ (int) ($cartSummary['unique_count'] ?? count($cartItems)) }}</strong>
                            </div>
                            <div class="xd-summary-row xd-summary-total">
                                <span>Tạm tính</span>
                                <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                            </div>

                            @if ($customerAuth['is_authenticated'] ?? false)
                                <a class="xd-button" href="{{ route('site.checkout.index') }}">Tiến hành thanh toán</a>
                            @else
                                <button type="button" class="xd-button" data-xd-auth-open="login">Đăng nhập để thanh toán</button>
                            @endif
                            <a class="xd-button is-ghost" href="{{ route('site.catalog.search') }}">Tiếp tục mua sắm</a>
                        </aside>
                    </section>
                @else
                    <section class="xd-empty">
                        <div>
                            <span class="xd-kicker">Empty cart</span>
                            <h2>Giỏ hàng đang trống</h2>
                            <p>Hãy quay lại danh mục sản phẩm để chọn vật tư, dịch vụ hoặc giải pháp phù hợp trước khi gửi yêu cầu đặt hàng.</p>
                            <a class="xd-button is-dark" href="{{ route('site.catalog.search') }}">Xem sản phẩm</a>
                        </div>
                    </section>
                @endif
            </div>
        </main>

        @include('theme-xd0301::partials.footer', ['footerNewsletterSource' => 'theme-footer-xd0301-cart'])
        @include('theme-xd0301::partials.auth-modal')
    </div>
</body>
</html>
