<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TH0050 Premium Wellness')</title>
    @include('theme-th0050::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="xd-page">
        @include('theme-th0050::partials.header')

        @yield('content')

        @include('theme-th0050::partials.footer')
    </div>

    @include('theme-th0050::partials.auth-modal')
    @include('theme-th0050::partials.inline-editor')
    @include('theme-th0050::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
