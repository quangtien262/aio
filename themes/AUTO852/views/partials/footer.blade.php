@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) data_get($branding, 'company_name', 'ONYX DETAILING'));
    $logo = trim((string) data_get($branding, 'logo_url', ''));
    $address = trim((string) data_get($branding, 'support_location', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
@endphp
<footer id="lien-he" class="a852-footer">
    <div class="a852-wrap a852-footer-trust">
        <span><i class="fa-solid fa-medal"></i><b>Sản phẩm chính hãng</b><small>Nguồn gốc rõ ràng</small></span>
        <span><i class="fa-solid fa-headset"></i><b>Dịch vụ chuyên nghiệp</b><small>Quy trình chuẩn hóa</small></span>
        <span><i class="fa-solid fa-shield-heart"></i><b>Bảo hành uy tín</b><small>Hỗ trợ tận tâm</small></span>
        <span><i class="fa-solid fa-truck"></i><b>Giao hàng toàn quốc</b><small>Đóng gói cẩn thận</small></span>
    </div>
    <div class="a852-wrap a852-footer-grid">
        <section><a class="a852-logo a852-logo-foot" href="#top">@if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span class="a852-logo-mark"><i class="fa-solid fa-gem"></i></span><span><b>ONYX</b><small>DETAILING</small></span>@endif</a><p>{{ data_get($branding, 'company_description', 'Trung tâm chăm sóc xe chuyên nghiệp với dịch vụ detailing cao cấp và sản phẩm bảo vệ xe được tuyển chọn.') }}</p>@if($email)<p><i class="fa-regular fa-envelope"></i> {{ $email }}</p>@endif @if($address)<p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p>@endif @if($hotline)<p><i class="fa-solid fa-phone"></i> {{ $hotline }}</p>@endif</section>
        <section><h3>Dịch vụ</h3><a href="#dich-vu">Chăm sóc ngoại thất</a><a href="#dich-vu">Chăm sóc nội thất</a><a href="#dich-vu">Vệ sinh khoang máy</a><a href="#dich-vu">Phủ ceramic</a><a href="#dich-vu">Dán PPF</a></section>
        <section><h3>Sản phẩm</h3><a href="#san-pham">Dung dịch vệ sinh</a><a href="#san-pham">Chăm sóc kính</a><a href="#san-pham">Chăm sóc lốp và mâm</a><a href="#san-pham">Phụ kiện và dụng cụ</a></section>
        <section><h3>Hỗ trợ</h3><a href="#bang-gia">Bảng giá dịch vụ</a><a href="#quy-trinh">Quy trình dịch vụ</a><a href="#tin-tuc">Kiến thức chăm xe</a><a href="#lien-he">Liên hệ tư vấn</a></section>
        <section><h3>Mạng xã hội</h3><div class="a852-social"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-tiktok"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-instagram"></i></div><h3 class="a852-pay-title">Thanh toán</h3><div class="a852-pay"><span>VISA</span><span>JCB</span><span>NAPAS</span></div></section>
    </div>
    <div class="a852-copy"><span>© {{ date('Y') }} {{ $company }}. All rights reserved.</span><span>Theme AUTO852 • Thiết kế cho hệ thống chăm sóc xe chuyên nghiệp</span></div>
    <a class="a852-to-top" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-chevron-up"></i></a>
</footer>
