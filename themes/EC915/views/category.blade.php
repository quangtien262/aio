@extends('theme-ec915::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec15-content"><div class="ec15-container ec15-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec915::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
