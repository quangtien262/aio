@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342')) ?: '0399162342';
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $cartSummary = (array) data_get($shell, 'cart_summary', ['count' => 0, 'unique_count' => 0, 'subtotal' => 0, 'items' => []]);
    $cartItems = array_values((array) ($cartSummary['items'] ?? []));
    $customerAuth = (array) data_get($shell, 'customer_auth', [
        'is_authenticated' => auth('customer')->check(),
        'customer' => auth('customer')->user(),
    ]);
    $formatCurrency = function ($value): string {
        if ($value === null || (float) $value <= 0) {
            return 'Liên hệ';
        }

        return number_format((float) $value, 0, ',', '.').'đ';
    };

    $localizeMenuUrl = static fn (?string $href): string => \App\Support\FrontendRouteUrl::localized($href);

    $normalizeNavItem = function (array $item) use (&$normalizeNavItem, $localizeMenuUrl): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
            'href' => $localizeMenuUrl($href),
            'target' => $item['target'] ?? '_self',
            'active' => false,
            'children' => collect($item['children'] ?? [])
                ->filter(fn ($child): bool => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))
                ->map(fn (array $child): array => $normalizeNavItem($child))
                ->values()
                ->all(),
        ];
    };

    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $homeUrl = route('site.home');
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chủ', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chủ',
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems->map(function (array $item) use ($productNavigationItems): array {
                $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
                if (in_array($label, ['sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                    $item['children'] = $productNavigationItems->all();
                }

                return $item;
            })->values();
        } else {
            $navArray = $navItems->values()->all();
            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [[
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ]]);
            $navItems = collect($navArray);
        }
    }

    $currentUrl = rtrim(url()->current(), '/');
    $navItems = $navItems->map(function (array $item) use ($currentUrl): array {
        $href = (string) ($item['href'] ?? '#');
        $absoluteHref = str_starts_with($href, 'http') ? rtrim($href, '/') : rtrim(url($href), '/');
        $item['active'] = $href !== '#' && ($absoluteHref === $currentUrl || (($item['active'] ?? false) && request()->routeIs('site.catalog.*')));

        return $item;
    })->values();
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-xd0304-cart';
@endphp

@extends('theme-xd0304::layout')

@section('title')Giỏ hàng | {{ $logoAlt }}@endsection

