@extends('theme-ec902::layout')
@section('title', 'Tìm kiếm sản phẩm')
@section('content')
<main>
    <section class="ec92-inner-hero"><div class="ec92-container"><p>NOVAPHONE TECHNOLOGY</p><h1>Tìm kiếm sản phẩm</h1></div></section>
    <section class="ec92-content"><div class="ec92-container">
        <form class="ec92-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Nhập tên hoặc mã sản phẩm"><button class="ec92-button">Tìm kiếm</button></form>
        <div class="ec92-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec902::partials.product-card', ['item' => $item])@empty<p class="ec92-empty">Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div>
    </div></section>
</main>
@endsection
