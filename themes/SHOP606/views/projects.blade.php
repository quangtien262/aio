@extends('theme-shop606::layout')
@section('title', $pageTitle ?? (app()->getLocale() === 'vi' ? 'Dự án' : 'Projects'))
@section('content')
@include('theme-shop606::partials.service-styles')
<main class="s606-service-page"><div class="s606-service-wrap">
    <header class="s606-service-heading"><h1>{{ $pageTitle ?? (app()->getLocale() === 'vi' ? 'Dự án' : 'Projects') }}</h1>@if(!empty($pageDescription))<p>{{ $pageDescription }}</p>@endif</header>
    <div class="s606-service-grid">
        @forelse($listingItems ?? [] as $item)
        <article class="s606-service-card">
            @if($item->featuredImage?->image_url)<a href="{{ route('site.projects.show', ['slug' => $item->slug]) }}"><img loading="lazy" src="{{ $item->featuredImage->image_url }}" alt="{{ $item->featuredImage->alt_text ?: $item->title }}"></a>@endif
            <div><h2><a href="{{ route('site.projects.show', ['slug' => $item->slug]) }}">{{ $item->title }}</a></h2>@if($item->summary)<p>{{ $item->summary }}</p>@endif</div>
        </article>
        @empty
        <p>{{ app()->getLocale() === 'vi' ? 'Chưa có dự án được xuất bản.' : 'No projects published yet.' }}</p>
        @endforelse
    </div>
    @if(isset($listingItems) && method_exists($listingItems, 'links')){{ $listingItems->links() }}@endif
</div></main>
@endsection
