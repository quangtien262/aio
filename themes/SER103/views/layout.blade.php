<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', data_get($siteProfile ?? [], 'site_name', 'Bøhu Wedding'))</title>
    <meta name="description" content="@yield('meta_description', data_get($siteProfile ?? [], 'description', 'Dịch vụ tổ chức, trang trí và ghi lại những khoảnh khắc cưới đáng nhớ.'))">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Allura&family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-ser103::partials.styles')
    @stack('head')
    @include('partials.localized-seo')
</head>
<body>
    <div id="top" class="ser103-page">
        @include('theme-ser103::partials.header')
        @yield('content')
        @include('theme-ser103::partials.footer')
    </div>
    @php
        $canEditLanding = $canEditLanding ?? false;
        $editorLocales = $editorLocales ?? [];
        $heroSlides = $heroSlides ?? [];
        $hero = $hero ?? [];
        $blockPayload = $blockPayload ?? [];
        $blockUpdateUrlTemplate = $blockUpdateUrlTemplate ?? '';
        $blockSourcePreviewUrlTemplate = $blockSourcePreviewUrlTemplate ?? '';
    @endphp
    @include('theme-ser103::partials.auth-modal')
    @include('theme-ser103::partials.booking-modal')
    @include('theme-ser103::partials.inline-editor')
    @include('theme-ser103::partials.scripts')
    @stack('scripts')
</body>
</html>
