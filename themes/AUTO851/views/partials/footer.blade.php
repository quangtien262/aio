@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $address = trim((string) data_get($branding, 'support_location', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
@endphp
<footer id="lien-he" class="a851-footer"><div class="a851-wrap a851-footer-grid">
<section><a class="a851-logo a851-logo-foot" href="#top"><span><i class="fa-solid fa-car-side"></i><b>OH!Car</b></span><small>CAR &amp; SERVICE</small></a><p>{{ data_get($branding, 'company_description', 'Nền tảng mua bán ô tô minh bạch, nhanh chóng và đáng tin cậy.') }}</p><div class="a851-social"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-instagram"></i></div></section>
<section><h3>Thông tin liên hệ</h3><b>Hệ thống mua bán ô tô OH!Car</b>@if($address)<p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p>@endif @if($hotline)<p><i class="fa-solid fa-phone"></i> {{ $hotline }}</p>@endif @if($email)<p><i class="fa-solid fa-envelope"></i> {{ $email }}</p>@endif</section>
<section><h3>CSKH</h3><p>Hỗ trợ 24/7</p>@if($hotline)<p>Hotline {{ $hotline }}</p>@endif</section><section><h3>Hướng dẫn</h3><a href="#mua-xe">Chế độ bảo hành</a><a href="#phu-kien">Bảo dưỡng và sửa chữa</a><a href="#faq">Phụ tùng và phụ kiện</a></section><section><h3>Chính sách</h3><a href="#">Chính sách bảo mật</a><a href="#">Chính sách thanh toán</a><a href="#">Chính sách giao nhận</a></section>
</div><div class="a851-copy"><b>OH!CAR - HỆ THỐNG MUA BÁN Ô TÔ CŨ MỚI</b><span>© {{ date('Y') }} {{ data_get($branding, 'company_name', 'AUTO851 OH!Car') }}</span></div><a class="a851-to-top" href="#top"><i class="fa-solid fa-chevron-up"></i></a></footer>
