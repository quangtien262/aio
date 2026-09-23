@extends('theme-dl750::layout')
@section('title', data_get($product ?? [], 'title', ''))
@section('content')
<main class="dl-inner">
    <div class="dl-wrap dl-content dl-product-detail">
        <div class="dl-product-image">
            @if(data_get($product ?? [], 'image'))
                <img src="{{ data_get($product, 'image') }}" alt="{{ data_get($product, 'title') }}">
            @endif
        </div>
        <div>
            <h1>{{ data_get($product ?? [], 'title', data_get($productModel ?? null, 'name')) }}</h1>
            @if((float) data_get($product ?? [], 'old_price', data_get($productModel ?? null, 'original_price')) > (float) data_get($product ?? [], 'price'))
                <del>{{ number_format((float) data_get($product ?? [], 'old_price', data_get($productModel ?? null, 'original_price')), 0, ',', '.') }}đ</del>
            @endif
            <p class="dl-price">@if((float) data_get($product ?? [], 'price') > 0){{ number_format((float) data_get($product, 'price'), 0, ',', '.') }}đ @else @themeT('contact', 'Liên hệ') @endif</p>
            <form class="dl-purchase" method="post" action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}">
                @csrf
                <label>@themeT('quantity', 'Số lượng') <input type="number" name="quantity" value="1" min="1" required></label>
                <button class="dl-primary" type="submit">@themeT('add_to_cart', 'Thêm vào giỏ')</button>
            </form>
            <div class="dl-prose">{!! data_get($productModel ?? null, 'detail_content') ?: data_get($productModel ?? null, 'short_description') !!}</div>
        </div>
    </div>
</main>
@endsection
