@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? 'admin@htvietnam.vn'));
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM'));
    $gallery = $productGallery ?? [];
    $primaryImage = $gallery[0]['url'] ?? ($product['image'] ?? 'https://picsum.photos/seed/xd0308-product/1200/900');
    $highlights = $productHighlights ?? [];
    $detailParagraphsList = $detailParagraphs ?? [];
    $detailHtml = trim((string) ($productModel->detail_content ?? ''));
    $seoTitle = trim((string) ($productModel->meta_title ?? '')) ?: ($product['title'] ?? $logoAlt);
    $seoDescription = trim((string) ($productModel->meta_description ?? '')) ?: trim((string) ($productModel->short_description ?? ''));
    $seoKeywords = trim((string) ($productModel->meta_keywords ?? ''));
    $usageTermsList = $usageTerms ?? [];
    $relatedProducts = $relatedProducts ?? [];
    $discount = (int) ($product['discount'] ?? 0);
    $isOutOfStock = $productModel->stock !== null && (int) $productModel->stock <= 0;
    $formatCurrency = fn ($value) => $value === null || (float) $value <= 0 ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    if ($highlights === [] && filled($productModel->short_description)) {
        $highlights = [trim((string) $productModel->short_description)];
    }

    if ($detailParagraphsList === []) {
        $detailParagraphsList = [
            $productModel->detail_content ?: ($productModel->short_description ?: 'Thông tin sản phẩm đang được cập nhật.'),
        ];
    }

    if ($detailHtml === '') {
        $detailHtml = collect($detailParagraphsList)
            ->map(fn ($paragraph): string => '<p>'.e((string) $paragraph).'</p>')
            ->implode('');
    }

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
            'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0308', app()->getLocale(), 'legacy_inline.4c23dc9bef7f79b4', 'Trang chủ'),
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

    if (false && ! $hasProductItem && \Illuminate\Support\Facades\Schema::hasTable('catalog_categories') && \Illuminate\Support\Facades\Schema::hasTable('catalog_products')) {
        $productCategories = \App\Models\CatalogCategory::query()
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->withCount(['products' => fn ($productQuery) => $productQuery->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category): bool => (int) $category->products_count > 0 || $category->children->contains(fn ($child): bool => (int) $child->products_count > 0))
            ->take(8)
            ->values();

        if ($productCategories->isNotEmpty()) {
            $productMenuItem = [
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0308', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productCategories
                    ->map(fn ($category): array => [
                        'label' => (string) $category->name,
                        'href' => route('site.catalog.category', ['slug' => $category->slug]),
                        'target' => '_self',
                        'active' => false,
                        'children' => $category->children
                            ->filter(fn ($child): bool => (int) $child->products_count > 0)
                            ->take(8)
                            ->map(fn ($child): array => [
                                'label' => (string) $child->name,
                                'href' => route('site.catalog.category', ['slug' => $child->slug]),
                                'target' => '_self',
                                'active' => false,
                                'children' => [],
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
            $navItems = collect($navArray);
        }
    }

    $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems
                ->map(function (array $item) use ($productNavigationItems): array {
                    $label = mb_strtolower(trim((string) ($item['label'] ?? '')));

                    if (in_array($label, ['sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                        $item['children'] = $productNavigationItems->all();
                    }

                    return $item;
                })
                ->values();
        } else {
            $productMenuItem = [
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0308', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
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
    $footerNewsletterSource = 'theme-footer-xd0308-product';
@endphp

@extends('theme-xd0308::layout')

@section('title', $seoTitle.' | '.$logoAlt)

@push('head')
    @if ($seoDescription !== '')
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    @if ($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <style>
        .xd-page-main{padding:46px 0 88px;background:var(--bg)}
        .xd-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:28px;color:var(--muted);font-size:14px;font-weight:750}.xd-breadcrumb a:hover{color:var(--lime-dark)}
        .xd-product-hero{display:grid;grid-template-columns:minmax(0,1fr) minmax(420px,.75fr);gap:42px;align-items:start}.xd-gallery-panel,.xd-info-panel,.xd-panel{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.07)}
        .xd-gallery-stage{height:min(680px,60vw);min-height:420px;background:#eef2ef;overflow:hidden}.xd-gallery-stage img{width:100%;height:100%;object-fit:cover}.xd-thumbs{display:flex;gap:12px;overflow:auto;padding:14px}.xd-thumb{flex:0 0 92px;width:92px;height:74px;padding:0;border:2px solid transparent;background:#eef2ef;cursor:pointer}.xd-thumb.is-active{border-color:var(--lime)}.xd-thumb img{width:100%;height:100%;object-fit:cover}
        .xd-info-panel{padding:44px}.xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-info-panel h1{margin:0 0 18px;font-size:clamp(38px,4vw,64px);line-height:1.06;letter-spacing:-.055em}.xd-summary{margin:0 0 24px;color:var(--muted);font-size:19px;font-weight:600}
        .xd-product-meta{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px}.xd-chip{display:inline-flex;align-items:center;min-height:36px;padding:0 14px;border:1px solid var(--line);border-radius:999px;background:#fbfcfa;color:var(--muted);font-size:13px;font-weight:850;text-transform:uppercase}.xd-price-box{padding:24px 0;margin-bottom:22px;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}.xd-price-row{display:flex;align-items:center;gap:14px;flex-wrap:wrap}.xd-price{color:#9a6a3e;font-size:42px;font-weight:950;letter-spacing:-.04em}.xd-old-price{color:#9aa3a9;font-size:24px;text-decoration:line-through}.xd-discount{display:inline-flex;align-items:center;height:30px;padding:0 10px;border-radius:999px;background:var(--ink);color:#fff;font-weight:900}
        .xd-purchase-form{display:grid;gap:18px}.xd-quantity{display:flex;align-items:center;gap:12px;color:var(--muted);font-weight:850}.xd-quantity select{height:44px;min-width:86px;border:1px solid var(--line);padding:0 12px;background:#fff}.xd-cta-row{display:flex;flex-wrap:wrap;gap:12px}.xd-btn{display:inline-flex;align-items:center;justify-content:center;min-height:54px;padding:0 24px;border:0;border-radius:3px;font:inherit;font-weight:950;text-transform:uppercase;cursor:pointer}.xd-btn-primary{background:var(--lime);color:#fff;box-shadow:0 15px 30px rgba(189,212,0,.28)}.xd-btn-dark{background:var(--ink);color:#fff}.xd-btn-outline{background:#fff;color:var(--ink);border:1px solid var(--line)}.xd-btn:hover{transform:translateY(-1px)}.xd-btn[disabled]{opacity:.55;cursor:not-allowed;transform:none}.xd-stock{color:var(--muted);font-size:14px;font-weight:800}
        .xd-content-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:34px;margin-top:42px}.xd-panel{padding:34px}.xd-panel h2,.xd-panel h3{margin:0 0 18px;font-size:30px;line-height:1.2;letter-spacing:-.035em}.xd-rich{color:#465461;font-size:18px}.xd-rich p{margin:0 0 16px}.xd-rich figure{margin:22px 0}.xd-rich img{max-width:100%;height:auto;border-radius:14px;box-shadow:0 18px 38px rgba(16,29,40,.12)}
        .xd-list{margin:0;padding:0;list-style:none;display:grid;gap:12px}.xd-list li{position:relative;padding-left:24px;color:#465461;font-weight:650}.xd-list li:before{content:"";position:absolute;left:0;top:.65em;width:9px;height:9px;background:var(--lime)}.xd-side-stack{display:grid;gap:18px}.xd-cart-message{margin-bottom:24px;border-left:5px solid var(--lime);font-weight:850}.xd-cart-message.is-error{border-left-color:#dc3545;background:#fff8f8;color:#9b1c31}.xd-out-of-stock{display:grid;gap:14px;margin-top:6px}.xd-stock-alert{padding:16px 18px;border:1px solid #f1c9cf;background:#fff8f8;color:#9b1c31;font-weight:850}
        .xd-section-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-top:64px}.xd-section-head h2{margin:0;font-size:40px;line-height:1.1;letter-spacing:-.04em}.xd-related{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px;margin-top:42px}.xd-related-card{background:#fff;border:1px solid var(--line);box-shadow:0 12px 34px rgba(16,29,40,.06);overflow:hidden}.xd-related-card img{width:100%;height:220px;object-fit:cover}.xd-related-card div{padding:18px}.xd-related-card h3{margin:0 0 10px;font-size:17px;line-height:1.35;text-transform:uppercase}.xd-related-price{color:#9a6a3e;font-size:20px;font-weight:950}
        @media (max-width:1180px){.xd-product-hero,.xd-content-grid{grid-template-columns:1fr}.xd-related{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width:640px){.xd-page-main{padding:28px 0 54px}.xd-gallery-stage{height:360px;min-height:0}.xd-info-panel,.xd-panel{padding:24px}.xd-info-panel h1{font-size:34px}.xd-price{font-size:32px}.xd-cta-row{display:grid}.xd-btn{width:100%}.xd-related{grid-template-columns:1fr}}
    </style>
@endpush

@section('content')
        <main class="xd-page-main">
            <div class="xd-container">
                @if (session('cart_success'))
                    <div class="xd-panel xd-cart-message">{{ session('cart_success') }}</div>
                @endif
                @if (session('cart_error') || $errors->has('cart'))
                    <div class="xd-panel xd-cart-message is-error">{{ session('cart_error') ?: $errors->first('cart') }}</div>
                @endif

                <nav class="xd-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a>
                    @if ($productModel->category?->parent)
                        <span>/</span>
                        <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->parent->slug]) }}">{{ $productModel->category->parent->name }}</a>
                    @endif
                    @if ($productModel->category)
                        <span>/</span>
                        <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->slug]) }}">{{ $productModel->category->name }}</a>
                    @endif
                    <span>/</span>
                    <span>{{ $product['title'] }}</span>
                </nav>

                <section class="xd-product-hero">
                    <div class="xd-gallery-panel">
                        <div class="xd-gallery-stage">
                            <img id="xd-product-main-image" src="{{ $primaryImage }}" alt="{{ $product['title'] }}">
                        </div>
                        @if (count($gallery) > 1)
                            <div class="xd-thumbs" aria-label="Gallery ảnh sản phẩm">
                                @foreach ($gallery as $index => $image)
                                    <button type="button" class="xd-thumb {{ $index === 0 ? 'is-active' : '' }}" data-xd-thumb data-image-url="{{ $image['url'] }}" data-image-alt="{{ $image['alt'] }}">
                                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <article class="xd-info-panel">
                        <span class="xd-kicker">{{ $productModel->category?->name ?: 'Sản phẩm' }}</span>
                        <h1>{{ $product['title'] }}</h1>
                        <p class="xd-summary">{!! nl2br(e($productModel->short_description ?: 'Giải pháp vật tư và nội thất cho công trình hiện đại.')) !!}</p>

                        <div class="xd-product-meta">
                            @if ($productModel->sku)
                                <span class="xd-chip">SKU {{ $productModel->sku }}</span>
                            @endif
                            <span class="xd-chip">Tồn kho {{ number_format((int) ($product['meta'] ?? 0), 0, ',', '.') }}</span>
                            @if ($productModel->category)
                                <span class="xd-chip">{{ $productModel->category->name }}</span>
                            @endif
                        </div>

                        <div class="xd-price-box">
                            <div class="xd-price-row">
                                <span class="xd-price">{{ $formatCurrency($product['price'] ?? null) }}</span>
                                @if (($product['old_price'] ?? null) !== null)
                                    <span class="xd-old-price">{{ $formatCurrency($product['old_price']) }}</span>
                                @endif
                                @if ($discount > 0)
                                    <span class="xd-discount">-{{ $discount }}%</span>
                                @endif
                            </div>
                        </div>

                        @if ($isOutOfStock)
                            <div class="xd-out-of-stock">
                                <div class="xd-stock-alert">Sản phẩm hiện đã hết hàng. Vui lòng liên hệ hotline {{ $hotline }} để được tư vấn.</div>
                                <div class="xd-cta-row">
                                    <button type="button" class="xd-btn xd-btn-dark" disabled>Tạm hết hàng</button>
                                    <a class="xd-btn xd-btn-primary" href="tel:{{ $phoneHref }}">Liên hệ ngay</a>
                                    @if (!empty($themeShellData['customer_auth']['is_authenticated']))
                                        <form method="POST" action="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">
                                            @csrf
                                            <button type="submit" class="xd-btn xd-btn-outline">{{ !empty($isFavorite) ? 'Đã lưu' : 'Lưu yêu thích' }}</button>
                                        </form>
                                    @else
                                        <button type="button" class="xd-btn xd-btn-outline" data-xd-auth-open="login">Đăng nhập để lưu</button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <form class="xd-purchase-form" method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}">
                                @csrf
                                <input type="hidden" name="quantity" value="1">
                                <div class="xd-cta-row">
                                    <button type="submit" class="xd-btn xd-btn-primary" formaction="{{ route('site.cart.buy_now', ['slug' => $productModel->slug]) }}">Mua ngay</button>
                                    <button type="submit" class="xd-btn xd-btn-dark">Thêm vào giỏ</button>
                                    @if (!empty($themeShellData['customer_auth']['is_authenticated']))
                                        <button type="submit" class="xd-btn xd-btn-outline" formaction="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">{{ !empty($isFavorite) ? 'Đã lưu' : 'Lưu yêu thích' }}</button>
                                    @else
                                        <button type="button" class="xd-btn xd-btn-outline" data-xd-auth-open="login">Đăng nhập để lưu</button>
                                    @endif
                                </div>
                                <div class="xd-stock">Tư vấn kỹ thuật qua hotline {{ $hotline }} trước khi đặt hàng số lượng lớn.</div>
                            </form>
                        @endif
                    </article>
                </section>

                <section class="xd-content-grid">
                    <article class="xd-panel">
                        <h2>Thông tin chi tiết</h2>
                        <div class="xd-rich">
                            {!! $detailHtml !!}
                        </div>
                    </article>

                    <aside class="xd-side-stack">
                        <section class="xd-panel">
                            <h3>Điểm nổi bật</h3>
                            <ul class="xd-list">
                                @forelse ($highlights as $item)
                                    <li>{{ $item }}</li>
                                @empty
                                    <li>Phù hợp cho công trình dân dụng và thương mại.</li>
                                    <li>Dễ phối hợp với quy trình tư vấn, báo giá và thi công.</li>
                                @endforelse
                            </ul>
                        </section>
                        @if ($usageTermsList !== [])
                            <section class="xd-panel">
                                <h3>Lưu ý sử dụng</h3>
                                <ul class="xd-list">
                                    @foreach ($usageTermsList as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    </aside>
                </section>

                @if (!empty($relatedProducts))
                    <div class="xd-section-head">
                        <div>
                            <span class="xd-kicker">Gợi ý thêm</span>
                            <h2>Sản phẩm liên quan</h2>
                        </div>
                    </div>
                    <section class="xd-related">
                        @foreach (collect($relatedProducts)->take(4) as $related)
                            <article class="xd-related-card">
                                <a href="{{ $related['url'] ?? '#' }}">
                                    <img src="{{ $related['image'] ?? 'https://picsum.photos/seed/xd0308-related/640/420' }}" alt="{{ $related['title'] ?? 'Sản phẩm' }}">
                                    <div>
                                        <h3>{{ $related['title'] ?? 'Sản phẩm' }}</h3>
                                        <span class="xd-related-price">{{ $formatCurrency($related['price'] ?? null) }}</span>
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </section>
                @endif
            </div>
        </main>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-xd-thumb]').forEach((button) => {
            button.addEventListener('click', () => {
                const image = document.getElementById('xd-product-main-image');
                if (!image) return;
                image.src = button.dataset.imageUrl || image.src;
                image.alt = button.dataset.imageAlt || image.alt;
                document.querySelectorAll('[data-xd-thumb]').forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
            });
        });
    </script>
@endpush
