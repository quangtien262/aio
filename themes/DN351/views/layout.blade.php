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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        @include('theme-dn351::partials.styles')
</x-storefront-head>
<body class="{{ request()->routeIs('site.home') ? 'dn351-is-home' : 'dn351-is-inner' }}">
<div id="top" class="dn351-page">
    @include('theme-dn351::partials.header')
    @yield('content')
    @include('theme-dn351::partials.footer')
</div>
@include('theme-dn351::partials.auth-modal')
@if ($canEditLanding ?? false)
    @include('theme-xd0302::partials.inline-editor')
@endif
@include('theme-dn351::partials.shell-scripts')
@stack('scripts')
</body>
</html>
