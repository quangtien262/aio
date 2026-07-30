@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0100', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0100.demo');
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
        <title>{{ $pageTitle ?? data_get($branding, 'company_name', 'SER0100') }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0100::partials.shell-styles')

            @include('theme-ser0100::partials.palette-tokens', ['branding' => $branding])

            :root{--ser-navy:#102a43;--ser-petrol:#1f6f78;--ser-ink:#243b53;--ser-orange:#c2410c;--ser-line:#d9e2ec;--ser-muted:#627d98}*{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;background:#f8fbfd;color:var(--ser-ink)}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}.wrap{width:min(1160px,calc(100% - 24px));margin:0 auto}.topbar{background:var(--ser-navy);color:#d9e2ec;font-size:13px}.topbar-inner,.header-inner,.nav-inner,.footer-inner{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}.topbar-inner{padding:8px 0}.inline{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.inline button{border:0;background:transparent;color:inherit;cursor:pointer;font:inherit}.header{background:#fff;border-bottom:1px solid var(--ser-line)}.header-inner{padding:16px 0}.brand strong{display:block;color:var(--ser-navy);font-size:20px}.brand span{display:block;color:var(--ser-muted);font-size:13px}.nav{background:#fff}.nav-inner{padding:12px 0}.nav-menu{display:flex;gap:22px;flex-wrap:wrap;font-size:14px;font-weight:700}.hero,.panel,.side-card,.list-card{background:#fff;border:1px solid var(--ser-line);border-radius:26px;box-shadow:0 16px 36px rgba(16,42,67,.08)}.hero{padding:28px;margin:22px 0 18px;background:linear-gradient(135deg,#fff8f1,#ffffff)}.hero h1{margin:12px 0;font-size:clamp(32px,5vw,54px);line-height:1.04;color:var(--ser-navy)}.hero p{margin:0;color:var(--ser-muted);line-height:1.8;font-size:16px}.badge{display:inline-flex;padding:7px 12px;border-radius:999px;background:#eef6f8;color:var(--ser-petrol);font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:18px;margin-bottom:32px;align-items:start}.panel{padding:24px}.panel h2,.side-card h3,.list-card h3{margin:0 0 14px;color:var(--ser-navy)}.body-copy{color:#334e68;line-height:1.9}.body-copy h2,.body-copy h3,.body-copy h4{color:#102a43}.body-copy blockquote{margin:18px 0;padding:16px 18px;border-left:4px solid var(--ser-orange);background:#fff8f1}.grid{display:grid;gap:18px}.post-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.post-card{background:#fff;border:1px solid var(--ser-line);border-radius:22px;overflow:hidden;box-shadow:0 12px 28px rgba(16,42,67,.06)}.post-card img{width:100%;aspect-ratio:16/10;object-fit:cover;background:#edf2f7}.post-card-body{padding:18px}.post-card h4{margin:0 0 10px;color:var(--ser-navy);font-size:20px}.post-card p{margin:0;color:var(--ser-muted);line-height:1.7}.meta{margin-bottom:10px;color:var(--ser-orange);font-size:13px;font-weight:700}.side-stack{display:grid;gap:18px;align-content:start;grid-auto-rows:max-content}.side-card{padding:20px;height:auto;align-self:start}.side-card strong{display:block;color:var(--ser-navy);margin-bottom:6px}.side-card p,.side-card a,.side-card span{color:var(--ser-muted);line-height:1.7}.side-post-list{display:grid;gap:12px}.side-post-item{display:grid;gap:4px;padding-top:12px;border-top:1px solid rgba(217,226,236,.9)}.side-post-item:first-child{padding-top:0;border-top:0}.side-post-item strong{margin-bottom:0;font-size:15px;line-height:1.45}.side-post-item span{font-size:13px;line-height:1.55;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.toolbar{display:grid;gap:12px}.toolbar input,.toolbar select{min-height:46px;border:1px solid var(--ser-line);border-radius:14px;padding:0 14px;font:inherit}.toolbar-buttons{display:grid;grid-template-columns:1fr 1fr;gap:10px}.toolbar-buttons button,.toolbar-buttons a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border-radius:999px;font-weight:800}.toolbar-buttons button{border:0;background:var(--ser-orange);color:#fff}.toolbar-buttons a{border:1px solid var(--ser-line);background:#fff;color:var(--ser-navy)}.pagination{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.pagination a,.pagination span{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 16px;border-radius:999px;border:1px solid var(--ser-line);background:#fff;color:#486581;font-weight:700}.footer{background:var(--ser-navy);color:#d9e2ec}.footer-inner{padding:28px 0 34px;align-items:flex-start}.footer-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:24px;width:100%}.footer-grid h4{margin:0 0 12px;color:#fff}.footer-grid p,.footer-grid a{color:#bcccdc;line-height:1.8}@media (max-width: 900px){.layout,.post-grid,.footer-grid{grid-template-columns:1fr}}@media (max-width: 680px){.wrap{width:min(100%,calc(100% - 16px))}}
        </style>
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0100::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])
        <main class="wrap">
            @if (!empty($isPreview))<div class="hero" style="padding:16px 20px;margin-top:20px;"><span>{{ $t('cms.preview_banner', 'Đang xem nội dung preview chưa publish.') }}</span></div>@endif
            @if (($contentType ?? null) === 'posts')
                <section class="hero"><span class="badge">{{ $t('menu.default.blog', 'Cẩm nang') }}</span><h1>{{ $pageTitle }}</h1><p>{{ $pageDescription }}</p></section>
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
                                <div class="toolbar-buttons"><button type="submit">{{ $t('cms.filter_posts', 'Lọc bài viết') }}</button><a href="{{ route('site.blog.index') }}">{{ $t('cms.clear_filters', 'Xóa lọc') }}</a></div>
                            </form>
                        </div>
                        <div class="post-grid">
                            @forelse ($listingCollection as $post)
                                <article class="post-card">
                                    <img src="{{ $post->featuredMedia?->url ?: 'https://picsum.photos/seed/ser0100-cms-post/960/720' }}" alt="{{ $post->title }}">
                                    <div class="post-card-body">
                                        <div class="meta">{{ $post->publish_at?->format('d/m/Y') }}</div>
                                        <h4><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h4>
                                        <p>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 180, '...') }}</p>
                                    </div>
                                </article>
                            @empty
                                <div class="panel"><h2>{{ $t('search.empty_title', 'Chưa có gói phù hợp') }}</h2><p>{{ $t('search.empty_summary', 'Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc.') }}</p></div>
                            @endforelse
                        </div>
                        @if (isset($listingItems) && method_exists($listingItems, 'previousPageUrl'))
                            <div class="pagination">
                                @if ($listingItems->previousPageUrl())<a href="{{ $listingItems->previousPageUrl() }}">{{ $t('search.prev_page', 'Trang trước') }}</a>@else<span>{{ $t('search.prev_page', 'Trang trước') }}</span>@endif
                                <span>{{ $t('search.page_of', 'Trang :current / :last') }}</span>
                                @if ($listingItems->nextPageUrl())<a href="{{ $listingItems->nextPageUrl() }}">{{ $t('search.next_page', 'Trang sau') }}</a>@else<span>{{ $t('search.next_page', 'Trang sau') }}</span>@endif
                            </div>
                        @endif
                    </div>
                    <aside class="side-stack">
                        <section class="side-card"><h3>{{ $t('cms.quick_contact_title', 'Liên hệ nhanh') }}</h3><strong>{{ $contactHotline }}</strong><span>{{ $contactEmail }}</span><p>{{ $contactLocation }}</p></section>
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
                <section class="hero"><span class="badge">{{ ($contentType ?? null) === 'post' ? $t('menu.default.blog', 'Cẩm nang') : $t('cms.company_content_badge', 'Nội dung doanh nghiệp') }}</span><h1>{{ $entry->title ?? $t('cms.default_title', 'Nội dung dịch vụ') }}</h1><p>{{ $entry->excerpt ?: $pageDescription }}</p></section>
                <section class="layout">
                    <div class="grid">
                        <article class="panel">
                            @if (!empty($entry->featuredMedia?->url))
                                <img src="{{ $entry->featuredMedia?->url }}" alt="{{ $entry->title }}" style="width:100%;aspect-ratio:16/8;object-fit:cover;border-radius:20px;margin-bottom:18px;">
                            @endif
                            @if (($contentType ?? null) === 'post')<div class="meta">{{ $entry->publish_at?->format('d/m/Y') }}</div>@endif
                            <div class="body-copy">{!! $entry->body ?? '' !!}</div>
                        </article>
                        @if (($contentType ?? null) === 'page' && $latestPostItems->isNotEmpty())
                            <section class="grid">
                                <div class="panel"><h2>{{ $t('cms.latest_posts_title', 'Bài viết mới') }}</h2></div>
                                <div class="post-grid">
                                    @foreach ($latestPostItems as $post)
                                        <article class="post-card"><img src="{{ $post->featuredMedia?->url ?: 'https://picsum.photos/seed/ser0100-page-post/960/720' }}" alt="{{ $post->title }}"><div class="post-card-body"><div class="meta">{{ $post->publish_at?->format('d/m/Y') }}</div><h4><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h4><p>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 160, '...') }}</p></div></article>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                        @if (($contentType ?? null) === 'post' && $relatedPostItems->isNotEmpty())
                            <section class="grid">
                                <div class="panel"><h2>{{ $t('cms.related_posts_title', 'Nội dung liên quan') }}</h2></div>
                                <div class="post-grid">
                                    @foreach ($relatedPostItems as $post)
                                        <article class="post-card"><img src="{{ $post->featuredMedia?->url ?: 'https://picsum.photos/seed/ser0100-related-post/960/720' }}" alt="{{ $post->title }}"><div class="post-card-body"><div class="meta">{{ $post->publish_at?->format('d/m/Y') }}</div><h4><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h4><p>{{ $post->excerpt ?: mb_strimwidth(strip_tags((string) $post->body), 0, 160, '...') }}</p></div></article>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                    <aside class="side-stack">
                        <section class="side-card"><h3>Thông tin điều phối</h3><strong>{{ data_get($branding, 'company_name', 'SER0100') }}</strong><span>{{ $contactHotline }}</span><span>{{ $contactEmail }}</span><p>{{ $contactLocation }}</p></section>
                        <section class="side-card"><h3>{{ $t('cms.trust_layer_title', 'Lớp nội dung tin cậy') }}</h3><p>{{ $t('cms.trust_layer_body', 'Theme service ưu tiên page giới thiệu, quy trình đặt xe, bảng giá tham khảo và bài viết hướng dẫn để tăng conversion.') }}</p></section>
                    </aside>
                </section>
            @endif
        </main>
        <footer class="footer"><div class="wrap footer-inner"><div class="footer-grid"><section><h4>{{ data_get($branding, 'company_name', 'SER0100') }}</h4><p>{{ $t('cms.footer_summary', 'Theme service-first cho nhà xe, shuttle và logistics nhẹ.') }}</p>@include('partials.boc-footer-status', ['branding' => $branding ?? [], 'class' => 'ser-footer-boc-status'])</section><section><h4>{{ $t('cms.footer_contact_title', 'Liên hệ') }}</h4><p>{{ $contactHotline }}</p><p>{{ $contactEmail }}</p><p>{{ $contactLocation }}</p></section><section><h4>{{ $t('cms.footer_nav_title', 'Điều hướng') }}</h4><a href="{{ route('site.home') }}">{{ $t('common.home', 'Trang chủ') }}</a><br><a href="{{ route('site.blog.index') }}">{{ $t('menu.default.blog', 'Cẩm nang') }}</a></section></div></div></footer>
        @include('theme-ser0100::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
