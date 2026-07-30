<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'Sudes Aquarium'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-ca0050::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
<div class="ca50-page theme-ca0050">
    @include('theme-ca0050::partials.header')
    @yield('content')
    @include('theme-ca0050::partials.footer')
</div>
@include('theme-xd0323::partials.auth-modal')
@include('theme-xd0323::partials.inline-editor', ['canEditLanding' => $canEditLanding ?? false, 'editorLocales' => $editorLocales ?? []])
@include('theme-ca0050::partials.shell-scripts')
@if(isset($canEditLanding) && $canEditLanding)@include('theme-xd0301::partials.scripts')@endif
@stack('scripts')
</body>
</html>
