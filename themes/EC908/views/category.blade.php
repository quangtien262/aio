@extends('theme-ec908::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec98-inner-hero"><div class="ec98-container"><p>EGO FITNESS</p><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec98-content"><div class="ec98-container ec98-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec908::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection

