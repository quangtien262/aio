@php
    $aboutText = fn ($key, $fallback) => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('NT501', app()->getLocale(), 'about_page.'.$key, $fallback);
    $aboutImage = $entry->featuredMedia?->file_url ?: '/theme-demo/dn302/dn302-living-room.png';
@endphp
@push('head')
    @include('theme-nt501::partials.about-styles')
@endpush
<main class="nt-about-page">
    <section class="nt-about-hero">
        <div class="nt-container">
            <nav class="nt-about-breadcrumb" aria-label="{{ $aboutText('breadcrumb', 'Điều hướng trang') }}"><a href="{{ route('site.home') }}">{{ $aboutText('home', 'Trang chủ') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $aboutText('about', 'Giới thiệu') }}</span></nav>
            <div class="nt-about-hero-grid">
                <div class="nt-about-intro"><p class="nt-about-kicker">{{ $branding['company_name'] ?? 'Interior Studio' }}</p><h1>{{ $entry->title }}</h1>@if($entry->excerpt)<p class="nt-about-lead">{{ $entry->excerpt }}</p>@endif<a class="nt-about-button" href="{{ route('site.contact') }}">{{ $aboutText('consult', 'Cùng trao đổi ý tưởng') }} <span aria-hidden="true">↗</span></a></div>
                <figure class="nt-about-cover"><img src="{{ $aboutImage }}" alt="{{ $entry->featuredMedia?->alt_text ?: $entry->title }}" fetchpriority="high"><figcaption>{{ $aboutText('caption', 'Không gian đẹp bắt đầu từ sự thấu hiểu') }}</figcaption></figure>
            </div>
        </div>
    </section>

    <section class="nt-about-story nt-container">
        <div class="nt-about-story-heading"><p class="nt-about-kicker">{{ $aboutText('story', 'Câu chuyện của chúng tôi') }}</p><h2>{{ $aboutText('story_title', 'Thiết kế từ nhu cầu sống của bạn') }}</h2><p>{{ $aboutText('story_description', 'Một không gian hài hòa là sự kết hợp giữa công năng, vật liệu và những thói quen hằng ngày.') }}</p><a class="nt-about-text-link" href="{{ route('site.projects.index') }}">{{ $aboutText('projects', 'Khám phá dự án') }} <span aria-hidden="true">↗</span></a></div>
        <article class="nt-about-rich">{!! $entry->body ?: '<p>'.e($aboutText('empty', 'Nội dung đang được cập nhật.')).'</p>' !!}</article>
    </section>

    <section class="nt-about-explore nt-container" aria-labelledby="nt-about-explore-title">
        <header><p class="nt-about-kicker">{{ $aboutText('explore', 'Tìm hiểu thêm') }}</p><h2 id="nt-about-explore-title">{{ $aboutText('explore_title', 'Từ cảm hứng đến không gian thực tế') }}</h2></header>
        <div class="nt-about-explore-grid">
            <a class="nt-about-explore-card" href="{{ route('site.services.index') }}"><span class="nt-about-card-number">01 <span aria-hidden="true">↗</span></span><h3>{{ $aboutText('services', 'Dịch vụ nội thất') }}</h3><p>{{ $aboutText('services_copy', 'Khám phá các dịch vụ tư vấn, thiết kế và thi công cho không gian của bạn.') }}</p></a>
            <a class="nt-about-explore-card" href="{{ route('site.projects.index') }}"><span class="nt-about-card-number">02 <span aria-hidden="true">↗</span></span><h3>{{ $aboutText('projects', 'Khám phá dự án') }}</h3><p>{{ $aboutText('projects_copy', 'Tham khảo cách bố trí, lựa chọn vật liệu và những ý tưởng cho từng không gian.') }}</p></a>
            <a class="nt-about-explore-card" href="{{ route('site.blog.index') }}"><span class="nt-about-card-number">03 <span aria-hidden="true">↗</span></span><h3>{{ $aboutText('journal', 'Ý tưởng & cảm hứng') }}</h3><p>{{ $aboutText('journal_copy', 'Đọc thêm những gợi ý về phong cách, ánh sáng và chăm sóc nội thất.') }}</p></a>
        </div>
    </section>

    <section class="nt-about-cta"><div class="nt-container"><div><p class="nt-about-kicker">{{ $aboutText('cta_kicker', 'Bắt đầu không gian mới của bạn') }}</p><h2>{{ $aboutText('cta_title', 'Bạn đang ấp ủ một ý tưởng?') }}</h2><p>{{ $aboutText('cta_description', 'Chia sẻ nhu cầu để cùng tìm một phương án phù hợp.') }}</p></div><a class="nt-about-button" href="{{ route('site.contact') }}">{{ $aboutText('cta_button', 'Đặt lịch tư vấn') }} <span aria-hidden="true">↗</span></a></div></section>
</main>
