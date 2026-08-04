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
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
        @include('theme-ec908::partials.styles')
</x-storefront-head>
<body>
@php
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $editorLocales = collect(\App\Support\FrontendLocalization::supportedLocales())->map(fn ($locale) => ['code' => $locale, 'label' => strtoupper($locale)])->all();
@endphp
<div class="ec98-page">@include('theme-ec908::partials.header') @yield('content') @include('theme-ec908::partials.footer')</div>
@include('theme-xd0323::partials.auth-modal')
@include('theme-xd0323::partials.inline-editor', ['canEditLanding' => $canEditLanding, 'editorLocales' => $editorLocales])
@include('theme-ec908::partials.scripts')
@if($canEditLanding) @include('theme-xd0301::partials.scripts') @endif
@stack('scripts')
</body></html>
