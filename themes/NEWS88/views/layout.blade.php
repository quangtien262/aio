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
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap&subset=vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    @include('theme-news88::partials.styles')
    @if(auth('admin')->check() && request('mod') === 'admin')
        @include('theme-foot403::partials.inline-editor-styles')
    @endif
</x-storefront-head>
<style>
    pre, code {
        font-family: 'Be Vietnam Pro', sans-serif;
    }
    pre {
        white-space: pre-wrap;
        word-wrap: break-word;
        background: #ededed;
        padding: 15px;
        border: 1px #9f9f9f solid;
    }
</style>
<body>
@php
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockPayload = $canEditLanding ? collect($landingBlocks ?? [])->keyBy('id')->toArray() : [];
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $editorLocales = collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn(array $locale): bool => (bool) ($locale['is_enabled_for_editing'] ?? $locale['is_active'] ?? $locale['active'] ?? false))
        ->map(fn(array $locale): array => [
            'code' => $locale['code'] ?? '',
            'label' => $locale['native_name'] ?? $locale['name'] ?? $locale['label'] ?? strtoupper($locale['code'] ?? ''),
            'is_published' => (bool) ($locale['is_published'] ?? false),
        ])
        ->filter(fn(array $locale): bool => filled($locale['code']))->values()->all();
@endphp
<div class="n88-page" id="top">
    @include('theme-news88::partials.header')
    @yield('content')
    @include('theme-news88::partials.footer')
</div>
@include('theme-xd0302::partials.inline-editor', ['canEditLanding' => $canEditLanding, 'editorLocales' => $editorLocales])
@include('theme-news88::partials.scripts')
@if($canEditLanding) @include('theme-xd0302::partials.scripts', ['hero' => $hero ?? [], 'heroSlides' => $heroSlides ?? []]) @endif
@stack('scripts')
</body>
</html>
