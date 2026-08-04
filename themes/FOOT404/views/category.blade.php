@extends('theme-foot404::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục sản phẩm'))
@section('content')
<main><section class="f404-inner-hero"><div class="f404-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="f404-content"><div class="f404-container f404-products f404-products--four">@forelse(collect($entries ?? []) as $item)@include('theme-foot404::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
