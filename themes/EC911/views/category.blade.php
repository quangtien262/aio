@extends('theme-ec911::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec97-inner-hero"><div class="ec97-container"><p>EGA GEAR</p><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec97-content"><div class="ec97-container ec97-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec911::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection

