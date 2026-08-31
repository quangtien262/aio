@extends('theme-e807::layout') @section('title','Giỏ hàng') @section('content')<main><section class="e807-inner-hero"><div class="e807-container"><h1>Giỏ hàng</h1></div></section><section class="e807-inner"><div class="e807-container">@forelse($lines as $line)<article style="display:flex;justify-content:space-between;padding:20px;border-bottom:1px solid #ddd"><h3>{{ data_get($line,'name',data_get($line,'product.name')) }}</h3><strong>{{ number_format((float)data_get($line,'line_total',data_get($line,'subtotal',0)),0,',','.') }}đ</strong></article>@empty<p>Giỏ hàng đang trống.</p>@endforelse<h2>Tổng cộng: {{ number_format((float)$total,0,',','.') }}đ</h2><a class="e807-more" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>@endsection



