@extends('theme-bz501::layout')

@php
    $item = $entry ?? $post ?? null;
    $title = data_get($item, 'title') ?? 'Chi tiết bài viết';
    $summary = data_get($item, 'excerpt') ?? data_get($item, 'summary') ?? '';
    $body = data_get($item, 'body') ?? data_get($item, 'content') ?? $summary;
    $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=85';
@endphp

@section('title', $title)

@section('content')
    <section class="bz501-page-head">
        <div class="bz501-container">
            <nav class="bz501-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Tin tức</span></nav>
            <h1>{{ $title }}</h1>
            @if(filled($summary))<p>{{ strip_tags($summary) }}</p>@endif
        </div>
    </section>
    <section class="bz501-subpage">
        <div class="bz501-container bz501-detail">
            <img src="{{ $image }}" alt="{{ $title }}">
            <article class="bz501-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
        </div>
    </section>
@endsection
