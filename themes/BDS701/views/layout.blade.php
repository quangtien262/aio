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
    <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        @include('theme-bds701::partials.styles')
        @if ($canEditLanding ?? false)
            @include('theme-foot403::partials.inline-editor-styles')
        @endif
</x-storefront-head>
<body>
<div id="top" class="bds-page">
    @include('theme-bds701::partials.header')
    @yield('content')
    @include('theme-bds701::partials.footer')
</div>
@include('theme-xd0323::partials.auth-modal')
@if ($canEditLanding ?? false)
    @include('theme-xd0302::partials.inline-editor')
@endif
@include('theme-bds701::partials.scripts')
@stack('scripts')
</body>
</html>
