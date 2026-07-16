<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'XD0323 Construction')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-XD0323::partials.styles')
    @stack('head')
</head>
<body>
    <div id="top" class="c323-page">
        @include('theme-XD0323::partials.header')
        @yield('content')
        @include('theme-XD0323::partials.footer')
    </div>
    @include('theme-XD0323::partials.auth-modal')
    @include('theme-XD0323::partials.inline-editor')
    @include('theme-XD0323::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
