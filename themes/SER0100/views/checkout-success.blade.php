@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0, 'items' => []];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0100.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $confirmedOrder = $order;
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0100', app()->getLocale(), $key, $default);
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $t('checkout_success.title', 'Gửi yêu cầu thành công') }} | {{ data_get($branding, 'company_name', 'SER0100') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0100::partials.shell-styles')

            :root {
                --navy: #102a43;
                --night: #081a2a;
                --orange: #c2410c;
                --line: #d9e2ec;
                --muted: #627d98;
                --bg: #f7fbfd;
                --shadow: 0 22px 56px rgba(16, 42, 67, 0.1);
            }

            @include('theme-ser0100::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                color: #243b53;
                background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.14), transparent 28%), var(--bg);
            }

            .wrap { width: min(980px, calc(100% - 24px)); margin: 0 auto; }
            .card {
                margin: 28px 0;
                border: 1px solid rgba(217, 226, 236, 0.92);
                border-radius: 30px;
                background: rgba(255, 255, 255, 0.94);
                box-shadow: var(--shadow);
                overflow: hidden;
            }
            .hero {
                padding: 30px;
                background: linear-gradient(135deg, rgba(16, 42, 67, 0.98), rgba(31, 111, 120, 0.9));
                color: #fff;
            }
            .hero span {
                display: inline-flex;
                padding: 8px 12px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.12);
                font-size: 12px;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .hero h1 { margin: 16px 0 10px; font-size: clamp(34px, 4.8vw, 52px); line-height: 1.02; }
            .hero p { max-width: 760px; margin: 0; color: #d9e2ec; line-height: 1.8; }
            .body { padding: 26px; }
            .line {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                padding: 12px 0;
                border-bottom: 1px solid rgba(217, 226, 236, 0.92);
            }
            .line:last-child { border-bottom: 0; }
            .line strong { color: var(--night); }
            .items { margin-top: 18px; padding: 18px; border-radius: 24px; background: linear-gradient(180deg, #f8fbfd, #eef5f7); }
            .items h2 { margin: 0 0 8px; color: var(--night); font-size: 24px; }
            .items p { margin: 0 0 12px; color: var(--muted); line-height: 1.75; }
            .cta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
            .cta a {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 18px;
                border-radius: 999px;
                font-weight: 800;
                text-decoration: none;
            }
            .cta .primary { background: linear-gradient(135deg, var(--orange), var(--o-deep)); color: #fff; }
            .cta .secondary { background: #fff; border: 1px solid var(--line); color: var(--navy); }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .line { flex-direction: column; }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0100::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="card">
                <div class="hero">
                    <span>Success</span>
                    <h1>{{ $t('checkout_success.title', 'Gửi yêu cầu thành công') }}</h1>
                    <p>{{ $t('checkout_success.summary', 'Thông tin của bạn đã được ghi nhận. Điều phối viên sẽ liên hệ để xác nhận lịch trình và báo giá.') }}</p>
                </div>

                <div class="body">
                    <div class="line">
                        <span>Mã đơn</span>
                        <strong>{{ $confirmedOrder->order_code }}</strong>
                    </div>
                    <div class="line">
                        <span>Thời gian tạo</span>
                        <strong>{{ $confirmedOrder->placed_at?->format('d/m/Y H:i:s') }}</strong>
                    </div>
                    <div class="line">
                        <span>Khách hàng</span>
                        <strong>{{ $confirmedOrder->customer_name }}</strong>
                    </div>
                    <div class="line">
                        <span>Số điện thoại</span>
                        <strong>{{ $confirmedOrder->customer_phone }}</strong>
                    </div>
                    <div class="line">
                        <span>Địa chỉ / điểm đón</span>
                        <strong>{{ $confirmedOrder->delivery_address }}</strong>
                    </div>
                    <div class="line">
                        <span>Tổng tham khảo</span>
                        <strong>{{ $formatCurrency($confirmedOrder->subtotal) }}</strong>
                    </div>

                    <div class="items">
                        <h2>Chi tiết yêu cầu</h2>
                        <p>Danh sách dịch vụ đã được ghi nhận trong đơn để đội điều phối đối chiếu khi gọi xác nhận.</p>
                        @foreach ($confirmedOrder->items as $item)
                            <div class="line">
                                <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                                <strong>{{ $formatCurrency($item->line_total) }}</strong>
                            </div>
                        @endforeach
                    </div>

                    <div class="cta">
                        <a class="primary" href="{{ route('site.home') }}">{{ $t('checkout_success.continue_shopping', 'Xem thêm dịch vụ') }}</a>
                        <a class="secondary" href="tel:{{ preg_replace('/\D+/', '', $contactHotline) }}">{{ $t('checkout_success.hotline_support', 'Gọi hotline') }}</a>
                    </div>
                </div>
            </section>
        </main>
        @include('theme-ser0100::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
