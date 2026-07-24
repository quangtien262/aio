@extends('theme-bds701::layout')
@section('title', 'Bất động sản đang giao dịch')
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>DELTA PLATINUM</p><h1>Tất cả tin bất động sản</h1></div></section>
<main class="bds-section soft">
    <div class="bds-container bds-filter-bar">
        <form method="GET">
            <input name="q" value="{{ data_get($filters, 'q') }}" placeholder="Tìm theo tên dự án, vị trí...">
            <select name="transaction_type"><option value="">Mọi giao dịch</option><option value="sale" @selected(data_get($filters, 'transaction_type') === 'sale')>Bán</option><option value="rent" @selected(data_get($filters, 'transaction_type') === 'rent')>Cho thuê</option></select>
            <select name="property_type"><option value="">Mọi loại hình</option>@foreach($propertyTypes as $type)<option value="{{ $type->slug }}" @selected((string)data_get($filters, 'property_type') === $type->slug)>{{ $type->name }}</option>@endforeach</select>
            <input name="province" value="{{ data_get($filters, 'province') }}" placeholder="Tỉnh / Thành">
            <button class="bds-btn">Tìm kiếm</button>
        </form>
    </div>
    <div class="bds-container">
        <header class="bds-heading"><div><h2><em>{{ $listings->total() }}</em> bất động sản</h2><p>Danh sách đã được cập nhật theo điều kiện tìm kiếm.</p></div></header>
        <div class="bds-grid">@forelse($listings as $item) @include('theme-bds701::partials.listing-card', ['item' => $item]) @empty <p>Chưa có bất động sản phù hợp.</p> @endforelse</div>
        <div style="margin-top:35px">{{ $listings->links() }}</div>
    </div>
</main>
@endsection
