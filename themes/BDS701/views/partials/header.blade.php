@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Delta Platinum'))) ?: 'Delta Platinum';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $supportHotline = trim((string) ($branding['support_hotline'] ?? '')) ?: '1900 6750';
    $supportEmail = trim((string) ($branding['support_email'] ?? '')) ?: 'contact@example.com';
@endphp
<header class="bds-header">
    <div class="bds-topbar">
        <div class="bds-container bds-topbar-inner">
            <div class="bds-topbar-contact">
                <a href="tel:{{ preg_replace('/\D+/', '', $supportHotline) }}"><i class="fa-solid fa-phone"></i>{{ $supportHotline }}</a>
                <a href="mailto:{{ $supportEmail }}"><i class="fa-regular fa-envelope"></i>{{ $supportEmail }}</a>
            </div>
            <div class="bds-topbar-account">
                @guest('customer')
                    <button type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i>@themeT('auth.login', 'Đăng nhập')</button>
                    <span>/</span>
                    <button type="button" data-xd-auth-open="register">@themeT('auth.register', 'Đăng ký')</button>
                @else
                    <a href="{{ route('customer.account', ['locale' => app()->getLocale()]) }}"><i class="fa-regular fa-user"></i>@themeT('auth.account', 'Tài khoản')</a>
                    <form method="POST" action="{{ route('customer.auth.logout', ['locale' => app()->getLocale()]) }}">
                        @csrf
                        <button type="submit">@themeT('auth.logout', 'Đăng xuất')</button>
                    </form>
                @endguest
            </div>
        </div>
    </div>
    <div class="bds-container bds-nav">
        <a class="bds-logo" href="{{ $homeUrl }}" aria-label="{{ $companyName }}">
            @if ($logoUrl !== '')
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="bds-logo-mark"><i class="fa-solid fa-house-chimney-window"></i></span>
                <span><strong>{{ $companyName }}</strong></span>
            @endif
        </a>
        <button class="bds-menu-toggle" type="button" data-bds-menu><i class="fa-solid fa-bars"></i></button>
        <nav data-bds-nav>
            <a href="{{ $homeUrl }}">@themeT('home', 'Trang chủ')</a>
            <a href="{{ route('site.real-estate.index', ['locale' => app()->getLocale()]) }}">@themeT('listings', 'Tất cả tin rao')</a>
            <a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">@themeT('news', 'Tin tức')</a>
            <a href="{{ route('site.pages.show', ['locale' => app()->getLocale(), 'slug' => 'gioi-thieu']) }}">@themeT('about', 'Giới thiệu')</a>
            <a href="{{ route('site.contact', ['locale' => app()->getLocale()]) }}">@themeT('contact', 'Liên hệ')</a>
        </nav>
    </div>
</header>
