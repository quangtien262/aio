@extends('theme-ec917::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec17-inner-hero"><div class="ec17-container"><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec17-content"><div class="ec17-container ec17-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec917::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
