@php
    $contentEntry = $post ?? $entry ?? null;
    $title = data_get($contentEntry, 'title', 'Tin tức');
    $excerpt = trim((string) data_get($contentEntry, 'excerpt', ''));
    $body = trim((string) data_get($contentEntry, 'body', ''));
    $cover = data_get($contentEntry, 'featuredMedia.file_url');
    $coverAlt = data_get($contentEntry, 'featuredMedia.alt_text') ?: $title;
    $category = data_get($contentEntry, 'category');
    $publishedAt = data_get($contentEntry, 'publish_at') ?: data_get($contentEntry, 'updated_at');
    $wordCount = count(preg_split('/\s+/u', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    $readingMinutes = max(1, (int) ceil($wordCount / 220));
    $related = collect($relatedPosts ?? [])->take(10)->values();
    $currentUrl = url()->current();
    $shareUrl = urlencode($currentUrl);
    $shareTitle = urlencode($title);
    $branding = (array) data_get($themeShellData ?? [], 'branding', []);
    $companyName = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'DN351')));

    if ($body === '') {
        $body = '<p>'.e($excerpt !== '' ? $excerpt : 'Nội dung bài viết đang được cập nhật.').'</p>';
    }
@endphp

@extends('theme-dn351::layout')

@section('title', $pageTitle ?? $title)

