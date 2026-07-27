@extends('theme-ec901::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content')
<main>
    <section class="ec91-inner-hero"><div class="ec91-container"><p>TEMPO WATCH STORE</p><h1>{{ data_get($category ?? null, 'name', 'Sản phẩm') }}</h1></div></section>
    <section class="ec91-content"><div class="ec91-container ec91-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec901::partials.product-card', ['item' => $item])@empty<p class="ec91-empty">Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section>
</main>
@endsection
