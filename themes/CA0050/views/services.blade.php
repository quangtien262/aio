@extends('theme-ca0050::layout')
@section('content')
<main class="ca50-inner-page"><div class="ca50-inner-container">
    @include('theme-ca0050::partials.inner-heading', ['heading' => $pageTitle ?? __('Dịch vụ'), 'intro' => __('Tìm giải pháp phù hợp để xây dựng và chăm sóc không gian thủy sinh của bạn.')])
    <div class="ca50-service-grid">
    @forelse(($listingItems ?? []) as $item)
        @php($url = route('site.services.show', ['locale' => app()->getLocale(), 'slug' => $item->slug]))
        <article class="ca50-service-card">
            <a class="ca50-service-image" href="{{ $url }}">@if($item->featuredImage?->image_url)<img src="{{ $item->featuredImage->image_url }}" alt="{{ $item->title }}" loading="lazy">@else<svg width="64" height="64" viewBox="0 0 64 64" fill="none" aria-hidden="true"><path d="M8 24c8-12 16 12 24 0s16 12 24 0M8 36c8-12 16 12 24 0s16 12 24 0M8 48c8-12 16 12 24 0s16 12 24 0" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>@endif</a>
            <div><h2><a href="{{ $url }}">{{ $item->title }}</a></h2>@if($item->summary)<p>{{ $item->summary }}</p>@endif<a class="ca50-service-link" href="{{ $url }}">{{ __('Xem chi tiết') }} →</a></div>
        </article>
    @empty
        <div class="ca50-empty"><h2>{{ __('Dịch vụ đang được cập nhật') }}</h2><p>{{ __('Hãy liên hệ để được tư vấn theo nhu cầu của bạn.') }}</p><a class="ca50-inner-button" href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">{{ __('Liên hệ tư vấn') }}</a></div>
    @endforelse
    </div>
    @if(isset($listingItems) && method_exists($listingItems, 'hasPages') && $listingItems->hasPages())
    <nav class="ca50-inner-pagination" aria-label="{{ __('Phân trang') }}">@if($listingItems->previousPageUrl())<a href="{{ $listingItems->previousPageUrl() }}">{{ __('Trang trước') }}</a>@endif<span>{{ $listingItems->currentPage() }} / {{ $listingItems->lastPage() }}</span>@if($listingItems->nextPageUrl())<a href="{{ $listingItems->nextPageUrl() }}">{{ __('Trang sau') }}</a>@endif</nav>
    @endif
</div></main>
@endsection
