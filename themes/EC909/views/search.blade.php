@extends('theme-ec909::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main><section class="ec99-inner-hero"><div class="ec99-container"><p>EURO SOUND</p><h1>Tìm kiếm sản phẩm</h1></div></section><section class="ec99-content"><div class="ec99-container"><form class="ec99-form ec99-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên hoặc mã sản phẩm"><button class="ec99-button">Tìm kiếm</button></form><div class="ec99-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec909::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></div></section></main>
@endsection


