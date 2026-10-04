@extends('theme-shop606::layout')
@section('title', $productModel->name)
@section('content')
@include('themes.common.catalog-shell-styles')
@include('theme-shop606::partials.product-styles')
<main class="s606-product-page">
    <div class="s606-product-wrap">
        <nav class="s606-product-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ __('news-detail.home') }}</a><span aria-hidden="true">/</span><a href="{{ route('site.catalog.search') }}">{{ __('catalog-listing.products') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $productModel->name }}</span></nav>
        <div class="s606-product-layout">
            <div class="s606-product-gallery">
                @foreach($productGallery ?? [] as $photo)
                    <img src="{{ is_array($photo) ? data_get($photo, 'url', data_get($photo, 'image')) : $photo }}" alt="{{ $productModel->name }}" @if(!$loop->first) loading="lazy" @endif>
                @endforeach
            </div>
            <section class="s606-product-info">
                <h1>{{ $productModel->name }}</h1>
                <strong class="s606-product-price">{{ data_get($product, 'price_label') ?: ((float)$productModel->price > 0 ? number_format((float)$productModel->price,0,',','.').'đ' : __('catalog.contact_price')) }}</strong>
                @if($productModel->short_description)<p class="s606-product-summary">{{ strip_tags($productModel->short_description) }}</p>@endif
                <form class="s606-product-purchase" method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}">
                    @csrf
                    <label for="s606-product-quantity">{{ __('catalog.quantity') }}</label>
                    <div><input id="s606-product-quantity" type="number" name="quantity" value="1" min="1" required><button type="submit">{{ __('catalog.add_to_cart') }} <span aria-hidden="true">→</span></button></div>
                </form>
                <a class="s606-product-consult" href="{{ route('site.contact') }}">{{ app()->getLocale() === 'vi' ? 'Tư vấn về sản phẩm' : 'Product enquiry' }} <span aria-hidden="true">↗</span></a>
            </section>
        </div>
        @if($productModel->detail_content ?: $productModel->short_description)
        <section class="s606-product-description" aria-labelledby="s606-description-title">
            <h2 id="s606-description-title">{{ app()->getLocale() === 'vi' ? 'Thông tin sản phẩm' : 'Product information' }}</h2>
            <div class="s606-product-prose">{!! $productModel->detail_content ?: $productModel->short_description !!}</div>
        </section>
        @endif
    </div>
</main>
@include('themes.common.product-recommendations', ['showRelatedRecommendations' => true])
@endsection
