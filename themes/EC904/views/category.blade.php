@extends('theme-ec904::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec94-inner-hero"><div class="ec94-container"><p>POCOMALL</p><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec94-content"><div class="ec94-container ec94-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec904::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
