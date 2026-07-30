@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $email = trim((string) ($branding['support_email'] ?? ''));
    $location = trim((string) ($branding['support_location'] ?? ''));
@endphp
<footer class="ec10-footer">
    <div class="ec10-container ec10-footer-brand"><span class="ec10-brand-mark"><i class="fa-regular fa-clock"></i>DOLA <b>WATCH</b></span><p>Đồng hồ chính hãng, dịch vụ tận tâm và bảo hành minh bạch.</p><div><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-google"></i></a><a href="#"><i class="fa-solid fa-comment"></i></a></div></div>
    <div class="ec10-container ec10-footer-grid">
        <section><h3>THÔNG TIN CHUNG</h3><p><b>Địa chỉ:</b> {{ $location }}</p><p><b>Điện thoại:</b> {{ $hotline }}</p><p><b>Email:</b> {{ $email }}</p><h3>HÌNH THỨC THANH TOÁN</h3><span class="ec10-pay">TIỀN MẶT · CHUYỂN KHOẢN · VISA</span></section>
        <section><h3>CHÍNH SÁCH</h3><a href="#">Chính sách thành viên</a><a href="#">Chính sách thanh toán</a><a href="#">Hướng dẫn mua hàng</a><a href="#">Bảo mật thông tin cá nhân</a></section>
        <section><h3>HƯỚNG DẪN</h3><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn thanh toán</a><a href="#">Đăng ký thành viên</a><a href="#">Hỗ trợ khách hàng</a><a href="#">Câu hỏi thường gặp</a></section>
        <section><h3>DANH MỤC</h3><a href="#thuong-hieu">Thương hiệu nổi bật</a><a href="#dong-ho-nam">Đồng hồ nam</a><a href="{{ route('site.catalog.search') }}">Đồng hồ nữ</a></section>
        <section><h3>ĐĂNG KÝ NHẬN TIN</h3><p>Đăng ký để nhận ngay nhiều ưu đãi hấp dẫn</p><form><input type="email" placeholder="Nhập địa chỉ email"><button type="button">ĐĂNG KÝ</button></form><h3>LIÊN KẾT SÀN</h3><span class="ec10-market">Shopee · Lazada · Tiki</span></section>
    </div>
</footer>
