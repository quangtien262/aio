@extends('theme-ec905::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec95-inner-hero"><div class="ec95-container"><p>EGO HOME</p><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec95-content"><div class="ec95-container ec95-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec905::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
