@php
    $branding = (array) data_get($themeShellData ?? $themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $phone = $branding['support_hotline'] ?? '';
    $email = $branding['support_email'] ?? '';
    $address = $branding['support_location'] ?? '';
@endphp
<footer id="footer" class="n503-footer">
    <div class="n503-container n503-footer-grid">
        <section><h3>WOLF BED</h3><p><i class="fa-regular fa-envelope"></i> {{ $email }}</p><p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p><p><i class="fa-solid fa-phone"></i> {{ $phone }}</p></section>
        <section><h3>VỀ CHÚNG TÔI</h3><a href="#">Giới thiệu</a><a href="#">Chứng nhận & Giải thưởng</a><a href="#">Hệ thống cửa hàng</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.blog.index') }}">Tin khuyến mãi</a></section>
        <section><h3>CHÍNH SÁCH BÁN HÀNG</h3><a href="#">Chính sách bảo hành</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách giao hàng</a><a href="#">Chính sách bảo mật</a><a href="#">Điều khoản và điều kiện</a></section>
        <section><h3>DỊCH VỤ VÀ THÔNG TIN KHÁC</h3><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách trả góp</a><a href="#">Khách hàng thân thiết</a><a href="#">Câu hỏi thường gặp</a><a href="#">Chính sách đặt cọc/hủy/đổi/trả</a></section>
    </div>
    <div class="n503-container n503-copyright">© {{ date('Y') }} WolfBed. Nâng niu giấc ngủ Việt.</div>
</footer>
