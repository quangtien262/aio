@php
    $media = collect($listing->media);
    $cover = $media->firstWhere('is_featured', true) ?? $media->first();
@endphp
@extends('theme-bds701::layout')
@section('title', $listing->meta_title ?: $listing->title)
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p><a href="{{ route('site.real-estate.index', ['locale' => app()->getLocale()]) }}">@themeT('listing.real_estate', 'Bất động sản')</a> / {{ $listing->propertyType?->name }}</p><h1>{{ $listing->title }}</h1></div></section>
<main class="bds-section soft"><div class="bds-container">
    <div class="bds-detail">
        <div>
            <img class="bds-gallery-main" data-bds-gallery-main src="{{ $cover?->media_url }}" alt="{{ $listing->title }}">
            <div class="bds-thumbs">@foreach($media as $index => $image)<button class="{{ $index === 0 ? 'is-active' : '' }}" type="button" data-bds-thumb="{{ $image->media_url }}"><img src="{{ $image->media_url }}" alt="{{ $image->alt_text ?: $listing->title }}"></button>@endforeach</div>
        </div>
        <aside class="bds-summary-card">
            <span class="bds-status" style="position:static;display:inline-block">{{ $listing->transaction_type === 'rent' ? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'rent', 'Cho thuê') : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'sale', 'Bán') }}</span>
            @if($listing->is_hot)<span class="bds-hot" style="position:static;display:inline-block">Hot</span>@endif
            <h1>{{ $listing->title }}</h1>
            <p class="bds-location"><i class="fa-solid fa-location-dot"></i> {{ collect([$listing->address,$listing->ward,$listing->district,$listing->province])->filter()->implode(', ') }}</p>
            <p class="bds-detail-price">{{ $listing->price ? number_format((float)$listing->price, 0, ',', '.').' '.$listing->currency : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'listing.contact_price', 'Liên hệ') }} @if($listing->price_unit === 'tháng')<small>@themeT('listing.per_month', '/Tháng')</small>@endif</p>
            <div class="bds-facts">
                <span><i class="fa-solid fa-bed"></i> {{ $listing->bedrooms ?? 0 }} @themeT('listing.bedrooms', 'phòng ngủ')</span>
                <span><i class="fa-solid fa-bath"></i> {{ $listing->bathrooms ?? 0 }} @themeT('listing.bathrooms', 'phòng tắm')</span>
                <span><i class="fa-regular fa-square"></i> {{ $listing->floor_area ?: $listing->land_area }} m²</span>
                <span><i class="fa-solid fa-compass"></i> {{ $listing->direction ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'listing.updating', 'Đang cập nhật') }}</span>
            </div>
            <p>{{ $listing->summary }}</p>
            <a class="bds-btn" style="display:block;text-align:center" href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">@themeT('listing.consult_now', 'Nhận tư vấn ngay')</a>
        </aside>
    </div>
    <article class="bds-content-card"><h2>@themeT('listing.details', 'Thông tin chi tiết')</h2>{!! $listing->content ?: nl2br(e($listing->summary)) !!}<h3>@themeT('listing.legal_and_furnishing', 'Pháp lý & nội thất')</h3><p><strong>@themeT('listing.legal', 'Pháp lý:')</strong> {{ $listing->legal_status ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'listing.updating', 'Đang cập nhật') }}</p><p><strong>@themeT('listing.furnishing', 'Nội thất:')</strong> {{ $listing->furnishing_status ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'listing.updating', 'Đang cập nhật') }}</p></article>
    @if($relatedListings->isNotEmpty())<section style="margin-top:65px"><header class="bds-heading"><div><h2><em>@themeT('listing.real_estate', 'Bất động sản')</em> @themeT('listing.related', 'liên quan')</h2></div></header><div class="bds-grid">@foreach($relatedListings->take(3) as $item) @include('theme-bds701::partials.listing-card', ['item' => $item]) @endforeach</div></section>@endif
</div></main>
@endsection
