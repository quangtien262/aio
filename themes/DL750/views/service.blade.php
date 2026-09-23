@extends('theme-dl750::layout')
@section('content')
@php
    $serviceTitle = data_get($entry ?? null, 'title', $pageTitle ?? '');
    $serviceSummary = data_get($entry ?? null, 'summary', data_get($entry ?? null, 'excerpt', ''));
    $serviceBody = data_get($entry ?? null, 'body') ?: data_get($entry ?? null, 'content');
    $serviceImage = data_get($entry ?? null, 'featuredImage.image_url') ?: data_get($entry ?? null, 'image_url');
    $serviceImages = collect(data_get($entry ?? null, 'images', []))->filter(fn ($image) => filled(data_get($image, 'image_url')))->unique('image_url')->values();
    $serviceImage = $serviceImage ?: data_get($serviceImages->first(), 'image_url');
    $serviceBranding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? null, 'branding', []));
    $servicePhone = trim((string) ($serviceBranding['support_hotline'] ?? ''));
    $servicePhoneHref = preg_replace('/[^0-9+]/', '', $servicePhone);
    $serviceEmail = trim((string) ($serviceBranding['support_email'] ?? ''));
@endphp
@include('theme-dl750::partials.service-styles')
<main class="dl-inner dl-service-page">
    <div class="dl-wrap">
        <nav class="dl-service-breadcrumb" aria-label="@themeT('service.breadcrumb', 'Điều hướng')">
            <a href="{{ route('site.home') }}">@themeT('home', 'Trang chủ')</a><span aria-hidden="true">/</span>
            <a href="{{ route('site.services.index') }}">@themeT('services', 'Dịch vụ')</a><span aria-hidden="true">/</span>
            <span aria-current="page">{{ $serviceTitle }}</span>
        </nav>
        <section class="dl-service-hero {{ $serviceImage ? '' : 'dl-service-hero-text' }}" aria-labelledby="dl-service-title">
            <div class="dl-service-hero-copy">
                <p class="dl-service-kicker"><span></span>@themeT('service.eyebrow', 'Đồng hành cùng chuyến đi')</p>
                <h1 id="dl-service-title">{{ $serviceTitle }}</h1>
                @if($serviceSummary)<p class="dl-service-lead">{{ strip_tags($serviceSummary) }}</p>@endif
                <div class="dl-service-hero-actions">
                    <a class="dl-primary" href="{{ route('site.contact') }}">@themeT('service.consult', 'Tư vấn dịch vụ') <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    <a class="dl-service-text-link" href="#dl-service-details">@themeT('service.discover', 'Khám phá chi tiết') <span aria-hidden="true">&darr;</span></a>
                </div>
            </div>
            @if($serviceImage)
                <figure class="dl-service-hero-media"><img src="{{ $serviceImage }}" alt="{{ $serviceTitle }}" width="800" height="800" fetchpriority="high"><span aria-hidden="true"><i class="fa-solid fa-compass"></i></span></figure>
            @endif
        </section>
        <nav class="dl-service-sections" aria-label="@themeT('service.sections', 'Nội dung dịch vụ')">
            <a href="#dl-service-details"><span>01</span>@themeT('service.overview', 'Thông tin dịch vụ')</a>
            @if($serviceImages->isNotEmpty())<a href="#dl-service-gallery"><span>02</span>@themeT('service.gallery', 'Hình ảnh trải nghiệm')</a>@endif
            <a href="#dl-service-prepare"><span>{{ $serviceImages->isNotEmpty() ? '03' : '02' }}</span>@themeT('service.prepare', 'Chuẩn bị cho chuyến đi')</a>
        </nav>
        <div class="dl-service-body-layout">
            <div class="dl-service-main">
                <section id="dl-service-details" class="dl-service-details">
                    <p class="dl-service-kicker">@themeT('service.about', 'Dịch vụ dành cho bạn')</p>
                    <h2>@themeT('service.overview', 'Thông tin dịch vụ')</h2>
                    @if($serviceBody)<div class="dl-prose">{!! $serviceBody !!}</div>
                    @else<p class="dl-service-muted">@themeT('service.empty', 'Liên hệ để được tư vấn chi tiết về dịch vụ và phương án phù hợp với nhu cầu của bạn.')</p>@endif
                </section>
                @if($serviceImages->isNotEmpty())
                    <section id="dl-service-gallery" class="dl-service-gallery">
                        <p class="dl-service-kicker">@themeT('service.moments', 'Góc nhìn hành trình')</p>
                        <h2>@themeT('service.gallery', 'Hình ảnh trải nghiệm')</h2>
                        <div class="dl-service-gallery-grid">
                            @foreach($serviceImages as $servicePhoto)
                                <a href="{{ data_get($servicePhoto, 'image_url') }}" target="_blank" rel="noopener" aria-label="@themeT('service.open_image', 'Mở ảnh kích thước lớn') — {{ $loop->iteration }}">
                                    <img src="{{ data_get($servicePhoto, 'image_url') }}" alt="{{ data_get($servicePhoto, 'alt_text') ?: $serviceTitle.' — '.$loop->iteration }}" width="700" height="500" loading="lazy">
                                    <span aria-hidden="true"><i class="fa-solid fa-expand"></i></span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
            <aside class="dl-service-sidebar">
                <div class="dl-service-contact">
                    <span class="dl-service-contact-icon"><i class="fa-regular fa-comments" aria-hidden="true"></i></span>
                    <p class="dl-service-kicker">@themeT('service.plan', 'Lên kế hoạch cùng bạn')</p>
                    <h2>@themeT('service.contact_title', 'Chuyến đi của bạn bắt đầu từ đây')</h2>
                    <p>@themeT('service.contact_text', 'Chia sẻ lịch trình và nhu cầu để được tư vấn phương án, chi phí và các hạng mục phù hợp.')</p>
                    <a class="dl-primary" href="{{ route('site.contact') }}">@themeT('service.send_request', 'Gửi yêu cầu tư vấn') <span aria-hidden="true">&rarr;</span></a>
                    @if($servicePhone && $servicePhoneHref)<a class="dl-service-contact-row" href="tel:{{ $servicePhoneHref }}"><i class="fa-solid fa-phone" aria-hidden="true"></i><span><small>@themeT('service.call', 'Trao đổi trực tiếp')</small><strong>{{ $servicePhone }}</strong></span></a>@endif
                    @if(filter_var($serviceEmail, FILTER_VALIDATE_EMAIL))<a class="dl-service-contact-row" href="mailto:{{ $serviceEmail }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i><span><small>@themeT('service.email', 'Gửi email')</small><strong>{{ $serviceEmail }}</strong></span></a>@endif
                    <p class="dl-service-contact-note">@themeT('service.note', 'Vui lòng xác nhận lịch trình, chi phí và điều kiện dịch vụ trước khi đặt.')</p>
                </div>
                <a class="dl-service-all" href="{{ route('site.services.index') }}">@themeT('service.all', 'Khám phá các dịch vụ khác') <span aria-hidden="true">&rarr;</span></a>
            </aside>
        </div>
        <section id="dl-service-prepare" class="dl-service-prepare">
            <div class="dl-service-section-heading"><p class="dl-service-kicker">@themeT('service.before', 'Trước khi khởi hành')</p><h2>@themeT('service.prepare', 'Chuẩn bị cho chuyến đi')</h2><p>@themeT('service.prepare_intro', 'Một vài thông tin giúp buổi tư vấn của bạn cụ thể và hiệu quả hơn.')</p></div>
            <div class="dl-service-steps">
                <article><span>01</span><i class="fa-regular fa-calendar" aria-hidden="true"></i><h3>@themeT('service.step1_title', 'Dự kiến lịch trình')</h3><p>@themeT('service.step1_text', 'Chuẩn bị điểm đến, ngày khởi hành, thời gian và số người tham gia.')</p></article>
                <article><span>02</span><i class="fa-solid fa-campground" aria-hidden="true"></i><h3>@themeT('service.step2_title', 'Chia sẻ nhu cầu')</h3><p>@themeT('service.step2_text', 'Cho biết trang bị đang có, hỗ trợ cần thêm và ngân sách dự kiến.')</p></article>
                <article><span>03</span><i class="fa-regular fa-circle-check" aria-hidden="true"></i><h3>@themeT('service.step3_title', 'Xác nhận phương án')</h3><p>@themeT('service.step3_text', 'Trao đổi phạm vi dịch vụ, chi phí, cách bàn giao và những lưu ý trước chuyến đi.')</p></article>
            </div>
        </section>
        <section class="dl-service-closing">
            <div><p class="dl-service-kicker">@themeT('service.next', 'Hành trình tiếp theo')</p><h2>@themeT('service.closing', 'Cùng chuẩn bị cho một chuyến đi đáng nhớ')</h2></div>
            <a class="dl-primary" href="{{ route('site.contact') }}">@themeT('service.consult', 'Tư vấn dịch vụ') <span aria-hidden="true">&rarr;</span></a>
        </section>
    </div>
</main>
@endsection
