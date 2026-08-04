@props([
    'siteProfile' => null,
    'themeShellData' => [],
    'activeTheme' => null,
    'landingPage' => null,
    'pageTitle' => null,
    'pageDescription' => null,
    'pageKeywords' => null,
    'canonicalUrl' => null,
    'hreflangUrls' => [],
    'isPreview' => false,
    'robots' => null,
    'openGraphType' => 'website',
])

@php
    $branding = (array) data_get($themeShellData, 'branding', data_get($siteProfile, 'branding', []));
    $siteName = trim((string) data_get($siteProfile, 'site_name', ''));
    $companyName = trim((string) data_get($branding, 'company_name', ''));
    $fallbackTitle = trim((string) ($pageTitle ?: $siteName ?: $companyName ?: 'AIO Website'));
    $sectionTitle = trim((string) $__env->yieldContent('title'));
    $themeKey = strtoupper(trim((string) data_get($activeTheme, 'key', data_get($landingPage, 'theme_key', ''))));
    $generatedLandingTitles = $themeKey !== ''
        ? ["{$themeKey} Landing", "{$themeKey} Construction Landing"]
        : [];
    $documentTitle = $sectionTitle !== '' && ! in_array($sectionTitle, $generatedLandingTitles, true)
        ? $sectionTitle
        : $fallbackTitle;
    $fallbackDescription = trim((string) ($pageDescription ?: data_get($siteProfile, 'description', '')));
    $documentDescription = trim((string) $__env->yieldContent('meta_description', $fallbackDescription));
    $documentKeywords = trim((string) $__env->yieldContent('meta_keywords', (string) ($pageKeywords ?? '')));
    $faviconUrl = trim((string) data_get($branding, 'favicon_url', ''));
    $socialImageUrl = trim((string) data_get($branding, 'logo_url', ''));
    $robotsContent = trim((string) ($robots ?: ($isPreview ? 'noindex,nofollow' : '')));
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $documentTitle }}</title>
    @if($documentDescription !== '')
        <meta name="description" content="{{ $documentDescription }}">
    @endif
    @if($documentKeywords !== '')
        <meta name="keywords" content="{{ $documentKeywords }}">
    @endif
    @if($faviconUrl !== '')
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif
    @if($robotsContent !== '')
        <meta name="robots" content="{{ $robotsContent }}">
    @endif
    <meta property="og:type" content="{{ $openGraphType }}">
    <meta property="og:title" content="{{ $documentTitle }}">
    <meta property="og:site_name" content="{{ $siteName ?: $companyName ?: $documentTitle }}">
    @if($documentDescription !== '')
        <meta property="og:description" content="{{ $documentDescription }}">
    @endif
    @if(filled($canonicalUrl))
        <meta property="og:url" content="{{ $canonicalUrl }}">
    @endif
    @if($socialImageUrl !== '')
        <meta property="og:image" content="{{ $socialImageUrl }}">
    @endif
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $documentTitle }}">
    @if($documentDescription !== '')
        <meta name="twitter:description" content="{{ $documentDescription }}">
    @endif
    @if($socialImageUrl !== '')
        <meta name="twitter:image" content="{{ $socialImageUrl }}">
    @endif

    {{ $slot }}
    @stack('head')
    @include('partials.localized-seo')
</head>
