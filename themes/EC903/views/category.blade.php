@extends('theme-ec903::layout')
@section('title', data_get($category ?? null, 'name', 'Deal'))
@section('content')
<main><section class="ec93-inner-hero"><div class="ec93-container"><p>DEALVUI E-VOUCHER</p><h1>{{ data_get($category ?? null, 'name', 'Deal') }}</h1></div></section><section class="ec93-content"><div class="ec93-container ec93-product-grid">@forelse(collect($entries ?? []) as $item)@include('theme-ec903::partials.product-card', ['item' => $item])@empty<p>Chưa có deal trong danh mục.</p>@endforelse</div></section></main>
@endsection
