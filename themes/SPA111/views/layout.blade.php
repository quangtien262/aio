<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<x-storefront-head
    :site-profile="$siteProfile ?? null"
    :theme-shell-data="$themeShellData ?? []"
    :active-theme="$activeTheme ?? null"
    :landing-page="$landingPage ?? null"
    :page-title="$pageTitle ?? null"
    :page-description="$pageDescription ?? null"
    :page-keywords="$pageKeywords ?? null"
    :canonical-url="$canonicalUrl ?? null"
    :hreflang-urls="$hreflangUrls ?? []"
    :is-preview="$isPreview ?? false"
>
    <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
        @include('theme-spa111::partials.styles')
</x-storefront-head>
<body>
    <div class="sp11-page">
        @include('theme-spa111::partials.header')
        @yield('content')
        @include('theme-spa111::partials.footer')
    </div>
    @php $canEditLanding = $canEditLanding ?? false; $editorLocales = $editorLocales ?? []; @endphp
    @include('theme-spa111::partials.auth-modal')
    @include('theme-spa111::partials.inline-editor')
    @include('theme-spa111::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
