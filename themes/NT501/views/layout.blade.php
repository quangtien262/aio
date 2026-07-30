<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NT501 Interior Studio')</title>
    @include('themes.common.fonts.chakra-manrope')
    @include('theme-nt501::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="nt-page">
        @include('theme-nt501::partials.header')
        @yield('content')
        @include('theme-nt501::partials.footer')
    </div>
    @include('theme-nt501::partials.auth-modal')
    @include('theme-nt501::partials.inline-editor')
    @include('theme-nt501::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
