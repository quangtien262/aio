@extends('theme-book920::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="book20-inner-hero"><div class="book20-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sách') }}</h1></div></section><section class="book20-content"><div class="book20-container book20-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-book920::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
