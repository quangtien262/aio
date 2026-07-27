@extends('theme-ec902::layout')
@section('title', data_get($category ?? null, 'name', 'Sản phẩm'))
@section('content')
<main>
    <section class="ec92-inner-hero"><div class="ec92-container"><p>NOVAPHONE TECHNOLOGY</p><h1>{{ data_get($category ?? null, 'name', 'Sản phẩm') }}</h1></div></section>
    <section class="ec92-content"><div class="ec92-container ec92-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@empty<p class="ec92-empty">Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section>
</main>
@endsection
