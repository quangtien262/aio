@extends('theme-ec904::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec94-inner-hero"><div class="ec94-container"><p>POCOMALL</p><h1>Giỏ hàng</h1></div></section><section class="ec94-content"><div class="ec94-container ec94-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec94-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
