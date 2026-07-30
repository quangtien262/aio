<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'XD0324 WolfArch')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-xd0324::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="xd324-page">
        @include('theme-xd0324::partials.header')
        @yield('content')
        @include('theme-xd0324::partials.footer')
    </div>
    @include('theme-xd0324::partials.auth-modal')
    @include('theme-xd0324::partials.inline-editor')
    @include('theme-xd0324::partials.shell-scripts')
    @stack('scripts')
</body>
</html>

