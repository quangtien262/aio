@php($shell = $themeShellData ?? $themeHomeData ?? [])
@php($branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', [])))
@php($logo = trim((string) data_get($branding, 'logo_url', '')))
@php($siteName = trim((string) data_get($branding, 'company_name', data_get($siteProfile ?? [], 'site_name', 'Euro Sound'))) ?: 'Euro Sound')
<footer class="ec99-footer"><div class="ec99-shell ec99-footer-grid">
    <section><a class="ec99-footer-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span>Ξ</span><strong>EURO SOUND</strong>@endif</a><p>Euro Sound là cửa hàng chuyên cung cấp các thiết bị âm thanh chính hãng như loa, tai nghe có dây, tai nghe không dây và headphone chất lượng cao.</p><nav><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a></nav></section>
    <section><h3>Thông tin</h3><p><b>Địa chỉ:</b> {{ data_get($branding, 'support_location', '70 Lữ Gia, Phường 15, Quận 11, TP.HCM') }}</p><p><b>Điện thoại:</b> {{ data_get($branding, 'support_hotline', '1900 6750') }}</p><p><b>Email:</b> {{ data_get($branding, 'support_email', 'support@sapo.vn') }}</p><div class="ec99-marketplaces"><i>S</i><i>L</i><i>T</i><i>S</i></div></section>
    <section><h3>Chính sách</h3><a href="#">Chính sách bảo mật</a><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách đổi trả</a><a href="#">Quy định sử dụng</a></section>
    <section><h3>Hỗ trợ</h3><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn thanh toán</a><a href="#">Hướng dẫn giao nhận</a><a href="#">Điều khoản dịch vụ</a></section>
    <section><h3>Danh mục nổi bật</h3><a href="{{ route('site.catalog.search') }}">Headphones</a><a href="{{ route('site.catalog.search') }}">Earphones</a><a href="{{ route('site.catalog.search') }}">Speakers</a><a href="{{ route('site.catalog.search') }}">Accessories</a></section>
</div></footer>
<a class="ec99-backtop" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-angle-up"></i></a>
