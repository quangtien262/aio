<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $searchQuery !== '' ? 'Tìm kiếm: '.$searchQuery : 'Tìm kiếm bất động sản' }} | {{ data_get($branding, 'company_name', 'LAN0201') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>@include('theme-lan0201::partials.landing-style', ['branding' => $branding])</style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', ['branding' => $branding, 'topMenu' => $topMenu, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'cartSummary' => $cartSummary, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation])
                <main class="th-landing-main">
                    <div class="th-landing-container">
                        <section class="th-landing-hero">
                            <div class="th-landing-hero-grid">
                                <div>
                                    <span class="th-landing-kicker">Bộ lọc lead tìm kiếm</span>
                                    <h1 class="th-landing-title">{{ $searchQuery !== '' ? 'Kết quả cho "'.$searchQuery.'"' : 'Khám phá bảng hàng LAN0201' }}</h1>
                                    <p class="th-landing-summary">Trang search cũng được chuyển thành một landing-hub: tìm nhanh, đọc insight và vào thẳng card listing mà không mang khung e-commerce cũ.</p>
                                </div>
                                <div class="th-landing-stats">
                                    @foreach ($searchInsightCards as $card)
                                        <div class="th-landing-stat"><strong>{{ $card['value'] }}</strong><span>{{ $card['label'] }}</span></div>
                                    @endforeach
                                </div>
                            </div>
                            <form method="GET" action="{{ route('site.catalog.search') }}" style="margin-top:20px;">
                                <div class="th-landing-form-grid">
                                    <div class="th-landing-field">
                                        <label for="q">Tìm kiếm</label>
                                        <input id="q" type="search" name="q" value="{{ $searchFilters['q'] }}" placeholder="Dự án, loại hình, mã căn...">
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="category">Nhóm sản phẩm</label>
                                        <select id="category" name="category">
                                            <option value="">Tất cả</option>
                                            @foreach ($searchCategories as $searchCategory)
                                                <option value="{{ $searchCategory['slug'] ?? $searchCategory['id'] ?? '' }}" @selected(($searchFilters['category'] ?? '') === ($searchCategory['slug'] ?? $searchCategory['id'] ?? ''))>{{ $searchCategory['name'] ?? $searchCategory['label'] ?? 'Danh mục' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="sort">Sắp xếp</label>
                                        <select id="sort" name="sort">
                                            <option value="default" @selected(($searchFilters['sort'] ?? 'default') === 'default')>Mặc định</option>
                                            <option value="bestseller" @selected(($searchFilters['sort'] ?? '') === 'bestseller')>Quan tâm nhiều</option>
                                            <option value="price_asc" @selected(($searchFilters['sort'] ?? '') === 'price_asc')>Giá tăng dần</option>
                                            <option value="price_desc" @selected(($searchFilters['sort'] ?? '') === 'price_desc')>Giá giảm dần</option>
                                        </select>
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="min_price">Giá từ</label>
                                        <input id="min_price" type="number" name="min_price" value="{{ $searchFilters['min_price'] }}">
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="max_price">Giá đến</label>
                                        <input id="max_price" type="number" name="max_price" value="{{ $searchFilters['max_price'] }}">
                                    </div>
                                </div>
                                <div class="th-landing-actions-row">
                                    <button type="submit" class="th-landing-button">Tìm bảng hàng</button>
                                    <a href="{{ route('site.catalog.search') }}" class="th-landing-outline">Reset</a>
                                </div>
                            </form>
                        </section>

                        <section style="margin-top:26px;">
                            @if ($productCollection->isEmpty())
                                <div class="th-landing-empty">Không có kết quả phù hợp. Thử đổi từ khóa hoặc bộ lọc.</div>
                            @else
                                <div class="th-landing-grid-listings">
                                    @foreach ($productCollection as $product)
                                        <article class="th-landing-listing">
                                            <a href="{{ $product['url'] ?? '#' }}">
                                                <div class="th-landing-listing-media"><img src="{{ $product['image'] ?? 'https://picsum.photos/seed/lan0201-search/'.($loop->index + 1).'/720/900' }}" alt="{{ $product['title'] ?? 'Listing' }}"></div>
                                            </a>
                                            <div class="th-landing-listing-body">
                                                <h3 class="th-landing-listing-title"><a href="{{ $product['url'] ?? '#' }}">{{ $product['title'] ?? 'Căn hộ mở bán' }}</a></h3>
                                                <div class="th-landing-price"><strong>{{ $formatCurrency($product['price'] ?? null) }}</strong></div>
                                                <div class="th-landing-copy">{{ $product['summary'] ?? 'Thông tin lead và tóm tắt vị trí được hiển thị trực tiếp trên card.' }}</div>
                                                <div class="th-landing-meta-row">
                                                    @if (!empty($product['sku']))<span class="th-landing-chip">{{ $product['sku'] }}</span>@endif
                                                    <span class="th-landing-pill">{{ $product['tag'] ?? 'Mở bán' }}</span>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    </div>
                </main>
                @include('theme-lan0201::partials.landing-footer', ['branding' => $branding])
            </div>
        </div>

        @include('theme-lan0201::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect ?? request()->fullUrl()])
    </body>
</html>