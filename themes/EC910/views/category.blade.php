@extends('theme-ec100::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content')
<main>
    <section class="ec10-inner-hero"><div class="ec10-container"><p>TEMPO WATCH STORE</p><h1>{{ data_get($category ?? null, 'name', 'Sản phẩm') }}</h1></div></section>
    <section class="ec10-content"><div class="ec10-container ec10-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec100::partials.product-card', ['item' => $item])@empty<p class="ec10-empty">Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section>
</main>
@endsection
