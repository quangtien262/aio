@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $cartSummary = $shell['cart_summary'] ?? ['count' => 0];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760 / 0354.466.968');
    $contactEmail = data_get($branding, 'support_email', 'cs@th0001.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội');
    $searchQuery = (string) ($searchQuery ?? request('q', ''));
    $productCollection = collect($products ?? []);
    $pagination = $pagination ?? null;
    $searchCategories = collect($searchCategories ?? []);
    $searchFilters = array_merge([
        'q' => $searchQuery,
        'category' => '',
        'sort' => 'default',
        'min_price' => 0,
        'max_price' => 0,
        'available_min_price' => 0,
        'available_max_price' => 0,
    ], $searchFilters ?? []);
    $hasActiveFilters = filled($searchFilters['q'])
        || filled($searchFilters['category'])
        || ($searchFilters['sort'] ?? 'default') !== 'default'
        || (($searchFilters['available_min_price'] ?? 0) > 0 && (($searchFilters['min_price'] ?? 0) > ($searchFilters['available_min_price'] ?? 0)
            || ($searchFilters['max_price'] ?? 0) < ($searchFilters['available_max_price'] ?? 0)));
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $searchQuery !== '' ? 'Tìm kiếm: '.$searchQuery : 'Tìm kiếm sản phẩm' }} | {{ data_get($branding, 'company_name', 'TH0001') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-ink: #222;
                --th-muted: #757575;
                --th-line: #e6e6e6;
                --th-bg: #f6f6f8;
                --th-surface: #fff;
                --th-green: #79c400;
                --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
            }
            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            button { font: inherit; }
            .th-container { width: min(1200px, calc(100% - 24px)); margin: 0 auto; }
            .th-topbar { background: #f3f3f3; border-top: 3px solid #ff4f92; color: var(--th-muted); font-size: 12px; }
            .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
            .th-topbar-inner { padding: 6px 0; }
            .th-inline { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
            .th-inline-action { padding: 0; border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
            .th-inline-form { margin: 0; }
            .th-accent { color: var(--th-red); }
            .th-header { background: var(--th-surface); }
            .th-header-inner { padding: 12px 0; }
            .th-logo { display: flex; align-items: center; gap: 12px; min-width: 220px; }
            .th-logo img { width: 160px; height: 52px; object-fit: contain; }
            .th-logo-mark { display: flex; flex-direction: column; font-size: 12px; line-height: 1.35; color: #555; }
            .th-logo-mark strong { color: var(--th-red); font-size: 16px; }
            .th-search { flex: 1; display: grid; grid-template-columns: minmax(0, 1fr) 52px; border: 2px solid var(--th-red); border-radius: 4px; overflow: hidden; background: #fff; max-width: 720px; }
            .th-search input, .th-search button { border: 0; height: 44px; font-size: 14px; }
            .th-search input { padding: 0 14px; background: transparent; }
            .th-search button { background: var(--th-red); color: #fff; font-weight: 700; cursor: pointer; }
            .th-cart { min-width: 120px; display: flex; justify-content: flex-end; font-weight: 700; color: #5f5f5f; }
            .th-main-nav { background: var(--th-red); color: #fff; }
            .th-main-nav-inner { min-height: 42px; justify-content: flex-start; }
            .th-main-nav-categories-wrap { position: relative; }
            .th-main-nav-categories { background: rgba(0,0,0,0.08); min-width: 210px; padding: 11px 14px; font-weight: 700; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 28px; font-size: 14px; font-weight: 700; }
            .th-main-nav-menu a { padding: 11px 0; display: block; text-transform: uppercase; }
            .th-category-panel { position: absolute; top: 100%; left: 0; width: 220px; background: #fff; border: 1px solid var(--th-line); z-index: 30; display: none; }
            .th-main-nav-categories-wrap:hover .th-category-panel { display: block; }
            .th-sidebar-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 13px 14px; border-bottom: 1px solid var(--th-line); font-size: 14px; color: #4f4f4f; background: #fff; }
            .th-page { padding-bottom: 40px; }
            .breadcrumb { display: flex; align-items: center; gap: 8px; padding: 14px 0; color: #8a8a8a; font-size: 13px; }
            .search-hero { background: #fff; border: 1px solid var(--th-line); border-top: 4px solid var(--th-green); padding: 18px 20px; display: grid; gap: 8px; }
            .search-hero h1 { margin: 0; font-size: 28px; }
            .search-hero p { margin: 0; color: #777; line-height: 1.6; }
            .search-summary { color: #666; font-size: 14px; }
            .search-toolbar { margin-top: 16px; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; align-items: end; }
            .search-field { display: grid; gap: 6px; }
            .search-field span { font-size: 12px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: .04em; }
            .search-field input, .search-field select { width: 100%; min-height: 46px; border: 1px solid var(--th-line); border-radius: 12px; background: #fff; padding: 0 14px; font-size: 14px; }
            .search-field input[type="number"] { appearance: textfield; }
            .search-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
            .search-button, .search-reset { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; padding: 0 18px; border-radius: 999px; font-weight: 700; }
            .search-button { border: 0; background: var(--th-red); color: #fff; cursor: pointer; }
            .search-reset { border: 1px solid var(--th-line); background: #fff; color: #666; }
            .search-filter-chips { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
            .search-filter-chip { display: inline-flex; align-items: center; gap: 6px; min-height: 34px; padding: 0 12px; border-radius: 999px; background: #fff4f4; color: #b63232; font-size: 13px; font-weight: 700; }
            .product-grid { margin-top: 18px; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
            .product-card { background: #fff; border: 1px solid var(--th-line); overflow: hidden; transition: transform .18s ease, box-shadow .18s ease; }
            .product-card:hover { transform: translateY(-3px); box-shadow: var(--th-shadow); }
            .product-media { position: relative; aspect-ratio: 1 / 1; background: #f1f1f1; overflow: hidden; }
            .product-media img { width: 100%; height: 100%; object-fit: cover; }
            .product-badge { position: absolute; right: 10px; bottom: 10px; background: rgba(22,22,22,0.68); color: #fff; padding: 4px 8px; border-radius: 999px; font-size: 11px; }
            .product-body { padding: 12px 12px 14px; }
            .product-title { margin: 0 0 12px; min-height: 44px; font-size: 15px; line-height: 1.45; color: #2f2f2f; }
            .product-pricing { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
            .price { color: var(--th-red); font-size: 20px; font-weight: 900; }
            .discount { display: inline-flex; align-items: center; height: 24px; padding: 0 8px; border-radius: 6px; background: var(--th-red); color: #fff; font-size: 13px; font-weight: 800; }
            .old-price-row { display: flex; justify-content: space-between; align-items: center; margin-top: 6px; color: #a8a8a8; font-size: 13px; }
            .old-price { text-decoration: line-through; }
            .empty-state { margin-top: 18px; background: #fff; border: 1px solid var(--th-line); padding: 28px; text-align: center; color: #666; }
            .search-pagination { margin-top: 22px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
            .search-page-link { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--th-line); background: #fff; color: #555; font-weight: 700; }
            .search-page-link.is-disabled { opacity: .45; pointer-events: none; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: 1.2fr repeat(3, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }
            @media (max-width: 1100px) { .product-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-main-nav-menu { gap: 16px; overflow-x: auto; }
                .th-main-nav-categories { min-width: 0; width: 100%; }
                .search-toolbar { grid-template-columns: 1fr; }
                .product-grid, .th-footer-grid { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <div class="th-page">
            <div class="th-topbar">
                <div class="th-container th-topbar-inner">
                    <div class="th-inline">
                        <span>📍 {{ $contactLocation }}</span>
                        <button type="button" class="th-inline-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? '📩 Đã đăng ký bản tin' : '📩 Đăng ký bản tin' }}</button>
                    </div>
                    <div class="th-inline">
                        <span>📞 Hotline: <span class="th-accent">{{ $contactHotline }}</span></span>
                        <span>✉ Email: {{ $contactEmail }}</span>
                        @if (!empty($customerAuth['is_authenticated']))
                            <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Tài khoản</a>
                            <form class="th-inline-form" method="POST" action="{{ $customerAuth['logout_url'] ?? route('customer.auth.logout') }}">
                                @csrf
                                <button type="submit" class="th-inline-action">Đăng xuất</button>
                            </form>
                        @else
                            <button type="button" class="th-inline-action" data-open-auth-modal="register">Đăng ký</button>
                            <button type="button" class="th-inline-action" data-open-auth-modal="login">Đăng nhập</button>
                        @endif
                    </div>
                </div>
            </div>

            <header class="th-header">
                <div class="th-container th-header-inner">
                    <a class="th-logo" href="{{ route('site.home') }}">
                        <img src="{{ data_get($branding, 'logo_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}" alt="{{ data_get($branding, 'company_name', 'Website logo') }}">
                        <span class="th-logo-mark">
                            <strong>{{ data_get($branding, 'company_name', data_get($siteProfile, 'site_name', 'AIO Commerce')) }}</strong>
                        </span>
                    </a>
                    <form class="th-search" method="GET" action="{{ route('site.catalog.search') }}" role="search">
                        <input type="search" name="q" value="{{ $searchQuery }}" placeholder="Tìm kiếm sản phẩm / khuyến mãi" aria-label="Tìm kiếm sản phẩm" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                        <button type="submit">Tìm</button>
                    </form>
                    <a class="th-cart" href="{{ route('site.cart.index') }}">🛒 {{ $cartSummary['count'] ?? 0 }} GIỎ HÀNG</a>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <div class="th-main-nav-categories-wrap">
                        <div class="th-main-nav-categories">DANH MỤC</div>
                        <div class="th-category-panel">
                            @foreach ($productMenu as $item)
                                <a href="{{ $item['url'] ?? '#' }}" class="th-sidebar-item">{{ $item['label'] ?? 'Danh mục' }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="th-main-nav-menu">
                        @foreach ($topMenu as $item)
                            <a href="{{ $item['url'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] ?? 'Menu' }}</a>
                        @endforeach
                    </div>
                </div>
            </nav>

            <main>
                <div class="th-container">
                    <div class="breadcrumb">
                        <a href="{{ route('site.home') }}">Trang chủ</a>
                        <span>/</span>
                        <span>Tìm kiếm sản phẩm</span>
                    </div>

                    <section class="search-hero">
                        <h1>{{ $searchQuery !== '' ? 'Kết quả cho "'.$searchQuery.'"' : 'Tìm kiếm sản phẩm' }}</h1>
                        <p>Tìm theo tên sản phẩm, SKU hoặc nội dung mô tả để điều hướng nhanh tới deal phù hợp.</p>
                        <div class="search-summary">Tìm thấy {{ $resultCount ?? $productCollection->count() }} sản phẩm.</div>

                        <form method="GET" action="{{ route('site.catalog.search') }}" class="search-toolbar">
                            <label class="search-field">
                                <span>Từ khóa</span>
                                <input type="search" name="q" value="{{ $searchFilters['q'] ?? '' }}" placeholder="Tên sản phẩm, SKU, mô tả" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                            </label>
                            <label class="search-field">
                                <span>Danh mục</span>
                                <select name="category">
                                    <option value="">Tất cả danh mục</option>
                                    @foreach ($searchCategories as $category)
                                        <option value="{{ $category->slug }}" @selected(($searchFilters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="search-field">
                                <span>Sắp xếp</span>
                                <select name="sort">
                                    <option value="default" @selected(($searchFilters['sort'] ?? 'default') === 'default')>Mặc định</option>
                                    <option value="newest" @selected(($searchFilters['sort'] ?? '') === 'newest')>Mới nhất</option>
                                    <option value="price_asc" @selected(($searchFilters['sort'] ?? '') === 'price_asc')>Giá thấp trước</option>
                                    <option value="price_desc" @selected(($searchFilters['sort'] ?? '') === 'price_desc')>Giá cao trước</option>
                                    <option value="bestseller" @selected(($searchFilters['sort'] ?? '') === 'bestseller')>Bán chạy</option>
                                </select>
                            </label>
                            <label class="search-field">
                                <span>Giá từ</span>
                                <input type="number" min="0" step="1000" name="min_price" value="{{ $searchFilters['min_price'] ?? 0 }}" placeholder="0">
                            </label>
                            <label class="search-field">
                                <span>Giá đến</span>
                                <input type="number" min="0" step="1000" name="max_price" value="{{ $searchFilters['max_price'] ?? 0 }}" placeholder="0">
                            </label>
                            <div class="search-actions">
                                <button type="submit" class="search-button">Áp dụng</button>
                                @if ($hasActiveFilters)
                                    <a href="{{ route('site.catalog.search') }}" class="search-reset">Xóa lọc</a>
                                @endif
                            </div>
                        </form>

                        @if ($hasActiveFilters)
                            <div class="search-filter-chips">
                                @if (filled($searchFilters['q'] ?? ''))
                                    <span class="search-filter-chip">Từ khóa: {{ $searchFilters['q'] }}</span>
                                @endif
                                @if (filled($searchFilters['category'] ?? ''))
                                    <span class="search-filter-chip">Danh mục: {{ optional($searchCategories->firstWhere('slug', $searchFilters['category']))->name ?? $searchFilters['category'] }}</span>
                                @endif
                                @if (($searchFilters['sort'] ?? 'default') !== 'default')
                                    <span class="search-filter-chip">Sắp xếp: {{ match($searchFilters['sort']) { 'newest' => 'Mới nhất', 'price_asc' => 'Giá thấp trước', 'price_desc' => 'Giá cao trước', 'bestseller' => 'Bán chạy', default => 'Mặc định' } }}</span>
                                @endif
                                @if (($searchFilters['available_max_price'] ?? 0) > 0)
                                    <span class="search-filter-chip">Giá: {{ number_format((int) ($searchFilters['min_price'] ?? 0), 0, ',', '.') }}đ - {{ number_format((int) ($searchFilters['max_price'] ?? 0), 0, ',', '.') }}đ</span>
                                @endif
                            </div>
                        @endif
                    </section>

                    @if ($productCollection->isNotEmpty())
                        <section class="product-grid">
                            @foreach ($productCollection as $product)
                                <article class="product-card">
                                    <a href="{{ $product['url'] }}">
                                        <div class="product-media">
                                            <img src="{{ $product['image'] }}" alt="{{ $product['title'] }}">
                                            <span class="product-badge">{{ $product['tag'] }}</span>
                                        </div>
                                        <div class="product-body">
                                            <h3 class="product-title">{{ $product['title'] }}</h3>
                                            <div class="product-pricing">
                                                <span class="price">{{ $formatCurrency($product['price']) }}</span>
                                                @if (($product['discount'] ?? 0) > 0)
                                                    <span class="discount">-{{ (int) $product['discount'] }}%</span>
                                                @endif
                                            </div>
                                            <div class="old-price-row">
                                                <span class="old-price">{{ $product['old_price'] ? $formatCurrency($product['old_price']) : 'Giá tốt hôm nay' }}</span>
                                                <span>{{ (int) ($product['meta'] ?? 0) }} SP</span>
                                            </div>
                                        </div>
                                    </a>
                                </article>
                            @endforeach
                        </section>

                        @if ($pagination && (method_exists($pagination, 'previousPageUrl') || method_exists($pagination, 'nextPageUrl')))
                            <div class="search-pagination">
                                <a href="{{ $pagination->previousPageUrl() ?: '#' }}" class="search-page-link {{ $pagination->previousPageUrl() ? '' : 'is-disabled' }}">Trang trước</a>
                                <span>Trang {{ $pagination->currentPage() }} / {{ $pagination->lastPage() }}</span>
                                <a href="{{ $pagination->nextPageUrl() ?: '#' }}" class="search-page-link {{ $pagination->nextPageUrl() ? '' : 'is-disabled' }}">Trang sau</a>
                            </div>
                        @endif
                    @else
                        <section class="empty-state">
                            <h3>Chưa có sản phẩm phù hợp</h3>
                            <p>Hãy thử từ khóa khác như tên sản phẩm, SKU hoặc danh mục.</p>
                        </section>
                    @endif
                </div>
            </main>

            <footer class="th-footer">
                <div class="th-container th-footer-inner">
                    <div class="th-footer-grid">
                        <div class="th-company">
                            <strong>{{ data_get($branding, 'company_name', $siteProfile?->site_name ?? 'AIO Website') }}</strong>
                            <div>{{ $contactLocation }}</div>
                            <div>Hotline: {{ $contactHotline }}</div>
                            <div>Email: {{ $contactEmail }}</div>
                        </div>
                        <div class="th-footer-card">
                            <h4>Khám phá</h4>
                            <div class="th-footer-links">
                                <a href="{{ route('site.blog.index') }}">Tin tức</a>
                                <a href="/gioi-thieu">Giới thiệu</a>
                                <a href="/lien-he">Liên hệ</a>
                            </div>
                        </div>
                        <div class="th-footer-card">
                            <h4>Tài khoản</h4>
                            <div class="th-footer-links">
                                @if (!empty($customerAuth['is_authenticated']))
                                    <a href="{{ $customerAuth['account_url'] ?? route('customer.account') }}">Trang tài khoản</a>
                                @else
                                    <button type="button" class="th-inline-action" data-open-auth-modal="login">Đăng nhập</button>
                                    <button type="button" class="th-inline-action" data-open-auth-modal="register">Đăng ký</button>
                                @endif
                            </div>
                        </div>
                        <div class="th-footer-card">
                            <h4>Bản tin</h4>
                            <div class="th-footer-links">
                                <button type="button" class="th-inline-action" data-open-newsletter-modal>{{ $newsletterState['is_subscribed'] ? 'Đã đăng ký' : 'Đăng ký nhận bản tin' }}</button>
                                <span>Cập nhật ưu đãi và nội dung mới mỗi tuần.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        @include('theme-th0001::partials.product-search-autocomplete')
        @include('theme-th0001::partials.engagement-modals')
    </body>
</html>
