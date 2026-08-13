@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = trim((string) data_get($branding, 'logo_url'));
    $siteName = trim((string) data_get($siteProfile ?? [], 'site_name', data_get($branding, 'company_name', 'NEWS88')));
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="n88-header">
    <div class="n88-topbar">
        <div class="n88-container n88-topbar-inner">
            <div class="n88-header-tools">
                <div class="n88-social"><a href="#footer" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a><a href="#footer" aria-label="X"><i class="fa-brands fa-x-twitter"></i></a><a href="#footer" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a></div>
                <button type="button" data-n88-search aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
                @include('partials.storefront-language-switcher')
            </div>
        </div>
    </div>
    <div class="n88-container n88-nav-wrap">
        <a class="n88-brand" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<strong>{{ $siteName }}</strong>@endif
        </a>
        <button class="n88-menu-button" type="button" data-n88-menu aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="n88-nav" data-n88-nav>
            @foreach($nav as $index => $item)
                <a class="{{ $index === 0 ? 'is-active' : '' }}" href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>
            @endforeach
        </nav>
    </div>
    <form class="n88-search" data-n88-search-panel method="get" action="{{ route('site.catalog.search') }}"><input type="search" name="q" placeholder="@themeT('NEWS88.search_placeholder', 'Nhập từ khóa...')"><button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button></form>
</header>