@push('head')
    <style>
        .dn-article-page{background:var(--dn-cream);color:var(--dn-ink)}
        .dn-article-hero{position:relative;overflow:hidden;padding:255px 0 90px;background:var(--dn-navy);color:#fff}.dn-article-hero::before{content:"";position:absolute;right:-180px;bottom:-310px;width:620px;height:620px;border:1px solid rgba(255,255,255,.1);border-radius:50%;box-shadow:0 0 0 75px rgba(255,255,255,.025),0 0 0 150px rgba(255,255,255,.018)}
        .dn-article-breadcrumb{position:relative;z-index:2;display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:38px;color:rgba(255,255,255,.6);font-size:14px;font-weight:700}.dn-article-breadcrumb a{color:rgba(255,255,255,.8)}.dn-article-breadcrumb a:hover{color:var(--dn-champagne)}
        .dn-article-hero-grid{position:relative;z-index:2;display:grid;grid-template-columns:minmax(0,.95fr) minmax(420px,.75fr);gap:70px;align-items:center}.dn-article-kicker{display:inline-flex;align-items:center;min-height:32px;margin-bottom:20px;padding:0 12px;background:var(--dn-champagne);color:var(--dn-navy);font-size:11px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.dn-article-hero h1{margin:0;color:#fff;font:700 clamp(43px,5vw,68px)/1.06 var(--dn-display);letter-spacing:-.042em}.dn-article-lead{max-width:820px;margin:24px 0 0;color:rgba(255,255,255,.72);font-size:19px;line-height:1.75}
        .dn-article-meta{display:flex;flex-wrap:wrap;gap:18px;margin-top:29px;color:rgba(255,255,255,.75);font-size:13px;font-weight:750}.dn-article-meta span{display:inline-flex;align-items:center;gap:8px}.dn-article-meta i{color:var(--dn-champagne)}
        .dn-article-hero-image{position:relative;height:460px;overflow:hidden;background:var(--dn-navy-deep);box-shadow:0 30px 75px rgba(14,22,39,.4)}.dn-article-hero-image::before{content:"";position:absolute;z-index:2;inset:18px;border:1px solid rgba(255,255,255,.4);pointer-events:none}.dn-article-hero-image img{width:100%;height:100%;object-fit:cover}.dn-article-hero-placeholder{width:100%;height:100%;display:grid;place-items:center;color:var(--dn-champagne);font-size:68px;background:linear-gradient(145deg,var(--dn-navy-deep),#596988)}
        .dn-article-main{padding:70px 0 105px}.dn-article-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:38px;align-items:start}.dn-article-card,.dn-article-sidebar-card{background:#fff;box-shadow:var(--dn-shadow)}.dn-article-card{padding:52px 60px}.dn-article-intro{margin:0 0 34px;padding:0 0 30px;border-bottom:1px solid #e1e5eb;color:var(--dn-navy);font:650 21px/1.7 var(--dn-display)}
        .dn-article-content{color:#4e5c74;font-size:18px;line-height:1.85}.dn-article-content>*:first-child{margin-top:0}.dn-article-content h2,.dn-article-content h3,.dn-article-content h4{scroll-margin-top:120px;color:var(--dn-navy);font-family:var(--dn-display);line-height:1.28}.dn-article-content h2{margin:48px 0 19px;font-size:34px}.dn-article-content h3{margin:36px 0 15px;font-size:26px}.dn-article-content p{margin:0 0 22px}.dn-article-content ul,.dn-article-content ol{margin:0 0 24px;padding-left:25px}.dn-article-content li{margin-bottom:9px}.dn-article-content a{color:#9b7434;text-decoration:underline;text-underline-offset:3px}.dn-article-content img{max-width:100%;height:auto;margin:18px auto;box-shadow:0 18px 45px rgba(31,42,68,.14)}.dn-article-content figure{margin:32px 0}.dn-article-content figcaption{margin-top:10px;color:var(--dn-muted);font-size:13px;text-align:center}.dn-article-content blockquote{margin:34px 0;padding:25px 30px;border-left:5px solid var(--dn-champagne);background:#f6f1e7;color:var(--dn-navy);font:650 20px/1.65 var(--dn-display)}.dn-article-content table{width:100%;margin:26px 0;border-collapse:collapse}.dn-article-content th,.dn-article-content td{padding:13px 15px;border:1px solid #dfe3e9;text-align:left}.dn-article-content th{background:var(--dn-navy);color:#fff}
        .dn-article-author{display:grid;grid-template-columns:64px 1fr;gap:17px;align-items:center;margin-top:50px;padding:25px;border-top:1px solid #e1e5eb;background:#faf8f3}.dn-article-author__mark{width:64px;height:64px;display:grid;place-items:center;border-radius:50%;background:var(--dn-navy);color:var(--dn-champagne);font:700 24px var(--dn-display)}.dn-article-author small,.dn-article-author strong,.dn-article-author span{display:block}.dn-article-author small{color:#9a783d;font-size:11px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.dn-article-author strong{margin-top:4px;color:var(--dn-navy);font-size:18px}.dn-article-author span{margin-top:3px;color:var(--dn-muted);font-size:13px}
        .dn-article-sidebar{position:sticky;top:115px;display:grid;gap:20px}.dn-article-sidebar-card{padding:28px}.dn-article-sidebar-card h2,.dn-article-sidebar-card h3{margin:0 0 18px;color:var(--dn-navy);font:700 23px/1.25 var(--dn-display)}.dn-article-toc[hidden]{display:none}.dn-article-toc-list{display:grid;gap:0;margin:0;padding:0;list-style:none}.dn-article-toc-list a{display:block;padding:11px 0;border-top:1px solid #e6e9ee;color:#5a667b;font-size:13px;font-weight:750;line-height:1.45}.dn-article-toc-list li:first-child a{border-top:0}.dn-article-toc-list .is-sub a{padding-left:15px;font-size:12px;font-weight:650}.dn-article-toc-list a:hover{color:#9a783d;padding-left:5px}.dn-article-toc-list .is-sub a:hover{padding-left:20px}
        .dn-article-share{display:flex;flex-wrap:wrap;gap:8px}.dn-article-share a,.dn-article-share button{width:42px;height:42px;display:grid;place-items:center;border:1px solid #dce1e8;background:#fff;color:var(--dn-navy);cursor:pointer;transition:.2s}.dn-article-share a:hover,.dn-article-share button:hover{border-color:var(--dn-champagne);background:var(--dn-champagne);transform:translateY(-2px)}.dn-article-share-copy.is-copied{border-color:#3b8b54;background:#edf8ef;color:#26743d}
        .dn-article-cta{padding:30px;background:var(--dn-navy);color:#fff;box-shadow:var(--dn-shadow)}.dn-article-cta>span{color:var(--dn-champagne);font-size:11px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.dn-article-cta h3{margin:10px 0 12px;color:#fff;font:700 25px/1.25 var(--dn-display)}.dn-article-cta p{margin:0;color:rgba(255,255,255,.7);font-size:14px;line-height:1.65}.dn-article-cta button{width:100%;min-height:50px;margin-top:20px;border:0;background:var(--dn-champagne);color:var(--dn-navy);font-weight:850;text-transform:uppercase;cursor:pointer}
        .dn-article-related{margin-top:80px}.dn-article-section-head{display:flex;align-items:end;justify-content:space-between;gap:25px;margin-bottom:32px}.dn-article-section-head p{margin:0 0 8px;color:#9a783d;font-size:12px;font-weight:850;letter-spacing:.15em;text-transform:uppercase}.dn-article-section-head h2{margin:0;color:var(--dn-navy);font:700 40px/1.15 var(--dn-display)}
        .dn-article-related-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:18px}.dn-article-related-card{overflow:hidden;background:#fff;box-shadow:var(--dn-shadow);transition:.25s}.dn-article-related-card:hover{transform:translateY(-7px)}.dn-article-related-image{display:block;height:175px;overflow:hidden;background:var(--dn-navy)}.dn-article-related-image img{width:100%;height:100%;object-fit:cover;transition:transform .5s}.dn-article-related-card:hover img{transform:scale(1.06)}.dn-article-related-placeholder{width:100%;height:100%;display:grid;place-items:center;color:var(--dn-champagne);font-size:34px}.dn-article-related-body{padding:18px}.dn-article-related-body time,.dn-article-related-body span{color:#9a783d;font-size:10px;font-weight:850;letter-spacing:.07em;text-transform:uppercase}.dn-article-related-body h3{margin:8px 0 0;color:var(--dn-navy);font:700 16px/1.4 var(--dn-display)}
        @media(max-width:1180px){.dn-article-hero-grid,.dn-article-layout{grid-template-columns:1fr}.dn-article-hero-image{height:540px}.dn-article-sidebar{position:static;grid-template-columns:1fr 1fr}.dn-article-related-grid{grid-template-columns:repeat(3,1fr)}}
        @media(max-width:760px){.dn-article-hero{padding:110px 0 70px}.dn-article-hero-grid{gap:38px}.dn-article-hero-image{height:350px}.dn-article-card{padding:28px 22px}.dn-article-intro{font-size:18px}.dn-article-content{font-size:16px}.dn-article-content h2{font-size:28px}.dn-article-sidebar,.dn-article-related-grid{grid-template-columns:1fr}.dn-article-section-head h2{font-size:32px}.dn-article-related-image{height:230px}}
    </style>
@endpush

@section('content')
    <main class="dn-article-page">
        <section class="dn-article-hero">
            <div class="dn-container">
                <nav class="dn-article-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><a href="{{ route('site.blog.index') }}">Tin tức</a><span>/</span><span>{{ $title }}</span></nav>
                <div class="dn-article-hero-grid">
                    <div>
                        <span class="dn-article-kicker">{{ data_get($category, 'name', 'Kiến thức & kinh nghiệm') }}</span>
                        <h1>{{ $title }}</h1>
                        @if ($excerpt !== '')<p class="dn-article-lead">{{ $excerpt }}</p>@endif
                        <div class="dn-article-meta">
                            @if ($publishedAt)<span><i class="fa-regular fa-calendar"></i>{{ $publishedAt->format('d/m/Y') }}</span>@endif
                            <span><i class="fa-regular fa-clock"></i>{{ $readingMinutes }} phút đọc</span>
                            <span><i class="fa-regular fa-folder"></i>{{ data_get($category, 'name', 'Tin tức') }}</span>
                        </div>
                    </div>
                    <div class="dn-article-hero-image">
                        @if ($cover)<img src="{{ $cover }}" alt="{{ $coverAlt }}">@else<span class="dn-article-hero-placeholder"><i class="fa-regular fa-newspaper"></i></span>@endif
                    </div>
                </div>
            </div>
        </section>

        <section class="dn-article-main">
            <div class="dn-container">
                <div class="dn-article-layout">
                    <article class="dn-article-card">
                        @if ($excerpt !== '')<p class="dn-article-intro">{{ $excerpt }}</p>@endif
                        <div class="dn-article-content" data-dn-article-content>{!! $body !!}</div>
                        <footer class="dn-article-author">
                            <span class="dn-article-author__mark">{{ mb_strtoupper(mb_substr($companyName, 0, 1)) }}</span>
                            <div><small>Biên tập bởi</small><strong>{{ $companyName }}</strong><span>Nội dung được tổng hợp và kiểm duyệt bởi đội ngũ chuyên môn.</span></div>
                        </footer>
                    </article>

                    <aside class="dn-article-sidebar">
                        <section class="dn-article-sidebar-card dn-article-toc" data-dn-article-toc hidden><h2>Mục lục bài viết</h2><ol class="dn-article-toc-list" data-dn-article-toc-list></ol></section>
                        <section class="dn-article-sidebar-card"><h3>Chia sẻ bài viết</h3><div class="dn-article-share"><a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Chia sẻ Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}" target="_blank" rel="noopener" aria-label="Chia sẻ LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a><a href="mailto:?subject={{ $shareTitle }}&body={{ $shareUrl }}" aria-label="Chia sẻ qua email"><i class="fa-regular fa-envelope"></i></a><button type="button" class="dn-article-share-copy" data-dn-copy-url data-url="{{ $currentUrl }}" aria-label="Sao chép liên kết"><i class="fa-solid fa-link"></i></button></div></section>
                        <section class="dn-article-cta"><span>Bạn cần tư vấn?</span><h3>Trao đổi với chuyên gia</h3><p>Để lại nhu cầu để đội ngũ của chúng tôi tư vấn giải pháp phù hợp.</p><button type="button" data-dn-consult-open>Đăng ký tư vấn</button></section>
                    </aside>
                </div>

                @if ($related->isNotEmpty())
                    <section class="dn-article-related" aria-labelledby="dn-related-posts-title">
                        <header class="dn-article-section-head"><div><p>Đọc thêm</p><h2 id="dn-related-posts-title">Tin liên quan</h2></div><a class="dn-btn" href="{{ route('site.blog.index') }}">Xem tất cả</a></header>
                        <div class="dn-article-related-grid">
                            @foreach ($related as $relatedPost)
                                @php($relatedImage = $relatedPost->featuredMedia?->file_url)
                                <article class="dn-article-related-card" data-related-post-card>
                                    <a class="dn-article-related-image" href="{{ route('site.blog.show', ['slug' => $relatedPost->slug]) }}">@if ($relatedImage)<img src="{{ $relatedImage }}" alt="{{ $relatedPost->featuredMedia?->alt_text ?: $relatedPost->title }}" loading="lazy">@else<span class="dn-article-related-placeholder"><i class="fa-regular fa-newspaper"></i></span>@endif</a>
                                    <div class="dn-article-related-body">@if ($relatedPost->publish_at)<time datetime="{{ $relatedPost->publish_at->toDateString() }}">{{ $relatedPost->publish_at->format('d/m/Y') }}</time>@else<span>Tin mới</span>@endif<h3><a href="{{ route('site.blog.show', ['slug' => $relatedPost->slug]) }}">{{ $relatedPost->title }}</a></h3></div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const content = document.querySelector('[data-dn-article-content]');
            const toc = document.querySelector('[data-dn-article-toc]');
            const list = document.querySelector('[data-dn-article-toc-list]');
            if (content && toc && list) {
                const headings = Array.from(content.querySelectorAll('h2, h3'));
                headings.forEach((heading, index) => {
                    if (!heading.id) heading.id = `noi-dung-${index + 1}`;
                    const item = document.createElement('li');
                    const link = document.createElement('a');
                    link.href = `#${heading.id}`;
                    link.textContent = heading.textContent || `Nội dung ${index + 1}`;
                    item.className = heading.tagName === 'H3' ? 'is-sub' : '';
                    item.appendChild(link);
                    list.appendChild(item);
                });
                if (headings.length) toc.hidden = false;
            }
            document.querySelector('[data-dn-copy-url]')?.addEventListener('click', async (event) => {
                const button = event.currentTarget;
                try {
                    await navigator.clipboard.writeText(button.dataset.url || window.location.href);
                    button.classList.add('is-copied');
                    window.setTimeout(() => button.classList.remove('is-copied'), 1600);
                } catch (_) {}
            });
        })();
    </script>
@endpush
