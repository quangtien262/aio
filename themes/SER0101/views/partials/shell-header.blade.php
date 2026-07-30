@php
    $branding = $branding ?? [];
    $topMenu = $topMenu ?? [];
    $productMenu = $productMenu ?? [];
    $cartSummary = $cartSummary ?? ['count' => 0, 'subtotal' => 0];
    $customerAuth = $customerAuth ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $newsletterState ?? ['is_subscribed' => false];
    $presetSwitcher = $presetSwitcher ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $cartSummary = array_merge(['count' => 0, 'subtotal' => 0, 'items' => [], 'unique_count' => 0], $cartSummary ?? []);
    $contactHotline = $contactHotline ?? data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = $contactEmail ?? data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = $contactLocation ?? data_get($branding, 'support_location', 'Hồ Chí Minh');
    $activePreset = collect($presetSwitcher['options'] ?? [])->firstWhere('is_active', true);
    $brandSlogan = is_array($activePreset)
        ? (string) ($activePreset['description'] ?? '')
        : '';

    if ($brandSlogan === '') {
        $brandSlogan = data_get($branding, 'slogan', 'Premium booking cho dịch vụ đặt xe, shuttle và điều phối tuyến');
    }

    $isServiceBrand = filled(data_get($branding, 'demo_preset_key')) || str_contains(mb_strtolower((string) $brandSlogan), 'nhà xe');
    $topMenu = collect($topMenu)
        ->map(function (array $item) use ($isServiceBrand): array {
            if (! $isServiceBrand || !empty($item['children'])) {
                return $item;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $url = (string) ($item['url'] ?? '#');

            return match ($label) {
                'Cẩm nang' => [
                    ...$item,
                    'children' => [
                        ['label' => 'Kinh nghiệm đặt xe', 'summary' => 'Checklist, kinh nghiệm và nội dung SEO cho khách đặt tuyến.', 'url' => $url, 'target' => '_self'],
                        ['label' => 'Lịch trình tối ưu', 'summary' => 'Gợi ý cách chọn route, loại xe và thời gian khởi hành phù hợp.', 'url' => $url, 'target' => '_self'],
                    ],
                ],
                'Giới thiệu' => [
                    ...$item,
                    'children' => [
                        ['label' => 'Về nhà xe', 'summary' => 'Tổng quan thương hiệu, năng lực điều phối và đội xe hiện có.', 'url' => $url, 'target' => '_self'],
                        ['label' => 'Quy trình phục vụ', 'summary' => 'Cách tiếp nhận lịch trình, xác nhận chuyến và chăm sóc khách hàng.', 'url' => $url, 'target' => '_self'],
                    ],
                ],
                'Báo giá' => [
                    ...$item,
                    'children' => [
                        ['label' => 'Gửi yêu cầu báo giá', 'summary' => 'Điền nhu cầu tuyến, số khách và khung giờ để nhận tư vấn nhanh.', 'url' => $url, 'target' => '_self'],
                        ['label' => 'Liên hệ điều phối', 'summary' => 'Xem thông tin liên hệ và đầu mối hỗ trợ cho từng loại nhu cầu.', 'url' => $url, 'target' => '_self'],
                    ],
                ],
                default => $item,
            };
        })
        ->values()
        ->all();
@endphp

@if (session('cart_success'))
    <div class="ser-shell-flash">
        <div class="wrap">{{ session('cart_success') }}</div>
    </div>
@endif

<div class="ser-shell-topbar">
    <div class="wrap ser-shell-topbar-inner">
        <div class="ser-shell-inline">
            <span class="ser-shell-status">Concierge 24/7</span>
            <span>{{ $contactLocation }}</span>
            <span>{{ $t('common.hotline_label', 'Hotline') }}: {{ $contactHotline }}</span>
            <span>{{ $t('common.email_label', 'Email') }}: {{ $contactEmail }}</span>
        </div>
        <div class="ser-shell-inline">
            <button type="button" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? $t('common.newsletter_subscribed', 'Đã đăng ký bản tin') : $t('common.newsletter_subscribe', 'Đăng ký bản tin') }}</button>
            @if (!empty($customerAuth['is_authenticated']))
                <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">{{ $t('common.account', 'Tài khoản') }}</a>
            @else
                <button type="button" data-open-auth-modal="register" data-auth-redirect="{{ $postLoginRedirect ?? request()->fullUrl() }}">{{ $t('common.register', 'Đăng ký') }}</button>
                <button type="button" data-open-auth-modal="login" data-auth-redirect="{{ $postLoginRedirect ?? request()->fullUrl() }}">{{ $t('common.login', 'Đăng nhập') }}</button>
            @endif
        </div>
    </div>
