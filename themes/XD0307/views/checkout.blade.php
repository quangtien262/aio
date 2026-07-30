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
    $form = $checkoutForm ?? [];
    $paymentMethods = $paymentMethods ?? [];
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

    $normalizeLabel = fn ($value): string => \Illuminate\Support\Str::of((string) $value)->lower()->ascii()->squish()->toString();
    $isProductLabel = fn ($label): bool => in_array($normalizeLabel($label), ['san pham', 'products', 'product'], true);
    $isHomeLabel = fn ($label): bool => in_array($normalizeLabel($label), ['trang chu', 'home'], true);

    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $homeUrl = route('site.home');
    if (! $navItems->contains(fn (array $item): bool => $isHomeLabel($item['label'] ?? '') || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.4c23dc9bef7f79b4', 'Trang chủ'),
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

    if ($productNavigationItems->isNotEmpty()) {
        if ($navItems->contains(fn (array $item): bool => $isProductLabel($item['label'] ?? ''))) {
            $navItems = $navItems->map(function (array $item) use ($productNavigationItems, $isProductLabel): array {
                if ($isProductLabel($item['label'] ?? '') && empty($item['children'])) {
                    $item['children'] = $productNavigationItems->all();
                }

                return $item;
            })->values();
        } else {
            $navArray = $navItems->values()->all();
            $homeIndex = $navItems->search(fn (array $item): bool => $isHomeLabel($item['label'] ?? ''));
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [[
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
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
        $item['active'] = $href !== '#' && $absoluteHref === $currentUrl;

        return $item;
    })->values();
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-xd0307-checkout';
@endphp

@extends('theme-xd0307::layout')

@section('title'){{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.c787a47386bc5661', 'Thanh toán') }} | {{ $logoAlt }}@endsection

@push('head')
    <style>
        .xd-cart-link{position:relative;display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}
        .xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .xd-cart-link:hover,.xd-cart-link.is-active{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}
        .xd-cart-count{position:absolute;right:-7px;top:-7px;display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--dark);color:#fff;font-size:11px;font-weight:950}
        .xd-page-main{padding:54px 0 88px;background:linear-gradient(180deg,#fff 0,#fbfcfa 52%,#fff 100%)}
        .xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:24px;color:var(--muted);font-size:14px;font-weight:800}
        .xd-breadcrumb a:hover{color:var(--lime-dark)}
        .xd-heading{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:24px;margin-bottom:32px}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 16px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-heading h1{margin:0;font-size:clamp(42px,5vw,72px);line-height:1;letter-spacing:-.06em}
        .xd-heading p{margin:14px 0 0;color:var(--muted);font-size:19px;font-weight:700}
        .xd-alert{margin-bottom:18px;padding:14px 18px;border-left:5px solid #d94b5b;background:#fff3f4;color:#9e2d3b;font-weight:800}
        .xd-checkout-grid{display:grid;grid-template-columns:minmax(0,1fr) 430px;gap:30px;align-items:start}
        .xd-panel{background:#fff;border:1px solid var(--line);box-shadow:var(--shadow)}
        .xd-form-panel{padding:30px}
        .xd-section-title{margin:0 0 20px;font-size:30px;line-height:1.15;letter-spacing:-.035em}
        .xd-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
        .xd-field{display:grid;gap:8px}
        .xd-field.is-wide{grid-column:1/-1}
        .xd-field label{font-size:13px;font-weight:950;text-transform:uppercase;letter-spacing:.04em}
        .xd-field input,.xd-field textarea{width:100%;border:1px solid var(--line);background:#fff;padding:14px 16px;color:var(--ink);outline:none}
        .xd-field textarea{min-height:118px;resize:vertical}
        .xd-field input:focus,.xd-field textarea:focus{border-color:var(--lime);box-shadow:0 0 0 4px rgba(189,212,0,.12)}
        .xd-payment-list{display:grid;gap:12px;margin-top:24px}
        .xd-payment-option{display:grid;grid-template-columns:22px minmax(0,1fr);gap:14px;padding:16px;border:1px solid var(--line);background:#fbfcfa;cursor:pointer}
        .xd-payment-option:hover{border-color:var(--lime)}
        .xd-payment-option strong{display:block;margin-bottom:2px}
        .xd-payment-option span{color:var(--muted);font-size:14px;font-weight:650}
        .xd-button{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:58px;margin-top:24px;padding:0 22px;border:0;background:var(--lime);color:#fff;font-weight:950;text-transform:uppercase;box-shadow:0 18px 30px rgba(189,212,0,.28);cursor:pointer}
        .xd-button.is-ghost{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none}
        .xd-summary{position:sticky;top:126px;padding:28px}
        .xd-summary h2{margin:0 0 18px;font-size:30px;line-height:1.15;letter-spacing:-.035em}
        .xd-summary-items{display:grid;gap:14px}
        .xd-summary-item{display:grid;grid-template-columns:72px minmax(0,1fr);gap:14px;padding-bottom:14px;border-bottom:1px solid var(--line)}
        .xd-summary-thumb{aspect-ratio:1/1;background:#f5f8f2;overflow:hidden}
        .xd-summary-thumb img{width:100%;height:100%;object-fit:contain;padding:7px}
        .xd-summary-name{display:block;font-weight:900;line-height:1.35}
        .xd-summary-meta{display:block;color:var(--muted);font-size:13px;font-weight:750}
        .xd-summary-price{display:block;margin-top:4px;color:#9a6a3e;font-weight:950}
        .xd-summary-row{display:flex;justify-content:space-between;gap:16px;padding:14px 0;border-top:1px solid var(--line);font-weight:850}
        .xd-summary-row strong{font-size:28px;color:#9a6a3e}
        .xd-empty{padding:32px;border:1px dashed rgba(38,56,74,.25);text-align:center;background:#fff}
        .xd-empty h2{margin:0 0 8px;font-size:30px;letter-spacing:-.035em}
        .xd-empty p{margin:0 0 18px;color:var(--muted);font-weight:700}
        @media (max-width:1180px){.xd-checkout-grid{grid-template-columns:1fr}.xd-summary{position:static}}
        @media (max-width:640px){.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-page-main{padding:34px 0 54px}.xd-heading{grid-template-columns:1fr}.xd-heading h1{font-size:42px}.xd-form-panel{padding:20px}.xd-form-grid{grid-template-columns:1fr}}
    </style>
@endpush

@section('content')
        <main class="xd-page-main">
            <div class="xd-container">
                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.4c23dc9bef7f79b4', 'Trang chủ') }}</a>
                    <span>/</span>
                    <a href="{{ route('site.cart.index') }}">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.2ce71f8e08b31a32', 'Giỏ hàng') }}</a>
                    <span>/</span>
                    <strong>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.c787a47386bc5661', 'Thanh toán') }}</strong>
                </nav>

                <section class="xd-heading">
                    <div>
                        <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.c787a47386bc5661', 'Thanh toán') }}</span>
                        <h1>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.8704052e6d78fc4b', 'Hoàn tất đơn hàng') }}</h1>
                        <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.32aff3a79ecc459e', 'Xác nhận thông tin nhận hàng và phương thức thanh toán trước khi gửi đơn.') }}</p>
                    </div>
                </section>

                @if ($errors->any())
                    <div class="xd-alert">{{ $errors->first() }}</div>
                @endif

                @if (empty($cartItems))
                    <section class="xd-empty">
                        <h2>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.1fe583c7dd7f61f2', 'Giỏ hàng đang trống') }}</h2>
                        <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.b6eee9acf8b0f5bc', 'Vui lòng thêm sản phẩm trước khi thanh toán.') }}</p>
                        <a class="xd-button" href="{{ route('site.catalog.search') }}">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.9d8061ac223f2c93', 'Xem sản phẩm') }}</a>
                    </section>
                @else
                    <section class="xd-checkout-grid">
                        <form class="xd-panel xd-form-panel" method="POST" action="{{ route('site.checkout.store') }}">
                            @csrf
                            <h2 class="xd-section-title">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.8feb2faa773cc852', 'Thông tin nhận hàng') }}</h2>
                            <div class="xd-form-grid">
                                <label class="xd-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.33e59b3ff0831c9e', 'Họ tên') }}</span>
                                    <input name="customer_name" value="{{ old('customer_name', $form['customer_name'] ?? '') }}" required>
                                </label>
                                <label class="xd-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.e3a68c2c04d1cbdd', 'Số điện thoại') }}</span>
                                    <input name="customer_phone" value="{{ old('customer_phone', $form['customer_phone'] ?? '') }}" required>
                                </label>
                                <label class="xd-field is-wide">
                                    <span>Email</span>
                                    <input type="email" name="customer_email" value="{{ old('customer_email', $form['customer_email'] ?? '') }}">
                                </label>
                                <label class="xd-field is-wide">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.6ba7ecef72b74c49', 'Địa chỉ nhận hàng') }}</span>
                                    <textarea name="delivery_address" required>{{ old('delivery_address', $form['delivery_address'] ?? '') }}</textarea>
                                </label>
                                <label class="xd-field is-wide">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.ca5f5d391dbcb520', 'Ghi chú') }}</span>
                                    <textarea name="note">{{ old('note', $form['note'] ?? '') }}</textarea>
                                </label>
                            </div>

                            <div class="xd-payment-list">
                                <h2 class="xd-section-title">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.d8d8287d5f423154', 'Phương thức thanh toán') }}</h2>
                                @foreach ($paymentMethods as $value => $method)
                                    @php
                                        $methodValue = is_array($method) ? (string) ($method['value'] ?? $value) : (string) $value;
                                        $methodLabel = is_array($method) ? (string) ($method['label'] ?? $methodValue) : (string) $method;
                                        $methodHint = is_array($method) ? (string) ($method['hint'] ?? '') : '';
                                    @endphp
                                    <label class="xd-payment-option">
                                        <input type="radio" name="payment_method" value="{{ $methodValue }}" @checked(old('payment_method', $form['payment_method'] ?? 'cod') === $methodValue)>
                                        <span>
                                            <strong>{{ $methodLabel }}</strong>
                                            @if ($methodHint !== '')
                                                <span>{{ $methodHint }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <button class="xd-button" type="submit">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.7812995951409015', 'Đặt hàng') }}</button>
                        </form>

                        <aside class="xd-panel xd-summary">
                            <h2>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.ac70f1665a69d011', 'Tóm tắt đơn hàng') }}</h2>
                            <div class="xd-summary-items">
                                @foreach ($cartItems as $item)
                                    <div class="xd-summary-item">
                                        <span class="xd-summary-thumb">
                                            @if (!empty($item['image_url']))
                                                <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] ?? '' }}">
                                            @endif
                                        </span>
                                        <span>
                                            <strong class="xd-summary-name">{{ $item['name'] ?? 'Sản phẩm' }}</strong>
                                            <span class="xd-summary-meta">x{{ (int) ($item['quantity'] ?? 1) }}</span>
                                            <span class="xd-summary-price">{{ $formatCurrency($item['line_total'] ?? $item['price'] ?? null) }}</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="xd-summary-row">
                                <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.dca90aab7a1db7e1', 'Số sản phẩm') }}</span>
                                <b>{{ (int) ($cartSummary['count'] ?? 0) }}</b>
                            </div>
                            <div class="xd-summary-row">
                                <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.94abb38c6c63968a', 'Tạm tính') }}</span>
                                <strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong>
                            </div>
                            <a class="xd-button is-ghost" href="{{ route('site.cart.index') }}">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0307', app()->getLocale(), 'legacy_inline.3716101409124ae4', 'Quay lại giỏ hàng') }}</a>
                        </aside>
                    </section>
                @endif
            </div>
        </main>
@endsection
