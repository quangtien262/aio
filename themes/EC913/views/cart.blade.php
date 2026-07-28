@extends('theme-ec913::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>Giỏ hàng</h1></div></section><section class="ec13-content"><div class="ec13-container ec13-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec13-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
