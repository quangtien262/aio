@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $t = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('EC900', app()->getLocale(), $key);
    $name = trim((string) ($branding['company_name'] ?? '')) ?: 'ECOMAX';
    $hotline = trim((string) ($branding['support_hotline'] ?? '')) ?: '0399162342';
    $email = trim((string) ($branding['support_email'] ?? '')) ?: 'support@ecomax.vn';
    $location = trim((string) ($branding['support_location'] ?? '')) ?: '70 Lữ Gia, Phường 15, Quận 11, Thành phố Hồ Chí Minh';
@endphp
<footer class="ec9-footer">
    <div class="ec9-container ec9-footer-grid">
        <section><h3>{{ $name }}</h3><p>{{ data_get($branding, 'company_description', $t('EC900.footer.about')) }}</p><a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i>{{ $email }}</a><span><i class="fa-solid fa-location-dot"></i>{{ $location }}</span><a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a></section>
        <section><h3>Về chúng tôi</h3><a href="{{ route('site.home') }}#danh-muc-noi-bat">Giới thiệu</a><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a><a href="{{ route('site.blog.index') }}">Tin tuyển dụng</a><a href="{{ route('site.contact') }}">Liên hệ</a></section>
        <section><h3>Chính sách bán hàng</h3><a href="#">Chính sách bảo hành</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách bảo mật</a><a href="#">Chính sách trả góp</a><a href="#">Chính sách giao hàng</a></section>
        <section><h3>Dịch vụ và thông tin khác</h3><a href="#">Điều khoản và điều kiện</a><a href="#">Câu hỏi thường gặp</a><a href="#">Khách hàng thân thiết</a><a href="#">Chính sách vận chuyển</a></section>
        <section><h3>Hotline hỗ trợ</h3><p>Tư vấn mua hàng (Miễn phí)</p><b>{{ $hotline }} <small>(Nhánh 1)</small></b><p>Hỗ trợ kỹ thuật</p><b>{{ $hotline }} <small>(Nhánh 2)</small></b></section>
        <section><h3>Kết nối với {{ $name }}</h3><div class="ec9-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-tiktok"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div></section>
        <section class="ec9-pay"><h3>Phương thức thanh toán</h3><div><span>VNPAY</span><span>ZaloPay</span><span>NAPAS</span><span>momo</span><span>VISA</span><span>JCB</span></div></section>
        <section class="ec9-qr"><div><i class="fa-solid fa-qrcode"></i></div><p><b>Mua hàng nhanh</b><br>Quét mã QR để bắt đầu</p></section>
    </div>
</footer>
