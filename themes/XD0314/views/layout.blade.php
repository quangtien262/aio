<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'XD0314 Build Bench')</title>
    @include('theme-xd0314::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="bb14-page">
        @include('theme-xd0314::partials.header')

        @yield('content')

        @include('theme-xd0314::partials.footer')
    </div>

    @include('theme-xd0314::partials.auth-modal')
    @include('theme-xd0314::partials.inline-editor')
    @include('theme-xd0314::partials.shell-scripts')
    @stack('scripts')
</body>
</html>

