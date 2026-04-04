@php
    $themeShellData = $themeShellData ?? [];
    $branding = $themeShellData['branding'] ?? [];
    $topMenu = $themeShellData['top_menu'] ?? [];
    $cartSummary = $themeShellData['cart_summary'] ?? ['count' => 0];
    $customerAuth = $themeShellData['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $themeShellData['newsletter'] ?? ['is_subscribed' => false];
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', config('mail.from.address', 'cs@aio.local'));
    $contactLocation = data_get($branding, 'support_location', 'Hà Nội');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $pageSlug = (string) ($entry->slug ?? '');
    $isAboutPage = ($contentType ?? null) === 'page' && in_array($pageSlug, ['gioi-thieu', 'about'], true);
    $isContactPage = ($contentType ?? null) === 'page' && in_array($pageSlug, ['lien-he', 'contact'], true);
    $isPostDetail = ($contentType ?? null) === 'post';
    $listingCollection = isset($listingItems) ? collect($listingItems->items()) : collect();
    $postFilters = $postFilters ?? ['q' => '', 'category' => ''];
    $postCategories = collect($postCategories ?? []);
    $latestPostItems = collect($latestPosts ?? [])->filter(fn ($post) => (int) ($post->id ?? 0) !== (int) ($entry->id ?? 0))->take(3)->values();
    $relatedPostItems = collect($relatedPosts ?? [])->filter(fn ($post) => (int) ($post->id ?? 0) !== (int) ($entry->id ?? 0))->take(3)->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle ?? data_get($branding, 'company_name', $siteProfile?->site_name ?? config('app.name', 'AIO Platform')) }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            :root {
                --th-red: #ef2b2d;
                --th-red-deep: #d91c20;
                --th-ink: #222222;
                --th-muted: #6d6d6d;
                --th-line: #e6e6e6;
                --th-bg: #f6f6f8;
                --th-surface: #ffffff;
                --th-soft: #fff7f5;
                --th-shadow: 0 18px 40px rgba(19, 21, 33, 0.08);
            }

            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: var(--th-ink); background: var(--th-bg); }
            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .th-page { min-height: 100vh; }
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
            .th-main-nav-categories { background: rgba(0,0,0,0.08); min-width: 170px; padding: 11px 14px; font-weight: 700; }
            .th-main-nav-menu { display: flex; justify-content: flex-start; gap: 28px; font-size: 14px; font-weight: 700; }
            .th-main-nav-menu a { padding: 11px 0; display: block; text-transform: uppercase; }
            .th-content { padding: 20px 0 46px; }
            .th-preview-banner, .th-contact-status { margin-bottom: 16px; padding: 12px 16px; border-radius: 16px; }
            .th-preview-banner { background: #fff7e0; border: 1px solid #ffd591; color: #8a5a00; }
            .th-contact-status { background: #effcf3; border: 1px solid #9ae6b4; color: #166534; }
            .th-breadcrumb { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; color: #8a8a8a; font-size: 13px; }
            .th-breadcrumb span:last-child { color: var(--th-red); font-weight: 700; }
            .th-cms-hero { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(280px, 0.85fr); gap: 18px; padding: 28px; border: 1px solid #ffd8d8; background: linear-gradient(135deg, #fff6f1 0%, #ffffff 66%); box-shadow: var(--th-shadow); }
            .th-cms-hero-card, .th-cms-panel, .th-cms-card, .th-cms-article, .th-cms-sidebar-card { border-radius: 24px; background: #fff; }
            .th-cms-kicker { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: rgba(239, 43, 45, 0.09); color: var(--th-red); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .th-cms-title { margin: 16px 0 12px; font-size: clamp(30px, 5vw, 50px); line-height: 1.04; }
            .th-cms-summary { margin: 0; color: #6b6b6b; font-size: 16px; line-height: 1.8; }
            .th-cms-hero-actions { margin-top: 22px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .th-cms-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 999px; font-weight: 800; border: 0; cursor: pointer; }
            .th-cms-button.primary { background: var(--th-red); color: #fff; }
            .th-cms-button.secondary { background: #fff; color: var(--th-red); border: 1px solid #ffd0d0; }
            .th-cms-hero-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .th-cms-stat { padding: 18px; border: 1px solid var(--th-line); border-radius: 22px; background: linear-gradient(180deg, #fff 0%, #fff7f7 100%); }
            .th-cms-stat strong { display: block; font-size: 28px; color: var(--th-red); }
            .th-cms-stat span { display: block; margin-top: 8px; color: #666; line-height: 1.5; }
            .th-cms-grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 18px; margin-top: 18px; }
            .th-cms-main-column { display: grid; gap: 18px; }
            .th-cms-panel, .th-cms-card, .th-cms-article, .th-cms-sidebar-card { border: 1px solid var(--th-line); box-shadow: var(--th-shadow); }
            .th-cms-panel { padding: 24px; }
            .th-cms-section-title { margin: 0 0 14px; font-size: 24px; }
            .th-cms-body { color: #444; line-height: 1.85; }
            .th-cms-body h2, .th-cms-body h3, .th-cms-body h4 { color: #202020; margin: 1.3em 0 0.65em; }
            .th-cms-body p, .th-cms-body ul, .th-cms-body ol { margin: 0 0 1em; }
            .th-cms-body ul, .th-cms-body ol { padding-left: 20px; }
            .th-cms-body blockquote { margin: 18px 0; padding: 16px 18px; border-left: 4px solid var(--th-red); background: #fff8f7; color: #5d4747; }
            .th-cms-image { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 22px; border: 1px solid var(--th-line); }
            .th-cms-feature { overflow: hidden; }
            .th-cms-feature-image { width: 100%; aspect-ratio: 16 / 8; object-fit: cover; }
            .th-cms-feature-body { padding: 24px; }
            .th-cms-card-media {
                width: 100%;
                aspect-ratio: 16 / 10;
                display: block;
                object-fit: cover;
                background: linear-gradient(135deg, #ffe0e0 0%, #fff4f4 100%);
                border-bottom: 1px solid var(--th-line);
            }
            .th-cms-card-media.is-placeholder {
                display: grid;
                place-items: center;
                color: var(--th-red);
                font-size: 14px;
                font-weight: 800;
                letter-spacing: .08em;
                text-transform: uppercase;
            }
            .th-cms-meta-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; color: #7a7a7a; font-size: 13px; }
            .th-cms-card-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
            .th-cms-card { overflow: hidden; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
            .th-cms-card:hover { transform: translateY(-4px); box-shadow: 0 20px 44px rgba(19, 21, 33, 0.12); border-color: #ffd2d2; }
            .th-cms-card a { cursor: pointer; }
            .th-cms-card-body { padding: 20px; }
            .th-cms-card-title { margin: 0 0 10px; font-size: 20px; line-height: 1.35; }
            .th-cms-card-title a { transition: color .18s ease; }
            .th-cms-card:hover .th-cms-card-title a { color: var(--th-red); }
            .th-cms-card-summary { margin: 0 0 18px; color: #666; line-height: 1.7; }
            .th-cms-link { color: var(--th-red); font-weight: 700; }
            .th-cms-listing-head { padding: 24px; }
            .th-cms-listing-head h1 { margin: 0 0 10px; font-size: clamp(28px, 4vw, 42px); line-height: 1.08; }
            .th-cms-listing-head p { margin: 0; color: #666; line-height: 1.75; }
            .th-news-toolbar { display: grid; gap: 12px; }
            .th-news-field { display: grid; gap: 8px; }
            .th-news-field span { font-size: 13px; font-weight: 700; color: #555; }
            .th-news-field input, .th-news-field select {
                width: 100%; min-height: 46px; padding: 0 14px; border: 1px solid #e5dede; border-radius: 14px; background: #fff; font: inherit;
            }
            .th-news-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .th-news-meta { margin-top: 14px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; color: #666; }
            .th-news-meta strong { color: var(--th-red); }
            .th-news-empty { padding: 36px 24px; text-align: center; color: #666; }
            .th-cms-sidebar { display: grid; gap: 18px; }
            .th-cms-sidebar-card { padding: 20px; }
            .th-cms-sidebar-card h3 { margin: 0 0 14px; font-size: 20px; }
            .th-cms-contact-list, .th-cms-mini-list { display: grid; gap: 12px; }
            .th-cms-contact-item, .th-cms-mini-item { padding: 14px 16px; border-radius: 18px; background: #fafafa; border: 1px solid var(--th-line); }
            .th-cms-contact-item small, .th-cms-mini-item small { display: block; margin-bottom: 6px; color: #8b8b8b; text-transform: uppercase; letter-spacing: 0.08em; font-size: 11px; }
            .th-cms-contact-item strong, .th-cms-mini-item strong { display: block; font-size: 16px; line-height: 1.5; }
            .th-cms-contact-item span, .th-cms-mini-item span { display: block; margin-top: 6px; color: #666; line-height: 1.6; }
            .th-cms-highlight-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
            .th-cms-highlight { padding: 18px; border-radius: 20px; background: linear-gradient(180deg, #fff, #fff6f6); border: 1px solid #ffdede; }
            .th-cms-highlight strong { display: block; margin-bottom: 8px; font-size: 18px; color: var(--th-red); }
            .th-cms-highlight span { color: #666; line-height: 1.6; }
            .th-cms-pagination { margin-top: 22px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
            .th-cms-page-link { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--th-line); background: #fff; color: #555; font-weight: 700; }
            .th-cms-page-link.is-disabled { opacity: 0.45; pointer-events: none; }
            .th-related-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
            .th-contact-form { display: grid; gap: 14px; }
            .th-contact-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
            .th-contact-field { display: grid; gap: 8px; }
            .th-contact-field span { font-size: 14px; font-weight: 700; color: #444; }
            .th-contact-field input, .th-contact-field textarea {
                width: 100%; min-height: 46px; border: 1px solid #e5dede; border-radius: 16px; padding: 12px 14px; font: inherit; background: #fff;
            }
            .th-contact-field textarea { min-height: 150px; resize: vertical; }
            .th-contact-field input.has-error, .th-contact-field textarea.has-error { border-color: #dc2626; box-shadow: 0 0 0 3px rgba(220,38,38,.08); }
            .th-contact-error { min-height: 18px; color: #dc2626; font-size: 13px; line-height: 1.4; }
            .th-footer { margin-top: 32px; background: #fff; border-top: 1px solid var(--th-line); }
            .th-footer-inner { padding: 26px 0 40px; align-items: flex-start; }
            .th-footer-grid { display: grid; grid-template-columns: 1.2fr repeat(3, minmax(0, 1fr)); gap: 24px; width: 100%; }
            .th-footer-card h4 { margin: 0 0 14px; color: #444; text-transform: uppercase; font-size: 14px; }
            .th-footer-links { display: grid; gap: 8px; color: #7b7b7b; font-size: 13px; }
            .th-company { background: #fff7f7; border: 1px solid #ffd9d9; border-radius: 16px; padding: 16px; }
            .th-company strong { display: block; color: var(--th-red); margin-bottom: 8px; }

            @media (max-width: 1100px) {
                .th-cms-hero, .th-cms-grid, .th-related-grid { grid-template-columns: 1fr; }
                .th-cms-highlight-grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 760px) {
                .th-topbar-inner, .th-header-inner, .th-main-nav-inner, .th-footer-inner { flex-direction: column; align-items: stretch; }
                .th-logo { text-align: center; }
                .th-search { max-width: none; grid-template-columns: 1fr; }
                .th-main-nav-categories { min-width: 0; }
                .th-main-nav-menu { gap: 16px; overflow-x: auto; }
                .th-cms-hero, .th-cms-panel, .th-cms-feature-body, .th-cms-sidebar-card { padding: 18px; }
                .th-cms-hero-meta, .th-cms-card-grid, .th-footer-grid, .th-contact-grid, .th-news-toolbar { grid-template-columns: 1fr; }
                .th-cms-card-media { aspect-ratio: 4 / 3; }
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
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm kiếm sản phẩm / khuyến mãi" aria-label="Tìm kiếm sản phẩm" data-th-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                        <button type="submit">Tìm</button>
                    </form>
                    <a class="th-cart" href="{{ route('site.cart.index') }}">🛒 {{ $cartSummary['count'] ?? 0 }} GIỎ HÀNG</a>
                </div>
            </header>

            <nav class="th-main-nav">
                <div class="th-container th-main-nav-inner">
                    <div class="th-main-nav-categories">KHÁM PHÁ</div>
                    <div class="th-main-nav-menu">
                        <a href="{{ route('site.home') }}">Trang chủ</a>
                        @foreach ($topMenu as $menuItem)
                            <a href="{{ $menuItem['url'] ?? '#' }}" target="{{ $menuItem['target'] ?? '_self' }}">{{ $menuItem['label'] ?? 'Menu' }}</a>
                        @endforeach
                    </div>
                </div>
            </nav>

            <main class="th-content">
                <div class="th-container">
                    @if (!empty($isPreview))
                        <div class="th-preview-banner">Đây là chế độ preview unpublished chỉ dành cho admin.</div>
                    @endif
                    @if (session('contact_status'))
                        <div class="th-contact-status">{{ session('contact_status') }}</div>
                    @endif

                    <div class="th-breadcrumb">
                        <a href="{{ route('site.home') }}">Trang chủ</a>
                        <span>/</span>
                        <span>{{ $pageTitle ?? ($entry->title ?? 'Nội dung CMS') }}</span>
                    </div>

                    @if (($contentType ?? null) === 'posts')
                        <div class="th-cms-grid" id="news-grid">
                            <div class="th-cms-main-column">
                                <section class="th-cms-panel th-cms-listing-head">
                                    <h1>{{ $pageTitle }}</h1>
                                    <p>{{ $pageDescription }}</p>
                                    <div class="th-news-meta">
                                        <span>Tìm thấy <strong>{{ method_exists($listingItems, 'total') ? $listingItems->total() : $listingCollection->count() }}</strong> bài viết.</span>
                                        @if (filled($postFilters['q'] ?? '') || filled($postFilters['category'] ?? ''))
                                            <span>Đang áp dụng bộ lọc cho danh sách tin tức.</span>
                                        @endif
                                    </div>
                                </section>

                                @if ($listingCollection->isNotEmpty())
                                    <section class="th-cms-card-grid">
                                        @foreach ($listingCollection as $post)
                                            <article class="th-cms-card">
                                                <a href="{{ route('site.blog.show', $post->slug) }}" aria-label="{{ $post->title }}">
                                                    <img
                                                        class="th-cms-card-media{{ empty($post->featuredMedia?->file_url ?? null) ? ' is-placeholder' : '' }}"
                                                        src="{{ $post->featuredMedia?->file_url ?? ('https://picsum.photos/seed/cms-post-'.($post->id ?? 'default').'/960/720') }}"
                                                        alt="{{ $post->title }}">
                                                </a>
                                                <div class="th-cms-card-body">
                                                    <div class="th-cms-meta-row">
                                                        <span>{{ optional($post->publish_at)->format('d/m/Y') ?? 'Đang cập nhật' }}</span>
                                                        @if (!empty($post->category?->name))
                                                            <span>{{ $post->category->name }}</span>
                                                        @endif
                                                    </div>
                                                    <h3 class="th-cms-card-title"><a href="{{ route('site.blog.show', $post->slug) }}">{{ $post->title }}</a></h3>
                                                    <p class="th-cms-card-summary">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 150) }}</p>
                                                </div>
                                            </article>
                                        @endforeach
                                    </section>
                                @else
                                    <section class="th-cms-panel th-news-empty">
                                        <h3>Không tìm thấy bài viết phù hợp</h3>
                                        <p>Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc để xem thêm nội dung.</p>
                                    </section>
                                @endif

                                @if (method_exists($listingItems, 'previousPageUrl') || method_exists($listingItems, 'nextPageUrl'))
                                    <div class="th-cms-pagination">
                                        <a href="{{ $listingItems->previousPageUrl() ?: '#' }}" class="th-cms-page-link {{ $listingItems->previousPageUrl() ? '' : 'is-disabled' }}">Bài mới hơn</a>
                                        <span>Trang {{ $listingItems->currentPage() }} / {{ $listingItems->lastPage() }}</span>
                                        <a href="{{ $listingItems->nextPageUrl() ?: '#' }}" class="th-cms-page-link {{ $listingItems->nextPageUrl() ? '' : 'is-disabled' }}">Bài cũ hơn</a>
                                    </div>
                                @endif
                            </div>

                            <aside class="th-cms-sidebar">
                                <section class="th-cms-sidebar-card">
                                    <h3>Tìm kiếm tin tức</h3>
                                    <form method="GET" action="{{ route('site.blog.index') }}" class="th-news-toolbar">
                                        <label class="th-news-field">
                                            <span>Từ khóa</span>
                                            <input type="search" name="q" value="{{ $postFilters['q'] ?? '' }}" placeholder="Nhập tiêu đề, mô tả hoặc từ khóa nội dung">
                                        </label>
                                        <label class="th-news-field">
                                            <span>Chuyên mục</span>
                                            <select name="category">
                                                <option value="">Tất cả chuyên mục</option>
                                                @foreach ($postCategories as $category)
                                                    <option value="{{ $category->slug }}" @selected(($postFilters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <div class="th-news-actions">
                                            <button type="submit" class="th-cms-button primary">Lọc tin</button>
                                            @if (filled($postFilters['q'] ?? '') || filled($postFilters['category'] ?? ''))
                                                <a href="{{ route('site.blog.index') }}" class="th-cms-button secondary">Xóa lọc</a>
                                            @else
                                                <button type="button" class="th-cms-button secondary" data-open-newsletter-modal>Nhận bản tin</button>
                                            @endif
                                        </div>
                                    </form>
                                </section>
                                <section class="th-cms-sidebar-card">
                                    <h3>Nhận bản tin ưu đãi</h3>
                                    <p class="th-cms-summary">Đăng ký email để nhận bài viết mới, lịch campaign và gợi ý sản phẩm nổi bật.</p>
                                    <button type="button" class="th-cms-button primary" data-open-newsletter-modal>Đăng ký ngay</button>
                                </section>
                                <section class="th-cms-sidebar-card">
                                    <h3>Kết nối nhanh</h3>
                                    <div class="th-cms-contact-list">
                                        <div class="th-cms-contact-item">
                                            <small>Hotline</small>
                                            <strong>{{ $contactHotline }}</strong>
                                        </div>
                                        <div class="th-cms-contact-item">
                                            <small>Email</small>
                                            <strong>{{ $contactEmail }}</strong>
                                        </div>
                                        <div class="th-cms-contact-item">
                                            <small>Khu vực</small>
                                            <strong>{{ $contactLocation }}</strong>
                                        </div>
                                    </div>
                                </section>
                            </aside>
                        </div>
                    @else
                        <section class="th-cms-hero">
                            <div class="th-cms-hero-card">
                                <span class="th-cms-kicker">
                                    @if ($isPostDetail)
                                        Bài viết chi tiết
                                    @elseif ($isAboutPage)
                                        Hồ sơ thương hiệu
                                    @elseif ($isContactPage)
                                        Kết nối với chúng tôi
                                    @else
                                        Nội dung CMS
                                    @endif
                                </span>
                                <h1 class="th-cms-title">{{ $entry->title }}</h1>
                                @if (!empty($entry->excerpt))
                                    <p class="th-cms-summary">{{ $entry->excerpt }}</p>
                                @endif
                                <div class="th-cms-hero-actions">
                                    @if ($isPostDetail)
                                        <a href="{{ route('site.blog.index') }}" class="th-cms-button primary">Về trang tin tức</a>
                                        <button type="button" class="th-cms-button secondary" data-open-newsletter-modal>Nhận bản tin</button>
                                    @elseif ($isContactPage)
                                        <a href="tel:{{ preg_replace('/\D+/', '', $contactHotline) }}" class="th-cms-button primary">Gọi hotline</a>
                                        <a href="mailto:{{ $contactEmail }}" class="th-cms-button secondary">Gửi email</a>
                                    @else
                                        <a href="{{ route('site.blog.index') }}" class="th-cms-button primary">Xem tin mới</a>
                                        <a href="{{ route('site.home') }}" class="th-cms-button secondary">Về trang chủ</a>
                                    @endif
                                </div>
                            </div>
                            @unless ($isPostDetail)
                                <div class="th-cms-hero-meta">
                                    <div class="th-cms-stat">
                                        <strong>{{ data_get($branding, 'company_name', $siteProfile?->site_name ?? 'AIO Commerce') }}</strong>
                                        <span>Đơn vị vận hành storefront và nội dung CMS trên cùng một nền tảng.</span>
                                    </div>
                                    <div class="th-cms-stat">
                                        <strong>{{ $contactLocation }}</strong>
                                        <span>Điểm hiện diện phục vụ tư vấn, trưng bày và hỗ trợ khách hàng.</span>
                                    </div>
                                    <div class="th-cms-stat">
                                        <strong>{{ $contactEmail }}</strong>
                                        <span>Kênh tiếp nhận liên hệ hợp tác, booking truyền thông và CSKH.</span>
                                    </div>
                                    <div class="th-cms-stat">
                                        <strong>{{ $contactHotline }}</strong>
                                        <span>Hotline đồng bộ từ cấu hình website để hiển thị trên toàn bộ storefront.</span>
                                    </div>
                                </div>
                            @endunless
                        </section>

                        <div class="th-cms-grid">
                            <div class="th-cms-main-column">
                                @if (!empty($entry->featuredMedia?->file_url ?? null))
                                    <section class="th-cms-panel">
                                        <img class="th-cms-image" src="{{ $entry->featuredMedia->file_url }}" alt="{{ $entry->title }}">
                                    </section>
                                @endif

                                @if ($isAboutPage)
                                    <section class="th-cms-panel">
                                        <h2 class="th-cms-section-title">Hồ sơ vận hành</h2>
                                        <div class="th-cms-highlight-grid">
                                            <div class="th-cms-highlight">
                                                <strong>Đồng bộ CMS và storefront</strong>
                                                <span>Nội dung giới thiệu, tin tức và dữ liệu thương hiệu được render trực tiếp trên giao diện TH0001.</span>
                                            </div>
                                            <div class="th-cms-highlight">
                                                <strong>Thông tin liên hệ tập trung</strong>
                                                <span>Hotline, email và địa điểm lấy từ cấu hình website để tránh hard-code theo từng trang.</span>
                                            </div>
                                            <div class="th-cms-highlight">
                                                <strong>Trải nghiệm mua hàng liền mạch</strong>
                                                <span>Khách hàng có thể đi từ nội dung giới thiệu sang tin tức, sản phẩm và checkout trong cùng một hệ thống.</span>
                                            </div>
                                        </div>
                                    </section>
                                @endif

                                <article class="th-cms-article th-cms-panel">
                                    <div class="th-cms-meta-row">
                                        @if ($isPostDetail)
                                            <span>{{ optional($entry->publish_at)->format('d/m/Y H:i') ?? 'Đang cập nhật' }}</span>
                                            @if (!empty($entry->category?->name))
                                                <span>{{ $entry->category->name }}</span>
                                            @endif
                                        @else
                                            <span>Trang thông tin</span>
                                        @endif
                                    </div>
                                    <div class="th-cms-body">{!! $entry->body ?: '<p>Nội dung đang được cập nhật.</p>' !!}</div>
                                </article>

                                @if ($isPostDetail && $relatedPostItems->isNotEmpty())
                                    <section class="th-cms-panel">
                                        <h2 class="th-cms-section-title">Bài liên quan</h2>
                                        <div class="th-related-grid">
                                            @foreach ($relatedPostItems as $post)
                                                <article class="th-cms-card">
                                                    @if (!empty($post->featuredMedia?->file_url ?? null))
                                                        <img class="th-cms-feature-image" src="{{ $post->featuredMedia->file_url }}" alt="{{ $post->title }}">
                                                    @endif
                                                    <div class="th-cms-card-body">
                                                        <div class="th-cms-meta-row">
                                                            <span>{{ optional($post->publish_at)->format('d/m/Y') ?? 'Bài viết' }}</span>
                                                            @if (!empty($post->category?->name))
                                                                <span>{{ $post->category->name }}</span>
                                                            @endif
                                                        </div>
                                                        <h3 class="th-cms-card-title">{{ $post->title }}</h3>
                                                        <p class="th-cms-card-summary">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 120) }}</p>
                                                        <a class="th-cms-link" href="{{ route('site.blog.show', $post->slug) }}">Xem chi tiết</a>
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            </div>

                            <aside class="th-cms-sidebar">
                                @if ($isContactPage)
                                    <section class="th-cms-sidebar-card">
                                        <h3>Liên hệ nhanh</h3>
                                        <div class="th-cms-contact-list">
                                            <div class="th-cms-contact-item">
                                                <small>Hotline</small>
                                                <strong>{{ $contactHotline }}</strong>
                                                <span>Hỗ trợ tư vấn mua hàng, booking campaign và chăm sóc sau bán.</span>
                                            </div>
                                            <div class="th-cms-contact-item">
                                                <small>Email</small>
                                                <strong>{{ $contactEmail }}</strong>
                                                <span>Nhận báo giá, đề xuất hợp tác và gửi yêu cầu chi tiết.</span>
                                            </div>
                                            <div class="th-cms-contact-item">
                                                <small>Địa chỉ</small>
                                                <strong>{{ $contactLocation }}</strong>
                                                <span>Phù hợp để đặt lịch gặp, làm việc với đội ngũ kinh doanh hoặc CSKH.</span>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="th-cms-sidebar-card">
                                        <h3>Gửi yêu cầu liên hệ</h3>
                                        <form method="POST" action="{{ route('site.contact.submit') }}" class="th-contact-form" novalidate>
                                            @csrf
                                            <div class="th-contact-grid">
                                                <label class="th-contact-field">
                                                    <span>Họ và tên</span>
                                                    <input type="text" name="name" value="{{ old('name') }}" class="{{ $errors->has('name') ? 'has-error' : '' }}" required>
                                                    <small class="th-contact-error">{{ $errors->first('name') }}</small>
                                                </label>
                                                <label class="th-contact-field">
                                                    <span>Email</span>
                                                    <input type="email" name="email" value="{{ old('email') }}" class="{{ $errors->has('email') ? 'has-error' : '' }}" required>
                                                    <small class="th-contact-error">{{ $errors->first('email') }}</small>
                                                </label>
                                            </div>
                                            <div class="th-contact-grid">
                                                <label class="th-contact-field">
                                                    <span>Số điện thoại</span>
                                                    <input type="text" name="phone" value="{{ old('phone') }}" class="{{ $errors->has('phone') ? 'has-error' : '' }}">
                                                    <small class="th-contact-error">{{ $errors->first('phone') }}</small>
                                                </label>
                                                <label class="th-contact-field">
                                                    <span>Chủ đề</span>
                                                    <input type="text" name="subject" value="{{ old('subject') }}" class="{{ $errors->has('subject') ? 'has-error' : '' }}">
                                                    <small class="th-contact-error">{{ $errors->first('subject') }}</small>
                                                </label>
                                            </div>
                                            <label class="th-contact-field">
                                                <span>Nội dung cần hỗ trợ</span>
                                                <textarea name="message" class="{{ $errors->has('message') ? 'has-error' : '' }}" required>{{ old('message') }}</textarea>
                                                <small class="th-contact-error">{{ $errors->first('message') }}</small>
                                            </label>
                                            <button type="submit" class="th-cms-button primary">Gửi yêu cầu liên hệ</button>
                                        </form>
                                    </section>
                                @else
                                    <section class="th-cms-sidebar-card">
                                        <h3>Thông tin nhanh</h3>
                                        <div class="th-cms-mini-list">
                                            <div class="th-cms-mini-item">
                                                <small>Thương hiệu</small>
                                                <strong>{{ data_get($branding, 'company_name', $siteProfile?->site_name ?? 'AIO Commerce') }}</strong>
                                            </div>
                                            <div class="th-cms-mini-item">
                                                <small>Hotline</small>
                                                <strong>{{ $contactHotline }}</strong>
                                            </div>
                                            <div class="th-cms-mini-item">
                                                <small>Email</small>
                                                <strong>{{ $contactEmail }}</strong>
                                            </div>
                                        </div>
                                    </section>
                                    @if ($isPostDetail && $relatedPostItems->isNotEmpty())
                                        <section class="th-cms-sidebar-card">
                                            <h3>Đọc tiếp</h3>
                                            <div class="th-cms-mini-list">
                                                @foreach ($relatedPostItems as $post)
                                                    <a class="th-cms-mini-item" href="{{ route('site.blog.show', $post->slug) }}">
                                                        <small>{{ optional($post->publish_at)->format('d/m/Y') ?? 'Bài viết' }}</small>
                                                        <strong>{{ $post->title }}</strong>
                                                        <span>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 90) }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </section>
                                    @elseif ($latestPostItems->isNotEmpty())
                                        <section class="th-cms-sidebar-card">
                                            <h3>Tin mới</h3>
                                            <div class="th-cms-mini-list">
                                                @foreach ($latestPostItems as $post)
                                                    <a class="th-cms-mini-item" href="{{ route('site.blog.show', $post->slug) }}">
                                                        <small>{{ optional($post->publish_at)->format('d/m/Y') ?? 'Bài viết' }}</small>
                                                        <strong>{{ $post->title }}</strong>
                                                        <span>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 90) }}</span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </section>
                                    @endif
                                @endif
                            </aside>
                        </div>
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
