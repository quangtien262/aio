@extends('theme-foot405::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục sản phẩm'))
@section('content')
<main><section class="f405-inner-hero"><div class="f405-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="f405-content"><div class="f405-container f405-products f405-products--four">@forelse(collect($entries ?? []) as $item)@include('theme-foot405::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
