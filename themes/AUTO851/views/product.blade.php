@extends('theme-auto851::layout')
@php
    $title = data_get($productModel, 'name', data_get($product, 'title', ''));
    $image = data_get($product, 'image') ?: data_get($productModel, 'image_url');
    $price = (float) data_get($productModel, 'price', 0);
    $pageTitle = data_get($productModel, 'meta_title') ?: $title;
    $pageDescription = data_get($productModel, 'meta_description') ?: strip_tags(data_get($productModel, 'short_description', '') ?? '');
    $inStock = (int) data_get($productModel, 'stock', 0) > 0;
@endphp
@section('content')
<main class="a851-product-page">
    <div class="a851-container">
        <nav class="a851-product-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">@themeT('home', 'Trang chủ')</a><span>/</span>
            <a href="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}">@themeT('products', 'Sản phẩm')</a><span>/</span><span aria-current="page">{{ $title }}</span>
        </nav>
        <section class="a851-product-overview">
            <div class="a851-product-gallery">
                <img id="a851-product-image" class="a851-product-main-image" src="{{ $image }}" alt="{{ $title }}">
                @if(count($productGallery ?? []) > 1)
                <div class="a851-product-thumbs">@foreach($productGallery as $photo)
                    <button type="button" data-a851-photo="{{ $photo['url'] }}" aria-label="{{ $photo['alt'] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy"></button>
                @endforeach</div>
                @endif
            </div>
            <div class="a851-product-info">
                @if($productModel->category)<span class="a851-product-category">{{ $productModel->category->name }}</span>@endif
                <h1>{{ $title }}</h1>
                <div class="a851-product-meta">@if($productModel->sku)<span>SKU: {{ $productModel->sku }}</span>@endif<span>{{ $inStock ? __('Còn hàng') : __('Hết hàng') }}</span></div>
                <div class="a851-product-price">{{ $price > 0 ? number_format($price, 0, ',', '.').'đ' : __('Liên hệ') }}</div>
                @if((float) $productModel->original_price > $price && $price > 0)<del class="a851-product-old-price">{{ number_format((float) $productModel->original_price, 0, ',', '.') }}đ</del>@endif
                @if($productModel->short_description)<p class="a851-product-summary">{{ $productModel->short_description }}</p>@endif
                @if($productHighlights)<ul class="a851-product-highlights">@foreach($productHighlights as $highlight)<li>{{ $highlight }}</li>@endforeach</ul>@endif
                <form class="a851-product-buy" method="POST" action="{{ route('site.cart.add', ['locale' => app()->getLocale(), 'slug' => $productModel->slug]) }}">
                    @csrf
                    <label for="a851-quantity">{{ __('Số lượng') }}</label>
                    <div><input id="a851-quantity" type="number" name="quantity" value="1" min="1" max="{{ max(1, (int) $productModel->stock) }}" required @disabled(!$inStock)><button class="a851-button" @disabled(!$inStock)>{{ __('Thêm vào giỏ hàng') }} <i class="fa-solid fa-cart-plus" aria-hidden="true"></i></button></div>
                </form>
            </div>
        </section>
        @if(filled($productModel->detail_content))<section class="a851-product-description"><h2>{{ __('Thông tin sản phẩm') }}</h2><div class="a851-prose">{!! $productModel->detail_content !!}</div></section>@endif
        @foreach([['title' => __('Sản phẩm liên quan'), 'items' => $relatedProducts ?? []], ['title' => __('Sản phẩm mới nhất'), 'items' => $latestProducts ?? []]] as $group)
            @if(count($group['items']))<section class="a851-product-recommendations"><header><h2>{{ $group['title'] }}</h2><a href="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}">@themeT('view_all', 'Xem tất cả') <span aria-hidden="true">→</span></a></header><div class="a851-listing-grid">@foreach(array_slice($group['items'], 0, 4) as $item)@include('theme-auto851::partials.product-card', ['item' => $item])@endforeach</div></section>@endif
        @endforeach
    </div>
</main>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-a851-photo]').forEach(button => button.addEventListener('click', () => {
    document.getElementById('a851-product-image').src = button.dataset.a851Photo;
    document.getElementById('a851-product-image').alt = button.getAttribute('aria-label');
    document.querySelectorAll('[data-a851-photo]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
}));
</script>
@endpush
