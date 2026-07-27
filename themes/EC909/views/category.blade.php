@extends('theme-ec909::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec99-inner-hero"><div class="ec99-container"><p>EURO SOUND</p><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec99-content"><div class="ec99-container ec99-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec909::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection


