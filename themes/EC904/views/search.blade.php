@extends('theme-ec904::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec94-inner-hero"><div class="ec94-container"><p>POCOMALL</p><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec94-content"><div class="ec94-container"><form class="ec94-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên hoặc mã sản phẩm"><button class="ec94-button">Tìm kiếm</button></form><div class="ec94-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec904::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></div></section></main>
@endsection
