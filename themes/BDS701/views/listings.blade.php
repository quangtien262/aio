@extends('theme-bds701::layout')
@section('title')@themeT('listing.all_title', 'Bất động sản đang giao dịch')@endsection
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>DELTA PLATINUM</p><h1>@themeT('listing.all_heading', 'Tất cả tin bất động sản')</h1></div></section>
<main class="bds-section soft">
    <div class="bds-container bds-filter-bar">
        <form method="GET">
            <input name="q" value="{{ data_get($filters, 'q') }}" placeholder="@themeT('listing.filter_placeholder', 'Tìm theo tên dự án, vị trí...')">
            <select name="transaction_type"><option value="">@themeT('listing.any_transaction', 'Mọi giao dịch')</option><option value="sale" @selected(data_get($filters, 'transaction_type') === 'sale')>@themeT('sale', 'Bán')</option><option value="rent" @selected(data_get($filters, 'transaction_type') === 'rent')>@themeT('rent', 'Cho thuê')</option></select>
            <select name="property_type"><option value="">@themeT('listing.any_type', 'Mọi loại hình')</option>@foreach($propertyTypes as $type)<option value="{{ $type->slug }}" @selected((string)data_get($filters, 'property_type') === $type->slug)>{{ $type->name }}</option>@endforeach</select>
            <input name="province" value="{{ data_get($filters, 'province') }}" placeholder="@themeT('listing.province', 'Tỉnh / Thành')">
            <button class="bds-btn">@themeT('search', 'Tìm kiếm')</button>
        </form>
    </div>
    <div class="bds-container">
        <header class="bds-heading"><div><h2><em>{{ $listings->total() }}</em> @themeT('listing.result_label', 'bất động sản')</h2><p>@themeT('listing.result_summary', 'Danh sách đã được cập nhật theo điều kiện tìm kiếm.')</p></div></header>
        <div class="bds-grid">@forelse($listings as $item) @include('theme-bds701::partials.listing-card', ['item' => $item]) @empty <p>@themeT('listing.empty', 'Chưa có bất động sản phù hợp.')</p> @endforelse</div>
        <div style="margin-top:35px">{{ $listings->links() }}</div>
    </div>
</main>
@endsection
