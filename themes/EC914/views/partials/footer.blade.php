@php
    $profile = $siteProfile ?? [];
    $branding = (array) data_get($profile, 'branding', []);
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $siteName = trim((string) data_get($profile, 'site_name', data_get($branding, 'company_name', 'Mộc Nhiên Craft'))) ?: 'Mộc Nhiên Craft';
    $phone = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $location = trim((string) data_get($branding, 'support_location', ''));
    $description = trim((string) data_get($branding, 'company_description', '')) ?: 'Đồ thủ công từ tre, mây và vật liệu tự nhiên, được hoàn thiện bằng đôi tay của người thợ Việt.';
@endphp

<footer class="ec14-footer">
    <div class="ec14-container ec14-footer-grid">
        <section class="ec14-footer-brand">
            <a class="ec14-logo" href="{{ route('site.home') }}">
                @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif
            </a>
            <p>{{ $description }}</p>
            <div class="ec14-footer-contact"><p><i class="fa-solid fa-location-dot"></i> {{ $location }}</p><p><i class="fa-solid fa-phone"></i> <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a></p><p><i class="fa-solid fa-envelope"></i> <a href="mailto:{{ $email }}">{{ $email }}</a></p></div>
            <div class="ec14-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-tiktok"></i></a></div>
        </section>
        <section><h3>Về chúng tôi</h3><a href="#cau-chuyen">Câu chuyện thương hiệu</a><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a><a href="#">Chính sách mua hàng</a><a href="#">Chính sách vận chuyển</a><a href="#">Hướng dẫn bảo quản</a></section>
        <section><h3>Chăm sóc khách hàng</h3><a href="{{ route('site.contact') }}">Liên hệ</a><a href="#">Hỏi đáp</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách thành viên</a><a href="#">Cam kết cửa hàng</a></section>
        <section class="ec14-footer-newsletter"><h3>Đăng ký nhận tin</h3><p>Nhận thông tin sản phẩm mới và chương trình ưu đãi.</p><form action="{{ route('site.newsletter.subscribe') }}" method="post">@csrf<input type="email" name="email" placeholder="Nhập địa chỉ email" required><button aria-label="Đăng ký"><i class="fa-solid fa-arrow-right"></i></button></form><h3>Hỗ trợ thanh toán</h3><div class="ec14-payments"><b>VISA</b><b>ATM</b><b>VNPAY</b><b>COD</b></div></section>
    </div>
    <div class="ec14-copyright"><div class="ec14-container">© {{ date('Y') }} {{ $siteName }} · Mộc từ thiên nhiên, đẹp bởi bàn tay người thợ.</div></div>
</footer>
