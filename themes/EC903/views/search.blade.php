@extends('theme-ec903::layout')
@section('title', 'Tìm kiếm deal')
@section('content')
<main><section class="ec93-inner-hero"><div class="ec93-container"><p>DEALVUI E-VOUCHER</p><h1>Tìm kiếm deal</h1></div></section><section class="ec93-content"><div class="ec93-container"><form class="ec93-search-form" action="{{ route('site.catalog.search') }}" method="get"><input name="q" value="{{ request('q') }}" placeholder="Tên dịch vụ hoặc mã voucher"><button class="ec93-button">Tìm kiếm</button></form><div class="ec93-product-grid">@forelse(collect($entries ?? $products ?? []) as $item)@include('theme-ec903::partials.product-card', ['item' => $item])@empty<p>Không tìm thấy deal phù hợp.</p>@endforelse</div></div></section></main>
@endsection
