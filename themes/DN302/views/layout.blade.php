<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($themeShellData ?? [], 'branding.company_name', data_get($siteProfile ?? [], 'site_name', 'Website')))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @include('theme-dn302::partials.styles')
    @stack('head')
</head>
<body>
    <div id="top" class="dn302-page">
        @include('theme-dn302::partials.header')
        @yield('content')
        @include('theme-dn302::partials.footer')
    </div>
    @include('theme-dn302::partials.auth-modal')
    @include('theme-dn302::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
