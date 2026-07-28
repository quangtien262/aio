@extends('theme-ec914::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>Giỏ hàng</h1></div></section><section class="ec14-content"><div class="ec14-container ec14-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec14-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
