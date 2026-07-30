@php
    $profile = $siteProfile ?? [];
    $branding = (array) data_get($profile, 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'NovaTech Mall'))) ?: 'NovaTech Mall';
    $phone = trim((string) data_get($branding, 'support_hotline', '')) ?: '0399162342';
    $email = trim((string) data_get($branding, 'support_email', '')) ?: 'hello@novatech.test';
    $location = trim((string) data_get($branding, 'support_location', '')) ?: '70 Lữ Gia, Phường 15, Quận 11, TP.HCM';
    $description = trim((string) data_get($branding, 'company_description', '')) ?: 'Trung tâm điện máy và công nghệ chính hãng cho mọi gia đình Việt.';
@endphp

<section class="ec13-newsletter">
    <div class="ec13-container"><div><i class="fa-regular fa-envelope"></i><span><b>Nhận tin khuyến mãi</b><small>Ưu đãi mới và mã giảm giá gửi thẳng tới bạn</small></span></div><form action="{{ route('site.newsletter.subscribe') }}" method="post">@csrf<input type="email" name="email" placeholder="Nhập email của bạn" required><button>Đăng ký ngay</button></form></div>
</section>
<footer class="ec13-footer">
    <div class="ec13-container ec13-footer-grid">
        <section class="ec13-footer-brand">
            <a class="ec13-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
                @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span class="ec13-logo-mark"><i class="fa-solid fa-bolt"></i></span><span><b>NOVA</b>TECH<small>Digital mall</small></span>@endif
            </a>
            <p>{{ $description }}</p>
            <div class="ec13-social"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Youtube"><i class="fa-brands fa-youtube"></i></a><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a><a href="#" aria-label="Tiktok"><i class="fa-brands fa-tiktok"></i></a></div>
        </section>
        <section><h3>Hỗ trợ khách hàng</h3><a href="#">Hướng dẫn mua hàng</a><a href="#">Phương thức thanh toán</a><a href="#">Chính sách giao hàng</a><a href="#">Đổi trả & hoàn tiền</a><a href="#">Câu hỏi thường gặp</a></section>
        <section><h3>Về NovaTech</h3><a href="{{ route('site.home') }}#gioi-thieu">Giới thiệu</a><a href="{{ route('site.blog.index') }}">Tin công nghệ</a><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a><a href="{{ route('site.contact') }}">Liên hệ hợp tác</a><a href="{{ route('site.contact') }}">Tuyển dụng</a></section>
        <section class="ec13-footer-contact"><h3>Liên hệ</h3><p><i class="fa-solid fa-location-dot"></i><span>{{ $location }}</span></p><p><i class="fa-solid fa-phone"></i><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></p><p><i class="fa-solid fa-envelope"></i><a href="mailto:{{ $email }}">{{ $email }}</a></p><h3>Thanh toán an toàn</h3><div class="ec13-payments"><b>VISA</b><b>ATM</b><b>VNPAY</b><b>COD</b></div></section>
    </div>
    <div class="ec13-copyright"><div class="ec13-container"><span>© {{ date('Y') }} {{ $siteName }}. Bảo lưu mọi quyền.</span><span>Hàng chính hãng · Giá minh bạch · Hậu mãi tận tâm</span></div></div>
</footer>
