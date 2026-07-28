@extends('theme-ec100::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main>
    <section class="ec10-inner-hero"><div class="ec10-container"><p>TEMPO WATCH STORE</p><h1>Tìm kiếm sản phẩm</h1></div></section>
    <section class="ec10-content"><div class="ec10-container">
        <form class="ec10-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Nhập tên hoặc mã đồng hồ"><button class="ec10-button">Tìm kiếm</button></form>
        <div class="ec10-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec100::partials.product-card', ['item' => $item])@empty<p class="ec10-empty">Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div>
    </div></section>
</main>
@endsection
