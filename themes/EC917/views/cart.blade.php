@extends('theme-ec917::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec17-inner-hero"><div class="ec17-container"><h1>Giỏ hàng</h1></div></section><section class="ec17-content"><div class="ec17-container ec17-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec17-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
