@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $siteName = trim((string) data_get($siteProfile ?? [], 'site_name', 'FOOT404'));
    $hotline = trim((string) data_get($branding, 'support_hotline'));
    $email = trim((string) data_get($branding, 'support_email'));
    $address = trim((string) data_get($branding, 'support_location'));
    $hours = trim((string) data_get($branding, 'business_hours', data_get($branding, 'working_hours')));
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<footer class="f404-footer" id="lien-he">
    <div class="f404-container f404-footer__grid">
        <section class="f404-footer__brand">
            <a class="f404-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span>F404</span><small>{{ $siteName }}</small>@endif</a>
            <h2>{{ data_get($branding, 'company_name', $siteName) }}</h2>
            @if($address)<p><i class="fa-solid fa-location-dot"></i><span>{{ $address }}</span></p>@endif
            @if($hotline)<p class="is-hotline"><i class="fa-solid fa-phone"></i><a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}">{{ $hotline }}</a></p>@endif
            @if($email)<p><i class="fa-regular fa-envelope"></i><a href="mailto:{{ $email }}">{{ $email }}</a></p>@endif
            @if($hours)<p><i class="fa-regular fa-clock"></i><span>{{ $hours }}</span></p>@endif
        </section>
        <section><h3>@themeT('FOOT404.support', 'Hỗ trợ khách hàng')</h3><a href="{{ route('site.contact') }}">@themeT('FOOT404.contact', 'Thông tin liên hệ')</a><a href="{{ route('site.catalog.search') }}">@themeT('FOOT404.product_categories', 'Danh mục sản phẩm')</a><button type="button" data-xd-auth-open="login">@themeT('FOOT404.login', 'Đăng nhập')</button><button type="button" data-xd-auth-open="register">@themeT('FOOT404.register', 'Đăng ký')</button></section>
        <section><h3>@themeT('FOOT404.menu', 'Menu')</h3>@foreach($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@endforeach</section>
    </div>
    <div class="f404-footer__bottom"><div class="f404-container">© {{ now()->year }} {{ $siteName }}. All rights reserved.</div></div>
</footer>
