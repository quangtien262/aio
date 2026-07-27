@extends('theme-ec905::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec95-inner-hero"><div class="ec95-container"><p>EGO HOME</p><h1>Giỏ hàng</h1></div></section><section class="ec95-content"><div class="ec95-container ec95-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec95-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
