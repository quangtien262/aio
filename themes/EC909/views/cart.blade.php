@extends('theme-ec909::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec99-inner-hero"><div class="ec99-container"><p>EURO SOUND</p><h1>Giỏ hàng</h1></div></section><section class="ec99-content"><div class="ec99-container ec99-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec99-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection


