@extends('theme-ec906::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec96-inner-hero"><div class="ec96-container"><p>EGA MINI</p><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec96-content"><div class="ec96-container"><form class="ec96-form ec96-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên hoặc mã sản phẩm"><button class="ec96-button">Tìm kiếm</button></form><div class="ec96-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec906::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></div></section></main>
@endsection
