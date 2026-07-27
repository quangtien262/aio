@extends('theme-ec903::layout')
@section('title', 'Giỏ voucher')
@section('content')
<main><section class="ec93-inner-hero"><div class="ec93-container"><p>DEALVUI E-VOUCHER</p><h1>Giỏ voucher</h1></div></section><section class="ec93-content"><div class="ec93-container ec93-prose">@forelse(collect($lines ?? []) as $line)<p><b>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</b> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ voucher của bạn đang trống.</p>@endforelse<a class="ec93-button" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
