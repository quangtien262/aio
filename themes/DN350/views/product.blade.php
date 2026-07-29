@php
    $title = data_get($product ?? null, 'title', data_get($productModel ?? null, 'name', 'Sản phẩm'));
    $gallery = $productGallery ?? [];
    $primaryImage = data_get($gallery, '0.url', data_get($product ?? null, 'image', data_get($productModel ?? null, 'image_url')));
    $summary = trim((string) data_get($productModel ?? null, 'short_description', data_get($product ?? null, 'summary', '')));
    $detailHtml = trim((string) data_get($productModel ?? null, 'detail_content', ''));
    $highlights = $productHighlights ?? [];
    $usageTermsList = $usageTerms ?? [];
    $usageLocations = $usageLocationLines ?? [];
    $related = collect($relatedProducts ?? [])->take(4);
    $price = (float) data_get($product ?? null, 'price', 0);
    $oldPrice = data_get($product ?? null, 'old_price');
    $discount = (int) data_get($product ?? null, 'discount', 0);
    $stock = data_get($product ?? null, 'meta');
    $isOutOfStock = $stock !== null && (int) $stock <= 0;
    $category = data_get($productModel ?? null, 'category');
    $formatPrice = fn ($value) => $value === null || (float) $value <= 0
        ? 'Liên hệ báo giá'
        : number_format((float) $value, 0, ',', '.').'đ';
    $seoTitle = trim((string) data_get($productModel ?? null, 'meta_title')) ?: $title;
    $seoDescription = trim((string) data_get($productModel ?? null, 'meta_description')) ?: $summary;
    $seoKeywords = trim((string) data_get($productModel ?? null, 'meta_keywords'));

    if ($detailHtml === '') {
        $detailHtml = '<p>'.e($summary !== '' ? $summary : 'Thông tin chi tiết sản phẩm đang được cập nhật.').'</p>';
    }

    if ($highlights === [] && $summary !== '') {
        $highlights = [$summary];
    }
@endphp

@extends('theme-dn350::layout')

@section('title', $seoTitle)

