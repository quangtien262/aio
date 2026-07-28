@extends('theme-ec100::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec10-inner-hero"><div class="ec10-container"><p>TEMPO WATCH STORE</p><h1>Giỏ hàng</h1></div></section><section class="ec10-content"><div class="ec10-container ec10-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec10-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
