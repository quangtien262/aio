@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]);
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Aurelia Estates'))) ?: 'Aurelia Estates';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="b702-header">
    <div class="b702-top"><div class="b702-container"><span>{{ $hotline }}</span><span>{{ $address }}</span><span class="b702-auth">@guest('customer')<button data-xd-auth-open="login">@themeT('auth.login', 'Đăng nhập')</button><button data-xd-auth-open="register">@themeT('auth.register', 'Đăng ký')</button>@else<a href="{{ route('customer.account', ['locale' => app()->getLocale()]) }}">@themeT('auth.account', 'Tài khoản')</a>@endguest</span></div></div>
    <div class="b702-container b702-nav-row">
        <button class="b702-menu-toggle" type="button" data-b702-menu aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="b702-nav b702-nav-left" data-b702-nav>
            @forelse($nav->take((int) ceil($nav->count()/2)) as $item)<a href="{{ data_get($item, 'url') }}">{{ data_get($item, 'label') }}</a>@empty<a href="{{ $homeUrl }}">@themeT('home', 'Trang chủ')</a><a href="#gioi-thieu">@themeT('about', 'Giới thiệu')</a><a href="#du-an">@themeT('projects', 'Dự án')</a>@endforelse
        </nav>
        <a class="b702-logo" href="{{ $homeUrl }}" aria-label="{{ $companyName }}">@if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $companyName }}">@else<span class="b702-mark"><i></i><i></i><i></i></span><strong>{{ $companyName }}</strong>@endif</a>
        <nav class="b702-nav b702-nav-right">
            @forelse($nav->skip((int) ceil($nav->count()/2)) as $item)<a href="{{ data_get($item, 'url') }}">{{ data_get($item, 'label') }}</a>@empty<a href="#hoat-dong">@themeT('services', 'Dịch vụ')</a><a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">@themeT('news', 'Tin tức')</a><a href="#lien-he">@themeT('contact', 'Liên hệ')</a>@endforelse
        </nav>
        <div class="b702-language">@include('partials.storefront-language-switcher')</div>
    </div>
</header>