@push('head')
    @if ($seoDescription !== '')
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    @if ($seoKeywords !== '')
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
    <style>
        .dn-product-page{background:var(--dn-cream);color:var(--dn-ink)}
        .dn-product-masthead{padding:270px 0 105px;background:linear-gradient(135deg,var(--dn-navy-deep),var(--dn-navy));color:#fff}
        .dn-product-breadcrumb{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:25px;color:rgba(255,255,255,.62);font-size:14px;font-weight:700}
        .dn-product-breadcrumb a{color:rgba(255,255,255,.78)}.dn-product-breadcrumb a:hover{color:var(--dn-champagne)}
        .dn-product-masthead__eyebrow{margin:0 0 13px;color:var(--dn-champagne);font-size:13px;font-weight:800;letter-spacing:.18em;text-transform:uppercase}
        .dn-product-masthead h1{max-width:1000px;margin:0;color:#fff;font:700 clamp(38px,5vw,66px)/1.07 var(--dn-display);letter-spacing:-.035em}
        .dn-product-main{padding:0 0 100px}.dn-product-main>.dn-container{position:relative;margin-top:-56px}
        .dn-product-alert{margin-bottom:22px;padding:15px 18px;border-left:5px solid var(--dn-champagne);background:#fff;box-shadow:var(--dn-shadow);font-weight:750}.dn-product-alert.is-error{border-color:#b93636;color:#9c2929}
        .dn-product-top{display:grid;grid-template-columns:minmax(0,1.12fr) minmax(390px,.88fr);gap:34px;align-items:start}
        .dn-product-gallery,.dn-product-info,.dn-product-panel{background:#fff;box-shadow:var(--dn-shadow)}
        .dn-product-gallery{padding:18px}.dn-product-stage{position:relative;height:600px;overflow:hidden;background:#edf0f4}
        .dn-product-stage img{width:100%;height:100%;object-fit:cover;transition:opacity .22s ease,transform .55s ease}.dn-product-stage.is-switching img{opacity:.3;transform:scale(1.015)}
        .dn-product-stage__count{position:absolute;right:18px;bottom:18px;padding:8px 13px;background:rgba(47,59,88,.88);color:#fff;font-size:12px;font-weight:800;letter-spacing:.06em}
        .dn-product-gallery-nav{position:absolute;z-index:2;top:50%;width:46px;height:46px;display:grid;place-items:center;border:0;border-radius:50%;background:rgba(255,255,255,.92);color:var(--dn-navy);box-shadow:0 10px 25px rgba(25,35,58,.18);cursor:pointer;transform:translateY(-50%);transition:.2s}.dn-product-gallery-nav:hover{background:var(--dn-champagne);transform:translateY(-50%) scale(1.05)}.dn-product-gallery-nav.prev{left:16px}.dn-product-gallery-nav.next{right:16px}
        .dn-product-thumbs{display:flex;gap:12px;overflow-x:auto;padding-top:14px;scrollbar-width:thin}.dn-product-thumb{flex:0 0 100px;width:100px;height:78px;padding:3px;border:2px solid transparent;background:#edf0f4;cursor:pointer;transition:.2s}.dn-product-thumb:hover,.dn-product-thumb.is-active{border-color:var(--dn-champagne);transform:translateY(-2px)}.dn-product-thumb img{width:100%;height:100%;object-fit:cover}
        .dn-product-info{padding:42px}.dn-product-category{display:inline-flex;align-items:center;min-height:34px;margin-bottom:18px;padding:0 13px;background:var(--dn-champagne);color:var(--dn-navy);font-size:12px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}
        .dn-product-info h2{margin:0 0 18px;color:var(--dn-navy);font:700 clamp(31px,3vw,45px)/1.12 var(--dn-display);letter-spacing:-.035em}
        .dn-product-summary{margin:0 0 22px;color:var(--dn-muted);font-size:17px;line-height:1.75}
        .dn-product-chips{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px}.dn-product-chip{padding:8px 11px;border:1px solid #e1e5eb;color:var(--dn-muted);font-size:12px;font-weight:800;text-transform:uppercase}
        .dn-product-pricebox{margin:0 0 25px;padding:23px 0;border-top:1px solid #e2e5eb;border-bottom:1px solid #e2e5eb}.dn-product-prices{display:flex;align-items:center;flex-wrap:wrap;gap:12px}.dn-product-price{color:var(--dn-navy);font-size:37px;font-weight:850;letter-spacing:-.035em}.dn-product-old-price{color:#9ba3b2;font-size:20px;text-decoration:line-through}.dn-product-discount{padding:6px 9px;background:var(--dn-navy);color:#fff;font-size:12px;font-weight:850}
        .dn-product-actions{display:flex;flex-wrap:wrap;gap:10px}.dn-product-action{min-height:52px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 20px;border:0;background:var(--dn-navy);color:#fff;font:800 13px var(--dn-body);letter-spacing:.035em;text-transform:uppercase;cursor:pointer;transition:.22s}.dn-product-action:hover{background:var(--dn-navy-deep);color:#fff;transform:translateY(-2px)}.dn-product-action.is-primary{background:var(--dn-champagne);color:var(--dn-navy)}.dn-product-action.is-outline{border:1px solid #d9dee6;background:#fff;color:var(--dn-navy)}.dn-product-action[disabled]{opacity:.55;cursor:not-allowed;transform:none}
        .dn-product-note{margin:16px 0 0;color:var(--dn-muted);font-size:13px;line-height:1.6}.dn-product-note i{margin-right:7px;color:var(--dn-champagne)}
        .dn-product-benefits{display:grid;grid-template-columns:repeat(3,1fr);gap:1px;margin-top:28px;background:#e3e6eb}.dn-product-benefit{min-height:105px;display:grid;grid-template-columns:42px 1fr;align-items:center;gap:12px;padding:18px;background:#fff}.dn-product-benefit i{font-size:24px;color:var(--dn-champagne)}.dn-product-benefit strong,.dn-product-benefit span{display:block}.dn-product-benefit strong{color:var(--dn-navy);font-size:14px}.dn-product-benefit span{margin-top:3px;color:var(--dn-muted);font-size:12px;line-height:1.45}
        .dn-product-content{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:30px;margin-top:38px}.dn-product-panel{padding:38px}.dn-product-panel h2,.dn-product-panel h3{margin:0 0 22px;color:var(--dn-navy);font:700 30px/1.2 var(--dn-display)}
        .dn-product-rich{color:#536078;font-size:17px;line-height:1.8}.dn-product-rich>*:first-child{margin-top:0}.dn-product-rich img{max-width:100%;height:auto;margin:16px 0;box-shadow:0 16px 38px rgba(31,43,68,.13)}
        .dn-product-side{display:grid;gap:20px}.dn-product-list{display:grid;gap:12px;margin:0;padding:0;list-style:none}.dn-product-list li{position:relative;padding-left:24px;color:#59657b;line-height:1.55}.dn-product-list li::before{content:"";position:absolute;left:0;top:.62em;width:9px;height:9px;background:var(--dn-champagne)}
        .dn-product-photo-section,.dn-product-related-section{margin-top:70px}.dn-product-section-head{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:30px}.dn-product-section-head p{margin:0 0 8px;color:#9a7a42;font-size:12px;font-weight:850;letter-spacing:.14em;text-transform:uppercase}.dn-product-section-head h2{margin:0;color:var(--dn-navy);font:700 38px var(--dn-display)}
        .dn-product-photo-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.dn-product-photo-grid figure{margin:0;overflow:hidden;background:#fff;box-shadow:var(--dn-shadow)}.dn-product-photo-grid img{width:100%;height:300px;object-fit:cover;transition:transform .5s}.dn-product-photo-grid figure:hover img{transform:scale(1.045)}.dn-product-photo-grid figcaption{padding:14px 17px;color:var(--dn-muted);font-size:13px;font-weight:700}
        .dn-product-related{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:20px}.dn-related-product{overflow:hidden;background:#fff;box-shadow:var(--dn-shadow);transition:.25s}.dn-related-product:hover{transform:translateY(-6px)}.dn-related-product img{width:100%;height:220px;object-fit:cover}.dn-related-product__body{padding:18px}.dn-related-product__body span{color:#9a7a42;font-size:11px;font-weight:850;text-transform:uppercase}.dn-related-product__body h3{margin:8px 0 12px;color:var(--dn-navy);font:700 18px/1.35 var(--dn-display)}.dn-related-product__price{color:var(--dn-navy);font-size:19px;font-weight:850}
        @media(max-width:1100px){.dn-product-top,.dn-product-content{grid-template-columns:1fr}.dn-product-stage{height:560px}.dn-product-related{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:760px){.dn-product-masthead{padding:115px 0 80px}.dn-product-main>.dn-container{margin-top:-36px}.dn-product-stage{height:390px}.dn-product-info,.dn-product-panel{padding:25px}.dn-product-benefits{grid-template-columns:1fr}.dn-product-photo-grid,.dn-product-related{grid-template-columns:1fr}.dn-product-photo-grid img{height:260px}.dn-product-actions{display:grid}.dn-product-action{width:100%}.dn-product-section-head h2{font-size:31px}}
    </style>
@endpush

@section('content')
    <main class="dn-product-page">
        <section class="dn-product-masthead">
            <div class="dn-container">
                <nav class="dn-product-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('site.home') }}">Trang chủ</a><span>/</span>
                    @if ($category)
                        <a href="{{ route('site.catalog.category', ['slug' => $category->slug]) }}">{{ $category->name }}</a><span>/</span>
                    @else
                        <a href="{{ route('site.catalog.search') }}">Sản phẩm</a><span>/</span>
                    @endif
                    <span>{{ $title }}</span>
                </nav>
                <p class="dn-product-masthead__eyebrow">Chi tiết sản phẩm</p>
                <h1>{{ $title }}</h1>
            </div>
        </section>

        <section class="dn-product-main">
            <div class="dn-container">
                @if (session('cart_success'))<div class="dn-product-alert">{{ session('cart_success') }}</div>@endif
                @if (session('cart_error') || $errors->has('cart'))<div class="dn-product-alert is-error">{{ session('cart_error') ?: $errors->first('cart') }}</div>@endif

                <div class="dn-product-top">
                    <section class="dn-product-gallery" data-dn-product-gallery>
                        <div class="dn-product-stage" data-dn-product-stage>
                            <img data-dn-product-main src="{{ $primaryImage }}" alt="{{ $title }}">
                            @if (count($gallery) > 1)
                                <button class="dn-product-gallery-nav prev" type="button" data-dn-product-prev aria-label="Ảnh trước"><i class="fa-solid fa-chevron-left"></i></button>
                                <button class="dn-product-gallery-nav next" type="button" data-dn-product-next aria-label="Ảnh tiếp theo"><i class="fa-solid fa-chevron-right"></i></button>
                            @endif
                            <span class="dn-product-stage__count"><b data-dn-product-index>1</b> / {{ max(count($gallery), 1) }}</span>
                        </div>
                        @if (count($gallery) > 1)
                            <div class="dn-product-thumbs" aria-label="Thư viện ảnh sản phẩm">
                                @foreach ($gallery as $index => $image)
                                    <button type="button" class="dn-product-thumb {{ $index === 0 ? 'is-active' : '' }}" data-dn-product-thumb data-index="{{ $index }}" data-url="{{ $image['url'] }}" data-alt="{{ $image['alt'] }}" aria-label="Xem ảnh {{ $index + 1 }}">
                                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <article class="dn-product-info">
                        <span class="dn-product-category">{{ data_get($category, 'name', 'Sản phẩm DN350') }}</span>
                        <h2>{{ $title }}</h2>
                        <p class="dn-product-summary">{{ $summary !== '' ? $summary : 'Giải pháp được tư vấn theo nhu cầu thực tế và tiêu chuẩn kỹ thuật của từng công trình.' }}</p>
                        <div class="dn-product-chips">
                            @if (data_get($productModel ?? null, 'sku'))<span class="dn-product-chip">Mã: {{ $productModel->sku }}</span>@endif
                            @if ($stock !== null)<span class="dn-product-chip">{{ $isOutOfStock ? 'Tạm hết hàng' : 'Còn '.number_format((int) $stock).' sản phẩm' }}</span>@endif
                            @if (data_get($product ?? null, 'sold_count'))<span class="dn-product-chip">Đã bán {{ number_format((int) data_get($product, 'sold_count')) }}</span>@endif
                        </div>
                        <div class="dn-product-pricebox">
                            <div class="dn-product-prices">
                                <strong class="dn-product-price">{{ $formatPrice($price) }}</strong>
                                @if ($oldPrice !== null && (float) $oldPrice > $price)<span class="dn-product-old-price">{{ $formatPrice($oldPrice) }}</span>@endif
                                @if ($discount > 0)<span class="dn-product-discount">Tiết kiệm {{ $discount }}%</span>@endif
                            </div>
                        </div>

                        @if ($price > 0 && ! $isOutOfStock)
                            <form method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}">
                                @csrf
                                <input type="hidden" name="quantity" value="1">
                                <div class="dn-product-actions">
                                    <button class="dn-product-action is-primary" type="submit" formaction="{{ route('site.cart.buy_now', ['slug' => $productModel->slug]) }}"><i class="fa-solid fa-bolt"></i>Mua ngay</button>
                                    <button class="dn-product-action" type="submit"><i class="fa-solid fa-cart-plus"></i>Thêm vào giỏ</button>
                                    <button class="dn-product-action is-outline" type="button" data-dn-consult-open><i class="fa-regular fa-comments"></i>Tư vấn</button>
                                </div>
                            </form>
                        @else
                            <div class="dn-product-actions">
                                <button class="dn-product-action is-primary" type="button" data-dn-consult-open><i class="fa-regular fa-comments"></i>Nhận tư vấn & báo giá</button>
                                <a class="dn-product-action is-outline" href="{{ route('site.contact') }}"><i class="fa-solid fa-arrow-right"></i>Gửi yêu cầu</a>
                            </div>
                        @endif
                        <p class="dn-product-note"><i class="fa-solid fa-shield-halved"></i>Thông tin và báo giá được xác nhận lại bởi đội ngũ tư vấn trước khi triển khai.</p>

                        <div class="dn-product-benefits">
                            <div class="dn-product-benefit"><i class="fa-solid fa-ruler-combined"></i><div><strong>Tư vấn đúng nhu cầu</strong><span>Khảo sát và đề xuất giải pháp phù hợp.</span></div></div>
                            <div class="dn-product-benefit"><i class="fa-solid fa-file-signature"></i><div><strong>Báo giá minh bạch</strong><span>Hạng mục và chi phí được trình bày rõ.</span></div></div>
                            <div class="dn-product-benefit"><i class="fa-solid fa-headset"></i><div><strong>Hỗ trợ chuyên nghiệp</strong><span>Đồng hành trong suốt quá trình sử dụng.</span></div></div>
                        </div>
                    </article>
                </div>

                <div class="dn-product-content">
                    <article class="dn-product-panel">
                        <h2>Thông tin chi tiết</h2>
                        <div class="dn-product-rich">{!! $detailHtml !!}</div>
                    </article>
                    <aside class="dn-product-side">
                        <section class="dn-product-panel">
                            <h3>Điểm nổi bật</h3>
                            <ul class="dn-product-list">
                                @forelse ($highlights as $item)<li>{{ $item }}</li>@empty<li>Giải pháp được thiết kế linh hoạt theo nhu cầu thực tế.</li>@endforelse
                            </ul>
                        </section>
                        @if ($usageTermsList !== [])
                            <section class="dn-product-panel"><h3>Điều kiện sử dụng</h3><ul class="dn-product-list">@foreach ($usageTermsList as $item)<li>{{ $item }}</li>@endforeach</ul></section>
                        @endif
                        @if ($usageLocations !== [])
                            <section class="dn-product-panel"><h3>Khu vực áp dụng</h3><ul class="dn-product-list">@foreach ($usageLocations as $item)<li>{{ $item }}</li>@endforeach</ul></section>
                        @endif
                    </aside>
                </div>

                @if (count($gallery) > 1)
                    <section class="dn-product-photo-section">
                        <header class="dn-product-section-head"><div><p>Thư viện</p><h2>Hình ảnh sản phẩm</h2></div></header>
                        <div class="dn-product-photo-grid">
                            @foreach ($gallery as $index => $image)
                                <figure><img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" loading="lazy"><figcaption>{{ $title }} · Hình {{ $index + 1 }}</figcaption></figure>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($related->isNotEmpty())
                    <section class="dn-product-related-section">
                        <header class="dn-product-section-head"><div><p>Gợi ý thêm</p><h2>Sản phẩm liên quan</h2></div><a class="dn-btn" href="{{ route('site.catalog.search') }}">Xem tất cả</a></header>
                        <div class="dn-product-related">
                            @foreach ($related as $item)
                                <article class="dn-related-product"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title', 'Sản phẩm') }}" loading="lazy"><div class="dn-related-product__body"><span>{{ data_get($item, 'tag', 'Sản phẩm') }}</span><h3>{{ data_get($item, 'title', 'Sản phẩm') }}</h3><strong class="dn-related-product__price">{{ $formatPrice(data_get($item, 'price')) }}</strong></div></a></article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </section>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const gallery = document.querySelector('[data-dn-product-gallery]');
            if (!gallery) return;
            const main = gallery.querySelector('[data-dn-product-main]');
            const stage = gallery.querySelector('[data-dn-product-stage]');
            const thumbs = Array.from(gallery.querySelectorAll('[data-dn-product-thumb]'));
            const counter = gallery.querySelector('[data-dn-product-index]');
            let active = 0;
            const show = (index) => {
                if (!main || !thumbs.length) return;
                active = (index + thumbs.length) % thumbs.length;
                const thumb = thumbs[active];
                stage?.classList.add('is-switching');
                window.setTimeout(() => {
                    main.src = thumb.dataset.url || main.src;
                    main.alt = thumb.dataset.alt || main.alt;
                    thumbs.forEach((item, itemIndex) => item.classList.toggle('is-active', itemIndex === active));
                    if (counter) counter.textContent = String(active + 1);
                    thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    stage?.classList.remove('is-switching');
                }, 120);
            };
            thumbs.forEach((thumb, index) => thumb.addEventListener('click', () => show(index)));
            gallery.querySelector('[data-dn-product-prev]')?.addEventListener('click', () => show(active - 1));
            gallery.querySelector('[data-dn-product-next]')?.addEventListener('click', () => show(active + 1));
        })();
    </script>
@endpush
