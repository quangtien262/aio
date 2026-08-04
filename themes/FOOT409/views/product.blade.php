@php
    $title=data_get($product??null,'title',data_get($productModel??null,'name','Món ăn'));
    $image=data_get($product??null,'image',data_get($productModel??null,'image_url'));
    $price=(float)data_get($product??null,'price',data_get($productModel??null,'price',0));
    $stock=(int)data_get($productModel??null,'stock',0);
    $body=data_get($productModel??null,'detail_content',data_get($productModel??null,'short_description'));
@endphp
@extends('theme-foot409::layout')
@section('title',$title)
@section('content')
<main><section class="f409-content"><div class="f409-container f409-product-detail"><div><img src="{{ $image?:'/theme-demo/foot409/promo-combo.png' }}" alt="{{ $title }}"></div><div><h1>{{ $title }}</h1><div class="f409-stars">★★★★★ <small>So sánh</small></div><p>Thương hiệu: <b>{{ data_get($productModel??null,'brand','Đang cập nhật') }}</b> &nbsp; Mã sản phẩm: <b>{{ data_get($productModel??null,'sku','Đang cập nhật') }}</b></p><p>Trạng thái: <b style="color:#34a72c">✓ {{ $stock>0?'Sẵn trong kho':'Liên hệ' }}</b></p><h2>{{ $price>0?number_format($price,0,',','.').'đ':'Liên hệ' }}</h2><form action="{{ route('site.cart.add',['slug'=>data_get($productModel??null,'slug')]) }}" method="post">@csrf<label><b>Ghi chú cho món ăn</b><textarea name="note" rows="3" style="display:block;width:100%;margin-top:10px;padding:12px;border:1px solid #ddd"></textarea></label><div class="f409-quantity"><span>Số lượng</span><input type="number" name="quantity" value="1" min="1"><button class="f409-button">Thêm vào giỏ</button></div></form><div>{!! $body !!}</div></div></div></section></main>
@endsection
