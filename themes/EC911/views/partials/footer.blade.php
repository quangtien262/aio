@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $phone = data_get($branding, 'support_hotline', '');
    $email = data_get($branding, 'support_email', '');
    $address = data_get($branding, 'support_location', '');
@endphp
<footer class="ec11-footer">
    <div class="ec11-container ec11-footer-grid">
        <section>
            <a class="ec11-logo ec11-logo-footer" href="#top"><span class="ec11-logo-mark"><i class="fa-solid fa-microchip"></i><b>DIGI</b><strong>TECH</strong></span></a>
            <p><i class="fa-regular fa-paper-plane"></i> {{ $address }}</p>
            <p><i class="fa-solid fa-phone"></i> {{ $phone }}</p>
            <p><i class="fa-regular fa-envelope"></i> {{ $email }}</p>
        </section>
        <section><h3>VỀ CHÚNG TÔI</h3><a href="{{ route('site.home') }}">Trang chủ</a><a href="#gioi-thieu">Giới thiệu</a><a href="{{ route('site.catalog.search') }}">Sản phẩm</a><a href="{{ route('site.blog.index') }}">Tin tức</a><a href="{{ route('site.contact') }}">Liên hệ</a></section>
        <section><h3>CHÍNH SÁCH</h3><a href="#">Chính sách giao hàng</a><a href="#">Chính sách đổi trả</a><a href="#">Chính sách bán hàng</a><a href="#">Hướng dẫn trả góp</a></section>
        <section><h3>TƯ VẤN KHÁCH HÀNG</h3><p>Mua hàng: <b>{{ $phone }}</b></p><p>Khiếu nại: <b>{{ $phone }}</b></p><p>Bảo hành: <b>{{ $phone }}</b></p><h3>PHƯƠNG THỨC THANH TOÁN</h3><div class="ec11-payment"><b>Mastercard</b><b>VISA</b><b>JCB</b><b>ZaloPay</b></div></section>
    </div>
    <div class="ec11-container ec11-copyright">© Bản quyền thuộc về HT Team | Cung cấp bởi HTVietNam</div>
</footer>
