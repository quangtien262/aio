@extends('theme-spa502::layout')

@php
    $source = $products ?? $items ?? collect();
    $entries = $source instanceof \Illuminate\Contracts\Pagination\Paginator ? $source->getCollection() : collect($source);
    $pageTitle = $pageTitle ?? 'Sản phẩm';
@endphp

@section('title', $pageTitle)

@section('content')
    <section class="spa502-page-head">
        <div class="spa502-container">
            <nav class="spa502-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $pageTitle }}</span></nav>
            <h1>{{ $pageTitle }}</h1>
            <p>Khám phá danh sách sản phẩm và giải pháp đang được giới thiệu.</p>
        </div>
    </section>
    <section class="spa502-subpage">
        <div class="spa502-container">
            @if($entries->isNotEmpty())
                <div class="spa502-list-grid">
                    @foreach($entries as $item)
                        @php
                            $title = data_get($item, 'name') ?? data_get($item, 'title') ?? 'Sản phẩm';
                            $summary = data_get($item, 'short_description') ?? data_get($item, 'summary') ?? data_get($item, 'description');
                            $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=900&q=85';
                            $url = data_get($item, 'url') ?? (filled(data_get($item, 'slug')) ? route('site.catalog.product', ['slug' => data_get($item, 'slug')]) : '#');
                            $price = data_get($item, 'price');
                        @endphp
                        <a class="spa502-list-card" href="{{ $url }}">
                            <img src="{{ $image }}" alt="{{ $title }}">
                            <h2>{{ $title }}</h2>
                            @if(filled($price))<span class="spa502-price">{{ number_format((float) $price, 0, ',', '.') }}đ</span>@endif
                            @if(filled($summary))<p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 150) }}</p>@endif
                        </a>
                    @endforeach
                </div>
            @else
                <div class="spa502-empty">Chưa có sản phẩm để hiển thị.</div>
            @endif
        </div>
    </section>
@endsection
