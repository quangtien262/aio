@extends('theme-foot405::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="f405-inner-hero"><div class="f405-container"><h1>Tìm kiếm sản phẩm</h1></div></section><section class="f405-content"><div class="f405-container f405-products f405-products--four">@forelse(collect($entries ?? []) as $item)@include('theme-foot405::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
