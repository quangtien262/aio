@extends('theme-ec905::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec95-inner-hero"><div class="ec95-container"><p>EGO HOME</p><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec95-content"><div class="ec95-container"><form class="ec95-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên hoặc mã sản phẩm"><button class="ec95-button">Tìm kiếm</button></form><div class="ec95-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec905::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></div></section></main>
@endsection
