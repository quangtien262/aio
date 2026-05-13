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
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $confirmedOrder = $order;
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $t('checkout_success.title', 'Gửi yêu cầu thành công') }} | {{ data_get($branding, 'company_name', 'SER0101') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

            :root {
                --navy: #0f172f;
                --night: #08111f;
                --teal: #0f766e;
                --orange: #b45309;
                --line: #d6e2de;
                --muted: #5d7288;
                --bg: #fbfcfb;
                --shadow: 0 22px 56px rgba(10, 30, 47, 0.1);
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                color: #243b53;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.1), transparent 24%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.16), transparent 30%),
                    linear-gradient(180deg, #fbfcfb 0%, #f3f8f5 42%, #fcf8f2 100%);
            }

            .wrap { width: min(980px, calc(100% - 24px)); margin: 0 auto; }
            .card {
                margin: 28px 0;
                border: 1px solid rgba(214, 226, 222, 0.92);
                border-radius: 32px;
                background: rgba(255, 255, 255, 0.94);
                box-shadow: var(--shadow);
                overflow: hidden;
            }
            .hero {
                padding: 20px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
            }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) 280px; gap: 18px; align-items: stretch; }
            .hero-copy { padding: 10px; }
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
            .hero-dossier {
                display: grid;
                gap: 12px;
                padding: 18px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.14);
                backdrop-filter: blur(10px);
            }
            .hero-dossier strong { display: block; font-size: 22px; line-height: 1.15; }
            .hero-dossier p { margin: 0; color: #d9e2ec; font-size: 14px; line-height: 1.75; }
            .hero-dossier-list { display: grid; gap: 10px; }
            .hero-dossier-item { padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.12); }
            .hero-dossier-item b { display: block; font-size: 18px; }
            .hero-dossier-item small { color: #cbd5e1; line-height: 1.5; }
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
            .items { margin-top: 18px; padding: 18px; border-radius: 24px; background: linear-gradient(180deg, #fffaf1, #f5fbf8); }
            .items h2 { margin: 0 0 8px; color: var(--night); font-size: 24px; }
            .items p { margin: 0 0 12px; color: var(--muted); line-height: 1.75; }
            .next-steps { margin-top: 18px; padding: 18px; border-radius: 22px; background: #fff; border: 1px dashed rgba(180, 83, 9, 0.2); }
            .next-steps h2 { margin: 0 0 10px; color: var(--night); font-size: 24px; }
            .next-steps ol { margin: 0; padding-left: 18px; color: var(--muted); }
            .next-steps li + li { margin-top: 8px; }
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
            .cta .primary { background: linear-gradient(135deg, var(--teal), #0f5d56); color: #fff; }
            .cta .secondary { background: #fff; border: 1px solid var(--line); color: var(--navy); }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .line { flex-direction: column; }
                .hero-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="card">
                <div class="hero">
                    <div class="hero-grid">
                        <div class="hero-copy">
                            <span>Success</span>
                            <h1>{{ $t('checkout_success.title', 'Gửi yêu cầu thành công') }}</h1>
                            <p>{{ $t('checkout_success.summary', 'Thông tin của bạn đã được ghi nhận. Điều phối viên sẽ liên hệ để xác nhận lịch trình và báo giá.') }}</p>
                        </div>
                        <aside class="hero-dossier">
                            <strong>Booking handoff</strong>
                            <p>Yêu cầu đã được chuyển sang hàng đợi điều phối. SER0101 giữ bước thành công như một biên nhận bàn giao, không chỉ là trang xác nhận đơn thuần.</p>
                            <div class="hero-dossier-list">
                                <div class="hero-dossier-item">
                                    <b>{{ $confirmedOrder->order_code }}</b>
                                    <small>Mã yêu cầu</small>
                                </div>
                                <div class="hero-dossier-item">
                                    <b>{{ $contactHotline }}</b>
                                    <small>Hotline concierge</small>
                                </div>
                                <div class="hero-dossier-item">
                                    <b>{{ $contactLocation }}</b>
                                    <small>Khu vực phục vụ</small>
                                </div>
                            </div>
                        </aside>
                    </div>
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

                    <div class="next-steps">
                        <h2>Tiếp theo sẽ diễn ra gì?</h2>
                        <ol>
                            <li>Điều phối viên kiểm tra tuyến, số khách và loại xe phù hợp.</li>
                            <li>Đội vận hành gọi xác nhận để chốt báo giá cuối cùng.</li>
                            <li>Thông tin chuyến được chuyển sang bước giữ chỗ hoặc điều xe.</li>
                        </ol>
                    </div>

                    <div class="cta">
                        <a class="primary" href="{{ route('site.home') }}">{{ $t('checkout_success.continue_shopping', 'Xem thêm dịch vụ') }}</a>
                        <a class="secondary" href="tel:{{ preg_replace('/\D+/', '', $contactHotline) }}">{{ $t('checkout_success.hotline_support', 'Gọi hotline') }}</a>
                    </div>
                </div>
            </section>
        </main>
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        <script>
            (() => {
                const cartSyncKey = 'ser0101-cart-sync';
                const cartTabIdKey = 'ser0101-cart-tab-id';
                const syncMessage = @json($t('checkout_success.sync_cart_cleared', 'Một tab khác vừa hoàn tất gửi yêu cầu. Giỏ hàng đã được làm mới.'));
                const tabId = (() => {
                    try {
                        const existing = window.sessionStorage.getItem(cartTabIdKey);

                        if (existing) {
                            return existing;
                        }

                        const created = `tab-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                        window.sessionStorage.setItem(cartTabIdKey, created);
                        return created;
                    } catch (error) {
                        return 'tab-fallback';
                    }
                })();

                try {
                    window.localStorage.setItem(cartSyncKey, JSON.stringify({
                        source: tabId,
                        origin: 'checkout-success',
                        message: syncMessage,
                        summary: {
                            count: 0,
                            subtotal: 0,
                            unique_count: 0,
                            items: [],
                        },
                        timestamp: Date.now(),
                    }));
                } catch (error) {
                    console.error(error);
                }
            })();
        </script>
    </body>
</html>
