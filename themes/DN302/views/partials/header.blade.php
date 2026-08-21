@php
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $siteName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Website')));
    $hotline = $branding['support_hotline'] ?? '';
    $email = $branding['support_email'] ?? '';
    $logo = trim((string) ($branding['logo_url'] ?? ''));
    $menuItems = collect(data_get(
        $themeShellData ?? [],
        'top_menu',
        data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))
    ))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label', data_get($item, 'title'))))->values();
    $headerLocales = collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false)
            && (bool) ($locale['is_published'] ?? true)
            && filled($locale['code'] ?? null))
        ->values();
    $currentLocale = \App\Support\FrontendLocalization::resolveLocale(app()->getLocale());
    $languageUrls = \App\Support\FrontendRouteUrl::localeSwitchUrls(
        $headerLocales->pluck('code')->map(fn ($locale): string => (string) $locale)->all()
    );
    $languageUrl = static fn (string $locale): string => $languageUrls[$locale]
        ?? \App\Support\FrontendRouteUrl::home($locale);
    $languageLabel = static fn (array $locale): string => trim((string) (
        ($locale['native_name'] ?? null)
        ?: ($locale['name'] ?? null)
        ?: strtoupper((string) ($locale['code'] ?? ''))
    ));
    $languageIcon = static fn (array $locale): string => (string) (
        ($locale['icon_url'] ?? null)
        ?: \App\Support\Localization\LocaleIcon::dataUri((string) ($locale['code'] ?? ''))
    );
    $activeHeaderLocale = $headerLocales->firstWhere('code', $currentLocale) ?? $headerLocales->first();
    if ($menuItems->isEmpty()) {
        $menuItems = collect([
            ['label' => 'Trang chủ', 'url' => route('site.home')],
            ['label' => 'Giới thiệu', 'url' => route('site.home').'#gioi-thieu'],
            ['label' => 'Sản phẩm', 'url' => route('site.home').'#san-pham'],
            ['label' => 'Dịch vụ', 'url' => route('site.home').'#dich-vu'],
            ['label' => 'Dự án', 'url' => route('site.home').'#du-an'],
            ['label' => 'Tin tức', 'url' => route('site.blog.index')],
            ['label' => 'Liên hệ', 'url' => route('site.contact')],
        ]);
    }
@endphp
<header class="dn-header" data-dn-header>
    <div class="dn-header-inner dn-container">
        <a class="dn-logo" href="{{ route('site.home') }}" aria-label="@themeT('DN302.common.home', 'Trang chủ')">
            @if($logo !== '')
                <img src="{{ $logo }}" alt="{{ $siteName }}">@endif
        </a>
        <div class="dn-head-main">
            <div class="dn-topbar">
                <a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i> {{ $email }}</a>
                <span class="dn-socials"><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-youtube"></i><i class="fa-brands fa-pinterest-p"></i></span>
                @if($headerLocales->count() > 1)
                    <div class="dn-language-switcher" data-dn-language-switcher data-storefront-language-switcher aria-label="Chọn ngôn ngữ">
                        @foreach($headerLocales as $locale)
                            @php($localeCode = (string) $locale['code'])
                            <a
                                class="{{ $localeCode === $currentLocale ? 'is-active' : '' }}"
                                href="{{ $languageUrl($localeCode) }}"
                                hreflang="{{ $localeCode }}"
                                data-locale-code="{{ $localeCode }}"
                                aria-label="{{ $languageLabel($locale) }}"
                                title="{{ $languageLabel($locale) }}"
                                @if($localeCode === $currentLocale) aria-current="true" @endif
                            ><img data-locale-icon="{{ $localeCode }}" src="{{ $languageIcon($locale) }}" alt="" width="28" height="20"></a>
                        @endforeach
                    </div>
                @endif
                <div class="dn-auth-actions">
                    @auth('admin')
                        <a href="{{ route('admin.index') }}">Quản trị</a>
                    @elseif(auth('customer')->check())
                        <a href="{{ route('customer.account') }}">Tài khoản</a>
                    @else
                        <button type="button" data-dn-auth-open="login">Đăng nhập</button>
                        <span aria-hidden="true">/</span>
                        <button type="button" data-dn-auth-open="register">Đăng ký</button>
                    @endauth
                </div>
            </div>
            <div class="dn-navbar">
                <button class="dn-menu-toggle" type="button" data-dn-menu aria-expanded="false" aria-controls="dn-main-menu"><i class="fa-solid fa-bars"></i><span>Menu</span></button>
                <nav id="dn-main-menu" data-dn-nav>
                    @foreach($menuItems as $item)
                        @include('theme-dn302::partials.menu-item', ['item' => $item, 'level' => 0])
                    @endforeach
                    @guest('customer')
                        @guest('admin')
                            <span class="dn-auth-mobile">
                                <button type="button" data-dn-auth-open="login">Đăng nhập</button>
                                <button type="button" data-dn-auth-open="register">Đăng ký</button>
                            </span>
                        @endguest
                    @endguest
                    @if($headerLocales->count() > 1)
                        <span class="dn-language-mobile" data-dn-language-switcher data-storefront-language-switcher aria-label="Chọn ngôn ngữ">
                            @foreach($headerLocales as $locale)
                                @php($localeCode = (string) $locale['code'])
                                <a
                                    class="{{ $localeCode === $currentLocale ? 'is-active' : '' }}"
                                    href="{{ $languageUrl($localeCode) }}"
                                    hreflang="{{ $localeCode }}"
                                    data-locale-code="{{ $localeCode }}"
                                    aria-label="{{ $languageLabel($locale) }}"
                                    @if($localeCode === $currentLocale) aria-current="true" @endif
                                >
                                    <img data-locale-icon="{{ $localeCode }}" src="{{ $languageIcon($locale) }}" alt="" width="28" height="20">
                                    <span>{{ $languageLabel($locale) }}</span>
                                </a>
                            @endforeach
                        </span>
                    @endif
                </nav>
                <div class="dn-navbar-actions">
                    <a class="dn-consult" href="{{ route('site.contact') }}" data-dn-consult-open>
                        <span class="dn-consult-label-full">@themeT('DN302.header.consultation', 'Đăng ký tư vấn')</span>
                        <span class="dn-consult-label-mobile">@themeT('DN302.header.consultation_short', 'ĐK Tư vấn')</span>
                    </a>
                    @if($headerLocales->count() > 1 && is_array($activeHeaderLocale))
                        <details class="dn-mobile-language" data-dn-language-switcher data-storefront-language-switcher>
                            <summary aria-label="@themeT('DN302.common.choose_language', 'Chọn ngôn ngữ')" title="{{ $languageLabel($activeHeaderLocale) }}">
                                <img src="{{ $languageIcon($activeHeaderLocale) }}" alt="" width="28" height="20">
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </summary>
                            <div class="dn-mobile-language-menu">
                                @foreach($headerLocales as $locale)
                                    @php($localeCode = (string) $locale['code'])
                                    <a
                                        class="{{ $localeCode === $currentLocale ? 'is-active' : '' }}"
                                        href="{{ $languageUrl($localeCode) }}"
                                        hreflang="{{ $localeCode }}"
                                        data-locale-code="{{ $localeCode }}"
                                        aria-label="{{ $languageLabel($locale) }}"
                                        @if($localeCode === $currentLocale) aria-current="true" @endif
                                    >
                                        <img data-locale-icon="{{ $localeCode }}" src="{{ $languageIcon($locale) }}" alt="" width="28" height="20">
                                        <span>{{ $languageLabel($locale) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            </div>
        </div>
    </div>
</header>
