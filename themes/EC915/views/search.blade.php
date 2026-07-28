@extends('theme-ec915::layout')
@section('title', 'Tìm kiếm')
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>Kết quả tìm kiếm</h1></div></section><section class="ec15-content"><div class="ec15-container ec15-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec915::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
