<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'DN202 Arc'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @include('theme-dn202::partials.styles')
    @stack('head')
</head>
<body>
    <div id="top" class="d202-page">
        @include('theme-dn202::partials.header')
        @yield('content')
        @include('theme-dn202::partials.footer')
    </div>
    @include('theme-dn202::partials.auth-modal')
    @include('theme-dn202::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
