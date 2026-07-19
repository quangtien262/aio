@php
    $blocks = collect($landingBlocks ?? [])->where('is_visible', '!=', false)->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $heroSlides = [];
    $hero = ['settings' => ['autoplay_ms' => 5600]];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn ($locale) => (bool) ($locale['is_active'] ?? false))->map(fn ($locale) => ['code' => (string) ($locale['code'] ?? ''), 'label' => (string) (($locale['native_name'] ?? null) ?: ($locale['name'] ?? $locale['code'] ?? ''))])->filter(fn ($locale) => $locale['code'] !== '')->values()->all() : [];
@endphp
@extends('theme-th0050::layout')
@section('title', data_get($landingPage ?? [], 'meta_title') ?: data_get($landingPage ?? [], 'title', 'TH0050 Premium Wellness'))
@section('content')
<main>@foreach($blocks as $block)@php
    $data = $block['data'] ?? []; $content = $data['content'] ?? []; $settings = $block['settings'] ?? []; $media = $block['media'] ?? []; $anchor = $block['anchor_id'] ?: $block['block_type'];
    $items = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? []))->filter(fn ($item) => is_array($item))->values();
    $editButton = $canEditLanding && filled($block['id'] ?? null) ? '<button type="button" class="xd-edit-block" data-xd-edit-block="'.e((string) $block['id']).'">Sửa khối</button>' : '';
@endphp
    @includeIf('theme-th0050::partials.blocks.'.($block['block_type'] ?? ''))
@endforeach</main>
@endsection
@push('scripts')@include('theme-th0050::partials.scripts')@endpush
