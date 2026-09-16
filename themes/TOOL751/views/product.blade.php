@extends('theme-tool751::layout')
@section('content')
<main><section class="t751-inner-hero"><div class="t751-container"><small>@themeT('products', 'Sản phẩm')</small><h1>{{ $title }}</h1></div></section><section class="t751-content"><div class="t751-container t751-detail">
    <div class="t751-detail-media"><img src="{{ $image ?: asset('themes/TOOL751/images/product-drill.png') }}" alt="{{ $title }}"></div>
    <div class="t751-detail-copy"><small>TOOL751 PRO SERIES</small><h1>{{ $title }}</h1><strong class="t751-detail-price">{{ (float) $price > 0 ? number_format((float) $price, 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong><div class="t751-prose">{!! data_get($productModel ?? null, 'detail_content', data_get($productModel ?? null, 'short_description')) !!}</div><form class="t751-buy-form" method="POST" action="{{ route('site.cart.add', ['locale' => app()->getLocale(), 'slug' => data_get($productModel ?? null, 'slug')]) }}">@csrf<input type="number" name="quantity" value="1" min="1" aria-label="{{ __('Số lượng') }}"><button class="t751-button">{{ __('Thêm vào giỏ hàng') }} <i class="fa-solid fa-cart-plus"></i></button></form></div>
</div></section></main>
@endsection
