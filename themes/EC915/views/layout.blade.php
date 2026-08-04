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
        @include('theme-ec915::partials.styles')
</x-storefront-head>
<body>
@php
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $editorLocales = collect(\App\Support\FrontendLocalization::supportedLocales())
        ->map(fn ($locale) => ['code' => $locale, 'label' => strtoupper($locale)])
        ->all();
@endphp
<div class="ec15-page">
    @include('theme-ec915::partials.header')
    @yield('content')
    @include('theme-ec915::partials.footer')
</div>
@include('theme-xd0323::partials.auth-modal')
@include('theme-xd0323::partials.inline-editor', ['canEditLanding' => $canEditLanding, 'editorLocales' => $editorLocales])
@include('theme-ec915::partials.scripts')
@if($canEditLanding)
    @include('theme-xd0301::partials.scripts')
@endif
@stack('scripts')
</body>
</html>
