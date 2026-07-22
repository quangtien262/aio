@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = $branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'HTRestaurant');
    $phone = $branding['phone'] ?? '1900 6750'; $email = $branding['email'] ?? 'support@sapo.vn'; $address = $branding['address'] ?? '70 Lữ Gia, Phường 15, Quận 11, TP.HCM';
@endphp
<footer class="dr-footer"><div class="dr-container dr-footer__grid">
    <section><div class="dr-footer__brand"><span class="dr-brand__seal">♨</span><strong>{{ $company }}</strong></div><p>Ẩm thực tinh tế, nguyên liệu tươi ngon và sự tận tâm trong từng trải nghiệm.</p><h3>Cửa hàng chính</h3><p><b>Địa chỉ:</b> {{ $address }}</p><p><b>Điện thoại:</b> <a href="tel:{{ preg_replace('/\D+/', '', $phone) }}">{{ $phone }}</a></p><p><b>Email:</b> <a href="mailto:{{ $email }}">{{ $email }}</a></p>
    </section><section><h3>Hướng dẫn</h3><a href="#thuc-don">Hướng dẫn mua hàng</a><a href="#lien-he">Hướng dẫn thanh toán</a><a href="#lien-he">Đăng ký thành viên</a><a href="#lien-he">Hỗ trợ khách hàng</a></section><section><h3>Chính sách</h3><a href="#">Chính sách thành viên</a><a href="#">Chính sách thanh toán</a><a href="#">Bảo mật thông tin cá nhân</a><a href="#">Quà tặng tri ân</a></section><section><h3>Mạng xã hội</h3><div class="dr-social"><a href="#">Zalo</a><a href="#">f</a><a href="#">▶</a></div><h3>Hình thức thanh toán</h3><div class="dr-pay"><span>Tiền mặt</span><span>Chuyển khoản</span><span>VISA</span></div></section>
</div><div class="dr-footer__bottom">© {{ date('Y') }} {{ $company }}. All rights reserved.</div></footer>
