@extends('theme-ser102::layout')

@php
    $item = $product ?? $entry ?? null;
    $title = data_get($item, 'name') ?? data_get($item, 'title') ?? 'Chi tiết sản phẩm';
    $summary = data_get($item, 'short_description') ?? data_get($item, 'summary') ?? '';
    $body = data_get($item, 'description') ?? data_get($item, 'body') ?? $summary;
    $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=1200&q=85';
    $price = data_get($item, 'price');
@endphp

@section('title', $title)

@section('content')
    <section class="ser102-page-head">
        <div class="ser102-container">
            <nav class="ser102-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Sản phẩm</span></nav>
            <h1>{{ $title }}</h1>
            @if(filled($summary))<p>{{ strip_tags($summary) }}</p>@endif
        </div>
    </section>
    <section class="ser102-subpage">
        <div class="ser102-container ser102-detail">
            <img src="{{ $image }}" alt="{{ $title }}">
            <div class="ser102-detail-body">
                @if(filled($price))<p class="ser102-price">{{ number_format((float) $price, 0, ',', '.') }}đ</p>@endif
                {!! filled($body) ? $body : '<p>Nội dung đang được cập nhật.</p>' !!}
            </div>
        </div>
    </section>
@endsection
