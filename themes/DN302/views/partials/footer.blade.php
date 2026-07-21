@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Website')));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $address = $branding['support_location'] ?? data_get($siteProfile ?? [], 'address', '344 Huỳnh Tấn Phát, Phường Bình Thuận, Quận 7, TP.HCM');
@endphp
<footer class="dn-footer">
    <div class="dn-container dn-footer-row" data-dn-reveal="up">
        <div class="dn-social-list" aria-label="Mạng xã hội"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a href="#"><i class="fa-brands fa-pinterest-p"></i></a></div>
        <a class="dn-logo dn-logo-footer" href="{{ route('site.home') }}" aria-label="{{ $siteName }} - Trang chủ">
            @if($logo !== '')
                <img src="{{ $logo }}" alt="{{ $siteName }}">
            @else
                <i class="fa-regular fa-window-maximize"></i><span>{{ $siteName }}</span>
            @endif
        </a>
        <p>{{ $address }}</p>
    </div>
    <div class="dn-footer-copy">@themeT('DN302.footer.copyright', 'Sản phẩm của HT Việt Nam')</div>
    <a class="dn-to-top" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-arrow-up"></i></a>
</footer>
