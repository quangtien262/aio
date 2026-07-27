@extends('theme-ec906::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec96-inner-hero"><div class="ec96-container"><p>EGA MINI</p><h1>Giỏ hàng</h1></div></section><section class="ec96-content"><div class="ec96-container ec96-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec96-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
