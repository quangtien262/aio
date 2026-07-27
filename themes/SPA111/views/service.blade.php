@extends('theme-spa111::layout')

@php
    $item = $entry ?? $service ?? null;
    $title = data_get($item, 'title') ?? data_get($item, 'name') ?? 'Chi tiết dịch vụ';
    $summary = data_get($item, 'summary') ?? '';
    $body = data_get($item, 'body') ?? data_get($item, 'description') ?? $summary;
    $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=1200&q=85';
@endphp

@section('title', $title)

@section('content')
    <section class="spa111-page-head">
        <div class="spa111-container">
            <nav class="spa111-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Dịch vụ</span></nav>
            <h1>{{ $title }}</h1>
            @if(filled($summary))<p>{{ strip_tags($summary) }}</p>@endif
        </div>
    </section>
    <section class="spa111-subpage">
        <div class="spa111-container spa111-detail">
            <img src="{{ $image }}" alt="{{ $title }}">
            <article class="spa111-detail-body">{!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}</article>
        </div>
    </section>
@endsection
