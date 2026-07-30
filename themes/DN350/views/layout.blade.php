<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($themeShellData ?? [], 'branding.company_name', data_get($siteProfile ?? [], 'site_name', 'Prinash')))</title>
    @if(filled($pageDescription ?? null))<meta name="description" content="{{ $pageDescription }}">@endif
    @if(filled($pageKeywords ?? null))<meta name="keywords" content="{{ $pageKeywords }}">@endif
    @include('themes.common.fonts.chakra-manrope')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @include('theme-dn350::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
<div id="top" class="dn350-page">
    @include('theme-dn350::partials.header')
    @yield('content')
    @include('theme-dn350::partials.footer')
</div>
@include('theme-dn350::partials.auth-modal')
@if ($canEditLanding ?? false)
    @include('theme-xd0302::partials.inline-editor')
@endif
@include('theme-dn350::partials.shell-scripts')
@stack('scripts')
</body>
</html>
