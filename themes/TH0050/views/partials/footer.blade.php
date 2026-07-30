@php
    $th5FooterProfileBranding = (array) data_get($siteProfile ?? [], 'branding', []);
    $th5FooterBranding = array_replace(
        (array) data_get($themeShellData ?? [], 'branding', []),
        $th5FooterProfileBranding,
    );
    $th5FooterCompany = $th5FooterBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'An Nhiên Nest');
    $th5FooterLogo = trim((string) ($th5FooterProfileBranding['logo_url'] ?? $th5FooterBranding['logo_url'] ?? ''));
    $th5FooterPhone = trim((string) ($th5FooterProfileBranding['support_hotline'] ?? $th5FooterBranding['support_hotline'] ?? ''));
    $th5FooterEmail = trim((string) ($th5FooterProfileBranding['support_email'] ?? $th5FooterBranding['support_email'] ?? ''));
    $th5FooterAddress = $th5FooterBranding['support_location'] ?? '';
@endphp
<footer class="th5-footer"><div class="th5-container th5-footer__grid">
    <section><a class="th5-logo is-footer" href="{{ route('site.home') }}">@if($th5FooterLogo !== '')<img src="{{ $th5FooterLogo }}" alt="{{ $th5FooterCompany }}">@endif</a><p>@themeT('TH0050.footer.intro')</p><span><i class="fa-solid fa-location-dot"></i> {{ $th5FooterAddress }}</span>@if($th5FooterPhone !== '')<a href="tel:{{ preg_replace('/\D+/', '', $th5FooterPhone) }}"><i class="fa-solid fa-phone"></i> {{ $th5FooterPhone }}</a>@endif @if($th5FooterEmail !== '')<a href="mailto:{{ $th5FooterEmail }}"><i class="fa-solid fa-envelope"></i> {{ $th5FooterEmail }}</a>@endif</section>
    <section><h3>@themeT('TH0050.footer.policy')</h3><a href="#">Chính sách mua hàng</a><a href="#">Chính sách thanh toán</a><a href="#">Chính sách vận chuyển</a><a href="#">Chính sách bảo mật</a></section>
    <section><h3>@themeT('TH0050.footer.guide')</h3><a href="#">Hướng dẫn mua hàng</a><a href="#">Hướng dẫn đổi trả</a><a href="#">Hướng dẫn thanh toán</a><a href="#">Câu hỏi thường gặp</a></section>
    <section><h3>@themeT('TH0050.footer.contact')</h3><div class="th5-payment"><span>VISA</span><span>ATM</span><span>QR PAY</span></div><div class="th5-social"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-instagram"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a></div><strong>Chứng nhận chất lượng</strong><small>Nguồn gốc rõ ràng · Kiểm định nghiêm ngặt</small></section>
</div><div class="th5-container th5-footer__bottom">© {{ date('Y') }} {{ $th5FooterCompany }}. @themeT('TH0050.footer.rights')</div></footer>
