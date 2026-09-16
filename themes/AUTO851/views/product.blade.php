@extends('theme-auto851::layout')
@section('content')
<main><section class="a851-inner-hero"><div class="a851-container"><small>@themeT('products', 'Sản phẩm')</small><h1>{{ $title }}</h1></div></section><section class="a851-content"><div class="a851-container a851-detail">
    <div class="a851-detail-media"><img src="{{ $image ?: asset('themes/AUTO851/images/accessory-1.png') }}" alt="{{ $title }}"></div>
    <div class="a851-detail-copy"><small>AUTO851 PRO SERIES</small><h1>{{ $title }}</h1><strong class="a851-detail-price">{{ (float) $price > 0 ? number_format((float) $price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong><div class="a851-prose">{!! data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description')) !!}</div><form class="a851-buy-form" method="POST" action="{{ route('site.cart.add', ['locale' => app()->getLocale(), 'slug' => data_get($productModel ?? null, 'slug')]) }}">@csrf<input type="number" name="quantity" value="1" min="1" aria-label="{{ __('Số lượng') }}"><button class="a851-button">{{ __('Thêm vào giỏ hàng') }} <i class="fa-solid fa-cart-plus"></i></button></form></div>
</div></section></main>
@endsection
