@extends('theme-ec914::layout')
@section('title', 'Tìm kiếm')
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>Kết quả tìm kiếm</h1></div></section><section class="ec14-content"><div class="ec14-container ec14-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec914::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
