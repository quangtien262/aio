@php
    $blocks = collect($landingBlocks ?? [])->values();
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Bizgrow Logistics'))) ?: 'Bizgrow Logistics';
    $companyDescription = trim((string) ($branding['company_description'] ?? 'Giải pháp kho bãi, vận chuyển và hậu cần cho doanh nghiệp hiện đại.'));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $supportEmail = trim((string) ($branding['support_email'] ?? ''));
    $supportAddress = trim((string) ($branding['support_location'] ?? ''));
    $navItems = collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', [])))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => ['label' => (string) ($item['label'] ?? $item['title']), 'href' => (string) ($item['url'] ?? $item['href'] ?? '#'), 'children' => $item['children'] ?? []])
        ->values();
    if ($navItems->isEmpty()) {
        $navItems = $blocks->filter(fn ($block) => filled($block['anchor_id'] ?? null))->map(fn ($block) => ['label' => (string) data_get($block, 'data.subtitle', data_get($block, 'data.title', 'Menu')), 'href' => '#'.($block['anchor_id'] ?? ''), 'children' => []])->values();
    }
    $hero = $blocks->firstWhere('block_type', 'hero_slider') ?? [];
    $heroSlides = collect(data_get($hero, 'data.content.slides', []))
        ->whenEmpty(fn () => collect($hero['dynamic_items'] ?? []))
        ->values()
        ->all();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = [];
@endphp

@extends('theme-xd0312::layout')

@section('title', data_get($landingPage ?? [], 'meta_title') ?: $companyName)

@section('content')
<main>
    @foreach ($blocks->where('block_type', '!=', 'footer_contact') as $block)
        @php
            $data = $block['data'] ?? [];
            $content = $data['content'] ?? [];
            $settings = $block['settings'] ?? [];
            $media = $block['media'] ?? [];
            $anchor = $block['anchor_id'] ?: $block['block_type'];
        @endphp
        @includeIf('theme-xd0312::partials.blocks.'.($block['block_type'] ?? ''))
    @endforeach
</main>
@endsection

@push('scripts')
    @include('theme-xd0312::partials.scripts')
@endpush
