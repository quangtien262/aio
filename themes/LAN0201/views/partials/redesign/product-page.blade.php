@php
    $bodyCopy = collect($detailParagraphsList ?? [])->filter()->values();
    $relatedItems = collect($relatedProducts ?? [])->take(3);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $product['title'] }} | {{ data_get($branding, 'company_name', 'LAN0201') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>
            @include('theme-lan0201::partials.landing-style', ['branding' => $branding])
            .th-product-gallery { display:grid; gap:14px; }
            .th-product-gallery-strip { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; }
            .th-product-gallery-strip img { width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:18px; border:1px solid var(--th-landing-line); }
            .th-product-cta-form { display:grid; gap:14px; }
            .th-product-list { margin:0; padding-left:18px; }
        </style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', ['branding' => $branding, 'topMenu' => $topMenu, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'cartSummary' => $cartSummary, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation])

                <main class="th-landing-main">
                    <div class="th-landing-container">
                        <div class="th-landing-breadcrumb">
                            <a href="{{ route('site.home') }}">Trang chủ</a><span>/</span>
                            @if ($productModel->category?->parent)
                                <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->parent->slug]) }}">{{ $productModel->category->parent->name }}</a><span>/</span>
                            @endif
                            @if ($productModel->category)
                                <a href="{{ route('site.catalog.category', ['slug' => $productModel->category->slug]) }}">{{ $productModel->category->name }}</a><span>/</span>
                            @endif
                            <span>{{ $product['title'] }}</span>
                        </div>

                        <section class="th-landing-hero">
                            <div class="th-landing-hero-grid">
                                <div class="th-product-gallery">
                                    <div class="th-landing-media" style="min-height:520px;"><img src="{{ $primaryImage }}" alt="{{ $product['title'] }}"></div>
                                    <div class="th-product-gallery-strip">
                                        @foreach (collect($gallery)->take(4) as $galleryItem)
                                            <img src="{{ $galleryItem['url'] ?? $galleryItem['image'] ?? $primaryImage }}" alt="{{ $product['title'] }}">
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <span class="th-landing-kicker">{{ $product['tag'] ?? 'Bảng hàng mở bán' }}</span>
                                    <h1 class="th-landing-title">{{ $product['title'] }}</h1>
                                    <p class="th-landing-summary">{{ $product['summary'] ?? ($productModel->short_description ?: 'Trang chi tiết được chuyển thành một landing detail page: hero, snapshot, thông tin quy trình và CTA lead.') }}</p>
                                    <div class="th-landing-price" style="margin:18px 0;">
                                        <strong>{{ $formatCurrency($product['price'] ?? null) }}</strong>
                                        @if (!empty($product['original_price']))<span>{{ $formatCurrency($product['original_price']) }}</span>@endif
                                        @if ($discount > 0)<span class="th-landing-chip">-{{ $discount }}%</span>@endif
                                    </div>
                                    <div class="th-landing-meta-row">
                                        @if (!empty($product['sku']))<span class="th-landing-chip">{{ $product['sku'] }}</span>@endif
                                        @if ($soldCount > 0)<span class="th-landing-pill">{{ number_format($soldCount, 0, ',', '.') }} lead</span>@endif
                                        @if ($deadline)<span class="th-landing-tag">{{ __('product.deal_ends') }} {{ $productModel->deal_end_at?->format('d/m/Y') }}</span>@endif
                                    </div>
                                    <div class="th-landing-panel" style="margin-top:20px;">
                                        <form method="POST" action="{{ route('site.cart.add', ['slug' => $productModel->slug]) }}" class="th-product-cta-form">
                                            @csrf
                                            <div class="th-landing-field">
                                                <label for="quantity">Số lượng quan tâm</label>
                                                <select id="quantity" name="quantity">
                                                    @for ($quantity = 1; $quantity <= $maxPurchaseQuantity; $quantity++)
                                                        <option value="{{ $quantity }}">{{ $quantity }}</option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div class="th-landing-actions-row">
                                                <button type="submit" class="th-landing-button">Thêm vào danh sách quan tâm</button>
                                                <button type="submit" class="th-landing-outline" formaction="{{ route('site.cart.buy_now', ['slug' => $productModel->slug]) }}">Gửi yêu cầu ngay</button>
                                                @if (!empty($customerAuth['is_authenticated']))
                                                    <button type="submit" class="th-landing-ghost" formaction="{{ route('site.favorite.toggle', ['product' => $productModel->slug]) }}">{{ !empty($isFavorite) ? __('product.favorite_saved') : __('product.favorite_save') }}</button>
                                                @else
                                                    <button type="button" class="th-landing-ghost" data-open-auth-modal="login">Đăng nhập để lưu yêu thích</button>
                                                @endif
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section style="margin-top:26px;" class="th-landing-two-col">
                            <div class="th-landing-panel">
                                <span class="th-landing-kicker">Snapshot</span>
                                <h2 class="th-landing-section-title">Thông tin căn và lead context</h2>
                                <div class="th-landing-stats">
                                    @foreach ($productionSnapshot as $snapshot)
                                        <div class="th-landing-stat"><strong>{{ $snapshot['value'] }}</strong><span>{{ $snapshot['label'] }}</span></div>
                                    @endforeach
                                </div>
                                @if (!empty($highlights))
                                    <ul class="th-landing-copy th-product-list" style="margin-top:16px;">
                                        @foreach ($highlights as $highlight)
                                            <li>{{ $highlight }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="th-landing-panel">
                                <span class="th-landing-kicker">Quy trinh sales</span>
                                <h2 class="th-landing-section-title">Đặt lịch, giữ chỗ và chốt giao dịch</h2>
                                <div class="th-landing-copy">
                                    @foreach ($orderGuideSteps as $guideStep)
                                        <p><strong>{{ $guideStep['step'] }}. {{ $guideStep['title'] }}</strong><br>{{ $guideStep['body'] }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        <section style="margin-top:26px;" class="th-landing-two-col">
                            <article class="th-landing-panel">
                                <span class="th-landing-kicker">Mô tả chi tiết</span>
                                <h2 class="th-landing-section-title">Landing detail block</h2>
                                <div class="th-landing-copy">
                                    @foreach ($bodyCopy as $paragraph)
                                        <p>{{ $paragraph }}</p>
                                    @endforeach
                                </div>
                            </article>
                            <aside class="th-landing-panel">
                                <span class="th-landing-kicker">Thông tin tham chiếu</span>
                                <h2 class="th-landing-section-title">Vị trí và điều kiện giao dịch</h2>
                                @if (!empty($usageLocationPreview))
                                    <ul class="th-landing-copy th-product-list">
                                        @foreach ($usageLocationPreview as $usageLocationItem)
                                            <li>{{ $usageLocationItem }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if (!empty($usageTermHighlights))
                                    <div class="th-landing-divider" style="margin:18px 0;"></div>
                                    <ul class="th-landing-copy th-product-list">
                                        @foreach ($usageTermHighlights as $usageTerm)
                                            <li>{{ $usageTerm }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </aside>
                        </section>

                        @if ($relatedItems->isNotEmpty())
                            <section style="margin-top:26px;">
                                <div class="th-landing-panel">
                                    <span class="th-landing-kicker">Căn liên quan</span>
                                    <h2 class="th-landing-section-title">Bảng hàng cùng phân khúc</h2>
                                    <div class="th-landing-grid-listings">
                                        @foreach ($relatedItems as $related)
                                            <article class="th-landing-listing">
                                                <a href="{{ $related['url'] ?? '#' }}">
                                                    <div class="th-landing-listing-media"><img src="{{ $related['image'] ?? $primaryImage }}" alt="{{ $related['title'] ?? 'Listing' }}"></div>
                                                </a>
                                                <div class="th-landing-listing-body">
                                                    <h3 class="th-landing-listing-title"><a href="{{ $related['url'] ?? '#' }}">{{ $related['title'] ?? 'Sản phẩm liên quan' }}</a></h3>
                                                    <div class="th-landing-price"><strong>{{ $formatCurrency($related['price'] ?? null) }}</strong></div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            </section>
                        @endif
                    </div>
                </main>

                @include('theme-lan0201::partials.landing-footer', ['branding' => $branding, 'footerColumns' => $footerColumns])
            </div>
        </div>

        @include('theme-lan0201::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>