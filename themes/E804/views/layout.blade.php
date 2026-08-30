<!doctype html>
<html lang="{{ str_replace('_','-',app()->getLocale()) }}">
<x-storefront-head :site-profile="$siteProfile??null" :theme-shell-data="$themeShellData??[]" :active-theme="$activeTheme??null" :landing-page="$landingPage??null" :page-title="$pageTitle??null" :page-description="$pageDescription??null" :page-keywords="$pageKeywords??null" :canonical-url="$canonicalUrl??null" :hreflang-urls="$hreflangUrls??[]" :is-preview="$isPreview??false">
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">@include('theme-e804::partials.styles')
</x-storefront-head>
<body><div class="e804-page">@include('theme-e804::partials.header')@yield('content')@include('theme-e804::partials.footer')</div>
@include('theme-xd0323::partials.auth-modal')
<style>.xd-auth-modal *{box-sizing:border-box;font-family:"Be Vietnam Pro","Segoe UI",Roboto,Arial,sans-serif}.xd-auth-tab.is-active,.xd-auth-submit{background:#df1823;box-shadow:0 12px 26px rgba(223,24,35,.24)}</style>
@include('theme-xd0323::partials.inline-editor',['canEditLanding'=>$canEditLanding??false,'editorLocales'=>$editorLocales??[]])
@include('theme-e804::partials.scripts')
@if(isset($canEditLanding)&&$canEditLanding)@include('theme-xd0301::partials.scripts')@endif
@stack('scripts')</body></html>
