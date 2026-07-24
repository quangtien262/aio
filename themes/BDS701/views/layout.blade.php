<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'Delta Platinum'))</title>
    @if(filled($pageDescription ?? null))<meta name="description" content="{{ $pageDescription }}">@endif
    @if(filled($pageKeywords ?? null))<meta name="keywords" content="{{ $pageKeywords }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @include('theme-bds701::partials.styles')
    @if ($canEditLanding ?? false)
        @include('theme-foot403::partials.inline-editor-styles')
    @endif
    @stack('head')
</head>
<body>
<div id="top" class="bds-page">
    @include('theme-bds701::partials.header')
    @yield('content')
    @include('theme-bds701::partials.footer')
</div>
@if ($canEditLanding ?? false)
    @include('theme-xd0302::partials.inline-editor')
@endif
@include('theme-bds701::partials.scripts')
@stack('scripts')
</body>
</html>
