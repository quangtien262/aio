@extends('theme-ec916::layout')
@section('title', 'Tìm kiếm')
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>Kết quả tìm kiếm</h1></div></section><section class="ec16-content"><div class="ec16-container ec16-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec916::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy sản phẩm phù hợp.</p>@endforelse</div></section></main>
@endsection
