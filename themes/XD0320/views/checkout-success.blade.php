@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit'))) ?: 'Arkit';
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342')) ?: '0399162342';
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $cartSummary = (array) data_get($shell, 'cart_summary', ['count' => 0, 'unique_count' => 0, 'subtotal' => 0, 'items' => []]);
    $customerAuth = (array) data_get($shell, 'customer_auth', [
        'is_authenticated' => auth('customer')->check(),
        'customer' => auth('customer')->user(),
    ]);
    $isEnglish = app()->getLocale() === 'en';
    $confirmedOrder = $order;
    $orderItems = collect($confirmedOrder->items ?? []);
    $formatCurrency = function ($value): string {
        if ($value === null || (float) $value <= 0) {
            return 'LiÃƒÂªn hÃ¡Â»â€¡';
        }

        return number_format((float) $value, 0, ',', '.').'Ã„â€˜';
    };

    $localizeMenuUrl = function (?string $href): string {
        $href = trim((string) $href);

        if ($href === '' || $href === '#' || str_starts_with($href, '#') || preg_match('/^(https?:)?\/\//i', $href) || preg_match('/^(mailto|tel):/i', $href)) {
            return $href !== '' ? $href : '#';
        }

        $parts = parse_url($href) ?: [];
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            return route('site.home').$query.$fragment;
        }

        $segments = explode('/', $path);
        $knownLocales = \App\Support\FrontendLocalization::knownLocaleCodes();

        if (! in_array($segments[0] ?? '', $knownLocales, true)) {
            array_unshift($segments, app()->getLocale());
        }

        return url('/'.implode('/', $segments)).$query.$fragment;
    };

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
            'label' => $isEnglish ? 'Home' : 'Trang chÃ¡Â»Â§',
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
                'label' => $isEnglish ? 'Products' : 'SÃ¡ÂºÂ£n phÃ¡ÂºÂ©m',
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
    $footerNewsletterSource = 'theme-footer-xd0320-checkout-success';
@endphp

@extends('theme-xd0320::layout')

@section('title'){{ $isEnglish ? 'Order confirmed' : 'Ã„ÂÃ¡ÂºÂ·t hÃƒÂ ng thÃƒÂ nh cÃƒÂ´ng' }} | {{ $logoAlt }}@endsection

