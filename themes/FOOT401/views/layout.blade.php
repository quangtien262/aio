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
    @include('theme-foot401::partials.dining-font')
        @include('theme-foot401::partials.styles')
        @if($canEditLanding ?? false)
            @include('theme-foot403::partials.inline-editor-styles')
        @endif
</x-storefront-head>
<body>
    <div id="top" class="foot-page">
        @include('theme-foot401::partials.header')
        @yield('content')
        @include('theme-foot401::partials.footer')
    </div>
    @include('theme-foot401::partials.auth-modal')
    @if($canEditLanding ?? false)
        @include('theme-xd0302::partials.inline-editor')
    @endif
    @include('theme-foot401::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
