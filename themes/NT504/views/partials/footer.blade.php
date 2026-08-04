@php
    $branding = (array) data_get($themeShellData ?? $themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '')));
    $description = trim((string) ($branding['company_description'] ?? ''));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $phone = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
@endphp
<footer id="footer" class="n504-footer"><div class="n504-container n504-footer-grid">
    <section><a class="n504-footer-logo" href="{{ route('site.home') }}">@if($logo !== '')<img src="{{ $logo }}" alt="{{ $name }}">@elseif($name !== '')<span>{{ $name }}</span>@endif</a>@if($description !== '')<p>{{ $description }}</p>@endif<div class="n504-socials"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="Youtube"><i class="fa-brands fa-youtube"></i></a><a href="#" aria-label="TikTok"><i class="fa-brands fa-tiktok"></i></a><a href="#" aria-label="Zalo">Z</a></div></section>
    <section><h3>Sản phẩm</h3><a href="#san-pham">Sơn nội thất</a><a href="#san-pham">Sơn ngoại thất</a><a href="#san-pham">Sơn chống thấm</a><a href="#san-pham">Sơn lót & Chất phủ</a><a href="#san-pham">Dụng cụ thi công</a></section>
    <section><h3>Hỗ trợ</h3><a href="{{ route('site.contact') }}">Hướng dẫn chọn sơn</a><a href="{{ route('site.contact') }}">Hướng dẫn thi công</a><a href="#">Chính sách bảo hành</a><a href="#">Chính sách đổi trả</a><a href="#">Câu hỏi thường gặp</a></section>
    <section class="n504-contact"><h3>Liên hệ</h3><p><i class="fa-solid fa-phone"></i><span>{{ $phone }}</span></p><p><i class="fa-regular fa-envelope"></i><span>{{ $email }}</span></p><p><i class="fa-solid fa-location-dot"></i><span>{{ $address }}</span></p><p>Mã số doanh nghiệp được đăng ký và quản lý theo quy định hiện hành.</p></section>
</div><div class="n504-container n504-copyright">© {{ date('Y') }} {{ $name }}</div></footer>
