@php $branding = (array) data_get($siteProfile ?? [], 'branding', []); @endphp
<footer class="sp11-footer xd-landing-block" data-landing-block-id="{{ data_get($footerBlock ?? [], 'id') }}" data-block-type="spa111_footer">
    <div class="sp11-container sp11-footer-grid">
        <div class="sp11-footer-brand">
            <a class="sp11-logo" href="#top"><span class="sp11-logo-mark"><i class="fa-solid fa-leaf"></i><b>B</b></span><span><strong>Bean <em>Spa</em></strong><small>ĐẸP TRÊN CẢ ƯỚC MƠ</small></span></a>
            <p>Mang đến các liệu trình chăm sóc tinh tế <b>an toàn và chuyên sâu</b> giúp bạn thư giãn trọn vẹn và <b>tái tạo năng lượng</b> từ bên trong.</p>
            <p><i class="fa-solid fa-location-dot"></i> {{ data_get($branding, 'support_location', '') }}</p>
            <p><i class="fa-solid fa-phone-volume"></i> {{ data_get($branding, 'support_hotline', '') }}</p>
            <p><i class="fa-regular fa-envelope"></i> {{ data_get($branding, 'support_email', '') }}</p>
            <div class="sp11-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-shopify"></i></a><a href="#"><i class="fa-brands fa-tiktok"></i></a></div>
        </div>
        <div><h3>Chính Sách</h3><a href="#">Chính sách thành viên</a><a href="#">Chính sách thanh toán</a><a href="#">Chính sách đổi sản phẩm</a><a href="#">Chính sách bảo mật</a><a href="#">Chính sách cộng tác viên</a><a href="#">Chính sách bảo hành</a></div>
        <div><h3>Dịch Vụ</h3><a href="#dich-vu">Massage thư giãn toàn thân</a><a href="#dich-vu">Chăm sóc da mặt chuyên sâu</a><a href="#dich-vu">Massage đá nóng trị liệu</a><a href="#dich-vu">Tắm dưỡng trắng toàn thân</a><a href="#dich-vu">Massage chân thảo dược</a><a href="#dich-vu">Gội đầu dưỡng sinh thảo mộc</a></div>
        <div class="sp11-newsletter"><h3>Đăng Ký Nhận Tin</h3><p>Đăng ký ngay! Để nhận thật nhiều ưu đãi</p><form action="{{ route('site.newsletter.subscribe') }}" method="POST">@csrf<input type="email" name="email" placeholder="Nhập địa chỉ email" required><button>Đăng ký</button></form><h3>Fanpage</h3><div class="sp11-fanpage"><i class="fa-brands fa-facebook"></i><span><b>Bean Spa</b><small>96.360 người theo dõi</small></span></div><div class="sp11-miniapp"><i class="fa-solid fa-qrcode"></i><span><b>Zalo Mini Apps</b><small>Quét mã QR để đặt lịch nhanh chóng</small></span></div></div>
    </div>
    <div class="sp11-copyright"><div class="sp11-container"><span>© Bản quyền thuộc về <b>Mr. Bean</b> | Cung cấp bởi <b>Sapo</b></span><span>Trung tâm hỗ trợ &nbsp;&nbsp;&nbsp; Điều khoản &amp; Điều kiện</span></div></div>
</footer>
<a class="sp11-float-bell" href="#lien-he"><i class="fa-solid fa-bell"></i></a>
<a class="sp11-float-top" href="#top"><i class="fa-solid fa-arrow-up"></i></a>
