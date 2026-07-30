@php $branding = (array) data_get($siteProfile ?? [], 'branding', []); @endphp
<footer class="ec93-footer">
    <div class="ec93-container ec93-footer-grid">
        <section><h3>TRỢ GIÚP</h3><a href="#">Chính sách giao hàng</a><a href="#">DealVui E-voucher</a><a href="#">Cách thức thanh toán</a><a href="#">Chính sách đổi trả hàng</a><a href="#">Điều khoản sử dụng</a></section>
        <section><h3>TUYỂN DỤNG</h3><a href="#">Account Manager (Spa & Beauty)</a><a href="#">Account Manager (Ngành Ẩm Thực)</a><h3>GIỚI THIỆU</h3><a href="#">Quy chế hoạt động</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="#">Về chúng tôi</a></section>
        <section><h3>CÔNG TY CỔ PHẦN DEALVUI</h3><h4>VĂN PHÒNG HCM</h4><p>{{ data_get($branding, 'support_location', '') }}<br>Điện thoại: {{ data_get($branding, 'support_hotline', '') }}</p><h4>CHĂM SÓC KHÁCH HÀNG</h4><p>Hotline: {{ data_get($branding, 'support_hotline', '') }}<br>Email: {{ data_get($branding, 'support_email', '') }}</p></section>
    </div>
</footer>
