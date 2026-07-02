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
    $form = $checkoutForm ?? [];
    $paymentMethods = $paymentMethods ?? [];
    $isEnglish = app()->getLocale() === 'en';
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

    $normalizeLabel = fn ($value): string => \Illuminate\Support\Str::of((string) $value)->lower()->ascii()->squish()->toString();
    $isProductLabel = fn ($label): bool => in_array($normalizeLabel($label), ['san pham', 'products', 'product'], true);
    $isHomeLabel = fn ($label): bool => in_array($normalizeLabel($label), ['trang chu', 'home'], true);

    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $homeUrl = route('site.home');
    if (! $navItems->contains(fn (array $item): bool => $isHomeLabel($item['label'] ?? '') || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => $isEnglish ? 'Home' : 'Trang chủ',
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

    if ($productNavigationItems->isNotEmpty()) {
        if ($navItems->contains(fn (array $item): bool => $isProductLabel($item['label'] ?? ''))) {
            $navItems = $navItems->map(function (array $item) use ($productNavigationItems, $isProductLabel): array {
                if ($isProductLabel($item['label'] ?? '') && empty($item['children'])) {
                    $item['children'] = $productNavigationItems->all();
                }

                return $item;
            })->values();
        } else {
            $navArray = $navItems->values()->all();
            $homeIndex = $navItems->search(fn (array $item): bool => $isHomeLabel($item['label'] ?? ''));
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [[
                'label' => $isEnglish ? 'Products' : 'Sản phẩm',
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
    <title>{{ $isEnglish ? 'Checkout' : 'Thanh toán' }} | {{ $logoAlt }}</title>
    <style>
        :root{--lime:#bdd400;--lime-dark:#8fa900;--ink:#26384a;--muted:#74808a;--line:#e6ebe8;--bg:#fbfcfa;--dark:#10202a;--shadow:0 22px 55px rgba(28,45,60,.13);--font:"Montserrat","Segoe UI",Arial,sans-serif}
        *{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--bg);font-family:var(--font);font-size:16px;line-height:1.7}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}button,input,select,textarea{font:inherit}.xd-container{width:min(1540px,calc(100% - 56px));margin:0 auto}
        .xd-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);box-shadow:0 10px 30px rgba(16,29,40,.08);backdrop-filter:blur(14px)}.xd-header-inner{display:flex;align-items:center;justify-content:space-between;min-height:102px;gap:34px}.xd-logo{display:inline-flex;align-items:center;gap:11px;font-size:34px;font-weight:900;letter-spacing:-.06em;color:var(--ink)}.xd-logo-image{display:block;width:auto;max-width:156px;height:64px;object-fit:contain}.xd-logo-mark{position:relative;width:38px;height:50px;background:linear-gradient(135deg,var(--lime),#d9ec27);clip-path:polygon(50% 0,100% 22%,100% 100%,0 100%,0 22%)}.xd-logo-mark:before,.xd-logo-mark:after{content:"";position:absolute;background:#fff}.xd-logo-mark:before{left:9px;bottom:7px;width:20px;height:30px}.xd-logo-mark:after{left:14px;top:17px;width:10px;height:7px;box-shadow:0 11px 0 #fff}.xd-logo span{color:var(--lime)}
        .xd-nav{display:flex;align-items:center;justify-content:center;gap:0;min-width:0;flex:1}.xd-nav-item{position:relative}.xd-nav-link{display:inline-flex;align-items:center;gap:8px;padding:39px 21px;color:#344354;font-size:15px;font-weight:850;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}.xd-nav-caret{color:var(--lime-dark);font-size:12px;transition:transform .18s ease}.xd-nav-link.is-active,.xd-nav-link:hover,.xd-nav-item:hover>.xd-nav-link,.xd-nav-item:focus-within>.xd-nav-link{color:var(--lime-dark)}.xd-nav-item:hover>.xd-nav-link .xd-nav-caret,.xd-nav-item:focus-within>.xd-nav-link .xd-nav-caret{transform:rotate(180deg)}.xd-dropdown{position:absolute;top:100%;left:0;z-index:90;min-width:250px;padding:12px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateY(12px);transition:.18s}.xd-nav-item:hover>.xd-dropdown,.xd-nav-item:focus-within>.xd-dropdown{opacity:1;visibility:visible;transform:translateY(0)}.xd-dropdown-link{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 14px;border-left:3px solid transparent;color:#53606b;font-size:14px;font-weight:800;line-height:1.35}.xd-dropdown-link:hover{background:#f7f9ee;border-left-color:var(--lime);color:var(--ink)}
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-cart-link{position:relative;display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}.xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.xd-cart-link:hover,.xd-cart-link.is-active{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}.xd-cart-count{position:absolute;right:-7px;top:-7px;display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--dark);color:#fff;font-size:11px;font-weight:950}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap;cursor:pointer}
        .xd-page-main{padding:54px 0 88px;background:linear-gradient(180deg,#fff 0,#fbfcfa 52%,#fff 100%)}.xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:24px;color:var(--muted);font-size:14px;font-weight:800}.xd-breadcrumb a:hover{color:var(--lime-dark)}.xd-heading{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:24px;margin-bottom:32px}.xd-kicker{position:relative;display:inline-block;margin:0 0 16px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-heading h1{margin:0;font-size:clamp(42px,5vw,72px);line-height:1;letter-spacing:-.06em}.xd-heading p{margin:14px 0 0;color:var(--muted);font-size:19px;font-weight:700}
        .xd-alert{margin-bottom:18px;padding:14px 18px;border-left:5px solid #d94b5b;background:#fff3f4;color:#9e2d3b;font-weight:800}.xd-checkout-grid{display:grid;grid-template-columns:minmax(0,1fr) 430px;gap:30px;align-items:start}.xd-panel{background:#fff;border:1px solid var(--line);box-shadow:var(--shadow)}.xd-form-panel{padding:30px}.xd-section-title{margin:0 0 20px;font-size:30px;line-height:1.15;letter-spacing:-.035em}.xd-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.xd-field{display:grid;gap:8px}.xd-field.is-wide{grid-column:1/-1}.xd-field label{font-size:13px;font-weight:950;text-transform:uppercase;letter-spacing:.04em}.xd-field input,.xd-field textarea{width:100%;border:1px solid var(--line);background:#fff;padding:14px 16px;color:var(--ink);outline:none}.xd-field textarea{min-height:118px;resize:vertical}.xd-field input:focus,.xd-field textarea:focus{border-color:var(--lime);box-shadow:0 0 0 4px rgba(189,212,0,.12)}.xd-payment-list{display:grid;gap:12px;margin-top:24px}.xd-payment-option{display:grid;grid-template-columns:22px minmax(0,1fr);gap:14px;padding:16px;border:1px solid var(--line);background:#fbfcfa;cursor:pointer}.xd-payment-option:hover{border-color:var(--lime)}.xd-payment-option strong{display:block;margin-bottom:2px}.xd-payment-option span{color:var(--muted);font-size:14px;font-weight:650}.xd-button{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:58px;margin-top:24px;padding:0 22px;border:0;background:var(--lime);color:#fff;font-weight:950;text-transform:uppercase;box-shadow:0 18px 30px rgba(189,212,0,.28);cursor:pointer}.xd-button.is-ghost{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none}.xd-summary{position:sticky;top:126px;padding:28px}.xd-summary h2{margin:0 0 18px;font-size:30px;line-height:1.15;letter-spacing:-.035em}.xd-summary-items{display:grid;gap:14px}.xd-summary-item{display:grid;grid-template-columns:72px minmax(0,1fr);gap:14px;padding-bottom:14px;border-bottom:1px solid var(--line)}.xd-summary-thumb{aspect-ratio:1/1;background:#f5f8f2;overflow:hidden}.xd-summary-thumb img{width:100%;height:100%;object-fit:contain;padding:7px}.xd-summary-name{display:block;font-weight:900;line-height:1.35}.xd-summary-meta{display:block;color:var(--muted);font-size:13px;font-weight:750}.xd-summary-price{display:block;margin-top:4px;color:#9a6a3e;font-weight:950}.xd-summary-row{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px solid var(--line);font-weight:850}.xd-summary-row strong{font-size:28px;color:#9a6a3e}.xd-empty{padding:32px;border:1px dashed rgba(38,56,74,.25);text-align:center;background:#fff}.xd-empty h2{margin:0 0 8px;font-size:30px;letter-spacing:-.035em}.xd-empty p{margin:0 0 18px;color:var(--muted);font-weight:700}
        .xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-checkout-grid{grid-template-columns:1fr}.xd-summary{position:static}.xd-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-container{width:min(100% - 24px,1540px)}.xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}.xd-logo-image{max-width:132px;height:52px}.xd-header-actions{margin-left:auto;gap:8px}.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-hotline{min-height:42px;padding:0 14px;border-radius:999px;font-size:0}.xd-hotline:after{content:"{{ $hotline }}";font-size:13px}.xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px}.xd-nav{flex:0 0 100%;gap:8px;overflow-x:auto;padding:8px 0 2px}.xd-nav::-webkit-scrollbar{display:none}.xd-nav-item{flex:0 0 auto}.xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;font-size:12px}.xd-page-main{padding:34px 0 54px}.xd-heading{grid-template-columns:1fr}.xd-heading h1{font-size:42px}.xd-form-panel{padding:20px}.xd-form-grid{grid-template-columns:1fr}.xd-footer-grid{grid-template-columns:1fr}.xd-footer{padding:52px 0 42px}.xd-footer h3{font-size:24px}.xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}.xd-newsletter{display:grid;border-radius:16px;overflow:hidden}.xd-newsletter input{min-height:52px}.xd-newsletter button{width:100%;min-height:52px}}
    </style>
</head>
<body>
    <div id="top" class="xd-page xd-checkout-page">
        <header class="xd-header">
            <div class="xd-container xd-header-inner">
                <a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt }} {{ $isEnglish ? 'home' : 'trang chủ' }}">
                    @if ($logoUrl !== '')
                        <img class="xd-logo-image" src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
                    @else
                        <i class="xd-logo-mark" aria-hidden="true"></i><b>Ar<span>kit</span></b>
                    @endif
                </a>
                <nav class="xd-nav" aria-label="{{ $isEnglish ? 'Main menu' : 'Menu chính' }}">
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
                    <a class="xd-cart-link" href="{{ route('site.cart.index') }}" aria-label="{{ $isEnglish ? 'Cart' : 'Giỏ hàng' }}" title="{{ $isEnglish ? 'Cart' : 'Giỏ hàng' }}">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6h15l-1.5 8.5H8L6 3H3"/><circle cx="9" cy="20" r="1.7"/><circle cx="18" cy="20" r="1.7"/></svg>
                        @if ((int) ($cartSummary['count'] ?? 0) > 0)
                            <span class="xd-cart-count">{{ (int) ($cartSummary['count'] ?? 0) }}</span>
                        @endif
                    </a>
                    @if ($customerAuth['is_authenticated'] ?? false)
                        <a class="xd-login-button" href="{{ route('customer.account') }}">{{ $isEnglish ? 'Account' : 'Tài khoản' }}</a>
                    @else
                        <button type="button" class="xd-login-button" data-xd-auth-open="login">{{ $isEnglish ? 'Login' : 'Đăng nhập' }}</button>
                    @endif
                </div>
            </div>
        </header>

        <main class="xd-page-main">
            <div class="xd-container">
                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">{{ $isEnglish ? 'Home' : 'Trang chủ' }}</a>
                    <span>/</span>
                    <a href="{{ route('site.cart.index') }}">{{ $isEnglish ? 'Cart' : 'Giỏ hàng' }}</a>
                    <span>/</span>
                    <strong>{{ $isEnglish ? 'Checkout' : 'Thanh toán' }}</strong>
                </nav>

                <section class="xd-heading">
                    <div>
                        <span class="xd-kicker">{{ $isEnglish ? 'Checkout' : 'Thanh toán' }}</span>
                        <h1>{{ $isEnglish ? 'Complete your order' : 'Hoàn tất đơn hàng' }}</h1>
                        <p>{{ $isEnglish ? 'Confirm delivery information and payment method before placing the order.' : 'Xác nhận thông tin nhận hàng và phương thức thanh toán trước khi gửi đơn.' }}</p>
                    </div>
                </section>

                @if ($errors->any())
                    <div class="xd-alert">{{ $errors->first() }}</div>
                @endif

                @if (empty($cartItems))
                    <section class="xd-empty">
                        <h2>{{ $isEnglish ? 'Your cart is empty' : 'Giỏ hàng đang trống' }}</h2>
                        <p>{{ $isEnglish ? 'Please add products before checkout.' : 'Vui lòng thêm sản phẩm trước khi thanh toán.' }}</p>
                        <a class="xd-button" href="{{ route('site.catalog.search') }}">{{ $isEnglish ? 'Browse products' : 'Xem sản phẩm' }}</a>
                    </section>
                @else
                    <section class="xd-checkout-grid">
                        <form class="xd-panel xd-form-panel" method="POST" action="{{ route('site.checkout.store') }}">
                            @csrf
                            <h2 class="xd-section-title">{{ $isEnglish ? 'Delivery information' : 'Thông tin nhận hàng' }}</h2>
                            <div class="xd-form-grid">
                                <label class="xd-field">
                                    <span>{{ $isEnglish ? 'Full name' : 'Họ tên' }}</span>
                                    <input name="customer_name" value="{{ old('customer_name', $form['customer_name'] ?? '') }}" required>
                                </label>
                                <label class="xd-field">
                                    <span>{{ $isEnglish ? 'Phone' : 'Số điện thoại' }}</span>
                                    <input name="customer_phone" value="{{ old('customer_phone', $form['customer_phone'] ?? '') }}" required>
                                </label>
                                <label class="xd-field is-wide">
                                    <span>Email</span>
                                    <input type="email" name="customer_email" value="{{ old('customer_email', $form['customer_email'] ?? '') }}">
                                </label>
                                <label class="xd-field is-wide">
                                    <span>{{ $isEnglish ? 'Delivery address' : 'Địa chỉ nhận hàng' }}</span>
                                    <textarea name="delivery_address" required>{{ old('delivery_address', $form['delivery_address'] ?? '') }}</textarea>
                                </label>
                                <label class="xd-field is-wide">
                                    <span>{{ $isEnglish ? 'Order note' : 'Ghi chú' }}</span>
                                    <textarea name="note">{{ old('note', $form['note'] ?? '') }}</textarea>
                                </label>
                            </div>

                            <div class="xd-payment-list">
                                <h2 class="xd-section-title">{{ $isEnglish ? 'Payment method' : 'Phương thức thanh toán' }}</h2>
                                @foreach ($paymentMethods as $value => $method)
                                    @php
                                        $methodValue = is_array($method) ? (string) ($method['value'] ?? $value) : (string) $value;
                                        $methodLabel = is_array($method) ? (string) ($method['label'] ?? $methodValue) : (string) $method;
                                        $methodHint = is_array($method) ? (string) ($method['hint'] ?? '') : '';
                                    @endphp
                                    <label class="xd-payment-option">
                                        <input type="radio" name="payment_method" value="{{ $methodValue }}" @checked(old('payment_method', $form['payment_method'] ?? 'cod') === $methodValue)>
                                        <span>
                                            <strong>{{ $methodLabel }}</strong>
                                            @if ($methodHint !== '')
                                                <span>{{ $methodHint }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <button class="xd-button" type="submit">{{ $isEnglish ? 'Place order' : 'Đặt hàng' }}</button>
                        </form>

                        <aside class="xd-panel xd-summary">
                            <h2>{{ $isEnglish ? 'Order summary' : 'Tóm tắt đơn hàng' }}</h2>
                            <div class="xd-summary-items">
                                @foreach ($cartItems as $item)
                                    <div class="xd-summary-item">
                                        <span class="xd-summary-thumb">
                                            @if (!empty($item['image_url']))
                                                <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] ?? '' }}">
                                            @endif
                                        </span>
                                        <span>
                                            <strong class="xd-summary-name">{{ $item['name'] ?? 'Sản phẩm' }}</strong>
                                            <span class="xd-summary-meta">x{{ (int) ($item['quantity'] ?? 1) }}</span>
                                            <span class="xd-summary-price">{{ $formatCurrency($item['line_total'] ?? $item['price'] ?? null) }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="xd-summary-row">
                                <span>{{ $isEnglish ? 'Products' : 'Số sản phẩm' }}</span>
                                <b>{{ (int) ($cartSummary['count'] ?? 0) }}</b>
                            </div>
                            <div class="xd-summary-row">
                                <span>{{ $isEnglish ? 'Estimated total' : 'Tạm tính' }}</span>
                                <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                            </div>
                            <a class="xd-button is-ghost" href="{{ route('site.cart.index') }}">{{ $isEnglish ? 'Back to cart' : 'Quay lại giỏ hàng' }}</a>
                        </aside>
                    </section>
                @endif
            </div>
        </main>

        @include('theme-xd0301::partials.footer', ['footerNewsletterSource' => 'theme-footer-xd0301-checkout'])
        @include('theme-xd0301::partials.auth-modal')
    </div>
</body>
</html>
