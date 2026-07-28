@extends('theme-ec914::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec14-content"><div class="ec14-container ec14-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec914::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
