<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SPA502 HALU Cosmetics')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-spa502::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="spa502-page">
        @include('theme-spa502::partials.header')
        @yield('content')
        @include('theme-spa502::partials.footer')
    </div>
    @php
        $canEditLanding = $canEditLanding ?? false;
        $editorLocales = $editorLocales ?? [];
    @endphp
    @include('theme-spa502::partials.auth-modal')
    @include('theme-spa502::partials.inline-editor')
    @include('theme-spa502::partials.shell-scripts')
    @stack('scripts')
</body>
</html>

