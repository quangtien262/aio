@extends('theme-ec906::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec96-inner-hero"><div class="ec96-container"><p>EGA MINI</p><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec96-content"><div class="ec96-container ec96-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec906::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
