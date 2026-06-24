@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0, 'items' => []];
    $cartItems = $cartSummary['items'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@TH0020.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà N?i');
    $companyTitle = data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', ''));
    $postLoginRedirect = session('post_login_redirect', route('site.checkout.index'));
    $formatCurrency = fn ($value) => $value === null ? 'Liên h?' : number_format((float) $value, 0, ',', '.').'d';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Gi? hàng{{ $companyTitle ? ' | '.$companyTitle : '' }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-th0020::partials.palette-tokens', ['branding' => $branding])
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--th-bg); color: var(--th-ink); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            button, input, textarea, select { font: inherit; }
            .wrap { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .utility { background: #f8f8f8; border-bottom: 1px solid var(--th-line); font-size: 13px; color: var(--th-muted); }
            .utility-inner { display: flex; justify-content: space-between; gap: 14px; padding: 8px 0; flex-wrap: wrap; }
            .utility-actions, .utility-group { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
            .utility-action { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .utility-form { margin: 0; }
            .header { background: #fff; }
            .header-main { display: grid; grid-template-columns: 220px 1fr auto; align-items: center; gap: 18px; padding: 16px 0; }
            .brand img { width: 184px; height: 52px; object-fit: contain; }
            .searchbar { display: grid; grid-template-columns: minmax(0, 1fr) 56px; align-items: stretch; height: 42px; border: 2px solid var(--th-red); border-radius: 4px; overflow: hidden; }
            .searchbar input { border: 0; background: #fff; padding: 0 14px; font-size: 14px; }
            .searchbar button { width: 56px; border: 0; background: var(--th-red); color: #fff; font-size: 18px; cursor: pointer; }
            .cart-link { font-size: 14px; font-weight: 700; color: #444; }
            .nav { background: var(--th-red); color: #fff; }
            .nav-inner { display: flex; align-items: center; justify-content: flex-start; gap: 28px; min-height: 42px; font-size: 14px; font-weight: 700; }
            .nav-category { display: block; background: rgba(0, 0, 0, .16); padding: 12px 18px; min-width: 210px; color: #fff; }
            .nav-links { display: flex; justify-content: flex-start; gap: 28px; flex-wrap: wrap; }
            .nav-links a { text-transform: uppercase; transition: color .18s ease; }
            .nav-links a:hover { color: #fff2bf; }
            .breadcrumb { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; padding: 18px 0; font-size: 13px; color: var(--th-muted); }
            .flash-banner { margin: 0 0 18px; padding: 14px 16px; border: 1px solid #c9e6b0; background: #f5ffe9; color: #3f6a18; font-size: 14px; }
            .error-banner { margin: 0 0 18px; padding: 14px 16px; border: 1px solid #f2c5c5; background: #fff2f2; color: #9a2b2b; font-size: 14px; }
            .cart-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; margin-bottom: 28px; }
            .cart-panel, .summary-panel, .empty-state { background: #fff; border: 1px solid var(--th-line); }
            .cart-panel, .summary-panel { padding: 18px; }
            .cart-title { margin: 0 0 16px; font-size: 30px; text-transform: uppercase; color: #444; }
            .cart-table { display: grid; gap: 14px; }
            .cart-row { display: grid; grid-template-columns: 100px minmax(0, 1fr) auto; gap: 16px; align-items: center; padding-bottom: 14px; border-bottom: 1px solid var(--th-line); }
            .cart-row:last-child { border-bottom: 0; padding-bottom: 0; }
            .cart-row img { width: 100px; height: 100px; object-fit: cover; border: 1px solid var(--th-line); }
            .cart-copy { display: grid; gap: 8px; min-width: 0; }
            .cart-copy h3 { margin: 0; font-size: 18px; line-height: 1.5; }
            .cart-meta { color: #7d7d7d; font-size: 14px; }
            .cart-price-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
            .cart-price { color: var(--th-red); font-size: 24px; font-weight: 800; }
            .cart-old-price { color: #999; text-decoration: line-through; font-size: 14px; }
            .cart-actions { display: grid; justify-items: end; gap: 10px; }
            .cart-total { color: #555; font-size: 14px; font-weight: 700; }
            .quantity-form { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
            .quantity-input { width: 74px; height: 40px; border: 1px solid var(--th-line); padding: 0 10px; }
            .update-button, .remove-button, .primary-button, .ghost-button { border: 0; cursor: pointer; }
            .update-button { min-height: 40px; padding: 0 14px; background: var(--th-green); color: #fff; font-weight: 700; }
            .remove-button { background: transparent; color: #888; font-size: 13px; text-decoration: underline; }
            .summary-panel h2 { margin: 0 0 16px; font-size: 22px; text-transform: uppercase; color: #444; }
            .summary-list { display: grid; gap: 12px; margin-bottom: 18px; }
            .summary-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; color: #555; font-size: 14px; }
            .summary-line strong { color: #1f1f1f; font-size: 16px; }
            .checkout-note { padding: 12px 14px; margin-bottom: 16px; border: 1px solid #ffe3a8; background: #fff8e3; color: #8b5b00; font-size: 14px; line-height: 1.6; }
            .primary-button, .ghost-button { display: inline-flex; align-items: center; justify-content: center; width: 100%; min-height: 46px; padding: 0 18px; font-size: 15px; font-weight: 700; }
            .primary-button { background: var(--th-green); color: #fff; }
            .ghost-button { background: #fff; border: 1px solid #b9d99c; color: var(--th-green-dark); margin-top: 10px; }
            .empty-state { padding: 28px; color: #666; line-height: 1.8; }
            @media (max-width: 1080px) {
                .cart-layout, .header-main { grid-template-columns: 1fr; }
            }
            @media (max-width: 720px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .nav-inner { display: block; }
                .nav-links { padding: 10px 0 12px; gap: 16px; }
                .cart-row { grid-template-columns: 1fr; }
                .cart-actions { justify-items: start; }
                .quantity-form { justify-content: start; }
            }
            @include('theme-th0020::partials.interior-shell-styles')
        </style>
    </head>
    <body class="th-interior-page">
        @include('theme-th0020::partials.interior-header')
        <div class="th-legacy-header" hidden>
        <div class="utility">
            <div class="wrap utility-inner">
                <div class="utility-group">
                    <span>{{ $contactLocation }}</span>
                    <button type="button" class="utility-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? __('common.newsletter_subscribed') : __('common.newsletter_subscribe') }}</button>
                </div>
                <div class="utility-actions">
                    <span>@themeT('common.hotline_label', 'Hotline'): {{ $contactHotline }}</span>
                    <span>@themeT('common.email_label', 'Email'): {{ $contactEmail }}</span>
                    @if (!empty($customerAuth['is_authenticated']))
                        <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">@themeT('common.account', 'Tài kho?n')</a>
                        <form class="utility-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                            @csrf
                            <button type="submit" class="utility-action">@themeT('common.logout', 'Ðang xu?t')</button>
                        </form>
                    @else
                        <button type="button" class="utility-action" data-open-auth-modal="register">@themeT('common.register', 'Ðang ký')</button>
                        <button type="button" class="utility-action" data-open-auth-modal="login" data-auth-redirect="{{ route('site.checkout.index') }}">@themeT('common.login', 'Ðang nh?p')</button>
                    @endif
                </div>
            </div>
        </div>

        <header class="header">
            <div class="wrap header-main">
                <a class="brand" href="{{ route('site.home') }}">
                    <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ $companyTitle ?: '' }}">
                </a>

                <form class="searchbar" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="@themeT('common.search_placeholder', 'Tìm ki?m s?n ph?m / khuy?n mãi')" aria-label="@themeT('common.search_aria', 'Tìm ki?m s?n ph?m')" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                    <button type="submit" aria-label="@themeT('common.search_button', 'Tìm')">?</button>
                </form>

                <a class="cart-link" href="{{ route('site.cart.index') }}">@themeT('common.cart_label', 'GI? HÀNG') ({{ $cartSummary['count'] ?? 0 }})</a>
            </div>
        </header>

        <nav class="nav">
            <div class="wrap nav-inner">
                <a class="nav-category" href="{{ route('site.catalog.search') }}">@themeT('common.products', 'S?N PH?M')</a>
                <div class="nav-links">
                    @foreach (collect($productMenu)->take(4) as $item)
                        <a href="{{ $item['url'] ?? route('site.catalog.search') }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? __('common.category') }}</a>
                    @endforeach
                    @foreach ($topMenu as $item)
                        <a href="{{ $item['url'] ?? route('site.home') }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? __('common.menu') }}</a>
                    @endforeach
                </div>
            </div>
        </nav>
        </div>

        <main class="wrap">
            <div class="breadcrumb">
                <a href="{{ route('site.home') }}">@themeT('common.home', 'Trang ch?')</a>
                <span>›</span>
                <span>@themeT('cart.breadcrumb', 'Gi? hàng')</span>
            </div>

            @if (session('cart_success'))
                <div class="flash-banner">{{ session('cart_success') }}</div>
            @endif

            @if ($errors->any())
                <div class="error-banner">{{ $errors->first() }}</div>
            @endif

            @if ($cartItems !== [])
                <section class="cart-layout">
                    <div class="cart-panel">
                        <h1 class="cart-title">@themeT('cart.title', 'Gi? hàng c?a b?n')</h1>
                        <div class="cart-table">
                            @foreach ($cartItems as $item)
                                <article class="cart-row">
                                    <a href="{{ $item['url'] ?? route('site.catalog.search') }}">
                                        <img src="{{ $item['image'] ?: 'https://picsum.photos/seed/TH0020-product-fallback/640/420' }}" alt="{{ $item['title'] }}">
                                    </a>

                                    <div class="cart-copy">
                                        <h3><a href="{{ $item['url'] ?? route('site.catalog.search') }}">{{ $item['title'] }}</a></h3>
                                        <div class="cart-meta">{{ __('cart.stock_available', ['count' => $item['stock'] ?? __('cart.stock_unlimited')]) }}</div>
                                        <div class="cart-price-row">
                                            <span class="cart-price">{{ $formatCurrency($item['price'] ?? null) }}</span>
                                            @if (($item['old_price'] ?? null) !== null)
                                                <span class="cart-old-price">{{ $formatCurrency($item['old_price']) }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="cart-actions">
                                        <div class="cart-total">{{ __('cart.item_subtotal', ['amount' => $formatCurrency(((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0)))]) }}</div>
                                        <form method="POST" action="{{ route('site.cart.update', ['productId' => $item['product_id']]) }}" class="quantity-form">
                                            @csrf
                                            <input class="quantity-input" type="number" name="quantity" min="1" max="{{ max(1, (int) ($item['stock'] ?? 99)) }}" value="{{ $item['quantity'] }}">
                                            <button type="submit" class="update-button">@themeT('cart.update', 'C?p nh?t')</button>
                                        </form>
                                        <form method="POST" action="{{ route('site.cart.remove', ['productId' => $item['product_id']]) }}">
                                            @csrf
                                            <button type="submit" class="remove-button">@themeT('cart.remove', 'Xóa kh?i gi?')</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    <aside class="summary-panel">
                        <h2>@themeT('cart.summary_title', 'Tóm t?t don hàng')</h2>
                        <div class="checkout-note">@themeT('cart.summary_note', 'B?n có th? ch?nh s? lu?ng ngay t?i dây, sau dó chuy?n sang bu?c thanh toán d? nh?p thông tin nh?n hàng và xác nh?n don.')</div>
                        <div class="summary-list">
                            <div class="summary-line">
                                <span>@themeT('cart.product_count', 'S? s?n ph?m')</span>
                                <strong>{{ $cartSummary['count'] ?? 0 }}</strong>
                            </div>
                            <div class="summary-line">
                                <span>@themeT('cart.unique_count', 'M?t hàng khác nhau')</span>
                                <strong>{{ $cartSummary['unique_count'] ?? 0 }}</strong>
                            </div>
                            <div class="summary-line">
                                <span>@themeT('cart.subtotal', 'T?m tính')</span>
                                <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                            </div>
                        </div>
                        @if (!empty($customerAuth['is_authenticated']))
                            <a href="{{ route('site.checkout.index') }}" class="primary-button">@themeT('cart.checkout', 'Ti?n hành thanh toán')</a>
                        @else
                            <button type="button" class="primary-button" data-open-auth-modal="login" data-auth-redirect="{{ route('site.checkout.index') }}">@themeT('cart.login_to_checkout', 'Ðang nh?p d? thanh toán')</button>
                        @endif
                        <a href="{{ route('site.home') }}" class="ghost-button">@themeT('cart.continue_shopping', 'Ti?p t?c mua s?m')</a>
                    </aside>
                </section>
            @else
                <div class="empty-state">@themeT('cart.empty', 'Gi? hàng hi?n dang tr?ng. Hãy quay l?i danh m?c ho?c trang s?n ph?m d? thêm s?n ph?m th?i trang vào gi? tru?c khi mua.')</div>
            @endif
        </main>
        @include('theme-th0020::partials.product-search-autocomplete')
        @include('theme-th0020::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
