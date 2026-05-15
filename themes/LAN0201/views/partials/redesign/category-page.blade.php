<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $category->name }} | {{ data_get($branding, 'company_name', 'LAN0201') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-lan0201::partials.landing-style', ['branding' => $branding])
            .th-category-toolbar { display:grid; gap:14px; }
            .th-category-toolbar form { display:grid; gap:14px; }
            .th-category-links { display:flex; gap:10px; flex-wrap:wrap; }
        </style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', [
                    'branding' => $branding,
                    'topMenu' => $topMenu,
                    'customerAuth' => $customerAuth,
                    'newsletterState' => $newsletterState,
                    'cartSummary' => $cartSummary,
                    'contactHotline' => $contactHotline,
                    'contactEmail' => $contactEmail,
                    'contactLocation' => $contactLocation,
                ])

                <main class="th-landing-main">
                    <div class="th-landing-container">
                        <div class="th-landing-breadcrumb">
                            <a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $category->name }}</span>
                        </div>

                        <section class="th-landing-hero">
                            <div class="th-landing-hero-grid">
                                <div>
                                    <span class="th-landing-kicker">Bảng hàng theo phân khu</span>
                                    <h1 class="th-landing-title">{{ $category->name }}</h1>
                                    <p class="th-landing-summary">{{ $categoryIntro }}</p>
                                    <div class="th-landing-chip-row">
                                        @foreach ($categoryLinks->take(5) as $link)
                                            <a href="{{ $link['url'] }}" class="th-landing-chip">{{ $link['label'] }} ({{ $link['count'] }})</a>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="th-landing-panel th-category-toolbar">
                                    <span class="th-landing-kicker">Lọc bảng hàng</span>
                                    <form method="GET" action="{{ route('site.catalog.category', ['slug' => $category->slug]) }}">
                                        <div class="th-landing-form-grid">
                                            <div class="th-landing-field">
                                                <label for="sort">Sắp xếp</label>
                                                <select id="sort" name="sort">
                                                    @foreach ($sortOptions as $sortOption)
                                                        <option value="{{ $sortOption['value'] }}" @selected($selectedSort === $sortOption['value'])>{{ $sortOption['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="th-landing-field">
                                                <label for="min_price">Giá từ</label>
                                                <input id="min_price" type="number" name="min_price" value="{{ $selectedMinPrice }}">
                                            </div>
                                            <div class="th-landing-field">
                                                <label for="max_price">Giá đến</label>
                                                <input id="max_price" type="number" name="max_price" value="{{ $selectedMaxPrice }}">
                                            </div>
                                        </div>
                                        <div class="th-landing-actions-row">
                                            <button type="submit" class="th-landing-button">Cập nhật bảng hàng</button>
                                            <a href="{{ route('site.catalog.category', ['slug' => $category->slug]) }}" class="th-landing-outline">Bỏ lọc</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </section>

                        <section style="margin-top:26px;">
                            @if ($productCollection->isEmpty())
                                <div class="th-landing-empty">Chưa có sản phẩm phù hợp với bộ lọc hiện tại.</div>
                            @else
                                <div class="th-landing-grid-listings">
                                    @foreach ($productCollection as $product)
                                        <article class="th-landing-listing">
                                            <a href="{{ $product['url'] ?? '#' }}">
                                                <div class="th-landing-listing-media">
                                                    <img src="{{ $product['image'] ?? 'https://picsum.photos/seed/lan0201-category/'.($loop->index + 1).'/720/900' }}" alt="{{ $product['title'] ?? 'Listing' }}">
                                                    <div class="th-landing-listing-badge th-landing-chip">{{ $product['tag'] ?? $category->name }}</div>
                                                </div>
                                            </a>
                                            <div class="th-landing-listing-body">
                                                <h3 class="th-landing-listing-title"><a href="{{ $product['url'] ?? '#' }}">{{ $product['title'] ?? 'Căn hộ mở bán' }}</a></h3>
                                                <div class="th-landing-price">
                                                    <strong>{{ $formatCurrency($product['price'] ?? null) }}</strong>
                                                    @if (!empty($product['original_price']))<span>{{ $formatCurrency($product['original_price']) }}</span>@endif
                                                </div>
                                                <div class="th-landing-copy">{{ $product['summary'] ?? 'Nội dung được map từ short_description và detail_content của CatalogProduct.' }}</div>
                                                <div class="th-landing-meta-row">
                                                    @if (!empty($product['sku']))<span class="th-landing-chip">{{ $product['sku'] }}</span>@endif
                                                    @if (!empty($product['usage_location']))<span class="th-landing-pill">{{ \Illuminate\Support\Str::limit($product['usage_location'], 26) }}</span>@endif
                                                    <span class="th-landing-tag">{{ number_format((int) ($product['meta'] ?? 0), 0, ',', '.') }} căn</span>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    </div>
                </main>

                @include('theme-lan0201::partials.landing-footer', ['branding' => $branding, 'footerColumns' => $footerColumns])
            </div>
        </div>

        @include('theme-lan0201::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>