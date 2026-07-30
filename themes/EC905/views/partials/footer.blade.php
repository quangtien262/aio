@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $brand = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($brand, 'logo_url', ''));
    $siteName = trim((string) data_get($brand, 'company_name', data_get($siteProfile ?? [], 'site_name', 'Ego Home'))) ?: 'Ego Home';
@endphp
<footer class="ec95-footer"><div class="ec95-container ec95-footer-grid">
    <section><a class="ec95-logo is-footer" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif</a><h3>{{ $siteName }}</h3><p><i class="fa-solid fa-location-dot"></i> {{ data_get($brand, 'support_location', '') }}</p><p><i class="fa-solid fa-phone"></i> {{ data_get($brand, 'support_hotline', '') }}</p><p><i class="fa-solid fa-envelope"></i> {{ data_get($brand, 'support_email', '') }}</p></section>
    <section><h3>Hỗ trợ / Dịch vụ</h3><a href="#">Hướng dẫn chung</a><a href="#">Hướng dẫn bảo hành</a><a href="#">Hướng dẫn kích hoạt</a><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn lắp đặt</a></section>
    <section><h3>Tư vấn khách hàng</h3><a href="#">Bảng giá sản phẩm</a><a href="#">Người dùng mới</a><a href="#">Làm thẻ thành viên</a><a href="#">Chính sách đổi mới</a><a href="#">Quy trình làm việc</a></section>
    <section><h3>Tổng đài hỗ trợ</h3><p class="ec95-hotline"><i class="fa-solid fa-phone"></i><b>{{ data_get($brand, 'support_hotline', '') }}</b><span>Tư vấn online 8:00 - 18:30</span></p><p class="ec95-hotline"><i class="fa-solid fa-phone"></i><b>{{ data_get($brand, 'support_hotline', '') }}</b><span>Phản ánh chất lượng dịch vụ</span></p></section>
</div><div class="ec95-container ec95-footer-bottom"><span>Bản quyền thuộc về <b>Ego Home Creative</b></span><nav><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a></nav></div></footer>
