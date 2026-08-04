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
    @include('theme-foot403::partials.styles')
        @if($canEditLanding ?? false)
            @include('theme-foot403::partials.inline-editor-styles')
        @endif
</x-storefront-head>
<body><div class="dr-page" id="top">@include('theme-foot403::partials.header')<main>@yield('content')</main>@include('theme-foot403::partials.footer')</div>@include('theme-foot403::partials.order-modal')@include('theme-foot403::partials.auth-modal')@if($canEditLanding ?? false)@include('theme-xd0302::partials.inline-editor')@endif@include('theme-foot403::partials.scripts')@stack('scripts')</body>
</html>
