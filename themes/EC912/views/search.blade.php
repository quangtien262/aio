@extends('theme-ec912::layout')
@section('title', 'Tìm kiếm')
@section('content')
<main><section class="ec12-inner-hero"><div class="ec12-container"><h1>Kết quả tìm kiếm</h1></div></section><section class="ec12-content"><div class="ec12-container ec12-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec912::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
