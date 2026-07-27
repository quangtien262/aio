@extends('theme-ec900::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec9-inner-hero"><div class="ec9-container"><p>ECOMAX SMART HOME</p><h1>Giỏ hàng</h1></div></section><section class="ec9-content"><div class="ec9-container ec9-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec9-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
