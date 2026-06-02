@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0, 'items' => []];
    $cartItems = $cartSummary['items'] ?? [];
    $form = $checkoutForm ?? [];
    $paymentMethods = $paymentMethods ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@TH0003.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội');
    $companyTitle = data_get($siteProfile, 'branding.company_name', data_get($branding, 'company_name', ''));
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Thanh toán{{ $companyTitle ? ' | '.$companyTitle : '' }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-th0003::partials.palette-tokens', ['branding' => $branding])
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--th-bg); color: var(--th-ink); }
            a { color: inherit; text-decoration: none; }
            button, input, textarea { font: inherit; }
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
            .nav-category { background: rgba(0, 0, 0, .16); padding: 12px 18px; min-width: 210px; }
            .nav-links { display: flex; justify-content: flex-start; gap: 28px; flex-wrap: wrap; }
            .nav-links a { text-transform: uppercase; transition: color .18s ease; }
            .nav-links a:hover { color: #fff2bf; }
            .breadcrumb { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; padding: 18px 0; font-size: 13px; color: var(--th-muted); }
            .error-banner { margin: 0 0 18px; padding: 14px 16px; border: 1px solid #f2c5c5; background: #fff2f2; color: #9a2b2b; font-size: 14px; }
            .checkout-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; margin-bottom: 28px; }
            .checkout-panel, .summary-panel { background: #fff; border: 1px solid var(--th-line); padding: 20px; }
            .checkout-title { margin: 0 0 16px; font-size: 30px; text-transform: uppercase; color: #444; }
            .section-title { margin: 0 0 14px; font-size: 18px; text-transform: uppercase; color: #444; }
            .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
            .field { display: grid; gap: 8px; margin-bottom: 14px; }
            .field.full { grid-column: 1 / -1; }
            .field label { font-size: 14px; color: #555; font-weight: 700; }
            .field input, .field textarea { width: 100%; min-height: 44px; border: 1px solid var(--th-line); padding: 10px 12px; background: #fff; }
            .field textarea { min-height: 110px; resize: vertical; }
            .payment-grid { display: grid; gap: 12px; margin-top: 4px; }
            .payment-option { display: grid; grid-template-columns: 18px 1fr; gap: 12px; padding: 14px; border: 1px solid var(--th-line); background: #fafafa; }
            .payment-option strong { display: block; margin-bottom: 4px; }
            .payment-option span { color: #666; font-size: 14px; line-height: 1.6; }
            .submit-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 22px; border: 0; background: var(--th-green); color: #fff; font-size: 15px; font-weight: 700; cursor: pointer; }
            .summary-panel h2 { margin: 0 0 16px; font-size: 22px; text-transform: uppercase; color: #444; }
            .summary-items { display: grid; gap: 14px; margin-bottom: 16px; }
            .summary-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--th-line); }
            .summary-item:last-child { border-bottom: 0; padding-bottom: 0; }
            .summary-item strong { display: block; margin-bottom: 6px; font-size: 15px; }
            .summary-item span { color: #777; font-size: 13px; }
            .summary-price { color: var(--th-red); font-weight: 800; }
            .summary-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; color: #555; }
            .summary-line strong { color: #1f1f1f; font-size: 16px; }
            @media (max-width: 1080px) {
                .checkout-layout, .header-main { grid-template-columns: 1fr; }
            }
            @media (max-width: 720px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .nav-inner { display: block; }
                .nav-links { padding: 10px 0 12px; gap: 16px; }
                .form-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="utility">
            <div class="wrap utility-inner">
                <div class="utility-group">
                    <span>{{ $contactLocation }}</span>
                    <button type="button" class="utility-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? __('common.newsletter_subscribed') : __('common.newsletter_subscribe') }}</button>
                </div>
                <div class="utility-actions">
                    <span>@themeT('common.hotline_label', 'Hotline'): {{ $contactHotline }}</span>
                    <span>@themeT('common.email_label', 'Email'): {{ $contactEmail }}</span>
                    <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">@themeT('common.account', 'Tài khoản')</a>
                    <form class="utility-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                        @csrf
                        <button type="submit" class="utility-action">@themeT('common.logout', 'Đăng xuất')</button>
                    </form>
                </div>
            </div>
        </div>

        <header class="header">
            <div class="wrap header-main">
                <a class="brand" href="{{ route('site.home') }}">
                    <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ $companyTitle ?: '' }}">
                </a>

                <form class="searchbar" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="@themeT('common.search_placeholder', 'Tìm kiếm sản phẩm / khuyến mãi')" aria-label="@themeT('common.search_aria', 'Tìm kiếm sản phẩm')" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                    <button type="submit" aria-label="@themeT('common.search_button', 'Tìm')">⌕</button>
                </form>

                <a class="cart-link" href="{{ route('site.cart.index') }}">@themeT('common.cart_label', 'GIỎ HÀNG') ({{ $cartSummary['count'] ?? 0 }})</a>
            </div>
        </header>

        <nav class="nav">
            <div class="wrap nav-inner">
                <div class="nav-category">@themeT('common.categories', 'DANH MỤC')</div>
                <div class="nav-links">
                    @foreach ($topMenu as $item)
                        <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? __('common.menu') }}</a>
                    @endforeach
                </div>
            </div>
        </nav>

        <main class="wrap">
            <div class="breadcrumb">
                <a href="{{ route('site.home') }}">@themeT('common.home', 'Trang chủ')</a>
                <span>›</span>
                <a href="{{ route('site.cart.index') }}">@themeT('cart.breadcrumb', 'Giỏ hàng')</a>
                <span>›</span>
                <span>@themeT('checkout.breadcrumb', 'Thanh toán')</span>
            </div>

            @if ($errors->any())
                <div class="error-banner">{{ $errors->first() }}</div>
            @endif

            <section class="checkout-layout">
                <form method="POST" action="{{ route('site.checkout.store') }}" class="checkout-panel">
                    @csrf
                    <h1 class="checkout-title">@themeT('checkout.title', 'Thanh toán đơn hàng')</h1>

                    <h2 class="section-title">@themeT('checkout.shipping_info', 'Thông tin nhận hàng')</h2>
                    <div class="form-grid">
                        <div class="field">
                            <label for="customer_name">@themeT('checkout.name', 'Họ và tên')</label>
                            <input id="customer_name" name="customer_name" value="{{ $form['customer_name'] ?? '' }}" required>
                        </div>
                        <div class="field">
                            <label for="customer_phone">@themeT('checkout.phone', 'Số điện thoại')</label>
                            <input id="customer_phone" name="customer_phone" value="{{ $form['customer_phone'] ?? '' }}" required>
                        </div>
                        <div class="field full">
                            <label for="customer_email">@themeT('modal.email', 'Email')</label>
                            <input id="customer_email" type="email" name="customer_email" value="{{ $form['customer_email'] ?? '' }}">
                        </div>
                        <div class="field full">
                            <label for="delivery_address">@themeT('checkout.address', 'Địa chỉ nhận hàng / nhận mã')</label>
                            <textarea id="delivery_address" name="delivery_address" required>{{ $form['delivery_address'] ?? '' }}</textarea>
                        </div>
                        <div class="field full">
                            <label for="note">@themeT('checkout.note', 'Ghi chú cho đơn hàng')</label>
                            <textarea id="note" name="note">{{ $form['note'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <h2 class="section-title">@themeT('checkout.payment_method', 'Phương thức thanh toán')</h2>
                    <div class="payment-grid">
                        @foreach ($paymentMethods as $value => $paymentMethod)
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="{{ $value }}" {{ ($form['payment_method'] ?? 'cod') === $value ? 'checked' : '' }}>
                                <div>
                                    <strong>{{ $paymentMethod['label'] }}</strong>
                                    <span>{{ $paymentMethod['hint'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div style="margin-top: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="submit" class="submit-button">@themeT('checkout.submit', 'Xác nhận đặt hàng')</button>
                        <a href="{{ route('site.cart.index') }}" style="display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 22px;border:1px solid #b9d99c;color:#4e9620;font-weight:700;">@themeT('checkout.back_to_cart', 'Quay lại giỏ hàng')</a>
                    </div>
                </form>

                <aside class="summary-panel">
                    <h2>@themeT('checkout.summary_title', 'Đơn hàng của bạn')</h2>
                    <div class="summary-items">
                        @foreach ($cartItems as $item)
                            <div class="summary-item">
                                <div>
                                    <strong>{{ $item['title'] }}</strong>
                                    <span>{{ __('checkout.quantity', ['count' => $item['quantity']]) }}</span>
                                </div>
                                <div class="summary-price">{{ $formatCurrency(((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0))) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="summary-line">
                        <span>@themeT('checkout.total_products', 'Tổng số sản phẩm')</span>
                        <strong>{{ $cartSummary['count'] ?? 0 }}</strong>
                    </div>
                    <div class="summary-line">
                        <span>@themeT('cart.subtotal', 'Tạm tính')</span>
                        <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                    </div>
                </aside>
            </section>
        </main>
        @include('theme-th0003::partials.product-search-autocomplete')
        @include('theme-th0003::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
