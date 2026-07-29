@extends('theme-ec917::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec17-inner-hero"><div class="ec17-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec17-content"><div class="ec17-container ec17-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec917::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
