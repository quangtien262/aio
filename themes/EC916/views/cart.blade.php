@extends('theme-ec916::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>Giỏ hàng</h1></div></section><section class="ec16-content"><div class="ec16-container ec16-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec16-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
