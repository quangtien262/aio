@php
    $title = data_get($productModel ?? null, 'name', data_get($product ?? null, 'title', 'Sách'));
    $image = data_get($productModel ?? null, 'image_url', data_get($product ?? null, 'image'));
    $price = data_get($productModel ?? null, 'price', data_get($product ?? null, 'price', 0));
    $original = data_get($productModel ?? null, 'original_price', data_get($product ?? null, 'original_price'));
    $body = data_get($productModel ?? null, 'detail_content', data_get($product ?? null, 'body', ''));
@endphp
@extends('theme-book920::layout')
@section('title', $title)
@section('content')
<main><section class="book20-content"><div class="book20-container book20-product-detail"><div><img src="{{ $image ?: '/theme-demo/book920/book-1.webp' }}" alt="{{ $title }}"></div><div><p>SÁCH ĐƯỢC TUYỂN CHỌN</p><h1>{{ $title }}</h1><p>@if($original && $original > $price)<del>{{ number_format((float) $original, 0, ',', '.') }}đ</del>@endif</p><strong>{{ number_format((float) $price, 0, ',', '.') }}đ</strong><p>Giao hàng toàn quốc · Đổi trả thuận tiện · Thanh toán linh hoạt</p><form action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}" method="post">@csrf<input type="number" name="quantity" value="1" min="1"><button class="book20-button">Thêm vào giỏ</button></form><div class="book20-prose">{!! $body !!}</div></div></div></section></main>
@endsection
