@php $lines = collect($lines ?? []); @endphp
@extends('theme-dn351::layout')
@section('title', 'Giỏ hàng')
@section('content')
<main><section class="dn-inner-hero"><div class="dn-container"><h1>Giỏ hàng</h1></div></section><section class="dn-section"><div class="dn-container dn-content-card">@forelse($lines as $line)<p><strong>{{ data_get($line, 'name', data_get($line, 'product.name')) }}</strong> × {{ data_get($line, 'quantity', 1) }}</p>@empty<p>Giỏ hàng đang trống.</p>@endforelse<a class="dn-btn" href="{{ route('site.checkout.index') }}">Thanh toán</a></div></section></main>
@endsection
