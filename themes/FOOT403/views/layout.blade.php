<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '')</title>
    @include('theme-foot403::partials.styles')
    @if($canEditLanding ?? false)
        @include('theme-foot403::partials.inline-editor-styles')
    @endif
    @stack('head')
</head>
<body><div class="dr-page" id="top">@include('theme-foot403::partials.header')<main>@yield('content')</main>@include('theme-foot403::partials.footer')</div>@include('theme-foot403::partials.order-modal')@include('theme-foot403::partials.auth-modal')@if($canEditLanding ?? false)@include('theme-xd0302::partials.inline-editor')@endif@include('theme-foot403::partials.scripts')@stack('scripts')</body>
</html>
