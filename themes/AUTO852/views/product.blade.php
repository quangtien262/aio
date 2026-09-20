@extends('theme-auto852::layout')
@php
    $title = data_get($productModel, 'name', data_get($product, 'title', ''));
    $image = data_get($product, 'image') ?: data_get($productModel, 'image_url');
    $price = data_get($productModel, 'price', 0);
    $pageTitle = data_get($productModel, 'meta_title') ?: $title;
    $pageDescription = data_get($productModel, 'meta_description') ?: strip_tags(data_get($productModel, 'short_description', '') ?? '');
@endphp
@section('content')
<main><section class="a852-inner-hero"><div class="a852-container"><small>@themeT('products', 'Sản phẩm')</small><h1>{{ $title }}</h1></div></section><section class="a852-content"><div class="a852-container a852-detail">
    <div class="a852-detail-media"><img src="{{ $image ?: asset('themes/AUTO852/images/product-1.png') }}" alt="{{ $title }}"></div>
    <div class="a852-detail-copy"><small>ONYX DETAILING PRO SERIES</small><h1>{{ $title }}</h1><strong class="a852-detail-price">{{ (float) $price > 0 ? number_format((float) $price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO852', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong><div class="a852-prose">{!! data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description')) !!}</div><form class="a852-buy-form" method="POST" action="{{ route('site.cart.add', ['locale' => app()->getLocale(), 'slug' => data_get($productModel ?? null, 'slug')]) }}">@csrf<input type="number" name="quantity" value="1" min="1" aria-label="{{ __('Số lượng') }}"><button class="a852-button">{{ __('Thêm vào giỏ hàng') }} <i class="fa-solid fa-cart-plus"></i></button></form></div>
</div></section></main>
@endsection
