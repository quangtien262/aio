<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'Dola Watch'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-ec910::partials.styles')
    @stack('head')
</head>
<body>
@php
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $editorLocales = collect(\App\Support\FrontendLocalization::supportedLocales())->map(fn ($locale) => ['code' => $locale, 'label' => strtoupper($locale)])->all();
@endphp
<div class="ec10-page">
    @include('theme-ec910::partials.header')
    @yield('content')
    @include('theme-ec910::partials.footer')
</div>
@include('theme-xd0323::partials.auth-modal')
@include('theme-xd0323::partials.inline-editor', ['canEditLanding' => $canEditLanding, 'editorLocales' => $editorLocales])
@include('theme-ec910::partials.scripts')
@if($canEditLanding) @include('theme-xd0301::partials.scripts') @endif
@stack('scripts')
</body>
</html>
