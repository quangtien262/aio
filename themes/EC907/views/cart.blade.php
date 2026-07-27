@extends('theme-ec907::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="ec97-inner-hero"><div class="ec97-container"><p>EGA GEAR</p><h1>Giỏ hàng</h1></div></section><section class="ec97-content"><div class="ec97-container ec97-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng của bạn đang trống.</p>@endforelse<a class="ec97-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection

