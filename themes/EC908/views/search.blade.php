@extends('theme-ec908::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec98-inner-hero"><div class="ec98-container"><p>EGO FITNESS</p><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec98-content"><div class="ec98-container"><form class="ec98-form ec98-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên hoặc mã sản phẩm"><button class="ec98-button">Tìm kiếm</button></form><div class="ec98-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec908::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></div></section></main>
@endsection

