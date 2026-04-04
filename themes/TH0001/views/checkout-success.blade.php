@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@th0001.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội');
    $postLoginRedirect = session('post_login_redirect', route('customer.account'));
    $confirmedOrder = $order;
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Đặt hàng thành công | {{ data_get($branding, 'company_name', 'TH0001') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-green: #65b32e;
                --th-ink: #202124;
                --th-muted: #70757f;
                --th-line: #e6e6e6;
                --th-bg: #f3f3f3;
            }
            * { box-sizing: border-box; }
            body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: var(--th-bg); color: var(--th-ink); }
            a { color: inherit; text-decoration: none; }
            .wrap { width: min(960px, calc(100% - 24px)); margin: 0 auto; }
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
            .nav-links a { text-transform: uppercase; }
            .success-card { margin: 28px 0 36px; background: #fff; border: 1px solid var(--th-line); padding: 28px; }
            .success-card h1 { margin: 0 0 12px; font-size: 32px; text-transform: uppercase; color: #3f6a18; }
            .success-card p { margin: 0 0 18px; color: #555; line-height: 1.7; }
            .order-grid { display: grid; gap: 14px; margin-top: 18px; }
            .order-line { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding-bottom: 12px; border-bottom: 1px solid var(--th-line); }
            .order-line:last-child { border-bottom: 0; padding-bottom: 0; }
            .order-label { color: #777; }
            .order-value { font-weight: 700; text-align: right; }
            .order-items { margin-top: 18px; display: grid; gap: 12px; }
            .order-item { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--th-line); }
            .order-item:last-child { border-bottom: 0; }
            .order-item strong { display: block; margin-bottom: 6px; }
            .cta-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
            .cta-link { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; font-weight: 700; }
            .cta-link.primary { background: var(--th-green); color: #fff; }
            .cta-link.secondary { border: 1px solid #cfd8c1; background: #fff; color: #4e9620; }
            @media (max-width: 720px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .header-main { grid-template-columns: 1fr; }
                .nav-inner { display: block; }
                .nav-links { padding: 10px 0 12px; gap: 16px; }
                .order-line, .order-item { grid-template-columns: 1fr; }
                .order-value { text-align: left; }
            }
        </style>
    </head>
    <body>
        <div class="utility">
            <div class="wrap utility-inner">
                <div class="utility-group">
                    <span>{{ $contactLocation }}</span>
                    <button type="button" class="utility-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? 'Đã đăng ký bản tin' : 'Đăng ký bản tin' }}</button>
                </div>
                <div class="utility-actions">
                    <span>Hotline: {{ $contactHotline }}</span>
                    <span>Email: {{ $contactEmail }}</span>
                    <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Tài khoản</a>
                    <form class="utility-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                        @csrf
                        <button type="submit" class="utility-action">Đăng xuất</button>
                    </form>
                </div>
            </div>
        </div>

        <header class="header">
            <div class="wrap header-main">
                <a class="brand" href="/">
                    <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding, 'company_name', 'TH0001') }}">
                </a>

                <form class="searchbar" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm sản phẩm" aria-label="Tìm kiếm sản phẩm" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                    <button type="submit" aria-label="Tìm kiếm">⌕</button>
                </form>

                <a class="cart-link" href="{{ route('site.cart.index') }}">GIỎ HÀNG ({{ $cartSummary['count'] ?? 0 }})</a>
            </div>
        </header>

        <nav class="nav">
            <div class="wrap nav-inner">
                <div class="nav-category">DANH MỤC</div>
                <div class="nav-links">
                    @foreach ($topMenu as $item)
                        <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}</a>
                    @endforeach
                </div>
            </div>
        </nav>

        <main class="wrap">
            <section class="success-card">
                <h1>Đặt hàng thành công</h1>
                <p>Đơn hàng của bạn đã được ghi nhận. Bộ phận chăm sóc khách hàng sẽ liên hệ theo số điện thoại bạn cung cấp để xác nhận thời gian giao nhận hoặc gửi mã voucher.</p>

                <div class="order-grid">
                    <div class="order-line">
                        <span class="order-label">Mã đơn hàng</span>
                        <span class="order-value">{{ $confirmedOrder->order_code }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Thời gian tạo đơn</span>
                        <span class="order-value">{{ $confirmedOrder->placed_at?->format('Y-m-d H:i:s') }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Khách hàng</span>
                        <span class="order-value">{{ $confirmedOrder->customer_name }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Điện thoại</span>
                        <span class="order-value">{{ $confirmedOrder->customer_phone }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Địa chỉ nhận hàng / nhận mã</span>
                        <span class="order-value">{{ $confirmedOrder->delivery_address }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Phương thức thanh toán</span>
                        <span class="order-value">{{ $confirmedOrder->payment_label }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Tổng thanh toán</span>
                        <span class="order-value">{{ $formatCurrency($confirmedOrder->subtotal) }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">Email xác nhận</span>
                        <span class="order-value">{{ $confirmedOrder->email_sent_at ? 'Da gui' : ($confirmedOrder->email_queued_at ? 'Da dua vao hang doi' : 'Chua xep hang') }}</span>
                    </div>
                    <div class="order-line">
                        <span class="order-label">SMS xác nhận</span>
                        <span class="order-value">Tạm dừng</span>
                    </div>
                </div>

                <div class="order-items">
                    @foreach ($confirmedOrder->items as $item)
                        <div class="order-item">
                            <div>
                                <strong>{{ $item->product_name }}</strong>
                                <span>Số lượng: {{ $item->quantity }}</span>
                            </div>
                            <div class="order-value">{{ $formatCurrency($item->line_total) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="cta-row">
                    <a href="/" class="cta-link primary">Tiếp tục mua sắm</a>
                    <a href="tel:{{ preg_replace('/\D+/', '', $contactHotline) }}" class="cta-link secondary">Gọi hotline hỗ trợ</a>
                </div>
            </section>
        </main>
        @include('theme-th0001::partials.product-search-autocomplete')
        @include('theme-th0001::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
