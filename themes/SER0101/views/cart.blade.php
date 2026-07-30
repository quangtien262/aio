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
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $t('cart.title', 'Yêu cầu của bạn') }} | {{ data_get($branding, 'company_name', 'SER0101') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

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

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

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
                transition: opacity 0.2s ease, transform 0.2s ease;
            }
            .item.is-pending { opacity: 0.55; transform: scale(0.99); }
            .item:first-child { padding-top: 0; }
            .item:last-child { border-bottom: 0; padding-bottom: 0; }
            .item img { width: 132px; height: 106px; object-fit: cover; border-radius: 22px; background: #edf2f7; }
            .item h3 { margin: 0 0 10px; font-size: 22px; }
            .item-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
            .item-meta span {
                display: inline-flex;
                padding: 8px 12px;
                border-radius: 999px;
                background: color-mix(in srgb, var(--petrol) 8%, white);
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
            .btn.primary { background: linear-gradient(135deg, var(--orange), color-mix(in srgb, var(--orange) 70%, black)); color: #fff; }
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
            .cart-inline-toast {
                margin-bottom: 16px;
                padding: 12px 14px;
                border-radius: 18px;
                font-size: 14px;
                font-weight: 700;
                line-height: 1.6;
            }
            .cart-inline-toast[data-state="success"] { background: color-mix(in srgb, var(--p) 10%, white); color: var(--p-deep); }
            .cart-inline-toast[data-state="error"] { background: rgba(185, 28, 28, 0.08); color: #991b1b; }
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
                background: color-mix(in srgb, var(--petrol) 8%, white);
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
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $cartSummary, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="hero">
                <span class="eyebrow">{{ $t('cart.eyebrow', 'Danh sách yêu cầu dịch vụ') }}</span>
                <h1>{{ $t('cart.title', 'Yêu cầu của bạn') }}</h1>
                <p>{{ $t('cart.hero_summary', 'Danh sách này giữ lại các gói Sếp đang cân nhắc để điều phối viên chốt lộ trình, loại xe và mức giá phù hợp trước khi gửi yêu cầu đặt xe.') }}</p>
                <div class="hero-meta">
                    <span data-cart-hero-count>{{ str_replace(':count', (string) ($cartSummary['count'] ?? 0), $t('cart.saved_count', ':count mục đang lưu')) }}</span>
                    <span data-cart-hero-unique>{{ str_replace(':count', (string) ($cartSummary['unique_count'] ?? 0), $t('cart.unique_count_chip', ':count gói khác nhau')) }}</span>
                    <span>{{ data_get($branding, 'support_hotline', '1900 6760') }}</span>
                </div>
            </section>

            <section class="layout">
                <div class="panel panel-body">
                    <div class="cart-inline-toast" data-cart-page-toast hidden aria-live="polite"></div>
                    <div class="stack" data-cart-page-items>
                        @forelse ($cartItems as $item)
                            <article class="item" data-cart-page-item data-product-id="{{ $item['product_id'] }}">
                                <img src="{{ $item['image'] ?: 'https://picsum.photos/seed/ser0101-cart/640/420' }}" alt="{{ $item['title'] }}">

                                <div>
                                    <div class="item-meta">
                                        <span>{{ $t('cart.item_status', 'Đã lưu yêu cầu') }}</span>
                                        <span>{{ $item['sku'] ?: 'SER0101' }}</span>
                                    </div>
                                    <h3><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] }}</a></h3>
                                    <p data-cart-page-meta>{{ str_replace([':quantity', ':price'], [(string) $item['quantity'], $formatCurrency($item['price'] ?? null)], $t('cart.item_meta', 'Số lượng lưu: :quantity · Giá tham khảo: :price')) }}</p>
                                </div>

                                <div class="actions">
                                    <form method="POST" action="{{ route('site.cart.update', ['productId' => $item['product_id']]) }}" data-cart-page-update-form>
                                        @csrf
                                        <input type="number" name="quantity" min="1" max="99" value="{{ $item['quantity'] }}" data-cart-page-quantity-input>
                                        <button type="submit" class="btn secondary">{{ $t('cart.update', 'Cập nhật') }}</button>
                                    </form>

                                    <form method="POST" action="{{ route('site.cart.remove', ['productId' => $item['product_id']]) }}" data-cart-page-remove-form>
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
                            <span>{{ $t('cart.item_count_label', 'Số lượng mục') }}</span>
                            <strong data-cart-summary-count>{{ $cartSummary['count'] ?? 0 }}</strong>
                        </div>
                        <div class="summary-line">
                            <span>{{ $t('cart.unique_count_label', 'Số gói khác nhau') }}</span>
                            <strong data-cart-summary-unique>{{ $cartSummary['unique_count'] ?? 0 }}</strong>
                        </div>
                        <div class="summary-line summary-total">
                            <span>{{ $t('cart.estimated_value_label', 'Giá trị tham khảo') }}</span>
                            <strong data-cart-summary-subtotal>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
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

        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
        <script>
            (() => {
                const itemsNode = document.querySelector('[data-cart-page-items]');
                const toastNode = document.querySelector('[data-cart-page-toast]');
                const heroCountNode = document.querySelector('[data-cart-hero-count]');
                const heroUniqueNode = document.querySelector('[data-cart-hero-unique]');
                const summaryCountNode = document.querySelector('[data-cart-summary-count]');
                const summaryUniqueNode = document.querySelector('[data-cart-summary-unique]');
                const summarySubtotalNode = document.querySelector('[data-cart-summary-subtotal]');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const emptyTitle = @json($t('cart.empty', 'Chưa có gói nào trong danh sách yêu cầu.'));
                const emptyHint = @json($t('cart.empty_hint', 'Chọn một dịch vụ từ trang chủ, danh mục hoặc tìm kiếm để lưu lại và tiếp tục gửi yêu cầu.'));
                const backHomeShort = @json($t('cart.back_home_short', 'Về trang chủ'));
                const itemMetaTemplate = @json($t('cart.item_meta', 'Số lượng lưu: :quantity · Giá tham khảo: :price'));
                const savedCountTemplate = @json($t('cart.saved_count', ':count mục đang lưu'));
                const uniqueCountTemplate = @json($t('cart.unique_count_chip', ':count gói khác nhau'));
                const itemStatus = @json($t('cart.item_status', 'Đã lưu yêu cầu'));
                const updateLabel = @json($t('cart.update', 'Cập nhật'));
                const removeLabel = @json($t('cart.remove', 'Xóa khỏi danh sách'));
                const homeUrl = @json(route('site.home'));
                const cartSyncKey = 'ser0101-cart-sync';
                const cartTabIdKey = 'ser0101-cart-tab-id';
                const externalCheckoutMessage = @json($t('checkout_success.sync_cart_cleared', 'Một tab khác vừa hoàn tất gửi yêu cầu. Giỏ hàng đã được làm mới.'));
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
                let currentSummary = JSON.parse(JSON.stringify(@json($cartSummary) || {}));

                if (!itemsNode || !toastNode) {
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

                const showToast = (message, isError = false) => {
                    toastNode.hidden = false;
                    toastNode.textContent = message;
                    toastNode.dataset.state = isError ? 'error' : 'success';
                    window.clearTimeout(showToast.timeoutId);
                    showToast.timeoutId = window.setTimeout(() => {
                        toastNode.hidden = true;
                    }, 2600);
                };

                const submitJson = async (url, body) => {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body,
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw payload;
                    }

                    return payload;
                };

                const normalizeSummary = (summary = {}) => {
                    const items = Array.isArray(summary.items) ? summary.items.map((item) => ({ ...item })) : [];
                    const count = Number(summary.count ?? items.reduce((total, item) => total + Number(item.quantity || 0), 0));
                    const subtotal = Number(summary.subtotal ?? items.reduce((total, item) => total + (Number(item.price || 0) * Number(item.quantity || 0)), 0));

                    return {
                        ...summary,
                        items,
                        count,
                        subtotal,
                        unique_count: Number(summary.unique_count ?? items.length),
                    };
                };

                const patchQuantitySummary = (summary, productId, quantity) => {
                    const nextSummary = normalizeSummary(summary);
                    const productKey = String(productId);
                    const nextItems = nextSummary.items.map((item) => ({ ...item }));
                    const itemIndex = nextItems.findIndex((item) => String(item.product_id) === productKey);

                    if (itemIndex < 0) {
                        return nextSummary;
                    }

                    nextItems[itemIndex].quantity = Math.max(1, Number(quantity || 1));

                    return normalizeSummary({
                        ...nextSummary,
                        items: nextItems,
                    });
                };

                const removeItemSummary = (summary, productId) => {
                    const nextSummary = normalizeSummary(summary);
                    const productKey = String(productId);

                    return normalizeSummary({
                        ...nextSummary,
                        items: nextSummary.items.filter((item) => String(item.product_id) !== productKey),
                    });
                };

                const setItemPending = (productId, nextState) => {
                    const item = itemsNode.querySelector(`[data-cart-page-item][data-product-id="${CSS.escape(String(productId))}"]`);

                    if (!item) {
                        return;
                    }

                    item.classList.toggle('is-pending', nextState);
                    item.querySelectorAll('button, input').forEach((node) => {
                        node.disabled = nextState;
                    });
                };

                const broadcastCartSync = ({ summary = currentSummary, message = '', origin = 'cart-updated', item = null } = {}) => {
                    try {
                        window.localStorage.setItem(cartSyncKey, JSON.stringify({
                            source: tabId,
                            origin,
                            message,
                            item,
                            summary: normalizeSummary(summary),
                            timestamp: Date.now(),
                        }));
                    } catch (error) {
                        console.error(error);
                    }
                };

                const renderSummary = (summary = {}) => {
                    currentSummary = normalizeSummary(summary);
                    const count = Number(currentSummary.count || 0);
                    const uniqueCount = Number(currentSummary.unique_count || 0);
                    const subtotal = currentSummary.subtotal ?? 0;

                    if (heroCountNode) {
                        heroCountNode.textContent = savedCountTemplate.replace(':count', `${count}`);
                    }

                    if (heroUniqueNode) {
                        heroUniqueNode.textContent = uniqueCountTemplate.replace(':count', `${uniqueCount}`);
                    }

                    if (summaryCountNode) {
                        summaryCountNode.textContent = `${count}`;
                    }

                    if (summaryUniqueNode) {
                        summaryUniqueNode.textContent = `${uniqueCount}`;
                    }

                    if (summarySubtotalNode) {
                        summarySubtotalNode.textContent = formatCurrency(subtotal);
                    }
                };

                const renderItems = (summary = {}) => {
                    currentSummary = normalizeSummary(summary);
                    const items = Array.isArray(currentSummary.items) ? currentSummary.items : [];

                    if (items.length === 0) {
                        itemsNode.innerHTML = `
                            <div class="empty">
                                <strong>${escapeHtml(emptyTitle)}</strong>
                                <p>${escapeHtml(emptyHint)}</p>
                                <a class="btn primary" style="margin-top: 16px;" href="${escapeHtml(homeUrl)}">${escapeHtml(backHomeShort)}</a>
                            </div>
                        `;
                        return;
                    }

                    itemsNode.innerHTML = items.map((item) => {
                        const meta = itemMetaTemplate
                            .replace(':quantity', `${Number(item.quantity || 0)}`)
                            .replace(':price', formatCurrency(item.price ?? null));

                        return `
                            <article class="item" data-cart-page-item data-product-id="${escapeHtml(item.product_id || '')}">
                                <img src="${escapeHtml(item.image || 'https://picsum.photos/seed/ser0101-cart/640/420')}" alt="${escapeHtml(item.title || '')}">
                                <div>
                                    <div class="item-meta">
                                        <span>${escapeHtml(itemStatus)}</span>
                                        <span>${escapeHtml(item.sku || 'SER0101')}</span>
                                    </div>
                                    <h3><a href="${escapeHtml(item.url || '#')}">${escapeHtml(item.title || '')}</a></h3>
                                    <p data-cart-page-meta>${escapeHtml(meta)}</p>
                                </div>
                                <div class="actions">
                                    <form method="POST" action="${escapeHtml(`{{ route('site.cart.update', ['productId' => '__PRODUCT__']) }}`.replace('__PRODUCT__', String(item.product_id || '')))}" data-cart-page-update-form>
                                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                        <input type="number" name="quantity" min="1" max="99" value="${escapeHtml(item.quantity || 1)}" data-cart-page-quantity-input>
                                        <button type="submit" class="btn secondary">${escapeHtml(updateLabel)}</button>
                                    </form>
                                    <form method="POST" action="${escapeHtml(`{{ route('site.cart.remove', ['productId' => '__PRODUCT__']) }}`.replace('__PRODUCT__', String(item.product_id || '')))}" data-cart-page-remove-form>
                                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                        <button type="submit" class="btn secondary">${escapeHtml(removeLabel)}</button>
                                    </form>
                                </div>
                            </article>
                        `;
                    }).join('');
                };

                itemsNode.addEventListener('submit', async (event) => {
                    const updateForm = event.target.closest('[data-cart-page-update-form]');
                    const removeForm = event.target.closest('[data-cart-page-remove-form]');

                    if (!updateForm && !removeForm) {
                        return;
                    }

                    event.preventDefault();

                    const form = updateForm || removeForm;
                    const itemNode = form.closest('[data-cart-page-item]');
                    const submitButton = form.querySelector('button[type="submit"]');
                    const originalLabel = submitButton?.textContent || '';
                    const productId = itemNode?.dataset.productId;
                    const quantityInput = updateForm?.querySelector('[data-cart-page-quantity-input]');
                    const optimisticQuantity = Number(quantityInput?.value || 1);
                    const previousSummary = currentSummary;
                    const isUpdateAction = Boolean(updateForm && productId);

                    if (isUpdateAction) {
                        const optimisticSummary = patchQuantitySummary(currentSummary, productId, optimisticQuantity);
                        renderItems(optimisticSummary);
                        renderSummary(optimisticSummary);
                        setItemPending(productId, true);
                    } else if (itemNode) {
                        const optimisticSummary = removeItemSummary(currentSummary, productId);
                        renderItems(optimisticSummary);
                        renderSummary(optimisticSummary);
                    }

                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = '...';
                    }

                    try {
                        const payload = await submitJson(form.action, new FormData(form));
                        const summary = normalizeSummary(payload?.data?.cart_summary || {});
                        renderItems(summary);
                        renderSummary(summary);
                        document.dispatchEvent(new CustomEvent('ser:cart-updated', { detail: { ...(payload?.data || {}), cart_summary: summary } }));
                        document.dispatchEvent(new CustomEvent('ser:cart-toast', { detail: { message: payload?.message || 'Đã cập nhật giỏ hàng.', isError: false } }));
                        broadcastCartSync({ summary, message: payload?.message || 'Đã cập nhật giỏ hàng.', origin: isUpdateAction ? 'cart-update' : 'cart-remove', item: payload?.data?.item || null });
                        showToast(payload?.message || 'Đã cập nhật giỏ hàng.');
                    } catch (error) {
                        renderItems(previousSummary);
                        renderSummary(previousSummary);
                        document.dispatchEvent(new CustomEvent('ser:cart-toast', { detail: { message: error?.message || 'Không thể cập nhật giỏ hàng lúc này.', isError: true } }));
                        showToast(error?.message || 'Không thể cập nhật giỏ hàng lúc này.', true);
                    } finally {
                        if (!isUpdateAction && itemNode && itemNode.isConnected) {
                            itemNode.classList.remove('is-pending');
                            itemNode.querySelectorAll('button, input').forEach((node) => {
                                node.disabled = false;
                            });
                        }

                        if (submitButton && form.isConnected) {
                            submitButton.disabled = false;
                            submitButton.textContent = originalLabel;
                        }
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

                        const summary = normalizeSummary(payload.summary || {});
                        renderItems(summary);
                        renderSummary(summary);

                        if (payload.origin === 'checkout-success') {
                            showToast(payload.message || externalCheckoutMessage);
                        }
                    } catch (error) {
                        console.error(error);
                    }
                });

                renderSummary(currentSummary);
            })();
        </script>
    </body>
</html>
