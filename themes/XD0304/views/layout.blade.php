<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'XD0304 Logistics')</title>
    @include('theme-xd0304::partials.styles')
    @stack('head')
</head>
<body>
    <div id="top" class="xd4-page">
        @include('theme-xd0304::partials.header')
        @yield('content')
        @include('theme-xd0304::partials.footer')
    </div>
    @include('theme-xd0304::partials.auth-modal')
    @include('theme-xd0304::partials.inline-editor')
    @include('theme-xd0304::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
