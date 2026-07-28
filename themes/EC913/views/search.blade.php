@extends('theme-ec913::layout')
@section('title', 'Tìm kiếm')
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>Kết quả tìm kiếm</h1></div></section><section class="ec13-content"><div class="ec13-container ec13-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec913::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
