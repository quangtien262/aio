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
        @include('theme-dn302::partials.styles')
</x-storefront-head>
<body>
    <div id="top" class="dn302-page">
        @include('theme-dn302::partials.header')
        @yield('content')
        @include('theme-dn302::partials.footer')
    </div>
    @include('theme-dn302::partials.auth-modal')
    @include('theme-dn302::partials.consultation-modal')
    @if ($canEditLanding ?? false)
        @include('theme-xd0302::partials.inline-editor')
    @endif
    @include('theme-dn302::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
