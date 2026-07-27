@extends('theme-ec902::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec92-inner-hero"><div class="ec92-container"><p>NOVAPHONE TECHNOLOGY</p><h1>Giỏ hàng</h1></div></section><section class="ec92-content"><div class="ec92-container ec92-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec92-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
