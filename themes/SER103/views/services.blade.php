@extends('theme-ser103::layout')

@php
    $source = $services ?? $entries ?? $items ?? collect();
    $entries = $source instanceof \Illuminate\Contracts\Pagination\Paginator ? $source->getCollection() : collect($source);
    $pageTitle = $pageTitle ?? 'Dịch vụ';
@endphp

@section('title', $pageTitle)

@section('content')
    <section class="ser103-page-head">
        <div class="ser103-container">
            <nav class="ser103-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $pageTitle }}</span></nav>
            <h1>{{ $pageTitle }}</h1>
            <p>Các dịch vụ tư vấn, triển khai và đồng hành dành cho khách hàng.</p>
        </div>
    </section>
    <section class="ser103-subpage">
        <div class="ser103-container">
            @if($entries->isNotEmpty())
                <div class="ser103-list-grid">
                    @foreach($entries as $item)
                        @php
                            $title = data_get($item, 'title') ?? data_get($item, 'name') ?? 'Dịch vụ';
                            $summary = data_get($item, 'summary') ?? data_get($item, 'description');
                            $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=900&q=85';
                            $url = data_get($item, 'url') ?? (filled(data_get($item, 'slug')) ? route('site.services.show', ['slug' => data_get($item, 'slug')]) : '#');
                        @endphp
                        <a class="ser103-list-card" href="{{ $url }}">
                            <img src="{{ $image }}" alt="{{ $title }}">
                            <h2>{{ $title }}</h2>
                            @if(filled($summary))<p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 170) }}</p>@endif
                        </a>
                    @endforeach
                </div>
            @else
                <div class="ser103-empty">Chưa có dịch vụ để hiển thị.</div>
            @endif
        </div>
    </section>
@endsection
