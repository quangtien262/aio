@extends('theme-ec907::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec97-inner-hero"><div class="ec97-container"><p>EGA GEAR</p><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec97-content"><div class="ec97-container"><form class="ec97-form ec97-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên hoặc mã sản phẩm"><button class="ec97-button">Tìm kiếm</button></form><div class="ec97-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec907::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></div></section></main>
@endsection

