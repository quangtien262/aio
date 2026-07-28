@extends('theme-ec912::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec12-inner-hero"><div class="ec12-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec12-content"><div class="ec12-container ec12-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec912::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
