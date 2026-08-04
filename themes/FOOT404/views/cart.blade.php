@extends('theme-foot404::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="f404-inner-hero"><div class="f404-container"><h1>Giỏ hàng</h1></div></section><section class="f404-content"><div class="f404-container f404-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="f404-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
