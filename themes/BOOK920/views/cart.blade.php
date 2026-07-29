@extends('theme-book920::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>Giỏ hàng</h1></div></section><section class="book20-content"><div class="book20-container book20-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="book20-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
