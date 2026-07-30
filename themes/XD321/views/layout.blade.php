<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'XD321 Industrial')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-xd321::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="xd20-page">
        @include('theme-xd321::partials.header')
        @yield('content')
        @include('theme-xd321::partials.footer')
    </div>
    @include('theme-xd321::partials.auth-modal')
    @include('theme-xd321::partials.inline-editor')
    @include('theme-xd321::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
