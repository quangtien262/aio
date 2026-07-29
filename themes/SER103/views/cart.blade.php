@extends('theme-ser103::layout')

@php
    $source = $cartItems ?? $items ?? collect();
    $entries = $source instanceof \Illuminate\Contracts\Pagination\Paginator ? $source->getCollection() : collect($source);
    $total = $cartTotal ?? $total ?? $entries->sum(fn ($item) => (float) (data_get($item, 'subtotal') ?? data_get($item, 'price', 0) * data_get($item, 'quantity', 1)));
@endphp

@section('title', 'Giỏ hàng')

@section('content')
    <section class="ser103-page-head">
        <div class="ser103-container">
            <nav class="ser103-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Giỏ hàng</span></nav>
            <h1>Giỏ hàng</h1>
            <p>Kiểm tra sản phẩm trước khi gửi yêu cầu đặt hàng.</p>
        </div>
    </section>
    <section class="ser103-subpage">
        <div class="ser103-container">
            @if($entries->isNotEmpty())
                <div class="ser103-list-grid">
                    @foreach($entries as $item)
                        @php
                            $title = data_get($item, 'name') ?? data_get($item, 'title') ?? 'Sản phẩm';
                            $image = data_get($item, 'image_url') ?? data_get($item, 'image') ?? 'https://images.unsplash.com/photo-1618220179428-22790b461013?auto=format&fit=crop&w=900&q=85';
                            $qty = data_get($item, 'quantity', 1);
                            $price = data_get($item, 'price', 0);
                        @endphp
                        <article class="ser103-list-card">
                            <img src="{{ $image }}" alt="{{ $title }}">
                            <h2>{{ $title }}</h2>
                            <p>Số lượng: {{ $qty }}</p>
                            <span class="ser103-price">{{ number_format((float) $price, 0, ',', '.') }}đ</span>
                        </article>
                    @endforeach
                </div>
                <p class="ser103-price" style="margin-top:30px">Tạm tính: {{ number_format((float) $total, 0, ',', '.') }}đ</p>
            @else
                <div class="ser103-empty">Giỏ hàng đang trống.</div>
            @endif
        </div>
    </section>
@endsection
