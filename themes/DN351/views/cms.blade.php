@php
    $contentEntry = $page ?? $entry ?? null;
    $title = trim((string) data_get($contentEntry, 'title', 'Nội dung'));
    $summary = trim((string) data_get($contentEntry, 'excerpt', ''));
    $body = trim((string) data_get($contentEntry, 'body', ''));
    $cover = data_get($contentEntry, 'featuredMedia.file_url');
    $coverAlt = data_get($contentEntry, 'featuredMedia.alt_text') ?: $title;
    $publishedAt = data_get($contentEntry, 'publish_at') ?: data_get($contentEntry, 'updated_at');
    $latest = collect($latestPosts ?? [])->take(3)->values();
    $branding = (array) data_get($themeShellData ?? [], 'branding', []);
    $companyName = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'DN351')));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));

    if ($body === '') {
        $body = '<p>'.e($summary !== '' ? $summary : 'Nội dung trang đang được cập nhật.').'</p>';
    }
@endphp

@extends('theme-dn351::layout')

@section('title', $pageTitle ?? $title)

@push('head')
    <style>
        .dn-page-detail{background:var(--dn-cream);color:var(--dn-ink)}
        .dn-page-hero{position:relative;overflow:hidden;padding:200px 0 0px;background:var(--dn-navy);color:#fff}
        .dn-page-hero::before{content:"";position:absolute;right:-170px;bottom:-300px;width:600px;height:600px;border:1px solid rgba(255,255,255,.1);border-radius:50%;box-shadow:0 0 0 70px rgba(255,255,255,.025),0 0 0 140px rgba(255,255,255,.018)}
        .dn-page-breadcrumb{position:relative;z-index:2;display:flex;flex-wrap:wrap;align-items:center;gap:10px;color:rgba(255,255,255,.58);font-size:14px;font-weight:700}
        .dn-page-breadcrumb a{color:rgba(255,255,255,.82)}.dn-page-breadcrumb a:hover{color:var(--dn-champagne)}
        .dn-page-hero-grid{position:relative;z-index:2;display:grid;grid-template-columns:minmax(0,.92fr) minmax(430px,.72fr);gap:72px;align-items:center}
        .dn-page-kicker{display:inline-flex;align-items:center;gap:10px;margin-bottom:20px;color:var(--dn-champagne);font-size:11px;font-weight:850;letter-spacing:.15em;text-transform:uppercase}
        .dn-page-kicker::before{content:"";width:35px;height:1px;background:currentColor}
        .dn-page-hero h1{max-width:780px;margin:0;color:#fff;font:700 clamp(46px,5.2vw,72px)/1.04 var(--dn-display);letter-spacing:-.045em}
        .dn-page-lead{max-width:760px;margin:26px 0 0;color:rgba(255,255,255,.72);font-size:19px;line-height:1.75}
        .dn-page-meta{display:flex;flex-wrap:wrap;gap:20px;margin-top:30px;color:rgba(255,255,255,.68);font-size:13px;font-weight:750}.dn-page-meta span{display:inline-flex;align-items:center;gap:8px}.dn-page-meta i{color:var(--dn-champagne)}
        .dn-page-hero-media{position:relative;overflow:hidden;box-shadow:0 30px 75px rgba(14,22,39,.42)}
        .dn-page-hero-media::after{content:"";position:absolute;inset:18px;border:1px solid rgba(255,255,255,.42);pointer-events:none}
        .dn-page-hero-media img{width:100%;}
        .dn-page-hero-placeholder{width:100%;height:100%;display:grid;place-items:center;background:linear-gradient(145deg,var(--dn-navy-deep),#596988);color:var(--dn-champagne);font-size:72px}
        .dn-page-main{padding:76px 0 110px}.dn-page-layout{display:grid;grid-template-columns:minmax(0,1fr) 335px;gap:40px;align-items:start}
        .dn-page-card{padding:56px 62px;background:#fff;box-shadow:var(--dn-shadow)}
        .dn-page-intro{display:grid;grid-template-columns:74px 1fr;gap:24px;align-items:start;margin:0 0 38px;padding:0 0 34px;border-bottom:1px solid #e1e5eb}
        .dn-page-intro__mark{width:74px;height:74px;display:grid;place-items:center;background:var(--dn-champagne);color:var(--dn-navy);font:700 24px var(--dn-display)}
        .dn-page-intro p{margin:0;color:var(--dn-navy);font:650 21px/1.7 var(--dn-display)}
        .dn-page-content{color:#4f5d74;font-size:18px;line-height:1.85}.dn-page-content>*:first-child{margin-top:0}.dn-page-content>*:last-child{margin-bottom:0}
        .dn-page-content h2,.dn-page-content h3,.dn-page-content h4{color:var(--dn-navy);font-family:var(--dn-display);line-height:1.28}.dn-page-content h2{margin:48px 0 19px;font-size:35px}.dn-page-content h3{margin:36px 0 15px;font-size:27px}.dn-page-content h4{margin:30px 0 12px;font-size:21px}
        .dn-page-content p{margin:0 0 22px}.dn-page-content ul,.dn-page-content ol{margin:0 0 24px;padding-left:25px}.dn-page-content li{margin-bottom:9px}.dn-page-content a{color:#967037;text-decoration:underline;text-underline-offset:3px}
        .dn-page-content img{max-width:100%;height:auto;margin:20px auto;box-shadow:0 18px 45px rgba(31,42,68,.14)}.dn-page-content figure{margin:32px 0}.dn-page-content figcaption{margin-top:10px;color:var(--dn-muted);font-size:13px;text-align:center}
        .dn-page-content blockquote{margin:34px 0;padding:25px 30px;border-left:5px solid var(--dn-champagne);background:#f6f1e7;color:var(--dn-navy);font:650 20px/1.65 var(--dn-display)}
        .dn-page-content table{width:100%;margin:28px 0;border-collapse:collapse}.dn-page-content th,.dn-page-content td{padding:13px 15px;border:1px solid #dfe3e9;text-align:left}.dn-page-content th{background:var(--dn-navy);color:#fff}
        .dn-page-sidebar{position:sticky;top:115px;display:grid;gap:20px}.dn-page-side-card{padding:28px;background:#fff;box-shadow:var(--dn-shadow)}
        .dn-page-side-card>span{color:#9a783d;font-size:10px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.dn-page-side-card h2,.dn-page-side-card h3{margin:8px 0 17px;color:var(--dn-navy);font:700 23px/1.28 var(--dn-display)}
        .dn-page-contact-list{display:grid;margin-top:5px}.dn-page-contact-list a{display:grid;grid-template-columns:38px 1fr;gap:12px;align-items:center;padding:13px 0;border-top:1px solid #e5e8ed;color:#556278;font-size:13px;font-weight:700}.dn-page-contact-list i{width:38px;height:38px;display:grid;place-items:center;background:#f3eee4;color:#987137}
        .dn-page-cta{padding:30px;background:var(--dn-navy);color:#fff;box-shadow:var(--dn-shadow)}.dn-page-cta>span{color:var(--dn-champagne);font-size:10px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.dn-page-cta h3{margin:10px 0 12px;color:#fff;font:700 25px/1.28 var(--dn-display)}.dn-page-cta p{margin:0;color:rgba(255,255,255,.7);font-size:14px;line-height:1.65}.dn-page-cta button{width:100%;min-height:50px;margin-top:20px;border:0;background:var(--dn-champagne);color:var(--dn-navy);font-weight:850;text-transform:uppercase;cursor:pointer}
        .dn-page-latest{margin-top:80px}.dn-page-section-head{display:flex;align-items:end;justify-content:space-between;gap:25px;margin-bottom:32px}.dn-page-section-head p{margin:0 0 8px;color:#9a783d;font-size:11px;font-weight:850;letter-spacing:.15em;text-transform:uppercase}.dn-page-section-head h2{margin:0;color:var(--dn-navy);font:700 40px/1.15 var(--dn-display)}
        .dn-page-latest-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.dn-page-latest-card{display:grid;grid-template-columns:160px 1fr;min-height:160px;overflow:hidden;background:#fff;box-shadow:var(--dn-shadow);transition:.25s}.dn-page-latest-card:hover{transform:translateY(-6px)}.dn-page-latest-image{overflow:hidden;background:var(--dn-navy)}.dn-page-latest-image img{width:100%;height:100%;object-fit:cover;transition:transform .5s}.dn-page-latest-card:hover img{transform:scale(1.06)}.dn-page-latest-placeholder{width:100%;height:100%;display:grid;place-items:center;color:var(--dn-champagne);font-size:32px}.dn-page-latest-body{padding:22px}.dn-page-latest-body time{color:#9a783d;font-size:10px;font-weight:850;letter-spacing:.07em;text-transform:uppercase}.dn-page-latest-body h3{margin:9px 0 0;color:var(--dn-navy);font:700 17px/1.42 var(--dn-display)}
        @media(max-width:1180px){.dn-page-hero-grid,.dn-page-layout{grid-template-columns:1fr}.dn-page-sidebar{position:static;grid-template-columns:1fr 1fr}.dn-page-latest-grid{grid-template-columns:1fr}.dn-page-latest-card{grid-template-columns:220px 1fr}}
        @media(max-width:760px){.dn-page-hero{padding:110px 0 70px}.dn-page-hero-grid{gap:38px}.dn-page-card{padding:30px 22px}.dn-page-intro{grid-template-columns:1fr}.dn-page-intro__mark{width:58px;height:58px}.dn-page-intro p{font-size:18px}.dn-page-content{font-size:16px}.dn-page-content h2{font-size:28px}.dn-page-sidebar{grid-template-columns:1fr}.dn-page-section-head h2{font-size:32px}.dn-page-latest-card{grid-template-columns:110px 1fr}.dn-page-latest-body{padding:17px}}
    </style>
@endpush

@section('content')
    <main class="dn-page-detail" data-dn-page-detail>
        <section class="dn-page-hero">
            <div class="dn-container">
                <nav class="dn-page-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $title }}</span>
                </nav>
                <div class="dn-page-hero-grid">
                    <div>
                        <span class="dn-page-kicker">Về {{ $companyName }}</span>
                        <h1>{{ $title }}</h1>
                        @if ($summary !== '')<p class="dn-page-lead">{{ $summary }}</p>@endif
                        <div class="dn-page-meta">
                            @if ($publishedAt)<span><i class="fa-regular fa-calendar"></i>Cập nhật {{ $publishedAt->format('d/m/Y') }}</span>@endif
                            <span><i class="fa-regular fa-file-lines"></i>Trang thông tin</span>
                        </div>
                    </div>
                    <div class="dn-page-hero-media">
                        @if ($cover)
                            <img src="{{ $cover }}" alt="{{ $coverAlt }}">
                        @else
                            <span class="dn-page-hero-placeholder"><i class="fa-regular fa-building"></i></span>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section class="dn-page-main">
            <div class="dn-container">
                <div class="dn-page-layout">
                    <article class="dn-page-card">
                        @if ($summary !== '')
                            <header class="dn-page-intro">
                                <span class="dn-page-intro__mark">01</span>
                                <p>{{ $summary }}</p>
                            </header>
                        @endif
                        <div class="dn-page-content" data-dn-page-content>{!! $body !!}</div>
                    </article>

                    <aside class="dn-page-sidebar">
                        <section class="dn-page-side-card">
                            <span>Kết nối với chúng tôi</span>
                            <h2>{{ $companyName }}</h2>
                            <div class="dn-page-contact-list">
                                <a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i><span>{{ $hotline }}</span></a>
                                <a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i><span>{{ $email }}</span></a>
                                <a href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i><span>Xem thông tin liên hệ</span></a>
                            </div>
                        </section>
                        <section class="dn-page-cta">
                            <span>Bạn có dự án mới?</span>
                            <h3>Cùng tạo nên không gian khác biệt</h3>
                            <p>Chia sẻ nhu cầu để đội ngũ chuyên môn đề xuất giải pháp phù hợp.</p>
                            <button type="button" data-dn-consult-open>Đăng ký tư vấn</button>
                        </section>
                    </aside>
                </div>

                @if ($latest->isNotEmpty())
                    <section class="dn-page-latest" aria-labelledby="dn-page-latest-title">
                        <header class="dn-page-section-head">
                            <div><p>Góc chuyên môn</p><h2 id="dn-page-latest-title">Bài viết mới nhất</h2></div>
                            <a class="dn-btn" href="{{ route('site.blog.index') }}">Xem tất cả</a>
                        </header>
                        <div class="dn-page-latest-grid">
                            @foreach ($latest as $post)
                                @php($postImage = data_get($post, 'featuredMedia.file_url'))
                                <article class="dn-page-latest-card">
                                    <a class="dn-page-latest-image" href="{{ route('site.blog.show', ['slug' => data_get($post, 'slug')]) }}">
                                        @if ($postImage)<img src="{{ $postImage }}" alt="{{ data_get($post, 'title') }}" loading="lazy">@else<span class="dn-page-latest-placeholder"><i class="fa-regular fa-newspaper"></i></span>@endif
                                    </a>
                                    <div class="dn-page-latest-body">
                                        @if (data_get($post, 'publish_at'))<time datetime="{{ data_get($post, 'publish_at')->toDateString() }}">{{ data_get($post, 'publish_at')->format('d/m/Y') }}</time>@endif
                                        <h3><a href="{{ route('site.blog.show', ['slug' => data_get($post, 'slug')]) }}">{{ data_get($post, 'title') }}</a></h3>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </section>
    </main>
@endsection
