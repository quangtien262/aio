<!doctype html><html lang="{{ str_replace('_', '-', app()->getLocale()) }}"><x-storefront-head
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
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">@include('theme-shop602::partials.styles')
</x-storefront-head><body><div class="s602-page">@include('theme-shop602::partials.header')@yield('content')@include('theme-shop602::partials.footer')</div>@include('theme-xd0323::partials.auth-modal')@include('theme-xd0323::partials.inline-editor', ['canEditLanding' => $canEditLanding ?? false, 'editorLocales' => $editorLocales ?? []])@include('theme-shop602::partials.scripts')@if(isset($canEditLanding) && $canEditLanding)@include('theme-xd0301::partials.scripts')@endif @stack('scripts')</body></html>
