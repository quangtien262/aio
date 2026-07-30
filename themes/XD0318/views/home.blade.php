@php
    $blocks = collect($landingBlocks ?? [])->values();
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Fast Gear'))) ?: 'Fast Gear';
    $companyDescription = trim((string) ($branding['company_description'] ?? data_get($siteProfile ?? [], 'description', 'Fast Gear cung cap giai phap logistics, van chuyen va giao nhan hang hoa nhanh chong, linh hoat cho doanh nghiep.'))) ?: 'Fast Gear cung cap giai phap logistics, van chuyen va giao nhan hang hoa nhanh chong, linh hoat cho doanh nghiep.';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $supportEmail = trim((string) ($branding['support_email'] ?? $branding['email'] ?? ''));
    $supportAddress = trim((string) ($branding['support_location'] ?? $branding['address'] ?? ''));

    $localizeMenuUrl = static fn (?string $href): string => \App\Support\FrontendRouteUrl::localized($href);

    $normalizeNavItem = function (array $item) use ($localizeMenuUrl): array {
        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
            'href' => $localizeMenuUrl((string) ($item['url'] ?? $item['href'] ?? '#')),
            'target' => $item['target'] ?? '_self',
            'children' => collect($item['children'] ?? [])->filter(fn ($child) => is_array($child))->map(fn ($child) => [
                'label' => (string) ($child['label'] ?? $child['title'] ?? 'Menu'),
                'href' => $localizeMenuUrl((string) ($child['url'] ?? $child['href'] ?? '#')),
                'target' => $child['target'] ?? '_self',
            ])->values()->all(),
        ];
    };

    $navItems = collect(data_get($themeHomeData ?? [], 'top_menu', []))
        ->whenEmpty(fn () => collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item) => $normalizeNavItem($item))
        ->values();

    if ($navItems->isEmpty()) {
        $navItems = collect([
            ['label' => 'Trang chu', 'href' => route('site.home'), 'children' => []],
            ['label' => 'Gioi thieu', 'href' => '#gioi-thieu', 'children' => []],
            ['label' => 'Dich vu', 'href' => '#dich-vu', 'children' => []],
            ['label' => 'Tin tuc', 'href' => '#tin-tuc', 'children' => []],
            ['label' => 'Thu vien', 'href' => '#thu-vien', 'children' => []],
            ['label' => 'Lien he', 'href' => '#lien-he', 'children' => []],
        ]);
    }

    $hero = $blocks->firstWhere('block_type', 'hero_slider') ?? [];
    $heroSlides = collect(data_get($hero, 'data.content.slides', []))
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect($hero['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();

    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))
        ->map(fn (array $locale): array => ['code' => (string) ($locale['code'] ?? ''), 'label' => (string) (($locale['native_name'] ?? null) ?: ($locale['name'] ?? $locale['code'] ?? ''))])
        ->filter(fn (array $locale): bool => $locale['code'] !== '')
        ->values()
        ->all() : [];
@endphp

@extends('theme-xd0318::layout')

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
            $editButton = $canEditLanding && filled($block['id'] ?? null)
                ? '<button type="button" class="xd-edit-block fg18-edit-block" data-xd-edit-block="'.e((string) $block['id']).'">Sua khoi</button>'
                : '';
        @endphp

        @includeIf('theme-xd0318::partials.blocks.'.($block['block_type'] ?? ''))
    @endforeach
</main>
@endsection

@push('scripts')
    @include('theme-xd0318::partials.scripts')
@endpush
