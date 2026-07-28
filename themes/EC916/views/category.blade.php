@extends('theme-ec916::layout')
@section('title', data_get($category ?? null, 'name', 'Danh mục'))
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>{{ data_get($category ?? null, 'name', 'Danh mục sản phẩm') }}</h1></div></section><section class="ec16-content"><div class="ec16-container ec16-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec916::partials.product-card', ['item' => $item])@empty<p>Chưa có sản phẩm trong danh mục.</p>@endforelse</div></section></main>
@endsection
