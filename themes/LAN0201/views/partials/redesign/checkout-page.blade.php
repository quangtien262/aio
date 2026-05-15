<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Gửi yêu cầu tư vấn | {{ data_get($branding, 'company_name', 'LAN0201') }}</title>
        <link rel="icon" href="{{ data_get($branding, 'favicon_url', 'https://htvietnam.vn/images/logo/logo_vn_noslogan.png') }}">
        @vite('resources/css/app.css')
        <style>@include('theme-lan0201::partials.landing-style', ['branding' => $branding])</style>
    </head>
    <body>
        <div class="th-landing-page">
            <div class="th-landing-shell">
                @include('theme-lan0201::partials.landing-header', ['branding' => $branding, 'topMenu' => $topMenu, 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'cartSummary' => $cartSummary, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation])
                <main class="th-landing-main">
                    <div class="th-landing-container">
                        <div class="th-landing-breadcrumb"><a href="{{ route('site.home') }}">Trang chủ</a><span>/</span><a href="{{ route('site.cart.index') }}">Quan tâm</a><span>/</span><span>Gửi yêu cầu</span></div>
                        @if ($errors->any())<div class="th-landing-alert is-error">{{ $errors->first() }}</div>@endif
                        <section class="th-landing-hero">
                            <div class="th-landing-hero-grid">
                                <div>
                                    <span class="th-landing-kicker">Luồng tư vấn</span>
                                    <h1 class="th-landing-title">Gửi yêu cầu tư vấn và đặt lịch xem nhà mẫu</h1>
                                    <p class="th-landing-summary">Checkout của LAN0201 đã được đổi vai trò thành một consultation form. Khách để lại thông tin, sales nhận bảng sản phẩm quan tâm và liên hệ theo quy trình mở bán.</p>
                                    <div class="th-landing-copy">
                                        <ul>
                                            @foreach ($checkoutHighlights as $highlight)
                                                <li>{{ $highlight }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                                <div class="th-landing-panel">
                                    <span class="th-landing-kicker">Tóm tắt lead</span>
                                    <h2 class="th-landing-section-title">{{ count($cartItems) }} sản phẩm được gửi kèm</h2>
                                    <div style="display:grid; gap:12px;">
                                        @foreach ($cartItems as $item)
                                            <div class="th-landing-card" style="padding:14px;">
                                                <strong>{{ $item['title'] ?? 'Sản phẩm' }}</strong>
                                                <div class="th-landing-copy">{{ $item['sku'] ?? 'LAN0201' }} · {{ $formatCurrency($item['price'] ?? null) }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section style="margin-top:26px;" class="th-landing-two-col">
                            <form method="POST" action="{{ route('site.checkout.store') }}" class="th-landing-panel">
                                @csrf
                                <span class="th-landing-kicker">Thông tin liên hệ</span>
                                <h2 class="th-landing-section-title">Hồ sơ khách hàng</h2>
                                <div class="th-landing-form-grid">
                                    <div class="th-landing-field">
                                        <label for="customer_name">Họ tên</label>
                                        <input id="customer_name" type="text" name="customer_name" value="{{ old('customer_name', $form['customer_name'] ?? '') }}">
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="customer_phone">Số điện thoại</label>
                                        <input id="customer_phone" type="text" name="customer_phone" value="{{ old('customer_phone', $form['customer_phone'] ?? '') }}">
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="customer_email">Email</label>
                                        <input id="customer_email" type="email" name="customer_email" value="{{ old('customer_email', $form['customer_email'] ?? '') }}">
                                    </div>
                                    <div class="th-landing-field">
                                        <label for="customer_city">Tỉnh thành</label>
                                        <input id="customer_city" type="text" name="customer_city" value="{{ old('customer_city', $form['customer_city'] ?? '') }}">
                                    </div>
                                    <div class="th-landing-field is-full">
                                        <label for="address">Địa chỉ</label>
                                        <input id="address" type="text" name="address" value="{{ old('address', $form['address'] ?? '') }}">
                                    </div>
                                    <div class="th-landing-field is-full">
                                        <label for="note">Nhu cầu / ghi chú</label>
                                        <textarea id="note" name="note">{{ old('note', $form['note'] ?? '') }}</textarea>
                                    </div>
                                </div>
                                <div class="th-landing-divider" style="margin:20px 0;"></div>
                                <div style="display:grid; gap:12px;">
                                    @foreach ($paymentMethods as $paymentMethod)
                                        <label class="th-landing-card" style="padding:16px; display:grid; grid-template-columns:18px 1fr; gap:12px; align-items:flex-start;">
                                            <input type="radio" name="payment_method" value="{{ $paymentMethod['code'] ?? $paymentMethod['value'] ?? '' }}" @checked(old('payment_method', $form['payment_method'] ?? '') === ($paymentMethod['code'] ?? $paymentMethod['value'] ?? ''))>
                                            <span>
                                                <strong>{{ $paymentMethod['label'] ?? 'Phương án thanh toán' }}</strong><br>
                                                <span class="th-landing-copy">{{ $paymentMethod['description'] ?? 'Thông tin sẽ được xác nhận lại bởi đội sales.' }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="th-landing-actions-row">
                                    <button type="submit" class="th-landing-button">Xác nhận gửi yêu cầu</button>
                                </div>
                            </form>
                            <aside class="th-landing-panel">
                                <span class="th-landing-kicker">Tổng quan bảng hàng</span>
                                <h2 class="th-landing-section-title">Tạm tính cho đội tư vấn</h2>
                                <div class="th-landing-stats" style="grid-template-columns:1fr;">
                                    <div class="th-landing-stat"><strong>{{ count($cartItems) }}</strong><span>Căn đang quan tâm</span></div>
                                    <div class="th-landing-stat"><strong>{{ $formatCurrency($cartSummary['subtotal'] ?? 0) }}</strong><span>Tổng giá trị tham chiếu</span></div>
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