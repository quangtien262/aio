@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Prinash')));
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '1900 9477'));
    $email = trim((string) ($branding['support_email'] ?? data_get($siteProfile ?? [], 'email', 'hello@prinash.vn')));
    $address = trim((string) ($branding['support_location'] ?? data_get($siteProfile ?? [], 'address', '344 Huỳnh Tấn Phát, Quận 7, TP. Hồ Chí Minh')));
    $gallery = ['/theme-demo/dn350/gallery-cleaner.webp','/theme-demo/dn350/gallery-kitchen-work.webp','/theme-demo/dn350/gallery-team.webp','/theme-demo/dn350/service-housekeeper.webp','/theme-demo/dn350/gallery-kitchen.webp','/theme-demo/dn350/service-garden.webp'];
@endphp
<footer class="dn350-footer">
    <div class="dn350-container dn350-footer__grid">
        <section>
            <a class="dn350-footer__logo" href="{{ route('site.home') }}">
                @if($logo !== '')<img src="{{ $logo }}" alt="{{ $siteName }}">@else<i class="fa-solid fa-spray-can-sparkles"></i><strong>{{ $siteName }}</strong>@endif
            </a>
            <h3>@themeT('DN350.footer.contact', 'Thông tin liên hệ')</h3>
            <p>@themeT('DN350.footer.description', 'Giải pháp vệ sinh toàn diện, tận tâm và an toàn cho mọi không gian.')</p>
            <a href="{{ route('site.contact') }}"><i class="fa-solid fa-location-dot"></i> {{ $address }}</a>
            <a class="dn350-footer__phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}"><i class="fa-solid fa-phone"></i><span>Điện thoại<strong>{{ $hotline }}</strong></span></a>
            <a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i> {{ $email }}</a>
        </section>
        <section><h3>@themeT('DN350.footer.categories', 'Danh mục')</h3><a href="{{ route('site.services.index') }}">Dịch vụ tại nhà</a><a href="{{ route('site.services.index') }}">Dành cho doanh nghiệp</a><a href="{{ route('site.blog.index') }}">Tin tức vệ sinh</a><div class="dn350-social"><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a></div></section>
        <section><h3>@themeT('DN350.footer.gallery', 'Thư viện ảnh')</h3><div class="dn350-footer__gallery">@foreach($gallery as $image)<img src="{{ $image }}" alt="Dịch vụ vệ sinh {{ $loop->iteration }}">@endforeach</div></section>
        <section><h3>Bản đồ</h3><a class="dn350-map" href="{{ route('site.contact') }}"><span><i class="fa-solid fa-location-dot"></i></span><strong>Prinash Cleaning</strong><small>{{ $address }}</small></a></section>
    </div>
    <div class="dn350-footer__copy"><div class="dn350-container">© {{ now()->year }} {{ $siteName }}. @themeT('DN350.footer.rights', 'Bảo lưu mọi quyền.')</div></div>
</footer>