@push('head')
    <style>
        .xd-cart-link{position:relative;display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}
        .xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
        .xd-cart-link:hover,.xd-cart-link.is-active{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}
        .xd-cart-count{position:absolute;right:-7px;top:-7px;display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:var(--dark);color:#fff;font-size:11px;font-weight:950}
        .xd-page-main{padding:58px 0 88px;background:linear-gradient(180deg,#fff 0,#fbfcfa 52%,#fff 100%)}
        .xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:24px;color:var(--muted);font-size:14px;font-weight:800}
        .xd-breadcrumb a:hover{color:var(--lime-dark)}
        .xd-success-hero{position:relative;overflow:hidden;padding:48px;border:1px solid var(--line);background:#fff;box-shadow:var(--shadow)}
        .xd-success-hero:before{content:"";position:absolute;right:-120px;top:-120px;width:360px;height:360px;border-radius:999px;background:rgba(189,212,0,.18)}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 16px 18px;font-size:14px;font-weight:950;letter-spacing:.055em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-success-hero h1{position:relative;margin:0;font-size:clamp(42px,5vw,72px);line-height:1;letter-spacing:-.06em}
        .xd-success-hero p{position:relative;max-width:760px;margin:18px 0 0;color:var(--muted);font-size:19px;font-weight:700}
        .xd-success-grid{display:grid;grid-template-columns:minmax(0,1fr) 420px;gap:30px;margin-top:30px;align-items:start}
        .xd-panel{background:#fff;border:1px solid var(--line);box-shadow:var(--shadow)}
        .xd-order-panel{padding:30px}
        .xd-order-panel h2{margin:0 0 22px;font-size:32px;line-height:1.15;letter-spacing:-.035em}
        .xd-order-lines{display:grid;gap:0;border-top:1px solid var(--line)}
        .xd-order-line{display:grid;grid-template-columns:190px minmax(0,1fr);gap:20px;padding:15px 0;border-bottom:1px solid var(--line)}
        .xd-order-label{color:var(--muted);font-weight:850}
        .xd-order-value{font-weight:900;overflow-wrap:anywhere}
        .xd-items{display:grid;gap:14px}
        .xd-item{display:grid;grid-template-columns:72px minmax(0,1fr) auto;gap:14px;align-items:center;padding-bottom:14px;border-bottom:1px solid var(--line)}
        .xd-item:last-child{border-bottom:0}
        .xd-item-thumb{aspect-ratio:1/1;background:#f5f8f2;overflow:hidden}
        .xd-item-thumb img{width:100%;height:100%;object-fit:contain;padding:7px}
        .xd-item-name{display:block;font-weight:900;line-height:1.35}
        .xd-item-meta{display:block;color:var(--muted);font-size:13px;font-weight:750}
        .xd-item-price{font-weight:950;color:#9a6a3e}
        .xd-button-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:28px}
        .xd-button{display:inline-flex;align-items:center;justify-content:center;min-height:56px;padding:0 22px;border:0;background:var(--lime);color:#fff;font-weight:950;text-transform:uppercase;box-shadow:0 18px 30px rgba(189,212,0,.28);cursor:pointer}
        .xd-button.is-ghost{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none}
        @media (max-width:1180px){.xd-success-grid{grid-template-columns:1fr}}
        @media (max-width:640px){.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-page-main{padding:34px 0 54px}.xd-success-hero{padding:28px}.xd-success-hero h1{font-size:42px}.xd-order-panel{padding:20px}.xd-order-line{grid-template-columns:1fr;gap:2px}.xd-item{grid-template-columns:60px minmax(0,1fr)}.xd-item-price{grid-column:2/-1}}
    </style>
@endpush

@section('content')
        <main class="xd-page-main">
            <div class="xd-container">
                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">{{ $isEnglish ? 'Home' : 'Trang chÃ¡Â»Â§' }}</a>
                    <span>/</span>
                    <strong>{{ $isEnglish ? 'Order confirmed' : 'Ã„ÂÃ¡ÂºÂ·t hÃƒÂ ng thÃƒÂ nh cÃƒÂ´ng' }}</strong>
                </nav>

                <section class="xd-success-hero">
                    <span class="xd-kicker">{{ $isEnglish ? 'Success' : 'ThÃƒÂ nh cÃƒÂ´ng' }}</span>
                    <h1>{{ $isEnglish ? 'Your order has been received' : 'Ã„ÂÃ†Â¡n hÃƒÂ ng Ã„â€˜ÃƒÂ£ Ã„â€˜Ã†Â°Ã¡Â»Â£c ghi nhÃ¡ÂºÂ­n' }}</h1>
                    <p>{{ $isEnglish ? 'Our team will contact you shortly to confirm product information, delivery and payment.' : 'Ã„ÂÃ¡Â»â„¢i ngÃ…Â© tÃ†Â° vÃ¡ÂºÂ¥n sÃ¡ÂºÂ½ liÃƒÂªn hÃ¡Â»â€¡ lÃ¡ÂºÂ¡i Ã„â€˜Ã¡Â»Æ’ xÃƒÂ¡c nhÃ¡ÂºÂ­n thÃƒÂ´ng tin sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m, giao nhÃ¡ÂºÂ­n vÃƒÂ  phÃ†Â°Ã†Â¡ng thÃ¡Â»Â©c thanh toÃƒÂ¡n.' }}</p>
                </section>

                <section class="xd-success-grid">
                    <article class="xd-panel xd-order-panel">
                        <h2>{{ $isEnglish ? 'Order information' : 'ThÃƒÂ´ng tin Ã„â€˜Ã†Â¡n hÃƒÂ ng' }}</h2>
                        <div class="xd-order-lines">
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Order code' : 'MÃƒÂ£ Ã„â€˜Ã†Â¡n hÃƒÂ ng' }}</span>
                                <span class="xd-order-value">{{ $confirmedOrder->order_code }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Created at' : 'ThÃ¡Â»Âi gian tÃ¡ÂºÂ¡o' }}</span>
                                <span class="xd-order-value">{{ optional($confirmedOrder->placed_at ?? $confirmedOrder->created_at)->format('H:i d/m/Y') }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Customer' : 'KhÃƒÂ¡ch hÃƒÂ ng' }}</span>
                                <span class="xd-order-value">{{ $confirmedOrder->customer_name }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Phone' : 'SÃ¡Â»â€˜ Ã„â€˜iÃ¡Â»â€¡n thoÃ¡ÂºÂ¡i' }}</span>
                                <span class="xd-order-value">{{ $confirmedOrder->customer_phone }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">Email</span>
                                <span class="xd-order-value">{{ $confirmedOrder->customer_email ?: '...' }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Delivery address' : 'Ã„ÂÃ¡Â»â€¹a chÃ¡Â»â€° nhÃ¡ÂºÂ­n hÃƒÂ ng' }}</span>
                                <span class="xd-order-value">{{ $confirmedOrder->delivery_address }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Payment method' : 'PhÃ†Â°Ã†Â¡ng thÃ¡Â»Â©c thanh toÃƒÂ¡n' }}</span>
                                <span class="xd-order-value">{{ $confirmedOrder->payment_label }}</span>
                            </div>
                            <div class="xd-order-line">
                                <span class="xd-order-label">{{ $isEnglish ? 'Estimated total' : 'TÃ¡ÂºÂ¡m tÃƒÂ­nh' }}</span>
                                <span class="xd-order-value">{{ $formatCurrency($confirmedOrder->subtotal) }}</span>
                            </div>
                        </div>

                        <div class="xd-button-row">
                            <a class="xd-button" href="{{ route('site.home') }}">{{ $isEnglish ? 'Back home' : 'VÃ¡Â»Â trang chÃ¡Â»Â§' }}</a>
                            <a class="xd-button is-ghost" href="{{ route('customer.account') }}">{{ $isEnglish ? 'My account' : 'TÃƒÂ i khoÃ¡ÂºÂ£n cÃ¡Â»Â§a tÃƒÂ´i' }}</a>
                        </div>
                    </article>

                    <aside class="xd-panel xd-order-panel">
                        <h2>{{ $isEnglish ? 'Products' : 'SÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' }}</h2>
                        <div class="xd-items">
                            @foreach ($orderItems as $item)
                                <div class="xd-item">
                                    <span class="xd-item-thumb">
                                        @if (!empty($item->image_url))
                                            <img src="{{ $item->image_url }}" alt="{{ $item->product_name }}">
                                        @endif
                                    </span>
                                    <span>
                                        <strong class="xd-item-name">{{ $item->product_name }}</strong>
                                        <span class="xd-item-meta">x{{ (int) $item->quantity }}</span>
                                    </span>
                                    <span class="xd-item-price">{{ $formatCurrency($item->line_total) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </section>
            </div>
        </main>
@endsection
