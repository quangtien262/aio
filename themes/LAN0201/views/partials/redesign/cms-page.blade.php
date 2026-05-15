@php
    $heroImage = $entry->featured_image_url ?? $entry->cover_image_url ?? $entry->image_url ?? 'https://picsum.photos/seed/lan0201-cms/1200/720';
    $bodyHtml = $entry->body ?: '<p>'.$t('cms.content_updating', 'Nội dung đang được cập nhật.').'</p>';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $pageTitle ?? data_get($branding, 'company_name', 'LAN0201') }}</title>
        @if (!empty($pageDescription))
            <meta name="description" content="{{ $pageDescription }}">
        @endif
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>@include('theme-lan0201::partials.landing-style', ['branding' => $branding])</style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', ['branding' => $branding, 'topMenu' => $topMenu, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'cartSummary' => $cartSummary, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'activeNav' => 'cms'])
                <main class="th-landing-main">
                    <div class="th-landing-container">
                        @if ($contentType === 'listing')
                            <section class="th-landing-hero">
                                <span class="th-landing-kicker">Tin dự án</span>
                                <h1 class="th-landing-title">Bản tin mở bán và cập nhật thị trường</h1>
                                <p class="th-landing-summary">Trang CMS được đổi thành một editorial landing hub, đồng bộ với shell mới của LAN0201 thay vì dùng layout tin tức kiểu store.</p>
                                <form method="GET" action="{{ route('site.blog.index') }}" style="margin-top:18px;">
                                    <div class="th-landing-form-grid">
                                        <div class="th-landing-field"><label for="q">Từ khóa</label><input id="q" type="search" name="q" value="{{ $postFilters['q'] ?? '' }}"></div>
                                        <div class="th-landing-field"><label for="category">Chuyên mục</label><select id="category" name="category"><option value="">Tất cả</option>@foreach ($postCategories as $postCategory)<option value="{{ $postCategory->slug ?? '' }}" @selected(($postFilters['category'] ?? '') === ($postCategory->slug ?? ''))>{{ $postCategory->name ?? 'Chuyên mục' }}</option>@endforeach</select></div>
                                    </div>
                                    <div class="th-landing-actions-row"><button type="submit" class="th-landing-button">Lọc bài viết</button></div>
                                </form>
                            </section>
                            <section style="margin-top:26px;">
                                @if ($listingCollection->isEmpty())
                                    <div class="th-landing-empty">Chưa có bài viết phù hợp.</div>
                                @else
                                    <div class="th-landing-three-col">
                                        @foreach ($listingCollection as $post)
                                            <article class="th-landing-card" style="overflow:hidden;">
                                                <a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}"><img src="{{ $post->featured_image_url ?? $post->cover_image_url ?? 'https://picsum.photos/seed/lan0201-post/'.($loop->index + 1).'/720/520' }}" alt="{{ $post->title }}" style="width:100%; aspect-ratio:16/10; object-fit:cover;"></a>
                                                <div style="padding:20px;">
                                                    <span class="th-landing-chip">{{ $post->category?->name ?? 'Tin dự án' }}</span>
                                                    <h3 class="th-landing-listing-title" style="margin-top:12px;"><a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}">{{ $post->title }}</a></h3>
                                                    <div class="th-landing-copy">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 150) }}</div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @else
                            <div class="th-landing-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $entry->title }}</span></div>
                            <section class="th-landing-hero">
                                <div class="th-landing-hero-grid">
                                    <div>
                                        <span class="th-landing-kicker">{{ $isPostDetail ? 'Chi tiết bài viết' : ($isContactPage ? 'Điểm chạm liên hệ' : 'Giới thiệu dự án') }}</span>
                                        <h1 class="th-landing-title">{{ $entry->title }}</h1>
                                        <p class="th-landing-summary">{{ $entry->excerpt ?: 'Nội dung CMS cũng đã được đưa vào shell landing-page mới của LAN0201.' }}</p>
                                    </div>
                                    <div class="th-landing-media" style="min-height:360px;"><img src="{{ $heroImage }}" alt="{{ $entry->title }}"></div>
                                </div>
                            </section>
                            <section style="margin-top:26px;" class="th-landing-two-col">
                                <article class="th-landing-panel">
                                    <div class="th-landing-copy">{!! $bodyHtml !!}</div>
                                </article>
                                <aside class="th-landing-panel">
                                    <span class="th-landing-kicker">Tiếp tục khám phá</span>
                                    <h2 class="th-landing-section-title">Bài viết và CTA liên quan</h2>
                                    <div style="display:grid; gap:12px;">
                                        @foreach (($isPostDetail ? $relatedPostItems : $latestPostItems) as $post)
                                            <a href="{{ route('site.blog.show', ['slug' => $post->slug]) }}" class="th-landing-card" style="padding:16px;">
                                                <strong>{{ $post->title }}</strong>
                                                <div class="th-landing-copy">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body ?? ''), 120) }}</div>
                                            </a>
                                        @endforeach
                                        <a href="{{ route('site.catalog.search') }}" class="th-landing-outline">Xem bảng hàng mở bán</a>
                                    </div>
                                </aside>
                            </section>
                        @endif
                    </div>
                </main>
                @include('theme-lan0201::partials.landing-footer', ['branding' => $branding, 'footerColumns' => $footerColumns])
            </div>
        </div>
        @include('theme-lan0201::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>