<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Yêu cầu đã ghi nhận | {{ data_get($branding, 'company_name', 'LAN0201') }}</title>
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
                        <section class="th-landing-hero">
                            <span class="th-landing-kicker">Lead đã gửi</span>
                            <h1 class="th-landing-title">Yêu cầu đã được ghi nhận</h1>
                            <p class="th-landing-summary">Bộ phận kinh doanh sẽ dùng mã yêu cầu này để gọi lại, gửi bảng giá và sắp lịch hẹn tham quan.</p>
                            <div class="th-landing-two-col" style="margin-top:20px;">
                                <div class="th-landing-panel">
                                    <div class="th-landing-copy">
                                        <p><strong>Mã yêu cầu:</strong> {{ $confirmedOrder->code ?? $confirmedOrder->order_code ?? 'N/A' }}</p>
                                        <p><strong>Khách hàng:</strong> {{ $confirmedOrder->customer_name ?? $confirmedOrder->name ?? 'N/A' }}</p>
                                        <p><strong>Số điện thoại:</strong> {{ $confirmedOrder->customer_phone ?? $confirmedOrder->phone ?? 'N/A' }}</p>
                                        <p><strong>Tổng tham chiếu:</strong> {{ $formatCurrency($confirmedOrder->grand_total ?? $confirmedOrder->total ?? 0) }}</p>
                                    </div>
                                </div>
                                <div class="th-landing-panel">
                                    <div class="th-landing-copy">
                                        <p><strong>Địa chỉ:</strong> {{ $confirmedOrder->shipping_address ?? $confirmedOrder->address ?? 'Đang cập nhật' }}</p>
                                        <p><strong>Thanh toán:</strong> {{ $confirmedOrder->payment_method_label ?? $confirmedOrder->payment_method ?? 'Sẽ xác nhận lại' }}</p>
                                        <p><strong>Hỗ trợ:</strong> {{ $contactHotline }} · {{ $contactEmail }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="th-landing-actions-row">
                                <a href="{{ route('site.catalog.search') }}" class="th-landing-link is-primary">Tiếp tục xem bảng hàng</a>
                                <a href="{{ route('customer.account') }}" class="th-landing-outline">Về tài khoản</a>
                            </div>
                        </section>
                    </div>
                </main>
                @include('theme-lan0201::partials.landing-footer', ['branding' => $branding])
            </div>
        </div>
        @include('theme-lan0201::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>