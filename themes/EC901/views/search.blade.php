@extends('theme-ec901::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main>
    <section class="ec91-inner-hero"><div class="ec91-container"><p>TEMPO WATCH STORE</p><h1>Tìm kiếm sản phẩm</h1></div></section>
    <section class="ec91-content"><div class="ec91-container">
        <form class="ec91-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Nhập tên hoặc mã đồng hồ"><button class="ec91-button">Tìm kiếm</button></form>
        <div class="ec91-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec901::partials.product-card', ['item' => $item])@empty<p class="ec91-empty">Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div>
    </div></section>
</main>
@endsection
