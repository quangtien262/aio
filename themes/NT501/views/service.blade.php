@extends('theme-nt501::layout')
@php
    $canEditLanding = false;
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $phoneHref = preg_replace('/[^0-9+]/', '', $hotline);
    $text = fn ($key, $fallback) => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('NT501', app()->getLocale(), 'service_detail.'.$key, $fallback);
@endphp
@push('head')
@include('theme-nt501::partials.service-styles')
@endpush
@section('content')
<main class="nt-service-detail">
    <section class="nt-service-hero"><div class="nt-container">
        <nav class="nt-service-breadcrumb" aria-label="{{ $text('breadcrumb', 'Điều hướng trang') }}"><a href="{{ route('site.home') }}">{{ $text('home', 'Trang chủ') }}</a><span>/</span><a href="{{ route('site.services.index') }}">{{ $text('services', 'Dịch vụ') }}</a><span>/</span><span aria-current="page">{{ $entry->title }}</span></nav>
        <div class="nt-service-hero-grid"><div class="nt-service-intro">
            <p class="nt-service-kicker">{{ $entry->category?->name ?: $text('studio', 'Không gian sống · Interior Studio') }}</p>
            <h1>{{ $entry->title }}</h1>
            @if($entry->summary ?: ($entry->excerpt ?? ''))<p class="nt-service-summary">{{ $entry->summary ?: $entry->excerpt }}</p>@endif
            <div class="nt-service-actions"><a class="nt-button" href="{{ route('site.contact') }}">{{ $text('consult', 'Trao đổi về không gian của bạn') }} <span aria-hidden="true">↗</span></a><a class="nt-service-outline" href="#chi-tiet">{{ $text('explore', 'Khám phá dịch vụ') }} <span aria-hidden="true">↓</span></a></div>
            <p class="nt-service-note">{{ $text('note', 'Từ ý tưởng đầu tiên đến phương án phù hợp với nhu cầu của bạn.') }}</p>
        </div>
        @if($entry->featuredImage?->image_url)<figure class="nt-service-cover"><img src="{{ $entry->featuredImage->image_url }}" alt="{{ $entry->featuredImage->alt_text ?: $entry->title }}" fetchpriority="high"><figcaption>{{ $text('image_caption', 'Cảm hứng cho một không gian sống hài hòa') }}</figcaption></figure>@endif
        </div>
    </div></section>
    <section id="chi-tiet" class="nt-service-content nt-container">
        <article class="nt-service-article"><p class="nt-service-kicker">{{ $text('overview', 'Tổng quan dịch vụ') }}</p><h2>{{ $text('detail_heading', 'Giải pháp dành cho không gian của bạn') }}</h2>
            <div class="nt-service-rich">{!! ($entry->content ?: ($entry->body ?? '')) ?: '<p>'.e($text('empty', 'Nội dung đang được cập nhật.')).'</p>' !!}</div>
            @if($entry->images->count() > 1)<div class="nt-service-gallery">@foreach($entry->images as $image)<figure><img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}" loading="lazy">@if($image->caption)<figcaption>{{ $image->caption }}</figcaption>@endif</figure>@endforeach</div>@endif
        </article>
        <aside class="nt-service-sidebar"><div class="nt-service-consult"><span class="nt-service-consult-icon" aria-hidden="true">↗</span><p class="nt-service-kicker">{{ $text('start', 'Bắt đầu từ một cuộc trò chuyện') }}</p><h2>{{ $text('consult_heading', 'Bạn đang có một ý tưởng?') }}</h2><p>{{ $text('consult_copy', 'Chia sẻ diện tích, phong cách yêu thích và nhu cầu sử dụng để cùng tìm phương án phù hợp.') }}</p><a class="nt-button" href="{{ route('site.contact') }}">{{ $text('appointment', 'Đặt lịch tư vấn') }} <span aria-hidden="true">↗</span></a>@if($phoneHref)<a class="nt-service-phone" href="tel:{{ $phoneHref }}">{{ $hotline }}</a>@endif</div><a class="nt-service-back" href="{{ route('site.services.index') }}">{{ $text('all_services', 'Khám phá tất cả dịch vụ') }} <span aria-hidden="true">→</span></a></aside>
    </section>
    <section class="nt-service-journal"><div class="nt-container"><header class="nt-service-journal-heading"><div><p class="nt-service-kicker">{{ $text('journal_kicker', 'Ý tưởng & cảm hứng') }}</p><h2>{{ $text('latest_posts', 'Bài viết mới nhất') }}</h2></div><a class="nt-service-back" href="{{ route('site.blog.index') }}">{{ $text('all_posts', 'Xem tất cả bài viết') }} <span aria-hidden="true">↗</span></a></header>
        <div class="nt-service-post-grid">@forelse($latestPosts ?? [] as $post)<article class="nt-service-post"><a class="nt-service-post-image" href="{{ \App\Support\FrontendRouteUrl::post($post->slug, app()->getLocale()) }}">@if($post->featuredMedia?->file_url)<img src="{{ $post->featuredMedia->file_url }}" alt="{{ $post->featuredMedia->alt_text ?: $post->title }}" loading="lazy">@else<span aria-hidden="true">Interior Journal</span>@endif</a><div class="nt-service-post-meta">@if($post->category)<span>{{ $post->category->name }}</span>@endif @if($post->publish_at)<time datetime="{{ $post->publish_at->toDateString() }}">{{ $post->publish_at->format('d.m.Y') }}</time>@endif</div><h3><a href="{{ \App\Support\FrontendRouteUrl::post($post->slug, app()->getLocale()) }}">{{ $post->title }}</a></h3>@if($post->excerpt)<p>{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 105) }}</p>@endif</article>@empty<p>{{ $text('posts_empty', 'Những ý tưởng mới đang được cập nhật.') }}</p>@endforelse</div>
    </div></section>
</main>
@endsection
