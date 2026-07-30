@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0, 'items' => []];
    $cartItems = $cartSummary['items'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $form = $checkoutForm ?? [];
    $paymentMethods = $paymentMethods ?? [];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0100', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0100.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $t('checkout.title', 'Gửi thông tin đặt xe') }} | {{ data_get($branding, 'company_name', 'SER0100') }}</title>
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

            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .hero {
                margin: 24px 0 18px;
                padding: 30px;
                border-radius: 30px;
                background: linear-gradient(135deg, rgba(16, 42, 67, 0.98), rgba(31, 111, 120, 0.9));
                color: #fff;
                box-shadow: var(--shadow);
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
            .hero h1 { margin: 16px 0 10px; font-size: clamp(34px, 4.8vw, 54px); line-height: 1.02; }
            .hero p { max-width: 780px; margin: 0; color: #d9e2ec; line-height: 1.8; }
            .layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; padding-bottom: 32px; }
            .panel {
                border: 1px solid rgba(217, 226, 236, 0.92);
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.94);
                box-shadow: var(--shadow);
                padding: 24px;
            }
            .title { margin: 0 0 16px; color: var(--night); }
            .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .field { display: grid; gap: 8px; }
            .field.full { grid-column: 1 / -1; }
            .field label { font-size: 14px; font-weight: 700; color: #334e68; }
            .field input,
            .field textarea {
                min-height: 48px;
                padding: 12px 14px;
                border: 1px solid var(--line);
                border-radius: 16px;
                font: inherit;
            }
            .field textarea { min-height: 126px; resize: vertical; }
            .payment { display: grid; gap: 12px; margin-top: 12px; }
            .payment label {
                display: grid;
                grid-template-columns: 18px 1fr;
                gap: 12px;
                padding: 16px;
                border: 1px solid rgba(217, 226, 236, 0.92);
                border-radius: 18px;
                background: linear-gradient(180deg, #f8fbfd, #eef5f7);
            }
            .payment strong { color: var(--night); }
            .payment div { color: var(--muted); line-height: 1.75; }
            .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 18px;
                border-radius: 999px;
                font-weight: 800;
                border: 0;
                cursor: pointer;
                text-decoration: none;
            }
            .btn.primary { background: linear-gradient(135deg, var(--orange), var(--o-deep)); color: #fff; }
            .btn.secondary { background: #fff; border: 1px solid var(--line); color: var(--navy); }
            .summary-box {
                padding: 22px;
                border-radius: 24px;
                background: linear-gradient(180deg, rgba(255, 247, 237, 0.92), rgba(247, 243, 236, 0.96));
                border: 1px solid rgba(194, 65, 12, 0.08);
            }
            .summary-line { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 9px 0; color: #486581; }
            .summary-line strong { color: var(--night); }
            .summary-total { margin-top: 10px; padding-top: 12px; border-top: 1px dashed rgba(194, 65, 12, 0.2); }
            .note {
                margin-top: 14px;
                padding: 16px;
                border-radius: 18px;
                background: color-mix(in srgb, var(--p) 8%, white);
                color: var(--muted);
                line-height: 1.75;
            }

            @media (max-width: 980px) {
                .layout, .grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
            }
        </style>
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0100::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="hero">
                <span>{{ $t('checkout.eyebrow', 'Gửi thông tin dịch vụ') }}</span>
                <h1>{{ $t('checkout.title', 'Gửi thông tin đặt xe') }}</h1>
                <p>{{ $t('checkout.hero_summary', 'Điền thông tin liên hệ để điều phối viên xác nhận lịch trình, loại xe, điểm đón và báo giá cuối cùng theo gói dịch vụ Sếp đã lưu.') }}</p>
            </section>

            <section class="layout">
                <form method="POST" action="{{ route('site.checkout.store') }}" class="panel">
                    @csrf

                    <h2 class="title">{{ $t('checkout.shipping_info', 'Thông tin liên hệ') }}</h2>

                    <div class="grid">
                        <div class="field">
                            <label for="customer_name">Họ và tên</label>
                            <input id="customer_name" name="customer_name" value="{{ $form['customer_name'] ?? '' }}" required>
                        </div>

                        <div class="field">
                            <label for="customer_phone">Số điện thoại</label>
                            <input id="customer_phone" name="customer_phone" value="{{ $form['customer_phone'] ?? '' }}" required>
                        </div>

                        <div class="field full">
                            <label for="customer_email">Email</label>
                            <input id="customer_email" type="email" name="customer_email" value="{{ $form['customer_email'] ?? '' }}">
                        </div>

                        <div class="field full">
                            <label for="delivery_address">Địa chỉ / ghi chú điểm đón</label>
                            <textarea id="delivery_address" name="delivery_address" required>{{ $form['delivery_address'] ?? '' }}</textarea>
                        </div>

                        <div class="field full">
                            <label for="note">Ghi chú thêm</label>
                            <textarea id="note" name="note">{{ $form['note'] ?? '' }}</textarea>
                        </div>
                    </div>

                    <h2 class="title" style="margin-top: 24px;">{{ $t('checkout.payment_method', 'Hình thức xác nhận') }}</h2>

                    <div class="payment">
                        @foreach ($paymentMethods as $value => $paymentMethod)
                            <label>
                                <input type="radio" name="payment_method" value="{{ $value }}" {{ ($form['payment_method'] ?? 'cod') === $value ? 'checked' : '' }}>
                                <div>
                                    <strong>{{ $paymentMethod['label'] }}</strong>
                                    <div>{{ $paymentMethod['hint'] }}</div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn primary">{{ $t('checkout.submit', 'Gửi yêu cầu') }}</button>
                        <a class="btn secondary" href="{{ route('site.cart.index') }}">{{ $t('checkout.back_to_cart', 'Quay lại danh sách') }}</a>
                    </div>
                </form>

                <aside class="panel">
                    <div class="summary-box">
                        <h2 class="title" style="margin-bottom: 8px;">{{ $t('checkout.summary_title', 'Thông tin đang gửi') }}</h2>
                        @foreach ($cartItems as $item)
                            <div class="summary-line">
                                <span>{{ $item['title'] }} × {{ $item['quantity'] }}</span>
                                <strong>{{ $formatCurrency(((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0))) }}</strong>
                            </div>
                        @endforeach
                        <div class="summary-line summary-total">
                            <span>Tổng số mục</span>
                            <strong>{{ $cartSummary['count'] ?? 0 }}</strong>
                        </div>
                        <div class="summary-line">
                            <span>Giá trị tham khảo</span>
                            <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                        </div>
                    </div>

                    <div class="note">
                        Sau khi gửi, hệ thống sẽ tạo đơn ghi nhận để đội điều phối gọi xác nhận lại trước khi chốt hành trình.
                    </div>
                </aside>
            </section>
        </main>
        @include('theme-ser0100::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
