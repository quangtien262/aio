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
    @include('theme-xd0301::partials.styles')
</x-storefront-head>
<body>
    <div id="top" class="xd-page">
        @include('theme-xd0301::partials.header')

        @yield('content')

        @include('theme-xd0301::partials.footer')
    </div>

    @include('theme-xd0301::partials.auth-modal')
    @include('theme-xd0301::partials.inline-editor')
    @include('theme-xd0301::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
