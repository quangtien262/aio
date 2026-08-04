@php
    $canEditLanding = auth('admin')->check()
        && request('mod') === 'admin'
        && is_array($landingPage ?? null);
    $nt502EditorBlocks = collect($landingBlocks ?? [])
        ->filter(fn (array $block): bool => filled($block['id'] ?? null))
        ->values();
    $editorLocales = collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn (array $locale): bool => (bool) ($locale['active'] ?? true))
        ->map(fn (array $locale): array => [
            'code' => $locale['code'] ?? '',
            'label' => $locale['label'] ?? ($locale['code'] ?? ''),
        ])
        ->filter(fn (array $locale): bool => filled($locale['code']))
        ->values()
        ->all();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
        @include('theme-nt502::partials.styles')
</x-storefront-head>
<body>
    <div class="n502-page">
        @include('theme-nt502::partials.header')
        @yield('content')
        @include('theme-nt502::partials.footer')
    </div>

    @include('theme-xd0323::partials.auth-modal')
    @include('theme-nt502::partials.scripts')

    @if($canEditLanding && $nt502EditorBlocks->isNotEmpty())
        @include('site.partials.landing-admin-editor', [
            'landingBlocks' => $nt502EditorBlocks,
            'blockPayload' => $nt502EditorBlocks->keyBy('id')->toArray(),
            'blockUpdateUrlTemplate' => route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']),
            'blockSourcePreviewUrlTemplate' => route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']),
            'editorLocales' => $editorLocales,
            'hasButtons' => false,
            'hasEditor' => false,
        ])
    @endif

    @stack('scripts')
</body>
</html>
