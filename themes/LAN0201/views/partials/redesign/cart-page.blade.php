<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Danh sách quan tâm | {{ data_get($branding, 'company_name', 'LAN0201') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>@include('theme-lan0201::partials.landing-style', ['branding' => $branding])</style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', ['branding' => $branding, 'topMenu' => $topMenu, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'cartSummary' => $cartSummary, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'activeNav' => 'cart'])
                <main class="th-landing-main">
                    <div class="th-landing-container">
                        <div class="th-landing-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><span>Danh sách quan tâm</span></div>
                        @if (session('cart_success'))<div class="th-landing-alert">{{ session('cart_success') }}</div>@endif
                        @if ($errors->any())<div class="th-landing-alert is-error">{{ $errors->first() }}</div>@endif
                        <section class="th-landing-two-col">
                            <div class="th-landing-panel">
                                <span class="th-landing-kicker">Giỏ lead</span>
                                <h1 class="th-landing-section-title">Danh sách quan tâm của khách</h1>
                                @if (empty($cartItems))
                                    <div class="th-landing-empty">Chưa có sản phẩm nào trong danh sách quan tâm.</div>
                                @else
                                    <div style="display:grid; gap:16px;">
                                        @foreach ($cartItems as $item)
                                            <article class="th-landing-card" style="padding:18px; display:grid; grid-template-columns:140px 1fr auto; gap:16px; align-items:center;">
                                                <img src="{{ $item['image'] ?? 'https://picsum.photos/seed/lan0201-cart/'.($loop->index + 1).'/360/360' }}" alt="{{ $item['title'] ?? 'Item' }}" style="width:140px; height:140px; object-fit:cover; border-radius:20px;">
                                                <div>
                                                    <h3 class="th-landing-listing-title" style="font-size:24px;">{{ $item['title'] ?? 'Căn đang quan tâm' }}</h3>
                                                    <div class="th-landing-copy">{{ $item['summary'] ?? 'Thông tin sản phẩm được giữ lại để gửi yêu cầu tư vấn hoặc đặt lịch xem nhà mẫu.' }}</div>
                                                    <div class="th-landing-meta-row" style="margin-top:10px;">
                                                        <span class="th-landing-chip">{{ $item['sku'] ?? 'LAN0201' }}</span>
                                                        <span class="th-landing-pill">{{ $formatCurrency($item['price'] ?? null) }}</span>
                                                    </div>
                                                </div>
                                                <div style="display:grid; gap:12px; min-width:200px;">
                                                    <form method="POST" action="{{ route('site.cart.update', ['productId' => $item['product_id']]) }}">
                                                        @csrf
                                                        <div class="th-landing-field">
                                                            <label>Số lượng</label>
                                                            <input type="number" name="quantity" min="1" value="{{ $item['quantity'] ?? 1 }}">
                                                        </div>
                                                        <button type="submit" class="th-landing-outline" style="width:100%; margin-top:10px;">Cập nhật</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('site.cart.remove', ['productId' => $item['product_id']]) }}">
                                                        @csrf
                                                        <button type="submit" class="th-landing-ghost">Xóa khỏi danh sách</button>
                                                    </form>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <aside class="th-landing-panel">
                                <span class="th-landing-kicker">Tổng kết yêu cầu</span>
                                <h2 class="th-landing-section-title">Tóm tắt lead</h2>
                                <div class="th-landing-copy">Mục này thay cho sidebar giỏ hàng kiểu e-commerce. Nó tập trung vào thông tin để sales tiếp nhận và chốt lịch hẹn.</div>
                                <div class="th-landing-stats" style="margin-top:16px; grid-template-columns:1fr;">
                                    <div class="th-landing-stat"><strong>{{ $cartSummary['count'] ?? 0 }}</strong><span>Sản phẩm quan tâm</span></div>
                                    <div class="th-landing-stat"><strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong><span>Tổng giá trị tham chiếu</span></div>
                                </div>
                                <div class="th-landing-actions-row">
                                    @if (!empty($customerAuth['is_authenticated']))
                                        <a href="{{ route('site.checkout.index') }}" class="th-landing-link is-primary">Gửi yêu cầu tư vấn</a>
                                    @else
                                        <button type="button" class="th-landing-button" data-open-auth-modal="login" data-auth-redirect="{{ route('site.checkout.index') }}">Đăng nhập để tiếp tục</button>
                                    @endif
                                    <a href="{{ route('site.catalog.search') }}" class="th-landing-outline">Thêm sản phẩm</a>
                                </div>
                            </aside>
                        </section>
                    </div>
                </main>
                @include('theme-lan0201::partials.landing-footer', ['branding' => $branding])
            </div>
        </div>
        @include('theme-lan0201::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>