@extends('theme-ser102::layout')

@php
    $item = $entry ?? $post ?? null;
    $title = data_get($item, 'title') ?? 'Chi tiết bài viết';
    $summary = data_get($item, 'excerpt') ?? data_get($item, 'summary') ?? '';
    $body = data_get($item, 'body') ?? data_get($item, 'content') ?? $summary;
    $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=85';
@endphp

@section('title', $title)

@section('content')
    <section class="ser102-page-head">
        <div class="ser102-container">
            <nav class="ser102-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Tin tức</span></nav>
            <h1>{{ $title }}</h1>
            @if(filled($summary))<p>{{ strip_tags($summary) }}</p>@endif
        </div>
    </section>
    <section class="ser102-subpage">
        <div class="ser102-container ser102-detail">
            <img src="{{ $image }}" alt="{{ $title }}">
            <article class="ser102-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
        </div>
    </section>
@endsection
