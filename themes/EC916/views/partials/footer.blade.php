@php $branding = (array) data_get($siteProfile ?? [], 'branding', []); @endphp
<footer class="ec16-footer">
    <div class="ec16-container ec16-footer-grid">
        <section><h3>Hỗ trợ khách hàng</h3><a href="#">Các câu hỏi thường gặp</a><a href="{{ route('site.contact') }}">Gửi yêu cầu hỗ trợ</a><a href="#">Hướng dẫn đặt hàng</a><a href="#">Phương thức vận chuyển</a><a href="#">Chính sách đổi trả</a></section>
        <section><h3>Về Bách Hóa Xanh Plus</h3><a href="#">Giới thiệu</a><a href="#">Tuyển dụng</a><a href="#">Chính sách bảo mật</a><a href="#">Điều khoản sử dụng</a><a href="{{ route('site.contact') }}">Liên hệ</a></section>
        <section><h3>Hợp tác</h3><a href="#">Bảo vệ người mua</a><a href="#">Giải quyết khiếu nại</a><a href="#">Hướng dẫn người bán</a><a href="#">Quy chế hoạt động</a></section>
        <section><h3>Liên hệ</h3><p><i class="fa-solid fa-phone"></i> {{ data_get($branding, 'support_hotline', '') }}</p><p><i class="fa-solid fa-envelope"></i> {{ data_get($branding, 'support_email', '') }}</p><p><i class="fa-solid fa-location-dot"></i> {{ data_get($branding, 'support_location', '') }}</p></section>
    </div>
    <div class="ec16-copyright">© {{ now()->year }} Bách Hóa Xanh Plus. Mua sắm tiện lợi mỗi ngày.</div>
</footer>
