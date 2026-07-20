@php
    $branding = (array) data_get($siteProfile ?? [], 'branding', []);
    $address = data_get($siteProfile ?? [], 'address', '344 Huỳnh Tấn Phát, Phường Bình Thuận, Quận 7, TP.HCM');
@endphp
<footer class="dn-footer">
    <div class="dn-container dn-footer-row" data-dn-reveal="up">
        <div class="dn-social-list" aria-label="Mạng xã hội"><a href="#"><i class="fa-brands fa-facebook-f"></i></a><a href="#"><i class="fa-brands fa-youtube"></i></a><a href="#"><i class="fa-brands fa-x-twitter"></i></a><a href="#"><i class="fa-brands fa-pinterest-p"></i></a></div>
        <a class="dn-logo dn-logo-footer" href="{{ route('site.home') }}"><i class="fa-regular fa-window-maximize"></i><span>janelas<small>Windows &amp; Doors</small></span></a>
        <p>{{ $address }}</p>
    </div>
    <div class="dn-footer-copy">@themeT('DN302.footer.copyright', 'Sản phẩm của HT Việt Nam')</div>
    <a class="dn-to-top" href="#top" aria-label="Lên đầu trang"><i class="fa-solid fa-arrow-up"></i></a>
</footer>
