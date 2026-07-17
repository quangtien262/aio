@php
    $title = $pageTitle ?? ($entry->title ?? (app()->getLocale() === 'en' ? 'Project' : 'Dự án'));
    $description = $pageDescription ?? ($entry->summary ?? '');
    $featuredImage = $entry->featuredImage?->image_url;
    $featuredAlt = $entry->featuredImage?->alt_text ?: $entry->title;
    $footerNewsletterSource = 'theme-footer-xd0301-project';
    $canEditLanding = false;
@endphp

@extends('theme-xd0301::layout')

@section('title', $title)

@if (!empty($description))
    @push('head')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif

@push('head')
    <style>
        .xd-page-main{padding:76px 0 90px}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-detail{display:grid;grid-template-columns:minmax(0,.85fr) minmax(300px,.35fr);gap:44px}
        .xd-detail-card,.xd-side-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}
        .xd-detail-card{overflow:hidden}
        .xd-detail-image{width:100%;max-height:560px;object-fit:cover}
        .xd-detail-body{padding:44px 52px}
        .xd-detail-body h1{margin:0 0 18px;font-size:clamp(38px,4vw,62px);line-height:1.1;letter-spacing:-.05em}
        .xd-detail-summary{margin:0 0 28px;color:var(--muted);font-size:20px}
        .xd-rich-content{color:#465461;font-size:18px}
        .xd-rich-content :first-child{margin-top:0}
        .xd-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:28px}
        .xd-gallery figure{margin:0}
        .xd-gallery img{width:100%;height:210px;object-fit:cover}
        .xd-gallery figcaption{margin-top:8px;color:var(--muted);font-size:14px}
        .xd-side-card{padding:28px}
        .xd-side-card h3{margin:0 0 18px;font-size:24px}
        .xd-side-card a,.xd-side-card span{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-side-card a:hover{color:var(--lime-dark)}
        @media (max-width:1180px){.xd-detail{grid-template-columns:1fr}}
        @media (max-width:640px){.xd-page-main{padding:38px 0 56px}.xd-detail-body{padding:28px 22px}.xd-detail-body h1{font-size:34px}.xd-detail-summary,.xd-rich-content{font-size:16px}.xd-gallery{grid-template-columns:1fr}}
    </style>
@endpush

@section('content')
<main class="xd-page-main">
    <div class="xd-container">
        <section class="xd-detail">
            <article class="xd-detail-card">
                @if ($featuredImage)
                    <img class="xd-detail-image" src="{{ $featuredImage }}" alt="{{ $featuredAlt }}">
                @endif
                <div class="xd-detail-body">
                    <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Project' : 'Dự án' }}</span>
                    <h1>{{ $entry->title }}</h1>
                    @if (!empty($entry->summary))
                        <p class="xd-detail-summary">{{ $entry->summary }}</p>
                    @endif
                    <div class="xd-rich-content">
                        {!! $entry->content ?: '<p>Nội dung đang được cập nhật.</p>' !!}
                    </div>

                    @if (!empty($entry->images) && $entry->images->count() > 1)
                        <div class="xd-gallery">
                            @foreach ($entry->images as $image)
                                <figure>
                                    <img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}">
                                    @if (!empty($image->caption))
                                        <figcaption>{{ $image->caption }}</figcaption>
                                    @endif
                                </figure>
                            @endforeach
                        </div>
                    @endif
                </div>
            </article>
            <aside class="xd-side-card">
                <h3>{{ app()->getLocale() === 'en' ? 'Project info' : 'Thông tin dự án' }}</h3>
                @if ($entry->category?->name)
                    <span>{{ $entry->category->name }}</span>
                @endif
                <a href="{{ route('site.projects.index') }}">{{ app()->getLocale() === 'en' ? 'All projects' : 'Tất cả dự án' }}</a>
            </aside>
        </section>
    </div>
</main>
@endsection
