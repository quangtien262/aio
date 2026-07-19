<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'SHOP601'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-shop601::partials.styles')
    @stack('head')
</head>
<body>
    <div id="top" class="s601-page">
        @include('theme-shop601::partials.header')
        @yield('content')
        @include('theme-shop601::partials.footer')
    </div>
    @include('theme-xd0323::partials.auth-modal')
    @include('theme-xd0323::partials.inline-editor', ['canEditLanding' => $canEditLanding ?? false, 'editorLocales' => $editorLocales ?? []])
    @include('theme-shop601::partials.scripts')
    <script async src="https://www.tiktok.com/embed.js"></script>
    @if (isset($canEditLanding) && $canEditLanding)
        @include('theme-xd0301::partials.scripts')
    @endif
    @stack('scripts')
</body>
</html>
