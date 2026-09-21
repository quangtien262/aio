@php
    $title = data_get($productModel ?? null, 'name', data_get($product ?? [], 'title', 'Sản phẩm'));
    $image = data_get($product ?? [], 'image') ?: data_get($productModel ?? null, 'image_url');
    $price = data_get($productModel ?? null, 'price', 0);
@endphp
@extends('theme-tool750::layout')
@section('content')
<main>
    <section class="t750-inner-hero"><div class="t750-container"><small>@themeT('products', 'Sản phẩm')</small><h1>{{ $title }}</h1></div></section>
    <section class="t750-content"><div class="t750-container t750-detail">
        <div class="t750-detail-media"><img src="{{ $image ?: asset('themes/TOOL750/images/tool-collection.png') }}" alt="{{ $title }}"></div>
        <div class="t750-detail-copy">
            <small>TOOL750 PROFESSIONAL</small>
            <h1>{{ $title }}</h1>
            <strong class="t750-detail-price">{{ (float) $price > 0 ? number_format((float) $price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong>
            <div class="t750-prose">{!! data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description')) !!}</div>
            <form class="t750-buy-form" method="POST" action="{{ route('site.cart.add', ['locale' => app()->getLocale(), 'slug' => data_get($productModel ?? null, 'slug')]) }}">
                @csrf
                <input type="number" name="quantity" value="1" min="1" aria-label="{{ __('Số lượng') }}">
                <button class="t750-button">@themeT('add_to_cart', 'Thêm vào giỏ hàng') <i class="fa-solid fa-cart-plus"></i></button>
            </form>
        </div>
    </div></section>
</main>
@endsection
