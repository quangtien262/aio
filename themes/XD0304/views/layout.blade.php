@php
    $xdShell = $themeShellData ?? $themeHomeData ?? [];
    $xdBranding = (array) data_get($xdShell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = $companyName ?? $xdBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '');
    $companyDescription = $companyDescription ?? $xdBranding['company_description'] ?? '';
    $supportEmail = $supportEmail ?? $xdBranding['support_email'] ?? '';
    $supportAddress = $supportAddress ?? $xdBranding['support_location'] ?? '';
    $hotline = $hotline ?? $xdBranding['support_hotline'] ?? '';
    $phoneHref = $phoneHref ?? preg_replace('/\D+/', '', $hotline);
    $logoUrl = $logoUrl ?? $xdBranding['logo_url'] ?? '';
    $logoAlt = $logoAlt ?? $companyName;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-storefront-head
    :site-profile="$siteProfile ?? null"
    :theme-shell-data="$themeShellData ?? []"
    :active-theme="$activeTheme ?? null"
    :landing-page="$landingPage ?? null"
    :page-title="$pageTitle ?? null"
    :page-description="$pageDescription ?? null"
    :page-keywords="$pageKeywords ?? null"
    :canonical-url="$canonicalUrl ?? null"
    :hreflang-urls="$hreflangUrls ?? []"
    :is-preview="$isPreview ?? false"
>
    @include('themes.common.fonts.chakra-manrope')
    @include('theme-xd0304::partials.styles')
    @include('theme-xd0304::partials.contact-styles')
</x-storefront-head>
<body class="{{ request()->routeIs('site.home') ? 'xd4-is-home' : 'xd4-is-inner' }}">
    <div id="top" class="xd4-page">
        @include('theme-xd0304::partials.header')
        @yield('content')
        @include('theme-xd0304::partials.footer')
    </div>
    @include('theme-xd0304::partials.consultation-modal')
    @include('theme-xd0304::partials.auth-modal')
    @include('theme-xd0304::partials.inline-editor')
    @include('theme-xd0304::partials.shell-scripts')
    @include('theme-xd0304::partials.scripts')
    @stack('scripts')
</body>
</html>
