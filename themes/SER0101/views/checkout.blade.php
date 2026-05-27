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
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $cartSyncUrl = route('site.cart.index');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $t('checkout.title', 'Gửi thông tin đặt xe') }} | {{ data_get($branding, 'company_name', 'SER0101') }}</title>
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

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

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

            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .hero {
                margin: 24px 0 18px;
                padding: 20px;
                border-radius: 34px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
                box-shadow: var(--shadow);
            }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) 300px; gap: 18px; align-items: stretch; }
            .hero-copy { padding: 8px 10px 4px; }
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
                border: 1px solid rgba(214, 226, 222, 0.92);
                border-radius: 18px;
                background: linear-gradient(180deg, #ffffff, #f5faf8);
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
            .btn.primary { background: linear-gradient(135deg, var(--teal), var(--p-deep)); color: #fff; }
            .btn.secondary { background: #fff; border: 1px solid var(--line); color: var(--navy); }
            .summary-box {
                padding: 22px;
                border-radius: 24px;
                background: linear-gradient(180deg, rgba(255, 250, 241, 0.96), rgba(245, 251, 248, 0.98));
                border: 1px solid color-mix(in srgb, var(--a) 8%, transparent);
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
            .next-steps {
                margin-top: 14px;
                padding: 18px;
                border-radius: 20px;
                background: #ffffff;
                border: 1px dashed color-mix(in srgb, var(--a) 20%, transparent);
            }
            .next-steps h3 { margin: 0 0 12px; color: var(--night); font-size: 20px; }
            .next-steps ol { margin: 0; padding-left: 18px; color: var(--muted); }
            .next-steps li + li { margin-top: 8px; }
            .checkout-sync-banner {
                margin-bottom: 16px;
                padding: 14px 16px;
                border-radius: 18px;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.7;
                background: color-mix(in srgb, var(--a) 12%, white);
                color: color-mix(in srgb, var(--a) 62%, black);
            }
            .checkout-sync-banner[hidden] { display: none; }
            .checkout-empty-state {
                margin-top: 18px;
                padding: 20px;
                border-radius: 22px;
                background: linear-gradient(180deg, #f8fbfd, #eef5f7);
                text-align: center;
            }
            .checkout-empty-state[hidden] { display: none; }
            .checkout-empty-state strong { display: block; margin-bottom: 10px; color: var(--night); font-size: 22px; }
            .checkout-empty-state p { margin: 0; color: var(--muted); line-height: 1.8; }
            .checkout-empty-state .btn { margin-top: 16px; }

            @media (max-width: 980px) {
                .layout, .grid, .hero-grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="hero">
                <div class="hero-grid">
                    <div class="hero-copy">
                        <span>{{ $t('checkout.eyebrow', 'Gửi thông tin dịch vụ') }}</span>
                        <h1>{{ $t('checkout.title', 'Gửi thông tin đặt xe') }}</h1>
                        <p>{{ $t('checkout.hero_summary', 'Điền thông tin liên hệ để điều phối viên xác nhận lịch trình, loại xe, điểm đón và báo giá cuối cùng theo gói dịch vụ Sếp đã lưu.') }}</p>
                    </div>
                    <aside class="hero-dossier">
                        <strong>{{ $t('common.booking_dossier_title', 'Hồ sơ đặt xe') }}</strong>
                        <p>Trang checkout của SER0101 được trình bày như bước bàn giao yêu cầu cho concierge desk, không giống giỏ hàng thương mại điện tử thuần.</p>
                        <div class="hero-dossier-list">
                            <div class="hero-dossier-item">
                                <b data-checkout-hero-count>{{ $cartSummary['count'] ?? 0 }}</b>
                                <small>Mục đang chờ xác nhận</small>
                            </div>
                            <div class="hero-dossier-item">
                                <b>{{ $contactHotline }}</b>
                                <small>Hotline điều phối</small>
                            </div>
                            <div class="hero-dossier-item">
                                <b>{{ $contactLocation }}</b>
                                <small>Khu vực phục vụ ưu tiên</small>
                            </div>
                        </div>
                    </aside>
                </div>
            </section>

            <section class="layout">
                <form method="POST" action="{{ route('site.checkout.store') }}" class="panel" data-checkout-form>
                    @csrf

                    <div class="checkout-sync-banner" data-checkout-sync-banner hidden></div>

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
                        <button type="submit" class="btn primary" data-checkout-submit>{{ $t('checkout.submit', 'Gửi yêu cầu') }}</button>
                        <a class="btn secondary" href="{{ route('site.cart.index') }}">{{ $t('checkout.back_to_cart', 'Quay lại danh sách') }}</a>
                    </div>

                    <div class="checkout-empty-state" data-checkout-empty-state hidden>
                        <strong>{{ $t('cart.empty', 'Chưa có gói nào trong danh sách yêu cầu.') }}</strong>
                        <p>{{ $t('checkout.empty_sync_hint', 'Giỏ hàng đã thay đổi ở tab khác. Hãy quay lại danh sách yêu cầu để chọn lại dịch vụ trước khi tiếp tục gửi thông tin.') }}</p>
                        <a class="btn secondary" href="{{ route('site.cart.index') }}">{{ $t('checkout.back_to_cart', 'Quay lại danh sách') }}</a>
                    </div>
                </form>

                <aside class="panel" data-checkout-summary-panel>
                    <div class="summary-box">
                        <h2 class="title" style="margin-bottom: 8px;">{{ $t('checkout.summary_title', 'Thông tin đang gửi') }}</h2>
                        <div data-checkout-summary-items>
                            @foreach ($cartItems as $item)
                                <div class="summary-line">
                                    <span>{{ $item['title'] }} × {{ $item['quantity'] }}</span>
                                    <strong>{{ $formatCurrency(((float) ($item['price'] ?? 0)) * ((int) ($item['quantity'] ?? 0))) }}</strong>
                                </div>
                            @endforeach
                        </div>
                        <div class="summary-line summary-total">
                            <span>Tổng số mục</span>
                            <strong data-checkout-summary-count>{{ $cartSummary['count'] ?? 0 }}</strong>
                        </div>
                        <div class="summary-line">
                            <span>Giá trị tham khảo</span>
                            <strong data-checkout-summary-subtotal>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                        </div>
                    </div>

                    <div class="note">
                        Sau khi gửi, hệ thống sẽ tạo đơn ghi nhận để đội điều phối gọi xác nhận lại trước khi chốt hành trình.
                    </div>

                    <div class="next-steps">
                        <h3>Tiếp theo sẽ diễn ra gì?</h3>
                        <ol>
                            <li>Điều phối viên rà lại tuyến, loại xe và khung giờ.</li>
                            <li>Đội vận hành gọi xác nhận và chốt báo giá cuối cùng.</li>
                            <li>Yêu cầu được chuyển sang bước điều xe hoặc giữ chỗ theo lịch.</li>
                        </ol>
                    </div>
                </aside>
            </section>
        </main>
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        <script>
            (() => {
                const checkoutForm = document.querySelector('[data-checkout-form]');
                const submitButton = document.querySelector('[data-checkout-submit]');
                const heroCountNode = document.querySelector('[data-checkout-hero-count]');
                const summaryItemsNode = document.querySelector('[data-checkout-summary-items]');
                const summaryCountNode = document.querySelector('[data-checkout-summary-count]');
                const summarySubtotalNode = document.querySelector('[data-checkout-summary-subtotal]');
                const bannerNode = document.querySelector('[data-checkout-sync-banner]');
                const emptyStateNode = document.querySelector('[data-checkout-empty-state]');
                const syncUrl = @json($cartSyncUrl);
                const initialSummary = @json($cartSummary);
                const checkoutChangedMessage = @json($t('checkout.sync_changed', 'Giỏ hàng vừa được cập nhật từ tab khác. Thông tin checkout đã được đồng bộ lại.'));
                const checkoutEmptyMessage = @json($t('checkout.sync_empty', 'Giỏ hàng đã trống do thay đổi ở tab khác. Vui lòng quay lại danh sách yêu cầu trước khi tiếp tục.'));
                const cartSyncKey = 'ser0101-cart-sync';
                const cartTabIdKey = 'ser0101-cart-tab-id';
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
                const emptySummaryLabel = @json($t('checkout.empty_summary', 'Hiện không còn gói nào để gửi ở bước checkout này.'));
                let lastSummarySignature = JSON.stringify(initialSummary || {});

                if (!checkoutForm || !summaryItemsNode) {
                    return;
                }

                const formatCurrency = (value) => value === null || value === undefined
                    ? 'Liên hệ'
                    : `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');

                const setBanner = (message = '', isVisible = false) => {
                    if (!bannerNode) {
                        return;
                    }

                    bannerNode.textContent = message;
                    bannerNode.hidden = !isVisible;
                };

                const renderSummary = (summary = {}) => {
                    const items = Array.isArray(summary.items) ? summary.items : [];
                    const count = Number(summary.count || 0);
                    const summarySignature = JSON.stringify(summary || {});
                    const hasChanged = summarySignature !== lastSummarySignature;
                    lastSummarySignature = summarySignature;

                    if (heroCountNode) {
                        heroCountNode.textContent = `${count}`;
                    }

                    if (summaryCountNode) {
                        summaryCountNode.textContent = `${count}`;
                    }

                    if (summarySubtotalNode) {
                        summarySubtotalNode.textContent = formatCurrency(summary.subtotal ?? 0);
                    }

                    if (items.length === 0) {
                        summaryItemsNode.innerHTML = `<div class="summary-line"><span>${escapeHtml(emptySummaryLabel)}</span><strong>0</strong></div>`;
                        checkoutForm.querySelectorAll('input, textarea, button').forEach((node) => {
                            if (node.closest('[data-checkout-empty-state]')) {
                                return;
                            }

                            node.disabled = true;
                        });
                        if (emptyStateNode) {
                            emptyStateNode.hidden = false;
                        }
                        setBanner(checkoutEmptyMessage, hasChanged);
                        return;
                    }

                    summaryItemsNode.innerHTML = items.map((item) => `
                        <div class="summary-line">
                            <span>${escapeHtml(item.title || '')} × ${escapeHtml(item.quantity || 0)}</span>
                            <strong>${escapeHtml(formatCurrency((Number(item.price || 0) * Number(item.quantity || 0))))}</strong>
                        </div>
                    `).join('');

                    checkoutForm.querySelectorAll('input, textarea, button').forEach((node) => {
                        if (node === submitButton) {
                            node.disabled = false;
                            return;
                        }

                        if (node.type === 'hidden') {
                            return;
                        }

                        node.disabled = false;
                    });
                    if (emptyStateNode) {
                        emptyStateNode.hidden = true;
                    }
                    setBanner(checkoutChangedMessage, hasChanged);
                };

                const syncCheckout = async () => {
                    try {
                        const response = await fetch(syncUrl, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw payload;
                        }

                        const summary = payload?.data?.cart_summary || {};
                        renderSummary(summary);
                    } catch (error) {
                        console.error(error);
                    }
                };

                window.addEventListener('focus', syncCheckout);
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        syncCheckout();
                    }
                });

                window.addEventListener('storage', (event) => {
                    if (event.key !== cartSyncKey || !event.newValue) {
                        return;
                    }

                    try {
                        const payload = JSON.parse(event.newValue);

                        if (!payload || payload.source === tabId) {
                            return;
                        }

                        renderSummary(payload.summary || {});
                    } catch (error) {
                        console.error(error);
                    }
                });
            })();
        </script>
    </body>
</html>
