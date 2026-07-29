@extends('theme-book920::layout')
@section('title', 'Tìm kiếm')
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>Kết quả tìm kiếm</h1></div></section><section class="book20-content"><div class="book20-container book20-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-book920::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy cuốn sách phù hợp.</p>@endforelse</div></section></main>
@endsection
