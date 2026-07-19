@php
    $th5FooterBranding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $th5FooterCompany = $th5FooterBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'An Nhiên Nest');
    $th5FooterPhone = $th5FooterBranding['support_hotline'] ?? '1900 6750';
    $th5FooterEmail = $th5FooterBranding['support_email'] ?? 'hello@annhien.vn';
    $th5FooterAddress = $th5FooterBranding['support_location'] ?? 'TP. Hồ Chí Minh';
@endphp
<footer class="th5-footer"><div class="th5-container th5-footer__grid">
    <section><a class="th5-logo is-footer" href="{{ route('site.home') }}"><span class="th5-logo__mark"><i class="fa-solid fa-feather-pointed"></i></span><span><strong>{{ $th5FooterCompany }}</strong><small>@themeT('TH0050.brand.tagline')</small></span></a><p>@themeT('TH0050.footer.intro')</p><span><i class="fa-solid fa-location-dot"></i> {{ $th5FooterAddress }}</span><a href="tel:{{ preg_replace('/\D+/', '', $th5FooterPhone) }}"><i class="fa-solid fa-phone"></i> {{ $th5FooterPhone }}</a><a href="mailto:{{ $th5FooterEmail }}"><i class="fa-solid fa-envelope"></i> {{ $th5FooterEmail }}</a></section>
    <section><h3>@themeT('TH0050.footer.policy')</h3><a href="#">Chính sách mua hàng</a><a href="#">Chính sách thanh toán</a><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách bảo mật</a></section>
    <section><h3>@themeT('TH0050.footer.guide')</h3><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn đổi trả</a><a href="#">Hướng dẫn thanh toán</a><a href="#">Câu hỏi thường gặp</a></section>
    <section><h3>@themeT('TH0050.footer.contact')</h3><div class="th5-payment"><span>VISA</span><span>ATM</span><span>QR PAY</span></div><div class="th5-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div><strong>Chứng nhận chất lượng</strong><small>Nguồn gốc rõ ràng · Kiểm định nghiêm ngặt</small></section>
</div><div class="th5-container th5-footer__bottom">© {{ date('Y') }} {{ $th5FooterCompany }}. @themeT('TH0050.footer.rights')</div></footer>
