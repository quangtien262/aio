@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $phone = data_get($branding, 'support_hotline', '1900 6750');
    $email = data_get($branding, 'support_email', 'support@egamart.vn');
    $address = data_get($branding, 'support_location', '70 Lữ Gia, Quận 11, TP. Hồ Chí Minh');
@endphp
<footer class="ec96-footer" id="footer"><div class="ec96-container ec96-footer-grid">
    <section class="ec96-footer-brand"><a class="ec96-logo" href="#top"><span><b>E</b><b>G</b><b>A</b><small>mini mart</small></span></a><h3>Siêu thị Mini EGA</h3><p>Thương hiệu siêu thị uy tín và chất lượng, mang đến trải nghiệm mua sắm tiện lợi, hiện đại và phong phú.</p><p>Mã số thuế: 12345678999</p><p><i class="fa-solid fa-location-dot"></i> {{ $address }}</p><div><a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"><i class="fa-solid fa-phone"></i> Hotline<br><b>{{ $phone }}</b></a><a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i> Email<br><b>{{ $email }}</b></a></div><h4>Mạng xã hội</h4><p class="ec96-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-tiktok"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a></p></section>
    <section><h3>Hỗ trợ khách hàng</h3><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a><a href="{{ route('site.contact') }}">Câu hỏi thường gặp</a><a href="{{ route('site.contact') }}">Chương trình cộng tác viên</a></section>
    <section><h3>Chính sách</h3><a href="#">Chính sách bảo hành</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách bảo mật</a><a href="#">Điều khoản dịch vụ</a><h3>Tổng đài hỗ trợ</h3><p>Gọi mua hàng: {{ $phone }} (8h–20h)</p><p>Gọi bảo hành: {{ $phone }} (8h–20h)</p></section>
    <section id="newsletter"><h3>Đăng ký nhận ưu đãi</h3><p>Bạn muốn nhận khuyến mãi đặc biệt? Đăng ký để cập nhật ưu đãi ngay lập tức.</p><form><input type="email" placeholder="Email của bạn..." aria-label="Email"><button type="submit">Đăng ký</button></form><h3>Phương thức thanh toán</h3><p class="ec96-payments"><b>VISA</b><b>MasterCard</b><b>momo</b><b>ZaloPay</b></p></section>
</div></footer>
