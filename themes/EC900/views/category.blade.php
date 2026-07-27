@extends('theme-ec900::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content')
<main>
    <section class="ec9-inner-hero"><div class="ec9-container"><p>ECOMAX SMART HOME</p><h1>{{ data_get($category ?? null, 'name', 'Sản phẩm') }}</h1></div></section>
    <section class="ec9-content"><div class="ec9-container ec9-product-grid ec9-product-grid-large">@forelse(collect($entries ?? []) as $item)@include('theme-ec900::partials.product-card', ['item' => $item])@empty<p class="ec9-empty">Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section>
</main>
@endsection
