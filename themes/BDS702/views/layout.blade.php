<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'Aurelia Estates'))</title>
    @if(filled($pageDescription ?? null))<meta name="description" content="{{ $pageDescription }}">@endif
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @include('theme-bds702::partials.styles')
    @if ($canEditLanding ?? false) @include('theme-foot403::partials.inline-editor-styles') @endif
    @stack('head') @include('partials.localized-seo')
</head>
<body><div id="top" class="b702-page">@include('theme-bds702::partials.header') @yield('content') @include('theme-bds702::partials.footer')</div>
@include('theme-xd0323::partials.auth-modal')
@if ($canEditLanding ?? false) @include('theme-xd0302::partials.inline-editor') @endif
@include('theme-bds702::partials.scripts') @stack('scripts')</body></html>