</div>

<header class="ser-shell-header">
    <div class="wrap ser-shell-header-inner">
        <a class="ser-shell-brand" href="{{ route('site.home') }}">
            <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding, 'company_name', 'SER0101') }}">
            <span class="ser-shell-brand-copy">
                <strong>{{ data_get($branding, 'company_name', 'SER0101') }}</strong>
            </span>
        </a>

        <div class="ser-shell-header-actions">
            <form class="ser-shell-search" method="GET" action="{{ route('site.catalog.search') }}">
                <input type="search" name="q" placeholder="{{ $t('common.search_placeholder', 'Tìm gói dịch vụ, tuyến đường, loại xe') }}" data-ser-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                <button type="submit">{{ $t('common.search_button', 'Tìm') }}</button>
            </form>

            <button type="button" class="ser-shell-cart ser-shell-cart--header" data-ser-cart-toggle aria-expanded="false" aria-controls="ser-shell-cart-drawer">
                <span class="ser-shell-cart-icon" aria-hidden="true">🛒</span>
                <span>{{ $t('common.cart_button', 'Giỏ hàng') }}</span>
                <span class="ser-shell-cart-badge" data-ser-cart-count>{{ $cartSummary['count'] ?? 0 }}</span>
            </button>
        </div>
    </div>

    <nav class="ser-shell-nav">
        <div class="wrap ser-shell-nav-inner">
            <div class="ser-shell-menu ser-shell-menu--editorial">
                @if (!empty($productMenu))
                    <div class="ser-shell-nav-item ser-shell-nav-item--catalog">
                        <a class="ser-shell-nav-link" href="{{ $productMenu[0]['url'] ?? route('site.catalog.search') }}">
                            <span>{{ $t('common.service_menu', 'Nhóm dịch vụ') }}</span>
                        </a>
                        <div class="ser-shell-flyout ser-shell-flyout--catalog">
                            <div class="ser-shell-flyout-grid">
                                @foreach ($productMenu as $menuSection)
                                    @php
                                        $menuSectionLabel = $menuSection['label'] ?? $menuSection['name'] ?? $t('common.service', 'Dịch vụ');
                                        $menuSectionChildren = collect($menuSection['children'] ?? [])->filter(fn ($child): bool => is_array($child))->values();
                                    @endphp
                                    <section class="ser-shell-flyout-card">
                                        <a class="ser-shell-flyout-title" href="{{ $menuSection['url'] ?? '#' }}" target="{{ $menuSection['target'] ?? '_self' }}">
                                            <strong>{{ $menuSectionLabel }}</strong>
                                            <span aria-hidden="true">→</span>
                                        </a>
                                        @if (!empty($menuSection['summary']))
                                            <p>{{ $menuSection['summary'] }}</p>
                                        @endif
                                        @if ($menuSectionChildren->isNotEmpty())
                                            <div class="ser-shell-flyout-list">
                                                @foreach ($menuSectionChildren as $child)
                                                    <a href="{{ $child['url'] ?? '#' }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? $child['name'] ?? $t('common.service', 'Dịch vụ') }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($topMenu as $item)
                    @php
                        $topMenuChildren = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child))->values();
                    @endphp
                    @if ($topMenuChildren->isNotEmpty())
                        <div class="ser-shell-nav-item">
                            <a class="ser-shell-nav-link" href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                                <span>{{ $item['label'] ?? $t('common.menu', 'Menu') }}</span>
                            </a>
                            <div class="ser-shell-flyout ser-shell-flyout--primary">
                                <div class="ser-shell-primary-stack">
                                    <a class="ser-shell-primary-feature" href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                                        <strong>{{ $item['label'] ?? $t('common.menu', 'Menu') }}</strong>
                                        <span>{{ $item['summary'] ?? 'Xem toàn bộ nội dung trong nhóm điều hướng này.' }}</span>
                                    </a>
                                    <div class="ser-shell-primary-links">
                                        @foreach ($topMenuChildren as $child)
                                            <a href="{{ $child['url'] ?? '#' }}" target="{{ $child['target'] ?? '_self' }}">
                                                <strong>{{ $child['label'] ?? $t('common.menu', 'Menu') }}</strong>
                                                <span>{{ $child['summary'] ?? ($child['description'] ?? 'Đi tới nội dung liên quan trong nhóm này.') }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <a class="ser-shell-nav-link ser-shell-nav-link--solo" href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                            <span>{{ $item['label'] ?? $t('common.menu', 'Menu') }}</span>
                        </a>
                    @endif
                @endforeach
            </div>

            <div class="ser-shell-tools">
                <button type="button" class="ser-shell-cta" data-open-quote-modal>{{ $t('theme.fallback.hero_cta', 'Nhận báo giá') }}</button>
            </div>
        </div>
    </nav>
</header>

<div class="ser-cart-drawer-backdrop" data-ser-cart-backdrop hidden></div>
<aside id="ser-shell-cart-drawer" class="ser-cart-drawer" data-ser-cart-drawer hidden aria-hidden="true">
    <div class="ser-cart-drawer-head">
        <div>
            <span class="ser-cart-drawer-kicker">{{ $t('cart.drawer_kicker', 'Premium booking cart') }}</span>
            <strong>{{ $t('cart.drawer_title', 'Giỏ hàng đang lưu') }}</strong>
            <span data-ser-cart-count-label>{{ str_replace(':count', (string) ($cartSummary['count'] ?? 0), $t('cart.saved_count', ':count mục đang lưu')) }}</span>
        </div>
        <button type="button" class="ser-cart-drawer-close" data-ser-cart-close aria-label="{{ $t('modal.close', 'Đóng') }}">×</button>
    </div>

    <div class="ser-cart-drawer-body" data-ser-cart-items>
        @php
            $drawerPreviewItems = collect($cartSummary['items'] ?? [])->take(4)->all();
            $drawerHiddenCount = max(0, (int) (($cartSummary['unique_count'] ?? 0) - count($drawerPreviewItems)));
        @endphp
        @if ($drawerPreviewItems !== [])
            @foreach ($drawerPreviewItems as $item)
                <article class="ser-cart-item" data-ser-cart-item data-product-id="{{ $item['product_id'] }}">
                    <img src="{{ $item['image'] ?: 'https://picsum.photos/seed/ser0101-mini-cart/320/240' }}" alt="{{ $item['title'] }}">
                    <div class="ser-cart-item-copy">
                        <a href="{{ $item['url'] ?? route('site.cart.index') }}">{{ $item['title'] }}</a>
                        <span>{{ str_replace([':quantity', ':price'], [(string) ($item['quantity'] ?? 1), number_format((float) ($item['price'] ?? 0), 0, ',', '.').'đ'], $t('cart.item_meta', 'Số lượng lưu: :quantity · Giá tham khảo: :price')) }}</span>
                        <div class="ser-cart-item-controls">
                            <div class="ser-cart-qty">
                                <button type="button" data-ser-cart-quantity="decrease" data-product-id="{{ $item['product_id'] }}" aria-label="{{ $t('cart.decrease_quantity', 'Giảm số lượng') }}">−</button>
                                <strong>{{ $item['quantity'] ?? 1 }}</strong>
                                <button type="button" data-ser-cart-quantity="increase" data-product-id="{{ $item['product_id'] }}" aria-label="{{ $t('cart.increase_quantity', 'Tăng số lượng') }}">+</button>
                            </div>
                            <button type="button" class="ser-cart-remove" data-ser-cart-remove data-product-id="{{ $item['product_id'] }}">{{ $t('cart.remove_short', 'Xóa') }}</button>
                        </div>
                    </div>
                </article>
            @endforeach

            @if ($drawerHiddenCount > 0)
                <div class="ser-cart-preview-more">{{ str_replace(':count', (string) $drawerHiddenCount, $t('cart.drawer_more_items', 'Còn :count gói khác trong giỏ hàng')) }}</div>
            @endif
        @else
            <div class="ser-cart-empty">{{ $t('cart.drawer_empty', 'Giỏ hàng hiện chưa có dịch vụ nào.') }}</div>
        @endif
    </div>

    <div class="ser-cart-drawer-foot">
        <div class="ser-cart-drawer-toast" data-ser-cart-toast hidden aria-live="polite"></div>
        <div class="ser-cart-drawer-summary">
            <span>{{ $t('cart.estimated_value_label', 'Giá trị tham khảo') }}</span>
            <strong data-ser-cart-subtotal>{{ number_format((float) ($cartSummary['subtotal'] ?? 0), 0, ',', '.') }}đ</strong>
        </div>
        <div class="ser-cart-drawer-actions">
            <a class="ser-cart-drawer-link" href="{{ route('site.cart.index') }}">{{ $t('cart.drawer_view_all', 'Xem giỏ hàng') }}</a>
            <a class="ser-cart-drawer-link ser-cart-drawer-link--primary" href="{{ route('site.checkout.index') }}">{{ $t('cart.drawer_checkout', 'Tiếp tục đặt xe') }}</a>
        </div>
    </div>
</aside>

<script>
    (() => {
        const drawer = document.querySelector('[data-ser-cart-drawer]');
        const backdrop = document.querySelector('[data-ser-cart-backdrop]');
        const toggleButtons = [...document.querySelectorAll('[data-ser-cart-toggle]')];
        const closeButtons = [...document.querySelectorAll('[data-ser-cart-close]')];
        const countNodes = [...document.querySelectorAll('[data-ser-cart-count]')];
        const countLabel = document.querySelector('[data-ser-cart-count-label]');
        const subtotalNode = document.querySelector('[data-ser-cart-subtotal]');
        const itemsNode = document.querySelector('[data-ser-cart-items]');
        const toastNode = document.querySelector('[data-ser-cart-toast]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value || '';
        const initialSummary = @json($cartSummary);
        const emptyText = @json($t('cart.drawer_empty', 'Giỏ hàng hiện chưa có dịch vụ nào.'));
        const countText = @json($t('cart.saved_count', ':count mục đang lưu'));
        const itemMetaTemplate = @json($t('cart.item_meta', 'Số lượng lưu: :quantity · Giá tham khảo: :price'));
        const moreItemsTemplate = @json($t('cart.drawer_more_items', 'Còn :count gói khác trong giỏ hàng'));
        const increaseLabel = @json($t('cart.increase_quantity', 'Tăng số lượng'));
        const decreaseLabel = @json($t('cart.decrease_quantity', 'Giảm số lượng'));
        const removeLabel = @json($t('cart.remove_short', 'Xóa'));
        const fallbackCartUrl = @json(route('site.cart.index'));
        const updateUrlTemplate = @json(route('site.cart.update', ['productId' => '__PRODUCT__']));
        const removeUrlTemplate = @json(route('site.cart.remove', ['productId' => '__PRODUCT__']));
        const cartSyncKey = 'ser0101-cart-sync';
        const cartTabIdKey = 'ser0101-cart-tab-id';
        const externalCheckoutMessage = @json($t('checkout_success.sync_cart_cleared', 'Một tab khác vừa hoàn tất gửi yêu cầu. Giỏ hàng đã được làm mới.'));
        const externalAddMessage = @json($t('product.sync_added_from_other_tab', 'Một tab khác vừa thêm dịch vụ này vào giỏ hàng.'));
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
        let isDrawerOpen = false;
        let currentSummary = JSON.parse(JSON.stringify(initialSummary || {}));

        if (!drawer || !backdrop || !itemsNode) {
            return;
        }

        const formatCurrency = (value) => `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))}đ`;
        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

        const openDrawer = () => {
            if (isDrawerOpen) {
                return;
            }

            drawer.hidden = false;
            backdrop.hidden = false;
            drawer.setAttribute('aria-hidden', 'false');
            requestAnimationFrame(() => {
                drawer.classList.add('is-open');
                backdrop.classList.add('is-open');
            });
            toggleButtons.forEach((button) => button.setAttribute('aria-expanded', 'true'));
            document.body.style.overflow = 'hidden';
            isDrawerOpen = true;
        };

        const closeDrawer = () => {
            if (!isDrawerOpen) {
                return;
            }

            drawer.classList.remove('is-open');
            backdrop.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            toggleButtons.forEach((button) => button.setAttribute('aria-expanded', 'false'));
            window.setTimeout(() => {
                if (!isDrawerOpen) {
                    drawer.hidden = true;
                    backdrop.hidden = true;
                }
            }, 240);
            document.body.style.overflow = '';
            isDrawerOpen = false;
        };

        const setDrawerItemPending = (productId, nextState) => {
            const item = itemsNode.querySelector(`[data-ser-cart-item][data-product-id="${CSS.escape(String(productId))}"]`);

            if (!item) {
                return;
            }

            item.classList.toggle('is-pending', nextState);
            item.querySelectorAll('button, input').forEach((node) => {
                node.disabled = nextState;
            });
        };

        const showDrawerToast = (message, isError = false) => {
            if (!toastNode || !message) {
                return;
            }

            toastNode.hidden = false;
            toastNode.textContent = message;
            toastNode.dataset.state = isError ? 'error' : 'success';
            window.clearTimeout(showDrawerToast.timeoutId);
            showDrawerToast.timeoutId = window.setTimeout(() => {
                toastNode.hidden = true;
            }, 2600);
        };

        const buildActionUrl = (template, productId) => template.replace('__PRODUCT__', encodeURIComponent(String(productId)));

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

        const submitCartChange = async (url, body) => {
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

        const renderCart = (summary = {}) => {
            currentSummary = normalizeSummary(summary);
            const items = Array.isArray(currentSummary.items) ? currentSummary.items : [];
            const previewItems = items.slice(0, 4);
            const count = Number(currentSummary.count || 0);
            const subtotal = Number(currentSummary.subtotal || 0);
            const hiddenCount = Math.max(0, Number(currentSummary.unique_count || items.length || 0) - previewItems.length);

            countNodes.forEach((node) => {
                node.textContent = `${count}`;
            });

            if (countLabel) {
                countLabel.textContent = countText.replace(':count', `${count}`);
            }

            if (subtotalNode) {
                subtotalNode.textContent = formatCurrency(subtotal);
            }

            if (previewItems.length === 0) {
                itemsNode.innerHTML = `<div class="ser-cart-empty">${escapeHtml(emptyText)}</div>`;
                return;
            }

            itemsNode.innerHTML = `${previewItems.map((item) => {
                const image = item.image || 'https://picsum.photos/seed/ser0101-mini-cart/320/240';
                const url = item.url || fallbackCartUrl;
                const meta = itemMetaTemplate
                    .replace(':quantity', `${Number(item.quantity || 1)}`)
                    .replace(':price', formatCurrency(item.price || 0));

                return `
                    <article class="ser-cart-item" data-ser-cart-item data-product-id="${escapeHtml(item.product_id || '')}">
                        <img src="${escapeHtml(image)}" alt="${escapeHtml(item.title || '')}">
                        <div class="ser-cart-item-copy">
                            <a href="${escapeHtml(url)}">${escapeHtml(item.title || '')}</a>
                            <span>${escapeHtml(meta)}</span>
                            <div class="ser-cart-item-controls">
                                <div class="ser-cart-qty">
                                    <button type="button" data-ser-cart-quantity="decrease" data-product-id="${escapeHtml(item.product_id || '')}" aria-label="${escapeHtml(decreaseLabel)}">−</button>
                                    <strong>${escapeHtml(item.quantity || 1)}</strong>
                                    <button type="button" data-ser-cart-quantity="increase" data-product-id="${escapeHtml(item.product_id || '')}" aria-label="${escapeHtml(increaseLabel)}">+</button>
                                </div>
                                <button type="button" class="ser-cart-remove" data-ser-cart-remove data-product-id="${escapeHtml(item.product_id || '')}">${escapeHtml(removeLabel)}</button>
                            </div>
                        </div>
                    </article>
                `;
            }).join('')}${hiddenCount > 0 ? `<div class="ser-cart-preview-more">${escapeHtml(moreItemsTemplate.replace(':count', `${hiddenCount}`))}</div>` : ''}`;
        };

        const setFeedback = (form, message, isError = false) => {
            const feedback = form.closest('.copy')?.querySelector('[data-ser-cart-feedback]') || form.querySelector('[data-ser-cart-feedback]');

            if (!feedback) {
                return;
            }

            feedback.hidden = false;
            feedback.textContent = message;
            feedback.dataset.state = isError ? 'error' : 'success';
        };

        toggleButtons.forEach((button) => {
            button.addEventListener('click', () => {
                if (drawer.hidden) {
                    openDrawer();
                    return;
                }

                closeDrawer();
            });
        });

        closeButtons.forEach((button) => button.addEventListener('click', closeDrawer));
        backdrop.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !drawer.hidden) {
                closeDrawer();
            }
        });

        document.querySelectorAll('[data-ser-add-to-cart-form]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submitButton = form.querySelector('button[type="submit"]');
                const feedback = form.closest('.copy')?.querySelector('[data-ser-cart-feedback]');
                const originalLabel = submitButton?.textContent || '';

                if (feedback) {
                    feedback.hidden = true;
                }

                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = submitButton.dataset.loadingLabel || 'Đang thêm...';
                }

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: new FormData(form),
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw payload;
                    }

                    const nextSummary = normalizeSummary(payload?.data?.cart_summary || {});
                    renderCart(nextSummary);
                    setFeedback(form, payload?.message || form.dataset.successMessage || 'Đã thêm vào giỏ hàng.');
                    document.dispatchEvent(new CustomEvent('ser:cart-updated', { detail: { ...(payload?.data || {}), cart_summary: nextSummary } }));
                    broadcastCartSync({ summary: nextSummary, message: payload?.message || form.dataset.successMessage || 'Đã thêm vào giỏ hàng.', origin: 'cart-add', item: payload?.data?.item || null });
                    showDrawerToast(payload?.message || form.dataset.successMessage || 'Đã thêm vào giỏ hàng.');
                    openDrawer();
                } catch (error) {
                    setFeedback(form, error?.message || form.dataset.errorMessage || 'Không thể thêm vào giỏ hàng lúc này.', true);
                    showDrawerToast(error?.message || form.dataset.errorMessage || 'Không thể thêm vào giỏ hàng lúc này.', true);
                } finally {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalLabel;
                    }
                }
            });
        });

        document.addEventListener('ser:cart-updated', (event) => {
            renderCart(event.detail?.cart_summary || event.detail || {});
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

                renderCart(payload.summary || {});

                if (payload.origin === 'checkout-success') {
                    showDrawerToast(payload.message || externalCheckoutMessage);
                }

                if (payload.origin === 'cart-add') {
                    document.querySelectorAll('[data-ser-add-to-cart-form]').forEach((form) => {
                        setFeedback(form, payload.message || externalAddMessage);
                    });
                }
            } catch (error) {
                console.error(error);
            }
        });

        itemsNode.addEventListener('click', async (event) => {
            const removeButton = event.target.closest('[data-ser-cart-remove]');
            const quantityButton = event.target.closest('[data-ser-cart-quantity]');

            if (!removeButton && !quantityButton) {
                return;
            }

            const productId = removeButton?.dataset.productId || quantityButton?.dataset.productId;

            if (!productId) {
                return;
            }

            setDrawerItemPending(productId, true);

            try {
                let payload;
                let previousSummary = currentSummary;

                if (removeButton) {
                    renderCart(removeItemSummary(currentSummary, productId));
                    payload = await submitCartChange(buildActionUrl(removeUrlTemplate, productId), new FormData());
                } else {
                    const currentQuantity = Number(quantityButton.parentElement?.querySelector('strong')?.textContent || 1);
                    const nextQuantity = quantityButton.dataset.serCartQuantity === 'increase'
                        ? currentQuantity + 1
                        : Math.max(1, currentQuantity - 1);
                    previousSummary = currentSummary;
                    renderCart(patchQuantitySummary(currentSummary, productId, nextQuantity));
                    setDrawerItemPending(productId, true);
                    const formData = new FormData();
                    formData.set('quantity', `${nextQuantity}`);
                    payload = await submitCartChange(buildActionUrl(updateUrlTemplate, productId), formData);
                }

                const nextSummary = normalizeSummary(payload?.data?.cart_summary || {});
                renderCart(nextSummary);
                document.dispatchEvent(new CustomEvent('ser:cart-updated', { detail: { ...(payload?.data || {}), cart_summary: nextSummary } }));
                broadcastCartSync({ summary: nextSummary, message: payload?.message || 'Đã cập nhật giỏ hàng.', origin: removeButton ? 'cart-remove' : 'cart-update' });
                showDrawerToast(payload?.message || 'Đã cập nhật giỏ hàng.');
            } catch (error) {
                console.error(error);
                if (quantityButton) {
                    renderCart(previousSummary || currentSummary);
                }
                showDrawerToast(error?.message || 'Không thể cập nhật giỏ hàng lúc này.', true);
            } finally {
                setDrawerItemPending(productId, false);
            }
        });

        document.addEventListener('ser:cart-toast', (event) => {
            showDrawerToast(event.detail?.message || '', Boolean(event.detail?.isError));
        });

        renderCart(initialSummary);
    })();
</script>
@include('partials.storefront-language-switcher')
