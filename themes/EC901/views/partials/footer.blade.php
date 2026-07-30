@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = trim((string) ($branding['company_name'] ?? '')) ?: 'TEMPO';
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $location = trim((string) ($branding['support_location'] ?? ''));
@endphp
<footer class="ec91-footer">
    <div class="ec91-container ec91-footer-grid">
        <section><h3>VỀ {{ $name }}</h3><p><i class="fa-solid fa-location-dot"></i>{{ $location }}</p><a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i>{{ $email }}</a><a class="ec91-hotline" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a><a href="{{ route('site.contact') }}"><i class="fa-solid fa-map-location-dot"></i>Hệ thống cửa hàng</a><div class="ec91-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-facebook-messenger"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div></section>
        <section><h3>CHÍNH SÁCH</h3><a href="#">Chính sách bán hàng</a><a href="#">Chính sách mua hàng</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách đặc biệt</a><a href="#">Chính sách đại lý</a></section>
        <section><h3>DỊCH VỤ</h3><a href="#">Dịch vụ bảo trì</a><a href="#">Dịch vụ TempoCare</a><a href="#">Dịch vụ vàng</a><a href="#">Dịch vụ vận chuyển</a><a href="#">Dịch vụ sau bán</a><a href="#">Dịch vụ mua lại</a></section>
        <section><h3>GIỜ MỞ CỬA</h3><p>Từ 9:00 - 21:30 tất cả các ngày trong tuần (bao gồm cả ngày lễ, ngày Tết).</p><h3>GÓP Ý, KHIẾU NẠI</h3><a class="ec91-hotline" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a><h3>NHẬN TIN KHUYẾN MÃI</h3><form><input type="email" placeholder="Nhập email của bạn"><button type="button">Đăng kí</button></form></section>
    </div>
</footer>
