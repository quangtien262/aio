<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'XD0303 Service Operations')</title>
    @include('theme-xd0303::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="xd3-page">
        @include('theme-xd0303::partials.header')
        @yield('content')
        @include('theme-xd0303::partials.footer')
    </div>
    @include('theme-xd0303::partials.auth-modal')
    @include('theme-xd0303::partials.inline-editor')
    @include('theme-xd0303::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
