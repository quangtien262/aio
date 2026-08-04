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
        @include('theme-nt501::partials.styles')
</x-storefront-head>
<body>
    <div id="top" class="nt-page">
        @include('theme-nt501::partials.header')
        @yield('content')
        @include('theme-nt501::partials.footer')
    </div>
    @include('theme-nt501::partials.auth-modal')
    @include('theme-nt501::partials.inline-editor')
    @include('theme-nt501::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
