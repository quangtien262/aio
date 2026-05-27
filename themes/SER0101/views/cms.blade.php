@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $listingCollection = isset($listingItems) ? collect($listingItems->items()) : collect();
    $latestPostItems = collect($latestPosts ?? [])->take(3)->values();
    $relatedPostItems = collect($relatedPosts ?? [])->take(3)->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle ?? data_get($branding, 'company_name', 'SER0101') }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

            :root {
                --navy: #0f172f;
                --night: #08111f;
                --teal: #0f766e;
                --orange: #b45309;
                --amber: #f0b429;
                --line: #d6e2de;
                --muted: #5d7288;
            }

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.1), transparent 24%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.16), transparent 30%),
                    linear-gradient(180deg, #fbfcfb 0%, #f3f8f5 42%, #fcf8f2 100%);
                color: #243b53;
            }

            a { text-decoration: none; color: inherit; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1160px, calc(100% - 24px)); margin: 0 auto; }
            .hero {
                margin: 22px 0 18px;
                padding: 20px;
                border-radius: 34px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
                box-shadow: 0 24px 56px rgba(10, 30, 47, 0.16);
            }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) 300px; gap: 18px; align-items: stretch; }
            .hero-copy { padding: 8px 10px 4px; }
            .badge { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em; }
            .hero h1 { margin: 16px 0 10px; font-size: clamp(34px, 5vw, 56px); line-height: 1.02; }
            .hero p { margin: 0; color: #d9e2ec; line-height: 1.8; }
            .hero-dossier {
                display: grid;
                gap: 12px;
                padding: 18px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.14);
                backdrop-filter: blur(10px);
            }
            .hero-dossier strong { display: block; font-size: 22px; line-height: 1.15; }
            .hero-dossier p { margin: 0; color: #d9e2ec; font-size: 14px; line-height: 1.75; }
            .hero-dossier-list { display: grid; gap: 10px; }
            .hero-dossier-item { padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.12); }
            .hero-dossier-item b { display: block; font-size: 18px; }
            .hero-dossier-item small { color: #cbd5e1; line-height: 1.5; }
            .layout { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; margin-bottom: 32px; align-items: start; }
            .grid { display: grid; gap: 18px; }
            .panel, .side-card, .post-card {
                background: rgba(255, 255, 255, 0.96);
                border: 1px solid rgba(214, 226, 222, 0.92);
                border-radius: 26px;
                box-shadow: 0 16px 36px rgba(16, 42, 67, 0.08);
            }
            .panel { padding: 24px; }
            .panel h2, .side-card h3 { margin: 0 0 14px; color: var(--navy); }
            .body-copy { color: #334e68; line-height: 1.9; }
            .body-copy h2, .body-copy h3, .body-copy h4 { color: var(--navy); }
            .body-copy blockquote { margin: 18px 0; padding: 16px 18px; border-left: 4px solid var(--orange); background: #fff8f1; }
            .toolbar { display: grid; gap: 12px; }
            .toolbar input, .toolbar select { min-height: 46px; border: 1px solid var(--line); border-radius: 14px; padding: 0 14px; font: inherit; }
            .toolbar-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .toolbar-buttons button, .toolbar-buttons a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; border-radius: 999px; font-weight: 800; }
            .toolbar-buttons button { border: 0; background: linear-gradient(135deg, var(--teal), var(--p-deep)); color: #fff; }
            .toolbar-buttons a { border: 1px solid var(--line); background: #fff; color: var(--navy); }
            .post-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
            .post-card { overflow: hidden; }
            .post-card img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; background: #edf2f7; }
            .post-card-body { padding: 18px; }
            .post-card h4 { margin: 0 0 10px; color: var(--navy); font-size: 22px; letter-spacing: -0.02em; }
            .post-card p { margin: 0; color: var(--muted); line-height: 1.7; }
            .meta { margin-bottom: 10px; color: var(--orange); font-size: 13px; font-weight: 700; }
            .side-stack { display: grid; gap: 18px; align-content: start; }
            .side-card { padding: 20px; }
            .side-card strong { display: block; color: var(--navy); margin-bottom: 6px; }
            .side-card p, .side-card a, .side-card span { color: var(--muted); line-height: 1.7; }
            .side-post-list { display: grid; gap: 12px; }
            .side-post-item { display: grid; gap: 4px; padding-top: 12px; border-top: 1px solid rgba(217, 226, 236, 0.9); }
            .side-post-item:first-child { padding-top: 0; border-top: 0; }
            .side-post-item strong { margin-bottom: 0; font-size: 15px; line-height: 1.45; }
            .side-post-item span { font-size: 13px; line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .pagination { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
            .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--line); background: #fff; color: #486581; font-weight: 700; }
            .footer { background: var(--navy); color: #d9e2ec; }
            .footer-inner { padding: 28px 0 34px; align-items: flex-start; }
            .footer-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 24px; width: 100%; }
            .footer-grid h4 { margin: 0 0 12px; color: #fff; }
            .footer-grid p, .footer-grid a { color: #bcccdc; line-height: 1.8; }

            @media (max-width: 900px) {
                .hero-grid, .layout, .post-grid, .footer-grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])
        <main class="wrap">
            @if (!empty($isPreview))
                <div class="hero" style="padding:16px 20px;margin-top:20px;">
                    <div class="hero-copy">
                        <span class="badge">Preview</span>
                        <p>{{ $t('cms.preview_banner', 'Đang xem nội dung preview chưa publish.') }}</p>
                    </div>
                </div>
            @endif

            @if (($contentType ?? null) === 'posts')
                <section class="hero">
                    <div class="hero-grid">
                        <div class="hero-copy">
                            <span class="badge">{{ $t('menu.default.blog', 'Cẩm nang') }}</span>
                            <h1>{{ $pageTitle }}</h1>
                            <p>{{ $pageDescription }}</p>
                        </div>
                        <aside class="hero-dossier">
                            <strong>Content concierge</strong>
                            <p>Khối nội dung của SER0101 được trình bày như trust layer cho hành trình đặt xe: gọn, rõ, và kéo người dùng về hotline hoặc bước báo giá.</p>
                            <div class="hero-dossier-list">
                                <div class="hero-dossier-item">
                                    <b>{{ $listingCollection->count() }}</b>
                                    <small>Bài viết đang hiển thị</small>
                                </div>
                                <div class="hero-dossier-item">
                                    <b>{{ $contactHotline }}</b>
                                    <small>Hotline concierge</small>
                                </div>
                                <div class="hero-dossier-item">
                                    <b>{{ $contactLocation }}</b>
                                    <small>Khu vực điều phối ưu tiên</small>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="layout">
                    <div class="grid">
                        <div class="panel">
                            <form method="GET" action="{{ route('site.blog.index') }}" class="toolbar">
                                <input type="search" name="q" value="{{ $postFilters['q'] ?? '' }}" placeholder="{{ $t('cms.search_placeholder', 'Tìm bài viết, kinh nghiệm, hướng dẫn') }}">
                                <select name="category">
                                    <option value="">{{ $t('cms.all_categories', 'Tất cả chuyên mục') }}</option>
                                    @foreach ($postCategories ?? [] as $category)
                                        <option value="{{ $category->slug }}" {{ ($postFilters['category'] ?? '') === $category->slug ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                <div class="toolbar-buttons">
                                    <button type="submit">{{ $t('cms.filter_posts', 'Lọc bài viết') }}</button>
                                    <a href="{{ route('site.blog.index') }}">{{ $t('cms.clear_filters', 'Xóa lọc') }}</a>
                                </div>
                            </form>
                        </div>

                        <div class="post-grid">
                            @forelse ($listingCollection as $post)
                                <article class="post-card">
                                    <img src="{{ $post->featuredMedia?->url ?: 'https://picsum.photos/seed/ser0101-cms-post/960/720' }}" alt="{{ $post->title }}">
                                    <div class="post-card-body">
                                        <div class="meta">{{ $post->publish_at?->format('d/m/Y') }}</div>
                                        <h4><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h4>
                                        <p>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 180, '...') }}</p>
                                    </div>
                                </article>
                            @empty
                                <div class="panel">
                                    <h2>{{ $t('search.empty_title', 'Chưa có gói phù hợp') }}</h2>
                                    <p>{{ $t('search.empty_summary', 'Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc.') }}</p>
                                </div>
                            @endforelse
                        </div>

                        @if (isset($listingItems) && method_exists($listingItems, 'previousPageUrl'))
                            <div class="pagination">
                                @if ($listingItems->previousPageUrl())
                                    <a href="{{ $listingItems->previousPageUrl() }}">{{ $t('search.prev_page', 'Trang trước') }}</a>
                                @else
                                    <span>{{ $t('search.prev_page', 'Trang trước') }}</span>
                                @endif
                                <span>{{ $t('search.page_of', 'Trang :current / :last') }}</span>
                                @if ($listingItems->nextPageUrl())
                                    <a href="{{ $listingItems->nextPageUrl() }}">{{ $t('search.next_page', 'Trang sau') }}</a>
                                @else
                                    <span>{{ $t('search.next_page', 'Trang sau') }}</span>
                                @endif
                            </div>
                        @endif
                    </div>

                    <aside class="side-stack">
                        <section class="side-card">
                            <h3>{{ $t('cms.quick_contact_title', 'Liên hệ nhanh') }}</h3>
                            <strong>{{ $contactHotline }}</strong>
                            <span>{{ $contactEmail }}</span>
                            <p>{{ $contactLocation }}</p>
                        </section>
                        @if ($latestPostItems->isNotEmpty())
                            <section class="side-card">
                                <h3>{{ $t('cms.latest_posts_title', 'Bài viết mới') }}</h3>
                                <div class="side-post-list">
                                    @foreach ($latestPostItems->take(2) as $post)
                                        <a class="side-post-item" href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">
                                            <strong>{{ $post->title }}</strong>
                                            <span>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 110, '...') }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </aside>
                </section>
            @else
                <section class="hero">
                    <div class="hero-grid">
                        <div class="hero-copy">
                            <span class="badge">{{ ($contentType ?? null) === 'post' ? $t('menu.default.blog', 'Cẩm nang') : $t('cms.company_content_badge', 'Nội dung doanh nghiệp') }}</span>
                            <h1>{{ $entry->title ?? $t('cms.default_title', 'Nội dung dịch vụ') }}</h1>
                            <p>{{ $entry->excerpt ?: $pageDescription }}</p>
                        </div>
                        <aside class="hero-dossier">
                            <strong>Trust layer</strong>
                            <p>Nội dung SER0101 được trình bày như lớp củng cố niềm tin cho bước chốt nhu cầu: rõ quy trình, rõ cách phục vụ và rõ điểm liên hệ.</p>
                            <div class="hero-dossier-list">
                                <div class="hero-dossier-item">
                                    <b>{{ data_get($branding, 'company_name', 'SER0101') }}</b>
                                    <small>Thương hiệu đang hoạt động</small>
                                </div>
                                <div class="hero-dossier-item">
                                    <b>{{ $contactHotline }}</b>
                                    <small>Hotline concierge</small>
                                </div>
                                <div class="hero-dossier-item">
                                    <b>{{ $contactLocation }}</b>
                                    <small>Khu vực điều phối</small>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <section class="layout">
                    <div class="grid">
                        <article class="panel">
                            @if (!empty($entry->featuredMedia?->url))
                                <img src="{{ $entry->featuredMedia?->url }}" alt="{{ $entry->title }}" style="width:100%;aspect-ratio:16/8;object-fit:cover;border-radius:20px;margin-bottom:18px;">
                            @endif
                            @if (($contentType ?? null) === 'post')
                                <div class="meta">{{ $entry->publish_at?->format('d/m/Y') }}</div>
                            @endif
                            <div class="body-copy">{!! $entry->body ?? '' !!}</div>
                        </article>

                        @if (($contentType ?? null) === 'page' && $latestPostItems->isNotEmpty())
                            <section class="grid">
                                <div class="panel"><h2>{{ $t('cms.latest_posts_title', 'Bài viết mới') }}</h2></div>
                                <div class="post-grid">
                                    @foreach ($latestPostItems as $post)
                                        <article class="post-card">
                                            <img src="{{ $post->featuredMedia?->url ?: 'https://picsum.photos/seed/ser0101-page-post/960/720' }}" alt="{{ $post->title }}">
                                            <div class="post-card-body">
                                                <div class="meta">{{ $post->publish_at?->format('d/m/Y') }}</div>
                                                <h4><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h4>
                                                <p>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 160, '...') }}</p>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        @endif

                        @if (($contentType ?? null) === 'post' && $relatedPostItems->isNotEmpty())
                            <section class="grid">
                                <div class="panel"><h2>{{ $t('cms.related_posts_title', 'Nội dung liên quan') }}</h2></div>
                                <div class="post-grid">
                                    @foreach ($relatedPostItems as $post)
                                        <article class="post-card">
                                            <img src="{{ $post->featuredMedia?->url ?: 'https://picsum.photos/seed/ser0101-related-post/960/720' }}" alt="{{ $post->title }}">
                                            <div class="post-card-body">
                                                <div class="meta">{{ $post->publish_at?->format('d/m/Y') }}</div>
                                                <h4><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h4>
                                                <p>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 160, '...') }}</p>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>

                    <aside class="side-stack">
                        <section class="side-card">
                            <h3>Thông tin điều phối</h3>
                            <strong>{{ data_get($branding, 'company_name', 'SER0101') }}</strong>
                            <span>{{ $contactHotline }}</span>
                            <span>{{ $contactEmail }}</span>
                            <p>{{ $contactLocation }}</p>
                        </section>
                        <section class="side-card">
                            <h3>{{ $t('cms.trust_layer_title', 'Lớp nội dung tin cậy') }}</h3>
                            <p>{{ $t('cms.trust_layer_body', 'Theme service ưu tiên page giới thiệu, quy trình đặt xe, bảng giá tham khảo và bài viết hướng dẫn để tăng conversion.') }}</p>
                        </section>
                    </aside>
                </section>
            @endif
        </main>
        <footer class="footer">
            <div class="wrap footer-inner">
                <div class="footer-grid">
                    <section>
                        <h4>{{ data_get($branding, 'company_name', 'SER0101') }}</h4>
                        <p>{{ $t('cms.footer_summary', 'Theme service-first cho nhà xe, shuttle và logistics nhẹ.') }}</p>
                    </section>
                    <section>
                        <h4>{{ $t('cms.footer_contact_title', 'Liên hệ') }}</h4>
                        <p>{{ $contactHotline }}</p>
                        <p>{{ $contactEmail }}</p>
                        <p>{{ $contactLocation }}</p>
                    </section>
                    <section>
                        <h4>{{ $t('cms.footer_nav_title', 'Điều hướng') }}</h4>
                        <a href="{{ route('site.home') }}">{{ $t('common.home', 'Trang chủ') }}</a><br>
                        <a href="{{ route('site.blog.index') }}">{{ $t('menu.default.blog', 'Cẩm nang') }}</a>
                    </section>
                </div>
            </div>
        </footer>
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
