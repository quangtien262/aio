@extends('theme-foot405::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="f405-inner-hero"><div class="f405-container"><h1>Giỏ hàng</h1></div></section><section class="f405-content"><div class="f405-container f405-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="f405-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
