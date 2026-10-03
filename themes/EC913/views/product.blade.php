@php
    $title = data_get($productModel ?? null, 'name', data_get($product ?? [], 'title', 'Sản phẩm'));
    $price = (float) data_get($productModel ?? null, 'price', 0);
    $original = (float) data_get($productModel ?? null, 'original_price', 0);
    $category = $productModel?->category;
    $body = $productModel?->detail_content ?: $productModel?->short_description;
    $images = collect([$productModel?->image_url])->merge($productModel?->images->pluck('image_url') ?? [])->filter()->unique()->values();
    $stock = $productModel?->stock;
    $available = $stock === null || $stock > 0;
@endphp
@extends('theme-ec913::layout')
@section('title', $title)
@section('content')
<main class="ec13-product-page">
    <div class="ec13-container">
        <nav class="ec13-breadcrumb" aria-label="Đường dẫn">
            <a href="{{ route('site.home') }}">Trang chủ</a><span aria-hidden="true">/</span>
            <a href="{{ route('site.catalog.search') }}">Sản phẩm</a>
            @if($category)<span aria-hidden="true">/</span><a href="{{ route('site.catalog.category', ['slug' => $category->slug]) }}">{{ $category->name }}</a>@endif
            <span aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span>
        </nav>
        <section class="ec13-product-detail">
            <div class="ec13-product-gallery">
                <div class="ec13-product-stage">
                    @if($images->isNotEmpty())<img data-ec13-product-image src="{{ $images->first() }}" alt="{{ $title }}">@else<p>Ảnh sản phẩm đang được cập nhật.</p>@endif
                </div>
                @if($images->count() > 1)
                    <div class="ec13-product-thumbs" aria-label="Ảnh sản phẩm">
                        @foreach($images as $image)<button type="button" data-ec13-product-thumb="{{ $image }}" aria-label="Xem ảnh {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><img src="{{ $image }}" alt="" loading="lazy"></button>@endforeach
                    </div>
                @endif
            </div>
            <div class="ec13-product-info">
                @if($category)<a class="ec13-product-category" href="{{ route('site.catalog.category', ['slug' => $category->slug]) }}">{{ $category->name }}</a>@endif
                <h1>{{ $title }}</h1>
                <div class="ec13-detail-meta">@if($productModel?->sku)<span>Mã sản phẩm: {{ $productModel->sku }}</span>@endif<span class="ec13-availability">{{ $available ? 'Có thể đặt hàng' : 'Tạm hết hàng' }}</span></div>
                <div class="ec13-product-pricing">
                    <span>Giá sản phẩm</span>
                    <div><strong>{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : 'Liên hệ' }}</strong>
                    @if($price > 0 && $original > $price)<del>{{ number_format($original, 0, ',', '.') }}đ</del><span class="ec13-saving">−{{ round((1 - $price / $original) * 100) }}%</span>@endif</div>
                    @if($price > 0 && $original > $price)<small>Tiết kiệm {{ number_format($original - $price, 0, ',', '.') }}đ so với giá gốc</small>@endif
                </div>
                @if($productModel?->short_description)<div class="ec13-product-summary">{!! $productModel->short_description !!}</div>@endif
                @if($errors->any())<div class="ec13-form-error" role="alert">{{ $errors->first() }}</div>@endif
                @if($available && $price > 0)
                <form class="ec13-purchase" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}" method="post">
                    @csrf
                    <label for="ec13-quantity">Số lượng</label>
                    <div class="ec13-purchase-row"><input id="ec13-quantity" type="number" name="quantity" value="1" min="1" step="1" @if($stock !== null) max="{{ $stock }}" @endif required><button class="ec13-button" type="submit">Thêm vào giỏ hàng</button></div>
                </form>
                @endif
                <a class="ec13-consult" href="{{ route('site.contact') }}">Liên hệ tư vấn sản phẩm <span aria-hidden="true">→</span></a>
                <p class="ec13-purchase-note">Cần thêm thông tin? Trao đổi với cửa hàng về cấu hình, giao hàng và điều kiện bảo hành trước khi đặt mua.</p>
            </div>
        </section>
        <section class="ec13-product-description" aria-labelledby="ec13-description-title">
            <h2 id="ec13-description-title">Thông tin sản phẩm</h2>
            <div class="ec13-prose">{!! $body ?: '<p>Thông tin chi tiết đang được cập nhật. Vui lòng liên hệ để được tư vấn.</p>' !!}</div>
        </section>
    </div>
    @include('themes.common.product-recommendations', ['showRelatedRecommendations' => true])
</main>
@endsection
