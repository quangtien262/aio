<!doctype html><html lang="{{ str_replace('_','-',app()->getLocale()) }}"><x-storefront-head
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
    <script>document.documentElement.classList.add('x325-js')</script><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">@include('theme-xd0325::partials.styles')
</x-storefront-head><body><div id="top" class="x325-page">@include('theme-xd0325::partials.header') @yield('content') @include('theme-xd0325::partials.footer')</div>@include('theme-xd0325::partials.shell-scripts') @stack('scripts')</body></html>
