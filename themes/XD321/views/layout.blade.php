@php
    $xdShell = $themeShellData ?? $themeHomeData ?? [];
    $xdBranding = (array) data_get($xdShell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = $companyName ?? $xdBranding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', '');
    $companyDescription = $companyDescription ?? $xdBranding['company_description'] ?? '';
    $supportEmail = $supportEmail ?? $xdBranding['support_email'] ?? '';
    $supportAddress = $supportAddress ?? $xdBranding['support_location'] ?? '';
    $hotline = $hotline ?? $xdBranding['support_hotline'] ?? '';
    $phoneHref = $phoneHref ?? preg_replace('/\D+/', '', $hotline);
    $logoUrl = $logoUrl ?? $xdBranding['logo_url'] ?? '';
    $logoAlt = $logoAlt ?? $companyName;
@endphp
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
    <script>document.documentElement.classList.add('x321-js')</script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
        @include('theme-xd321::partials.styles')
</x-storefront-head>
<body>
    <div id="top" class="xd20-page">
        @include('theme-xd321::partials.header')
        @yield('content')
        @include('theme-xd321::partials.footer')
    </div>
    @include('theme-xd321::partials.auth-modal')
    @include('theme-xd321::partials.inline-editor')
    @include('theme-xd321::partials.shell-scripts')
    @stack('scripts')
</body>
</html>
