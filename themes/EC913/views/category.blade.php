@extends('theme-ec913::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec13-content"><div class="ec13-container ec13-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec913::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
