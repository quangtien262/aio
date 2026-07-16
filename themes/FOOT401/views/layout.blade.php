<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'FOOT401 Restaurant')</title>
    @include('theme-foot401::partials.styles')
    @stack('head')
</head>
<body>
    <div id="top" class="foot-page">
        @include('theme-foot401::partials.header')
        @yield('content')
        @include('theme-foot401::partials.footer')
    </div>
    @include('theme-foot401::partials.auth-modal')
    @include('theme-foot401::partials.inline-editor')
    @include('theme-foot401::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
