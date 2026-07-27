@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $name = trim((string) ($branding['company_name'] ?? '')) ?: 'NovaPhone';
    $hotline = trim((string) ($branding['support_hotline'] ?? '')) ?: '1900 6750';
    $email = trim((string) ($branding['support_email'] ?? '')) ?: 'support@novaphone.vn';
    $location = trim((string) ($branding['support_location'] ?? '')) ?: '70 Lữ Gia, Phường 15, Quận 11, TP.HCM';
@endphp
<footer class="ec92-footer"><div class="ec92-container ec92-footer-grid">
    <section><h2>{{ $name }}</h2><p>Hệ thống bán lẻ smartphone, máy tính bảng, laptop và thiết bị công nghệ chính hãng với giá tốt, trả góp linh hoạt, giao hàng nhanh.</p><p><b>Địa chỉ:</b> {{ $location }}</p><p><b>Điện thoại:</b> {{ $hotline }}</p><p><b>Email:</b> {{ $email }}</p></section>
    <section><h3>MẠNG XÃ HỘI</h3><div class="ec92-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-tiktok"></i></a></div><h4>MUA ONLINE (08:00 - 21:00 mỗi ngày)</h4><a class="ec92-phone" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}">{{ $hotline }}</a><em>Tất cả các ngày trong tuần</em><h4>GÓP Ý & KHIẾU NẠI</h4><a class="ec92-phone" href="tel:{{ preg_replace('/\s+/', '', $hotline) }}">{{ $hotline }}</a></section>
    <section><h3>LIÊN KẾT SÀN</h3><div class="ec92-market"><span>S</span><span>L</span><span>T</span><span>N</span></div><h3>HÌNH THỨC THANH TOÁN</h3><div class="ec92-pay"><i class="fa-solid fa-money-bill"></i><i class="fa-solid fa-building-columns"></i><i class="fa-brands fa-cc-visa"></i><i class="fa-brands fa-cc-mastercard"></i></div></section>
</div></footer>
