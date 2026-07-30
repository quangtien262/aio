@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url'));
    $hotline = trim((string) data_get($branding, 'support_hotline'));
    $email = trim((string) data_get($branding, 'support_email'));
    $location = trim((string) data_get($branding, 'support_location'));
@endphp
<footer class="ec17-footer">
    <div class="ec17-container ec17-footer-grid">
        <section class="ec17-footer-brand">
            <a class="ec17-logo ec17-logo-light" href="{{ route('site.home') }}">
                @if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name', 'EGA Furniture') }}">@endif
            </a>
            <h2>{{ data_get($branding, 'company_name', 'Siêu thị nội thất EGA') }}</h2>
            <p>{{ data_get($branding, 'company_description', 'Thương hiệu nội thất uy tín và chất lượng, mang đến trải nghiệm mua sắm tiện lợi, hiện đại và phong phú.') }}</p>
            <p>Mã số thuế: {{ data_get($branding, 'tax_code', '12345678999') }}</p>
            <p><i class="fa-solid fa-location-dot"></i> {{ $location }}</p>
            <p><i class="fa-solid fa-mobile-screen-button"></i> {{ $hotline }}</p>
            <p><i class="fa-solid fa-envelope"></i> {{ $email }}</p>
        </section>
        <section><h3>HỖ TRỢ KHÁCH HÀNG</h3><a href="#">Giới thiệu</a><a href="{{ route('site.contact') }}">Thông tin liên hệ</a><a href="#">Tra cứu cửa hàng</a><a href="#">Tư vấn nội thất theo phong thủy</a></section>
        <section><h3>CHÍNH SÁCH</h3><a href="#">Điều khoản dịch vụ</a><a href="#">Chính sách bảo mật</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách giao hàng</a><a href="#">Chương trình cộng tác viên</a></section>
        <section class="ec17-newsletter"><h3>ĐĂNG KÝ NHẬN TIN</h3><p>Bạn muốn nhận khuyến mãi đặc biệt? Đăng ký ngay.</p><form><input type="email" placeholder="Nhập địa chỉ email"><button>Đăng ký</button></form><div><i class="fa-brands fa-facebook-f"></i><i class="fa-solid fa-comment"></i><i class="fa-brands fa-instagram"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-tiktok"></i></div><div class="ec17-payments"><b>VISA</b><b>●●</b><b>momo</b><b>ZaloPay</b><b>COD</b></div></section>
    </div>
    <div class="ec17-copyright">© {{ now()->year }} {{ data_get($siteProfile ?? [], 'site_name', 'EGA Furniture') }}. Cung cấp bởi Sapo.</div>
</footer>
