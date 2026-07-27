@extends('theme-ec908::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec98-inner-hero"><div class="ec98-container"><p>EGO FITNESS</p><h1>Giỏ hàng</h1></div></section><section class="ec98-content"><div class="ec98-container ec98-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec98-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection

