@extends('theme-foot404::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="f404-inner-hero"><div class="f404-container"><h1>Tìm kiếm sản phẩm</h1></div></section><section class="f404-content"><div class="f404-container f404-products f404-products--four">@forelse(collect($entries ?? []) as $item)@include('theme-foot404::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
