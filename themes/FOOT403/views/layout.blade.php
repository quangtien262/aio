<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dola Restaurant')</title>
    @include('theme-foot403::partials.styles')
    @stack('head')
</head>
<body><div class="dr-page" id="top">@include('theme-foot403::partials.header')<main>@yield('content')</main>@include('theme-foot403::partials.footer')</div>@include('theme-foot403::partials.order-modal')@include('theme-foot403::partials.scripts')@stack('scripts')</body>
</html>
