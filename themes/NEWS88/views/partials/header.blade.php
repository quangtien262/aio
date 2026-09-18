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
                <div class="n88-social">
                    @foreach(['facebook_url' => ['Facebook', 'facebook-f'], 'x_url' => ['X', 'x-twitter'], 'youtube_url' => ['YouTube', 'youtube']] as $socialKey => [$socialLabel, $socialIcon])
                        @php($socialUrl = trim((string) data_get($branding, $socialKey, '')))
                        @if(filter_var($socialUrl, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($socialUrl, PHP_URL_SCHEME)), ['http', 'https'], true))
                            <a href="{{ $socialUrl }}" aria-label="{{ $socialLabel }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-{{ $socialIcon }}"></i></a>
                        @endif
                    @endforeach
                </div>
                <button type="button" data-n88-search aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
                <div class="n88-auth-links">
                    @auth('admin')
                        <a class="n88-auth-admin" href="{{ url('/admin') }}"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i><span>Admin</span></a>
                    @endauth
                    @guest('customer')
                        <a class="n88-auth-login" href="{{ route('customer.auth.login') }}"><i class="fa-regular fa-user"></i><span>@themeT('NEWS88.login', 'Đăng nhập')</span></a>
                        <a class="n88-auth-register" href="{{ route('customer.auth.register') }}">@themeT('NEWS88.register', 'Đăng ký')</a>
                    @else
                        <a class="n88-auth-account" href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>@themeT('NEWS88.account', 'Tài khoản')</span></a>
                    @endguest
                </div>
                @include('partials.storefront-language-switcher')
            </div>
        </div>
    </div>
    <div class="n88-container n88-nav-wrap">
        <a class="n88-brand" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<strong>{{ $siteName }}</strong>@endif
        </a>
        <button class="n88-menu-button" type="button" data-n88-menu aria-label="Menu" aria-expanded="false" aria-controls="n88-navigation"><i class="fa-solid fa-bars"></i></button>
        <nav class="n88-nav" data-n88-nav id="n88-navigation" aria-label="Menu chính">
            <ul class="n88-nav-list">
                @include('theme-news88::partials.nav-items', ['items' => $nav, 'path' => 'root'])
            </ul>
        </nav>
    </div>
    <form class="n88-search" data-n88-search-panel method="get" action="{{ route('site.blog.index') }}"><input type="search" name="q" value="{{ request('q', '') }}" aria-label="@themeT('NEWS88.search_news', 'Tìm kiếm tin tức')" placeholder="@themeT('NEWS88.search_placeholder', 'Nhập từ khóa...')"><button type="submit" aria-label="@themeT('NEWS88.search_news', 'Tìm kiếm tin tức')"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></button></form>
</header>
