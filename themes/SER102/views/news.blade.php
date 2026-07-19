@extends('theme-ser102::layout')

@php
    $source = $posts ?? $entries ?? $items ?? collect();
    $entries = $source instanceof \Illuminate\Contracts\Pagination\Paginator ? $source->getCollection() : collect($source);
    $pageTitle = $pageTitle ?? 'Tin tức';
@endphp

@section('title', $pageTitle)

@section('content')
    <section class="ser102-page-head">
        <div class="ser102-container">
            <nav class="ser102-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>{{ $pageTitle }}</span></nav>
            <h1>{{ $pageTitle }}</h1>
            <p>Cập nhật thông tin mới nhất, chia sẻ kinh nghiệm và các câu chuyện dự án.</p>
        </div>
    </section>
    <section class="ser102-subpage">
        <div class="ser102-container">
            @if($entries->isNotEmpty())
                <div class="ser102-list-grid">
                    @foreach($entries as $item)
                        @php
                            $title = data_get($item, 'title') ?? 'Bài viết';
                            $summary = data_get($item, 'excerpt') ?? data_get($item, 'summary') ?? data_get($item, 'description');
                            $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=900&q=85';
                            $url = data_get($item, 'url') ?? (filled(data_get($item, 'slug')) ? route('site.blog.show', ['slug' => data_get($item, 'slug')]) : '#');
                        @endphp
                        <a class="ser102-list-card" href="{{ $url }}">
                            <img src="{{ $image }}" alt="{{ $title }}">
                            <h2>{{ $title }}</h2>
                            @if(filled($summary))<p>{{ \Illuminate\Support\Str::limit(strip_tags($summary), 170) }}</p>@endif
                        </a>
                    @endforeach
                </div>
            @else
                <div class="ser102-empty">Chưa có bài viết để hiển thị.</div>
            @endif
        </div>
    </section>
@endsection
