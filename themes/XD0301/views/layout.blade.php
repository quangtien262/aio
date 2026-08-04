<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', data_get($themeShellData ?? [], 'branding.company_name', 'AIO Website')))</title>
    @include('theme-xd0301::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
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
