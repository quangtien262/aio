@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0, 'items' => [], 'unique_count' => 0];
    $cartItems = $cartSummary['items'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $postLoginRedirect = session('post_login_redirect', route('site.checkout.index'));
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0100', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0100.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $t('cart.title', 'Yêu cầu của bạn') }} | {{ data_get($branding, 'company_name', 'SER0100') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0100::partials.shell-styles')

            :root {
                --navy: #102a43;
                --night: #081a2a;
                --petrol: #1f6f78;
                --orange: #c2410c;
                --line: #d9e2ec;
                --muted: #627d98;
                --bg: #f7fbfd;
                --shadow: 0 22px 56px rgba(16, 42, 67, 0.1);
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                color: #243b53;
                background: radial-gradient(circle at top right, rgba(245, 158, 11, 0.14), transparent 26%), var(--bg);
            }

            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .hero {
                margin: 24px 0 18px;
                padding: 30px;
                border-radius: 30px;
                background: linear-gradient(135deg, rgba(16, 42, 67, 0.98), rgba(31, 111, 120, 0.9));
                color: #fff;
                box-shadow: var(--shadow);
            }
            .eyebrow {
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
            .hero p { max-width: 760px; margin: 0; color: #d9e2ec; line-height: 1.8; }
            .hero-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
            .hero-meta span {
                display: inline-flex;
                padding: 10px 14px;
                border-radius: 999px;
                background: rgba(255, 255, 255, 0.12);
                font-size: 13px;
                font-weight: 700;
            }
            .layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; padding-bottom: 32px; }
            .panel {
                border: 1px solid rgba(217, 226, 236, 0.92);
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.94);
                box-shadow: var(--shadow);
            }
            .panel-body { padding: 24px; }
            .panel h2, .panel h3 { margin: 0 0 12px; color: var(--night); }
            .panel p { margin: 0; color: var(--muted); line-height: 1.75; }
            .stack { display: grid; gap: 16px; }
            .item {
                display: grid;
                grid-template-columns: 132px minmax(0, 1fr) auto;
                gap: 16px;
                padding: 20px 0;
                border-bottom: 1px solid rgba(217, 226, 236, 0.92);
            }
            .item:first-child { padding-top: 0; }
            .item:last-child { border-bottom: 0; padding-bottom: 0; }
            .item img { width: 132px; height: 106px; object-fit: cover; border-radius: 22px; background: #edf2f7; }
            .item h3 { margin: 0 0 10px; font-size: 22px; }
            .item-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
            .item-meta span {
                display: inline-flex;
                padding: 8px 12px;
                border-radius: 999px;
                background: rgba(31, 111, 120, 0.08);
                color: var(--petrol);
                font-size: 12px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.04em;
            }
            .actions { display: grid; justify-items: end; gap: 10px; }
            .actions form { display: flex; gap: 8px; flex-wrap: wrap; }
            .actions input {
                width: 86px;
                min-height: 40px;
                padding: 0 12px;
                border: 1px solid var(--line);
                border-radius: 14px;
                font: inherit;
            }
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 42px;
                padding: 0 16px;
                border-radius: 999px;
                font-weight: 800;
                border: 0;
                cursor: pointer;
            }
            .btn.primary { background: linear-gradient(135deg, var(--orange), #ea580c); color: #fff; }
            .btn.secondary { background: #fff; border: 1px solid var(--line); color: var(--navy); }
            .summary-box {
                padding: 22px;
                border-radius: 24px;
                background: linear-gradient(180deg, rgba(255, 247, 237, 0.92), rgba(247, 243, 236, 0.96));
                border: 1px solid rgba(194, 65, 12, 0.08);
            }
            .summary-line {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                align-items: center;
                padding: 9px 0;
                color: #486581;
            }
            .summary-line strong { color: var(--night); }
            .summary-total {
                margin-top: 10px;
                padding-top: 12px;
                border-top: 1px dashed rgba(194, 65, 12, 0.2);
            }
            .empty {
                padding: 34px;
                border-radius: 22px;
                background: linear-gradient(180deg, #f8fbfd, #eef5f7);
                text-align: center;
            }
            .empty strong { display: block; margin-bottom: 10px; color: var(--night); font-size: 22px; }
            .aside-note {
                margin-top: 14px;
                padding: 16px;
                border-radius: 18px;
                background: rgba(31, 111, 120, 0.08);
                color: var(--muted);
                line-height: 1.75;
            }

            @media (max-width: 980px) {
                .layout { grid-template-columns: 1fr; }
                .actions { justify-items: start; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
                .item { grid-template-columns: 1fr; }
                .item img { width: 100%; height: auto; aspect-ratio: 16 / 10; }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0100::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="hero">
                <span class="eyebrow">{{ $t('cart.eyebrow', 'Danh sách yêu cầu dịch vụ') }}</span>
                <h1>{{ $t('cart.title', 'Yêu cầu của bạn') }}</h1>
                <p>{{ $t('cart.hero_summary', 'Danh sách này giữ lại các gói Sếp đang cân nhắc để điều phối viên chốt lộ trình, loại xe và mức giá phù hợp trước khi gửi yêu cầu đặt xe.') }}</p>
                <div class="hero-meta">
                    <span>{{ str_replace(':count', (string) ($cartSummary['count'] ?? 0), $t('cart.saved_count', ':count mục đang lưu')) }}</span>
                    <span>{{ str_replace(':count', (string) ($cartSummary['unique_count'] ?? 0), $t('cart.unique_count_chip', ':count gói khác nhau')) }}</span>
                    <span>{{ data_get($branding, 'support_hotline', '1900 6760') }}</span>
                </div>
            </section>

            <section class="layout">
                <div class="panel panel-body">
                    <div class="stack">
                        @forelse ($cartItems as $item)
                            <article class="item">
                                <img src="{{ $item['image'] ?: 'https://picsum.photos/seed/ser0100-cart/640/420' }}" alt="{{ $item['title'] }}">

                                <div>
                                    <div class="item-meta">
                                        <span>{{ $t('cart.item_status', 'Đã lưu yêu cầu') }}</span>
                                        <span>{{ $item['sku'] ?: 'SER0100' }}</span>
                                    </div>
                                    <h3><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] }}</a></h3>
                                    <p>{{ str_replace([':quantity', ':price'], [(string) $item['quantity'], $formatCurrency($item['price'] ?? null)], $t('cart.item_meta', 'Số lượng lưu: :quantity · Giá tham khảo: :price')) }}</p>
                                </div>

                                <div class="actions">
                                    <form method="POST" action="{{ route('site.cart.update', ['productId' => $item['product_id']]) }}">
                                        @csrf
                                        <input type="number" name="quantity" min="1" max="99" value="{{ $item['quantity'] }}">
                                        <button type="submit" class="btn secondary">{{ $t('cart.update', 'Cập nhật') }}</button>
                                    </form>

                                    <form method="POST" action="{{ route('site.cart.remove', ['productId' => $item['product_id']]) }}">
                                        @csrf
                                        <button type="submit" class="btn secondary">{{ $t('cart.remove', 'Xóa khỏi danh sách') }}</button>
                                    </form>
                                </div>
                            </article>
                        @empty
                            <div class="empty">
                                <strong>{{ $t('cart.empty', 'Chưa có gói nào trong danh sách yêu cầu.') }}</strong>
                                <p>{{ $t('cart.empty_hint', 'Chọn một dịch vụ từ trang chủ, danh mục hoặc tìm kiếm để lưu lại và tiếp tục gửi yêu cầu.') }}</p>
                                <a class="btn primary" style="margin-top: 16px;" href="{{ route('site.home') }}">{{ $t('cart.back_home_short', 'Về trang chủ') }}</a>
                            </div>
                        @endforelse
                    </div>
                </div>

                <aside class="panel panel-body">
                    <div class="summary-box">
                        <h2>{{ $t('cart.summary_title', 'Tóm tắt yêu cầu') }}</h2>
                        <div class="summary-line">
                            <span>Số lượng mục</span>
                            <strong>{{ $cartSummary['count'] ?? 0 }}</strong>
                        </div>
                        <div class="summary-line">
                            <span>{{ $t('cart.unique_count_label', 'Số gói khác nhau') }}</span>
                            <strong>{{ $cartSummary['unique_count'] ?? 0 }}</strong>
                        </div>
                        <div class="summary-line summary-total">
                            <span>{{ $t('cart.estimated_value_label', 'Giá trị tham khảo') }}</span>
                            <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                        </div>
                    </div>

                    <div class="aside-note">
                        {{ $t('cart.aside_note', 'Hệ thống đang giữ danh sách yêu cầu theo session để Sếp có thể gom nhiều gói dịch vụ trước khi gửi thông tin đặt xe cho điều phối viên.') }}
                    </div>

                    @if (!empty($customerAuth['is_authenticated']))
                        <a class="btn primary" style="width: 100%; margin-top: 18px;" href="{{ route('site.checkout.index') }}">{{ $t('cart.checkout', 'Gửi thông tin đặt xe') }}</a>
                    @else
                        <button type="button" class="btn primary" style="width: 100%; margin-top: 18px;" data-open-auth-modal="login" data-auth-redirect="{{ route('site.checkout.index') }}">{{ $t('cart.login_continue', 'Đăng nhập để tiếp tục') }}</button>
                    @endif

                    <a class="btn secondary" style="width: 100%; margin-top: 10px;" href="{{ route('site.home') }}">{{ $t('cart.back_home', 'Quay về trang chủ') }}</a>
                </aside>
            </section>
        </main>

        @include('theme-ser0100::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
