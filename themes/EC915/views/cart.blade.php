@extends('theme-ec915::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>Giỏ hàng</h1></div></section><section class="ec15-content"><div class="ec15-container ec15-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec15-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
