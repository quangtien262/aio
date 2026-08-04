@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'ATELIER'))) ?: 'ATELIER';
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $address = trim((string) data_get($branding, 'support_location', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $copyright = trim((string) data_get($branding, 'copyright_text', data_get($siteProfile ?? [], 'copyright_text', '')));
@endphp
<footer class="s606-footer" id="lien-he"><div class="s606-footer-grid">
    <section class="s606-footer-brand">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<h2>{{ $name }}</h2>@endif<p>Phong cách được tạo nên từ những lựa chọn tinh tế, chất lượng và phù hợp với chính bạn.</p>@if($address)<p><i class="fa-solid fa-location-dot"></i>{{ $address }}</p>@endif<div>@if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>@endif @if($email)<a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i>{{ $email }}</a>@endif</div></section>
    <section><h3>Hỗ trợ khách hàng</h3><a href="{{ route('site.catalog.search') }}">Tìm kiếm</a><a href="#bo-suu-tap">Giới thiệu</a><a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">Liên hệ</a><a href="#tin-tuc">Tin tức</a></section>
    <section><h3>Chính sách</h3><a href="#">Chính sách bán hàng</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách giao hàng</a><a href="#">Bảo mật thông tin</a></section>
    <section><h3>Đăng ký nhận ưu đãi</h3><p>Nhận thông tin bộ sưu tập mới và chương trình dành riêng cho khách hàng.</p><form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="email" name="email" required placeholder="Email của bạn..."><button aria-label="Đăng ký"><i class="fa-solid fa-arrow-right"></i></button></form><h4>PHƯƠNG THỨC THANH TOÁN</h4><b class="s606-payments">VISA&nbsp;&nbsp; Mastercard&nbsp;&nbsp; VNPAY</b></section>
</div>@if($copyright)<div class="s606-copyright">{{ $copyright }}</div>@endif</footer>
