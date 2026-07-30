@php
    $contentEntry = $service ?? $entry ?? null;
    $title = data_get($contentEntry, 'title', 'Dịch vụ');
    $summary = trim((string) data_get($contentEntry, 'summary', data_get($contentEntry, 'excerpt', '')));
    $body = trim((string) data_get($contentEntry, 'content', data_get($contentEntry, 'body', '')));
    $category = data_get($contentEntry, 'category');
    $images = collect(data_get($contentEntry, 'images', []))
        ->filter(fn ($image) => filled(data_get($image, 'image_url')))
        ->unique(fn ($image) => data_get($image, 'image_url'))
        ->values();
    $featured = data_get($contentEntry, 'featuredImage');

    if ($images->isEmpty() && filled(data_get($featured, 'image_url'))) {
        $images = collect([$featured]);
    }

    $primaryImage = data_get($images->first(), 'image_url', data_get($featured, 'image_url'));
    $relatedServices = collect($latestServices ?? [])->take(6);
    $branding = (array) data_get($themeShellData ?? [], 'branding', []);
    $hotline = trim((string) data_get($branding, 'support_hotline', '0399162342'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $serviceLink = trim((string) data_get($contentEntry, 'link_url', ''));
    $serviceButton = trim((string) data_get($contentEntry, 'button_label', ''));

    if ($body === '') {
        $body = '<p>'.e($summary !== '' ? $summary : 'Thông tin dịch vụ đang được cập nhật.').'</p>';
    }
@endphp

@extends('theme-dn350::layout')

@section('title', $pageTitle ?? $title)

@push('head')
    <style>
        .dn-service-detail{background:var(--dn-cream);color:var(--dn-ink)}
        .dn-service-detail-hero{position:relative;overflow:hidden;padding:250px 0 85px;background:var(--dn-navy);color:#fff}
        .dn-service-detail-hero::before{content:"";position:absolute;right:-90px;top:180px;width:390px;height:390px;border:1px solid rgba(255,255,255,.09);border-radius:50%;box-shadow:0 0 0 65px rgba(255,255,255,.025),0 0 0 130px rgba(255,255,255,.018)}
        .dn-service-detail-breadcrumb{position:relative;z-index:2;display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:36px;color:rgba(255,255,255,.64);font-size:14px;font-weight:700}.dn-service-detail-breadcrumb a{color:rgba(255,255,255,.8)}.dn-service-detail-breadcrumb a:hover{color:var(--dn-champagne)}
        .dn-service-detail-hero-grid{position:relative;z-index:2;display:grid;grid-template-columns:minmax(0,.86fr) minmax(460px,1.14fr);gap:70px;align-items:center}
        .dn-service-detail-kicker{display:flex;align-items:center;gap:13px;margin:0 0 18px;color:var(--dn-champagne);font-size:12px;font-weight:850;letter-spacing:.17em;text-transform:uppercase}.dn-service-detail-kicker::before{content:"";width:36px;height:2px;background:var(--dn-champagne)}
        .dn-service-detail-hero h1{margin:0;color:#fff;font:700 clamp(43px,5vw,70px)/1.04 var(--dn-display);letter-spacing:-.04em}.dn-service-detail-summary{margin:25px 0 0;color:rgba(255,255,255,.75);font-size:19px;line-height:1.75}
        .dn-service-detail-actions{display:flex;flex-wrap:wrap;gap:11px;margin-top:34px}.dn-service-detail-action{min-height:54px;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:0 23px;border:1px solid rgba(255,255,255,.22);background:transparent;color:#fff;font:800 13px var(--dn-body);letter-spacing:.04em;text-transform:uppercase;cursor:pointer;transition:.22s}.dn-service-detail-action:hover{border-color:var(--dn-champagne);background:rgba(255,255,255,.08);color:#fff;transform:translateY(-2px)}.dn-service-detail-action.is-primary{border-color:var(--dn-champagne);background:var(--dn-champagne);color:var(--dn-navy)}
        [data-dn-service-gallery]{min-width:0;max-width:100%;overflow:hidden}.dn-service-detail-stage{position:relative;height:540px;overflow:hidden;background:var(--dn-navy-deep);box-shadow:0 30px 70px rgba(16,24,43,.38)}.dn-service-detail-stage>img{display:block;width:100%;height:100%;object-fit:cover;transition:opacity .2s ease,transform .55s ease}.dn-service-detail-stage.is-switching>img{opacity:.35;transform:scale(1.02)}
        .dn-service-detail-stage::after{content:"";position:absolute;inset:auto 0 0;height:34%;background:linear-gradient(transparent,rgba(21,30,51,.72));pointer-events:none}.dn-service-detail-stage-count{position:absolute;z-index:2;right:20px;bottom:18px;padding:8px 12px;background:rgba(255,255,255,.92);color:var(--dn-navy);font-size:12px;font-weight:850}
        .dn-service-detail-nav{position:absolute;z-index:3;top:50%;width:46px;height:46px;display:grid;place-items:center;border:0;border-radius:50%;background:rgba(255,255,255,.92);color:var(--dn-navy);box-shadow:0 10px 24px rgba(0,0,0,.2);cursor:pointer;transform:translateY(-50%);transition:.2s}.dn-service-detail-nav:hover{background:var(--dn-champagne);transform:translateY(-50%) scale(1.05)}.dn-service-detail-nav.prev{left:16px}.dn-service-detail-nav.next{right:16px}
        .dn-service-detail-thumbs{display:flex;max-width:100%;gap:10px;margin-top:12px;overflow-x:auto;overflow-y:hidden;overscroll-behavior-inline:contain;scrollbar-width:thin}.dn-service-detail-thumb{flex:0 0 86px;width:86px;height:68px;padding:3px;border:2px solid transparent;background:#fff;cursor:pointer;opacity:.72;transition:.2s}.dn-service-detail-thumb:hover,.dn-service-detail-thumb.is-active{border-color:var(--dn-champagne);opacity:1;transform:translateY(-2px)}.dn-service-detail-thumb img{display:block;width:100%;height:100%;object-fit:cover}
        .dn-service-detail-main{padding:70px 0 100px}.dn-service-assurances{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;margin-bottom:44px;background:#dfe3e9;box-shadow:var(--dn-shadow)}.dn-service-assurance{display:grid;grid-template-columns:54px 1fr;gap:15px;align-items:center;min-height:130px;padding:25px 28px;background:#fff}.dn-service-assurance i{width:54px;height:54px;display:grid;place-items:center;border-radius:50%;background:var(--dn-champagne);color:var(--dn-navy);font-size:21px}.dn-service-assurance strong,.dn-service-assurance span{display:block}.dn-service-assurance strong{color:var(--dn-navy);font-size:16px}.dn-service-assurance span{margin-top:4px;color:var(--dn-muted);font-size:13px;line-height:1.5}
        .dn-service-detail-content{display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:34px;align-items:start}.dn-service-detail-panel{padding:42px;background:#fff;box-shadow:var(--dn-shadow)}.dn-service-detail-panel h2,.dn-service-detail-panel h3{margin:0 0 23px;color:var(--dn-navy);font:700 32px/1.2 var(--dn-display)}.dn-service-detail-rich{color:#536078;font-size:17px;line-height:1.82}.dn-service-detail-rich>*:first-child{margin-top:0}.dn-service-detail-rich img{max-width:100%;height:auto;margin:18px 0;box-shadow:0 16px 38px rgba(34,45,70,.13)}
        .dn-service-detail-aside{position:sticky;top:120px;display:grid;gap:20px}.dn-service-detail-cta{padding:34px;background:var(--dn-navy);color:#fff;box-shadow:var(--dn-shadow)}.dn-service-detail-cta>span{color:var(--dn-champagne);font-size:11px;font-weight:850;letter-spacing:.15em;text-transform:uppercase}.dn-service-detail-cta h3{margin:11px 0 12px;color:#fff;font:700 28px/1.2 var(--dn-display)}.dn-service-detail-cta p{margin:0;color:rgba(255,255,255,.7);line-height:1.65}.dn-service-detail-cta .dn-service-detail-action{width:100%;margin-top:22px}.dn-service-detail-phone{display:flex;align-items:center;gap:12px;margin-top:18px;padding-top:18px;border-top:1px solid rgba(255,255,255,.13);color:#fff;font-size:18px;font-weight:850}.dn-service-detail-phone i{color:var(--dn-champagne)}
        .dn-service-detail-facts{display:grid;gap:13px;margin:0;padding:0;list-style:none}.dn-service-detail-facts li{display:flex;align-items:flex-start;gap:12px;color:#59657b;line-height:1.55}.dn-service-detail-facts i{margin-top:4px;color:#b28a45}
        .dn-service-process,.dn-service-gallery-section,.dn-service-related-section{margin-top:75px}.dn-service-section-head{max-width:780px;margin-bottom:34px}.dn-service-section-head p{margin:0 0 9px;color:#9a783d;font-size:12px;font-weight:850;letter-spacing:.16em;text-transform:uppercase}.dn-service-section-head h2{margin:0;color:var(--dn-navy);font:700 40px/1.15 var(--dn-display)}.dn-service-section-head>span{display:block;margin-top:13px;color:var(--dn-muted);font-size:16px;line-height:1.7}
        .dn-service-process-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;counter-reset:service-step}.dn-service-step{position:relative;min-height:220px;padding:30px 26px;background:#fff;box-shadow:var(--dn-shadow);counter-increment:service-step}.dn-service-step::before{content:"0" counter(service-step);display:block;margin-bottom:34px;color:var(--dn-champagne);font:700 43px/1 var(--dn-display)}.dn-service-step::after{content:"";position:absolute;top:49px;right:-18px;width:18px;height:1px;background:#bbc1cc}.dn-service-step:last-child::after{display:none}.dn-service-step h3{margin:0 0 10px;color:var(--dn-navy);font:700 20px var(--dn-display)}.dn-service-step p{margin:0;color:var(--dn-muted);font-size:14px;line-height:1.65}
        .dn-service-gallery-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.dn-service-gallery-grid figure{margin:0;overflow:hidden;background:#fff;box-shadow:var(--dn-shadow)}.dn-service-gallery-grid img{width:100%;height:300px;object-fit:cover;transition:transform .5s}.dn-service-gallery-grid figure:hover img{transform:scale(1.05)}.dn-service-gallery-grid figcaption{padding:15px 18px;color:var(--dn-muted);font-size:13px;font-weight:700}
        .dn-service-related-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}.dn-service-related-card{overflow:hidden;background:#fff;box-shadow:var(--dn-shadow);transition:.25s}.dn-service-related-card:hover{transform:translateY(-7px)}.dn-service-related-image{display:block;height:230px;overflow:hidden;background:var(--dn-navy)}.dn-service-related-image img{width:100%;height:100%;object-fit:cover;transition:transform .5s}.dn-service-related-card:hover img{transform:scale(1.055)}.dn-service-related-placeholder{width:100%;height:100%;display:grid;place-items:center;color:var(--dn-champagne);font-size:40px}.dn-service-related-body{padding:24px}.dn-service-related-body span{color:#9a783d;font-size:11px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}.dn-service-related-body h3{margin:9px 0 12px;color:var(--dn-navy);font:700 21px/1.35 var(--dn-display)}.dn-service-related-body p{margin:0;color:var(--dn-muted);font-size:14px;line-height:1.6}
        @media(max-width:1100px){.dn-service-detail-hero-grid,.dn-service-detail-content{grid-template-columns:1fr}.dn-service-detail-stage{height:560px}.dn-service-detail-aside{position:static;grid-template-columns:1fr 1fr}.dn-service-process-grid{grid-template-columns:repeat(2,1fr)}.dn-service-step:nth-child(2)::after{display:none}}
        @media(max-width:760px){.dn-service-detail-hero{padding:110px 0 70px}.dn-service-detail-hero-grid{gap:38px}.dn-service-detail-stage{height:380px}.dn-service-detail-actions{display:grid}.dn-service-detail-action{width:100%}.dn-service-assurances,.dn-service-detail-aside,.dn-service-process-grid,.dn-service-gallery-grid,.dn-service-related-grid{grid-template-columns:1fr}.dn-service-step::after{display:none}.dn-service-detail-panel{padding:25px}.dn-service-section-head h2{font-size:32px}.dn-service-gallery-grid img{height:260px}}
    </style>
@endpush

@section('content')
    <main class="dn-service-detail">
        <section class="dn-service-detail-hero">
            <div class="dn-container">
                <nav class="dn-service-detail-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a><span>/</span>
                    <a href="{{ route('site.services.index') }}">Dịch vụ</a><span>/</span><span>{{ $title }}</span>
                </nav>
                <div class="dn-service-detail-hero-grid">
                    <div>
                        <p class="dn-service-detail-kicker">{{ data_get($category, 'name', 'Dịch vụ chuyên nghiệp') }}</p>
                        <h1>{{ $title }}</h1>
                        <p class="dn-service-detail-summary">{{ $summary !== '' ? $summary : 'Giải pháp được nghiên cứu và triển khai theo nhu cầu thực tế của từng khách hàng.' }}</p>
                        <div class="dn-service-detail-actions">
                            <button type="button" class="dn-service-detail-action is-primary" data-dn-consult-open><i class="fa-regular fa-comments"></i>Nhận tư vấn</button>
                            <a class="dn-service-detail-action" href="tel:{{ $phoneHref }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>
                            @if ($serviceLink !== '')<a class="dn-service-detail-action" href="{{ $serviceLink }}"><i class="fa-solid fa-arrow-right"></i>{{ $serviceButton !== '' ? $serviceButton : 'Xem thêm' }}</a>@endif
                        </div>
                    </div>
                    @if ($primaryImage)
                        <div data-dn-service-gallery>
                            <div class="dn-service-detail-stage" data-dn-service-stage>
                                <img data-dn-service-main src="{{ $primaryImage }}" alt="{{ data_get($images->first(), 'alt_text', $title) ?: $title }}">
                                @if ($images->count() > 1)
                                    <button type="button" class="dn-service-detail-nav prev" data-dn-service-prev aria-label="Ảnh trước"><i class="fa-solid fa-chevron-left"></i></button>
                                    <button type="button" class="dn-service-detail-nav next" data-dn-service-next aria-label="Ảnh tiếp theo"><i class="fa-solid fa-chevron-right"></i></button>
                                @endif
                                <span class="dn-service-detail-stage-count"><b data-dn-service-index>1</b> / {{ $images->count() }}</span>
                            </div>
                            @if ($images->count() > 1)
                                <div class="dn-service-detail-thumbs" aria-label="Thư viện ảnh dịch vụ">
                                    @foreach ($images as $index => $image)
                                        <button type="button" class="dn-service-detail-thumb {{ $index === 0 ? 'is-active' : '' }}" data-dn-service-thumb data-index="{{ $index }}" data-url="{{ $image->image_url }}" data-alt="{{ $image->alt_text ?: $title }}"><img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $title }}"></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="dn-service-detail-main">
            <div class="dn-container">
                <div class="dn-service-assurances">
                    <div class="dn-service-assurance"><i class="fa-solid fa-ruler-combined"></i><div><strong>Khảo sát kỹ lưỡng</strong><span>Tìm hiểu nhu cầu, hiện trạng và mục tiêu sử dụng.</span></div></div>
                    <div class="dn-service-assurance"><i class="fa-solid fa-pen-ruler"></i><div><strong>Giải pháp cá nhân hóa</strong><span>Đề xuất phương án phù hợp ngân sách và phong cách.</span></div></div>
                    <div class="dn-service-assurance"><i class="fa-solid fa-shield-halved"></i><div><strong>Đồng hành dài hạn</strong><span>Hỗ trợ xuyên suốt từ tư vấn đến khi hoàn thiện.</span></div></div>
                </div>

                <div class="dn-service-detail-content">
                    <article class="dn-service-detail-panel">
                        <h2>Giới thiệu dịch vụ</h2>
                        <div class="dn-service-detail-rich">{!! $body !!}</div>
                    </article>
                    <aside class="dn-service-detail-aside">
                        <section class="dn-service-detail-cta">
                            <span>Tư vấn miễn phí</span><h3>Bắt đầu dự án của bạn</h3><p>Chia sẻ nhu cầu để đội ngũ chuyên môn đề xuất hướng triển khai phù hợp.</p>
                            <button type="button" class="dn-service-detail-action is-primary" data-dn-consult-open>Đăng ký tư vấn <i class="fa-solid fa-arrow-right"></i></button>
                            <a class="dn-service-detail-phone" href="tel:{{ $phoneHref }}"><i class="fa-solid fa-phone-volume"></i>{{ $hotline }}</a>
                        </section>
                        <section class="dn-service-detail-panel">
                            <h3>Cam kết dịch vụ</h3>
                            <ul class="dn-service-detail-facts"><li><i class="fa-solid fa-check"></i><span>Tư vấn rõ ràng và đúng trọng tâm.</span></li><li><i class="fa-solid fa-check"></i><span>Quy trình minh bạch, cập nhật tiến độ.</span></li><li><i class="fa-solid fa-check"></i><span>Phương án linh hoạt theo nhu cầu thực tế.</span></li></ul>
                        </section>
                    </aside>
                </div>

                <section class="dn-service-process">
                    <header class="dn-service-section-head"><p>Quy trình làm việc</p><h2>4 bước triển khai rõ ràng</h2><span>Mỗi giai đoạn đều có đầu việc cụ thể để khách hàng dễ theo dõi và chủ động ra quyết định.</span></header>
                    <div class="dn-service-process-grid"><article class="dn-service-step"><h3>Tiếp nhận nhu cầu</h3><p>Trao đổi mục tiêu, phạm vi công việc và những ưu tiên quan trọng.</p></article><article class="dn-service-step"><h3>Khảo sát & đề xuất</h3><p>Phân tích hiện trạng và xây dựng phương án phù hợp nhất.</p></article><article class="dn-service-step"><h3>Thống nhất triển khai</h3><p>Chốt giải pháp, tiến độ, hạng mục và trách nhiệm hai bên.</p></article><article class="dn-service-step"><h3>Nghiệm thu & hỗ trợ</h3><p>Bàn giao kết quả, hướng dẫn sử dụng và tiếp tục đồng hành.</p></article></div>
                </section>

                @if ($images->count() > 1)
                    <section class="dn-service-gallery-section">
                        <header class="dn-service-section-head"><p>Hình ảnh thực tế</p><h2>Thư viện dịch vụ</h2></header>
                        <div class="dn-service-gallery-grid">@foreach ($images as $image)<figure><img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $title }}" loading="lazy">@if ($image->caption)<figcaption>{{ $image->caption }}</figcaption>@else<figcaption>{{ $title }}</figcaption>@endif</figure>@endforeach</div>
                    </section>
                @endif

                @if ($relatedServices->isNotEmpty())
                    <section class="dn-service-related-section">
                        <header class="dn-service-section-head"><p>Khám phá thêm</p><h2>Dịch vụ liên quan</h2></header>
                        <div class="dn-service-related-grid">
                            @foreach ($relatedServices as $related)
                                @php($relatedImage = $related->featuredImage?->image_url)
                                <article class="dn-service-related-card"><a class="dn-service-related-image" href="{{ route('site.services.show', ['slug' => $related->slug]) }}">@if ($relatedImage)<img src="{{ $relatedImage }}" alt="{{ $related->featuredImage?->alt_text ?: $related->title }}" loading="lazy">@else<span class="dn-service-related-placeholder"><i class="fa-solid fa-plus"></i></span>@endif</a><div class="dn-service-related-body"><span>Dịch vụ</span><h3><a href="{{ route('site.services.show', ['slug' => $related->slug]) }}">{{ $related->title }}</a></h3><p>{{ Illuminate\Support\Str::limit(strip_tags((string) $related->summary), 120) }}</p></div></article>
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
            const gallery = document.querySelector('[data-dn-service-gallery]');
            if (!gallery) return;
            const main = gallery.querySelector('[data-dn-service-main]');
            const stage = gallery.querySelector('[data-dn-service-stage]');
            const thumbs = Array.from(gallery.querySelectorAll('[data-dn-service-thumb]'));
            const thumbsRail = gallery.querySelector('.dn-service-detail-thumbs');
            const counter = gallery.querySelector('[data-dn-service-index]');
            let active = 0;
            const centerThumbInsideRail = (thumb) => {
                if (!thumbsRail || thumbsRail.scrollWidth <= thumbsRail.clientWidth) return;
                const railRect = thumbsRail.getBoundingClientRect();
                const thumbRect = thumb.getBoundingClientRect();
                const targetLeft = thumbsRail.scrollLeft
                    + (thumbRect.left - railRect.left)
                    - ((thumbsRail.clientWidth - thumbRect.width) / 2);
                thumbsRail.scrollTo({ left: Math.max(0, targetLeft), behavior: 'smooth' });
            };
            const show = (index) => {
                if (!main || !thumbs.length) return;
                active = (index + thumbs.length) % thumbs.length;
                const thumb = thumbs[active];
                stage?.classList.add('is-switching');
                window.setTimeout(() => {
                    main.src = thumb.dataset.url || main.src;
                    main.alt = thumb.dataset.alt || main.alt;
                    thumbs.forEach((item, itemIndex) => item.classList.toggle('is-active', itemIndex === active));
                    if (counter) counter.textContent = String(active + 1);
                    centerThumbInsideRail(thumb);
                    stage?.classList.remove('is-switching');
                }, 120);
            };
            thumbs.forEach((thumb, index) => thumb.addEventListener('click', () => show(index)));
            gallery.querySelector('[data-dn-service-prev]')?.addEventListener('click', () => show(active - 1));
            gallery.querySelector('[data-dn-service-next]')?.addEventListener('click', () => show(active + 1));
        })();
    </script>
@endpush
