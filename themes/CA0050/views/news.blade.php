@extends('theme-ca0050::layout')
@section('content')
<main class="ca50-inner-page"><div class="ca50-inner-container">
@include('theme-ca0050::partials.inner-heading', ['heading' => $pageTitle ?? __('Tin tức'), 'intro' => $pageDescription ?? ''])
<div class="ca50-service-grid">
@forelse(($listingItems ?? []) as $item)
@php($url = route('site.blog.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]))
<article class="ca50-service-card">
@if($item->featuredMedia?->file_url)<a class="ca50-service-image" href="{{ $url }}"><img src="{{ $item->featuredMedia->file_url }}" alt="{{ $item->title }}" loading="lazy"></a>@endif
<div><h2><a href="{{ $url }}">{{ $item->title }}</a></h2><p>{{ $item->excerpt }}</p><a class="ca50-service-link" href="{{ $url }}">{{ __('Đọc tiếp') }} →</a></div>
</article>
@empty
<div class="ca50-empty"><h2>{{ __('Tin tức đang được cập nhật') }}</h2></div>
@endforelse
</div>
@if(isset($listingItems) && method_exists($listingItems, 'hasPages') && $listingItems->hasPages())
<nav class="ca50-inner-pagination" aria-label="{{ __('Phân trang') }}">@if($listingItems->previousPageUrl())<a href="{{ $listingItems->previousPageUrl() }}">{{ __('Trang trước') }}</a>@endif<span>{{ $listingItems->currentPage() }} / {{ $listingItems->lastPage() }}</span>@if($listingItems->nextPageUrl())<a href="{{ $listingItems->nextPageUrl() }}">{{ __('Trang sau') }}</a>@endif</nav>
@endif
</div></main>
@endsection
