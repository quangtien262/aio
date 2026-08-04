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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
        @include('theme-xd0323::partials.styles')
</x-storefront-head>
<body>
    <div id="top" class="xd323-page">
        @include('theme-xd0323::partials.header')
        @yield('content')
        @include('theme-xd0323::partials.footer')
    </div>
    @include('theme-xd0323::partials.auth-modal')
    @include('theme-xd0323::partials.inline-editor')
    @include('theme-xd0323::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
