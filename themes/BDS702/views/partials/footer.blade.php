@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $address = trim((string) data_get($branding, 'support_location', ''));
    $email = trim((string) data_get($branding, 'support_email', ''));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $copyright = trim((string) data_get($branding, 'copyright_text', '')) ?: '© '.date('Y').' '.data_get($siteProfile ?? [], 'site_name', 'Aurelia Estates');
    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
@endphp
<footer class="b702-footer"><div class="b702-container b702-footer-grid">
    <div class="b702-contact-list">
        @if($address)<a href="{{ $mapsUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-location-dot"></i><span><b>Địa chỉ</b>{{ $address }}</span></a>@endif
        @if($email)<a href="mailto:{{ $email }}"><i class="fa-regular fa-envelope"></i><span><b>Email</b>{{ $email }}</span></a>@endif
        @if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i><span><b>Liên hệ với chúng tôi</b>{{ $hotline }}</span></a>@endif
    </div>
    <div class="b702-footer-menu"><h3>@themeT('footer.support', 'Hỗ trợ khách hàng')</h3><div><a href="{{ route('site.home', ['locale' => app()->getLocale()]) }}">@themeT('home', 'Trang chủ')</a><a href="#gioi-thieu">@themeT('about', 'Giới thiệu')</a><a href="{{ route('site.real-estate.index', ['locale' => app()->getLocale()]) }}">@themeT('projects', 'Dự án')</a><a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">@themeT('news', 'Tin tức')</a><a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">@themeT('contact', 'Liên hệ')</a></div></div>
    <a class="b702-map-card" href="{{ $mapsUrl }}" target="_blank" rel="noopener"><i class="fa-solid fa-map-location-dot"></i><strong>@themeT('footer.map', 'Xem bản đồ')</strong><small>{{ $address }}</small></a>
</div><div class="b702-copyright">{{ $copyright }}</div><a class="b702-top-link" href="#top" aria-label="@themeT('footer.back_to_top', 'Lên đầu trang')"><i class="fa-solid fa-chevron-up"></i></a></footer>
