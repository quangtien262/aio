@extends('theme-dl750::layout')
@section('title', data_get($product ?? [], 'title', ''))
@section('content')
@php
    $title = data_get($product ?? [], 'title', data_get($productModel ?? null, 'name', ''));
    $price = (float) data_get($product ?? [], 'price', 0);
    $oldPrice = (float) data_get($product ?? [], 'old_price', data_get($productModel ?? null, 'original_price', 0));
    $gallery = collect($productGallery ?? [])->filter(fn ($image) => filled(data_get($image, 'url')))->values();
    if ($gallery->isEmpty() && data_get($product ?? [], 'image')) {
        $gallery->push(['url' => $product['image'], 'alt' => $title]);
    }
    $sku = data_get($product ?? [], 'sku', data_get($productModel ?? null, 'sku'));
    $description = data_get($productModel ?? null, 'detail_content');
@endphp
@include('theme-dl750::partials.product-styles')
<main class="dl-inner dl-pdp">
    <div class="dl-wrap">
        <nav class="dl-pdp-breadcrumb" aria-label="@themeT('product.breadcrumb', 'Điều hướng')">
            <a href="{{ route('site.home') }}">@themeT('home', 'Trang chủ')</a><span aria-hidden="true">/</span>
            <a href="{{ route('site.catalog.search') }}">@themeT('products', 'Sản phẩm')</a><span aria-hidden="true">/</span>
            <span aria-current="page">{{ $title }}</span>
        </nav>
        @if(session('cart_success'))
            <div class="dl-pdp-notice" role="status">{{ session('cart_success') }} <a href="{{ route('site.cart.index') }}">@themeT('cart', 'Giỏ hàng') &rarr;</a></div>
        @endif
        @if(isset($errors) && $errors->any())<div class="dl-pdp-notice" role="alert">{{ $errors->first() }}</div>@endif
        <div class="dl-pdp-overview">
            <div class="dl-pdp-gallery" data-dl-gallery>
                <div class="dl-pdp-photo">
                    @if($gallery->isNotEmpty())
                        <a href="{{ $gallery[0]['url'] }}" data-dl-full-image target="_blank" rel="noopener" aria-label="@themeT('product.full_image', 'Mở ảnh kích thước lớn')">
                            <img data-dl-main-image src="{{ $gallery[0]['url'] }}" alt="{{ $gallery[0]['alt'] ?? $title }}" fetchpriority="high" width="720" height="720">
                        </a>
                    @else
                        <span class="dl-pdp-no-image"><i class="fa-solid fa-mountain-sun" aria-hidden="true"></i>@themeT('product.no_image', 'Hình ảnh đang cập nhật')</span>
                    @endif
                    @if($price > 0 && $oldPrice > $price)<span class="dl-pdp-discount">-{{ (int) round(($oldPrice - $price) / $oldPrice * 100) }}%</span>@endif
                </div>
                @if($gallery->count() > 1)
                    <div class="dl-pdp-thumbnails" aria-label="@themeT('product.gallery', 'Hình ảnh sản phẩm')">
                        @foreach($gallery as $image)
                            <button type="button" data-dl-thumbnail aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="{{ $image['alt'] ?? $title }}">
                                <img src="{{ $image['url'] }}" alt="{{ $image['alt'] ?? $title }}" width="88" height="88" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
            <section class="dl-pdp-summary" aria-labelledby="dl-product-title">
                <p class="dl-pdp-eyebrow">@themeT('product.eyebrow', 'Sẵn sàng cho hành trình mới')</p>
                @if(data_get($product ?? [], 'tag'))<span class="dl-pdp-category">{{ $product['tag'] }}</span>@endif
                <h1 id="dl-product-title">{{ $title }}</h1>
                @if($sku)<p class="dl-pdp-sku">@themeT('product.sku', 'Mã sản phẩm'): <strong>{{ $sku }}</strong></p>@endif
                <div class="dl-pdp-price">
                    <strong>@if($price > 0){{ number_format($price, 0, ',', '.') }}đ @else @themeT('product.contact_price', 'Liên hệ báo giá') @endif</strong>
                    @if($price > 0 && $oldPrice > $price)<del>{{ number_format($oldPrice, 0, ',', '.') }}đ</del>@endif
                </div>
                @if(data_get($product ?? [], 'summary'))<div class="dl-pdp-intro dl-prose">{!! $product['summary'] !!}</div>@endif
                @if(!empty($productHighlights))
                    <ul class="dl-pdp-highlights">@foreach($productHighlights as $highlight)<li><i class="fa-solid fa-check" aria-hidden="true"></i><span>{{ $highlight }}</span></li>@endforeach</ul>
                @endif
                <form class="dl-pdp-purchase" method="post" action="{{ route('site.cart.add', ['slug' => data_get($productModel ?? null, 'slug')]) }}">
                    @csrf
                    <label for="dl-product-quantity">@themeT('quantity', 'Số lượng')</label>
                    <div class="dl-pdp-buy-row">
                        <div class="dl-pdp-quantity" data-dl-quantity>
                            <button type="button" data-dl-step="-1" aria-label="@themeT('product.decrease', 'Giảm số lượng')">&minus;</button>
                            <input id="dl-product-quantity" type="number" name="quantity" value="1" min="1" max="99" step="1" inputmode="numeric" required>
                            <button type="button" data-dl-step="1" aria-label="@themeT('product.increase', 'Tăng số lượng')">+</button>
                        </div>
                        <button class="dl-primary" type="submit"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>@themeT('add_to_cart', 'Thêm vào giỏ')</button>
                    </div>
                </form>
                <a class="dl-pdp-consult" href="{{ route('site.contact') }}"><i class="fa-regular fa-comments" aria-hidden="true"></i>@themeT('product.consult', 'Cần tư vấn? Trao đổi cùng chúng tôi')<span aria-hidden="true">&rarr;</span></a>
                <p class="dl-pdp-help">@themeT('product.help', 'Liên hệ để được tư vấn lựa chọn sản phẩm và thông tin giao nhận phù hợp với chuyến đi.')</p>
            </section>
        </div>
        <div class="dl-pdp-information">
            <section class="dl-pdp-description" aria-labelledby="dl-product-description">
                <p class="dl-pdp-eyebrow">@themeT('product.discover', 'Tìm hiểu sản phẩm')</p>
                <h2 id="dl-product-description">@themeT('product.description', 'Thông tin chi tiết')</h2>
                <div class="dl-prose">{!! $description ?: data_get($product ?? [], 'summary', '') !!}</div>
                @if(!$description && !data_get($product ?? [], 'summary'))<p>@themeT('product.description_empty', 'Liên hệ với chúng tôi để biết thêm thông tin về sản phẩm.')</p>@endif
            </section>
            <aside class="dl-pdp-advice">
                <i class="fa-solid fa-compass" aria-hidden="true"></i>
                <h2>@themeT('product.advice_title', 'Chọn đúng đồ, trọn chuyến đi')</h2>
                <p>@themeT('product.advice_text', 'Chia sẻ điểm đến, thời gian và nhu cầu của bạn. Chúng tôi sẽ giúp bạn tìm trang bị phù hợp.')</p>
                <a href="{{ route('site.contact') }}">@themeT('product.advice_cta', 'Nhận tư vấn sản phẩm') <span aria-hidden="true">&rarr;</span></a>
                @if(!empty($usageTerms))<details><summary>@themeT('product.terms', 'Lưu ý sử dụng')</summary><ul>@foreach($usageTerms as $term)<li>{{ $term }}</li>@endforeach</ul></details>@endif
            </aside>
        </div>
        @if(!empty($relatedProducts))
            <section class="dl-pdp-related" aria-labelledby="dl-related-title">
                <div class="dl-pdp-section-head"><div><p class="dl-pdp-eyebrow">@themeT('product.explore', 'Thêm lựa chọn cho bạn')</p><h2 id="dl-related-title">@themeT('product.related', 'Sản phẩm liên quan')</h2></div><a href="{{ route('site.catalog.search') }}">@themeT('product.view_all', 'Xem tất cả sản phẩm') &rarr;</a></div>
                <div class="dl-catalog-grid">@foreach(array_slice($relatedProducts, 0, 4) as $item)@include('theme-dl750::partials.product-card', ['item' => $item])@endforeach</div>
            </section>
        @endif
    </div>
</main>

@include('themes.common.product-recommendations', ['showRelatedRecommendations' => false])
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-dl-gallery]').forEach(gallery => {
    gallery.querySelectorAll('[data-dl-thumbnail]').forEach(button => button.addEventListener('click', () => {
        const thumbnail = button.querySelector('img');
        const main = gallery.querySelector('[data-dl-main-image]');
        main.src = thumbnail.src;
        main.alt = thumbnail.alt;
        gallery.querySelector('[data-dl-full-image]').href = thumbnail.src;
        gallery.querySelectorAll('[data-dl-thumbnail]').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    }));
});
document.querySelectorAll('[data-dl-quantity]').forEach(control => {
    const input = control.querySelector('input');
    control.querySelectorAll('[data-dl-step]').forEach(button => button.addEventListener('click', () => {
        const value = Number(input.value);
        input.value = Math.max(Number(input.min), Math.min(Number(input.max), (Number.isFinite(value) ? Math.trunc(value) : 1) + Number(button.dataset.dlStep)));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
});
</script>
@endpush
