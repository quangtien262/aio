@extends('theme-ec901::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec91-inner-hero"><div class="ec91-container"><p>TEMPO WATCH STORE</p><h1>Giỏ hàng</h1></div></section><section class="ec91-content"><div class="ec91-container ec91-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec91-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