@push('head')
    <style>
        .xd-cart-link{position:relative;display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}
        .xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .xd-cart-link:hover,.xd-cart-link.is-active{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}
        .xd-cart-count{position:absolute;right:-7px;top:-7px;display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--dark);color:#fff;font-size:11px;font-weight:950}
        .xd-page-main{padding:54px 0 88px;background:linear-gradient(180deg,#fff 0,#fbfcfa 52%,#fff 100%)}
        .xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:24px;color:var(--muted);font-size:14px;font-weight:800}
        .xd-breadcrumb a:hover{color:var(--lime-dark)}
        .xd-cart-heading{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:24px;margin-bottom:32px}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 16px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-cart-heading h1{margin:0;font-size:clamp(42px,5vw,72px);line-height:1;letter-spacing:-.06em}
        .xd-cart-heading p{margin:14px 0 0;color:var(--muted);font-size:19px;font-weight:700}
        .xd-cart-badge{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 18px;border:1px solid rgba(189,212,0,.55);background:#f8faed;color:var(--lime-dark);font-weight:950;text-transform:uppercase}
        .xd-alert{margin-bottom:18px;padding:14px 18px;border-left:5px solid var(--lime);background:#f8faed;color:var(--ink);font-weight:800}
        .xd-alert.is-error{border-left-color:#d94b5b;background:#fff3f4;color:#9e2d3b}
        .xd-cart-layout{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:30px;align-items:start}
        .xd-panel{background:#fff;border:1px solid var(--line);box-shadow:var(--shadow)}
        .xd-cart-list{display:grid;gap:14px}
        .xd-cart-item{display:grid;grid-template-columns:154px minmax(0,1fr) 230px;gap:20px;padding:18px;border:1px solid var(--line);background:#fff;box-shadow:0 16px 38px rgba(16,29,40,.06)}
        .xd-cart-thumb{display:block;aspect-ratio:1/1;background:radial-gradient(circle at 50% 34%,#fff 0,#f5f8f2 48%,#e9efe8 100%);overflow:hidden}
        .xd-cart-thumb img{width:100%;height:100%;padding:14px;object-fit:contain}
        .xd-cart-copy h2{margin:0 0 10px;font-size:24px;line-height:1.25;letter-spacing:-.035em}
        .xd-cart-meta{display:flex;flex-wrap:wrap;gap:8px}
        .xd-chip{display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border:1px solid var(--line);border-radius:999px;background:#fff;color:#66717a;font-size:12px;font-weight:900;text-transform:uppercase}
        .xd-cart-actions{display:grid;gap:12px;align-content:start;text-align:right}
        .xd-price{color:#9a6a3e;font-size:24px;font-weight:950;letter-spacing:-.035em}
        .xd-item-total{color:var(--ink);font-weight:950}
        .xd-quantity-form{display:flex;justify-content:flex-end;gap:8px}
        .xd-quantity-form input{width:74px;height:42px;border:1px solid var(--line);padding:0 10px;text-align:center;font-weight:850}
        .xd-small-button,.xd-remove-button{height:42px;border:0;padding:0 14px;font-weight:950;cursor:pointer}
        .xd-small-button{background:var(--ink);color:#fff}
        .xd-remove-button{background:#fff1f1;color:#c23b48;border:1px solid #ffd2d7}
        .xd-summary{position:sticky;top:126px;padding:28px}
        .xd-summary h2{margin:0 0 8px;font-size:30px;line-height:1.15;letter-spacing:-.035em}
        .xd-summary p{margin:0 0 22px;color:var(--muted);font-weight:700}
        .xd-summary-row{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px solid var(--line);font-weight:800}
        .xd-summary-row strong{font-size:20px}
        .xd-summary-total strong{font-size:28px;color:#9a6a3e}
        .xd-button{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:56px;margin-top:18px;padding:0 22px;border:0;background:var(--lime);color:#fff;font-weight:950;text-transform:uppercase;box-shadow:0 18px 30px rgba(189,212,0,.28);cursor:pointer}
        .xd-button.is-dark{background:var(--dark);box-shadow:none}
        .xd-button.is-ghost{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none}
        .xd-empty{display:grid;place-items:center;min-height:320px;padding:52px;text-align:center;background:#fff;border:1px dashed rgba(38,56,74,.25);box-shadow:var(--shadow)}
        .xd-empty h2{margin:0 0 10px;font-size:36px;letter-spacing:-.04em}
        .xd-empty p{max-width:620px;margin:0 auto 22px;color:var(--muted);font-weight:700}
        @media (max-width:1180px){.xd-cart-layout{grid-template-columns:1fr}.xd-summary{position:static}.xd-cart-item{grid-template-columns:130px minmax(0,1fr)}.xd-cart-actions{grid-column:1/-1;text-align:left}.xd-quantity-form{justify-content:flex-start}}
        @media (max-width:640px){.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-page-main{padding:34px 0 54px}.xd-cart-heading{grid-template-columns:1fr}.xd-cart-heading h1{font-size:42px}.xd-cart-item{grid-template-columns:96px minmax(0,1fr);gap:14px;padding:14px}.xd-cart-copy h2{font-size:19px}.xd-price{font-size:21px}}
    </style>
@endpush

@section('content')
        <main class="xd-page-main">
            <div class="xd-container">
                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a>
                    <span>/</span>
                    <strong>Giỏ hàng</strong>
                </nav>

                <div class="xd-cart-heading">
                    <div>
                        <span class="xd-kicker">Checkout</span>
                        <h1>Giỏ hàng của bạn</h1>
                        <p>Kiểm tra sản phẩm, số lượng và chuyển sang bước đặt hàng khi thông tin đã đúng.</p>
                    </div>
                    <span class="xd-cart-badge">{{ (int) ($cartSummary['count'] ?? 0) }} sản phẩm</span>
                </div>

                @if (session('cart_success'))
                    <div class="xd-alert">{{ session('cart_success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="xd-alert is-error">{{ $errors->first() }}</div>
                @endif

                @if (! empty($cartItems))
                    <section class="xd-cart-layout">
                        <div class="xd-cart-list">
                            @foreach ($cartItems as $item)
                                @php
                                    $quantity = max(1, (int) ($item['quantity'] ?? 1));
                                    $price = (float) ($item['price'] ?? 0);
                                    $itemSubtotal = $price * $quantity;
                                    $itemUrl = (string) ($item['url'] ?? '#');
                                    $itemImage = trim((string) ($item['image'] ?? ''));
                                    $itemCategory = trim((string) ($item['category'] ?? $item['category_name'] ?? ''));
                                @endphp
                                <article class="xd-cart-item">
                                    <a class="xd-cart-thumb" href="{{ $itemUrl }}">
                                        @if ($itemImage !== '')
                                            <img src="{{ $itemImage }}" alt="{{ $item['title'] ?? 'Sản phẩm' }}">
                                        @endif
                                    </a>
                                    <div class="xd-cart-copy">
                                        <h2><a href="{{ $itemUrl }}">{{ $item['title'] ?? 'Sản phẩm' }}</a></h2>
                                        <div class="xd-cart-meta">
                                            @if ($itemCategory !== '')
                                                <span class="xd-chip">{{ $itemCategory }}</span>
                                            @endif
                                            <span class="xd-chip">Tồn kho {{ $item['stock'] ?? 'không giới hạn' }}</span>
                                            @if (! empty($item['sku']))
                                                <span class="xd-chip">SKU {{ $item['sku'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="xd-cart-actions">
                                        <div>
                                            <div class="xd-price">{{ $formatCurrency($item['price'] ?? null) }}</div>
                                            <div class="xd-item-total">Tạm tính: {{ $formatCurrency($itemSubtotal) }}</div>
                                        </div>
                                        <form method="POST" action="{{ route('site.cart.update', ['productId' => $item['product_id']]) }}" class="xd-quantity-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="number" name="quantity" min="1" max="{{ $item['stock'] ?? 999 }}" value="{{ $quantity }}" aria-label="Số lượng">
                                            <button type="submit" class="xd-small-button">Cập nhật</button>
                                        </form>
                                        <form method="POST" action="{{ route('site.cart.remove', ['productId' => $item['product_id']]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="xd-remove-button">Xóa khỏi giỏ</button>
                                        </form>
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <aside class="xd-panel xd-summary">
                            <h2>Tóm tắt đơn hàng</h2>
                            <p>Đơn hàng sẽ được xác nhận lại trước khi thi công hoặc giao sản phẩm.</p>
                            <div class="xd-summary-row">
                                <span>Số sản phẩm</span>
                                <strong>{{ (int) ($cartSummary['count'] ?? 0) }}</strong>
                            </div>
                            <div class="xd-summary-row">
                                <span>Mặt hàng khác nhau</span>
                                <strong>{{ (int) ($cartSummary['unique_count'] ?? count($cartItems)) }}</strong>
                            </div>
                            <div class="xd-summary-row xd-summary-total">
                                <span>Tạm tính</span>
                                <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                            </div>

                            @if ($customerAuth['is_authenticated'] ?? false)
                                <a class="xd-button" href="{{ route('site.checkout.index') }}">Tiến hành thanh toán</a>
                            @else
                                <button type="button" class="xd-button" data-xd-auth-open="login">Đăng nhập để thanh toán</button>
                            @endif
                            <a class="xd-button is-ghost" href="{{ route('site.catalog.search') }}">Tiếp tục mua sắm</a>
                        </aside>
                    </section>
                @else
                    <section class="xd-empty">
                        <div>
                            <span class="xd-kicker">Empty cart</span>
                            <h2>Giỏ hàng đang trống</h2>
                            <p>Hãy quay lại danh mục sản phẩm để chọn vật tư, dịch vụ hoặc giải pháp phù hợp trước khi gửi yêu cầu đặt hàng.</p>
                            <a class="xd-button is-dark" href="{{ route('site.catalog.search') }}">Xem sản phẩm</a>
                        </div>
                    </section>
                @endif
            </div>
        </main>
@endsection
