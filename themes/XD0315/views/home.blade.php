@php
    $blocks = collect($landingBlocks ?? [])->values();
    $branding = (array) data_get($themeShellData ?? [], 'branding', data_get($themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', [])));
    $companyName = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Build Bench'))) ?: 'Build Bench';
    $companyDescription = trim((string) ($branding['company_description'] ?? data_get($siteProfile ?? [], 'description', 'Tieu chi hang dau cua chung toi la tao ra cong trinh chat luong, dung tien do va dung mong doi cua khach hang.'))) ?: 'Tieu chi hang dau cua chung toi la tao ra cong trinh chat luong, dung tien do va dung mong doi cua khach hang.';
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $hotline = trim((string) ($branding['support_hotline'] ?? '(028) 62563737')) ?: '(028) 62563737';
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $supportEmail = trim((string) ($branding['support_email'] ?? $branding['email'] ?? 'admin@demo.web30s.vn')) ?: 'admin@demo.web30s.vn';
    $supportAddress = trim((string) ($branding['support_location'] ?? $branding['address'] ?? '196 Nguyen Dinh Chieu, P.Vo Thi Sau, Q.3, TP.HCM')) ?: '196 Nguyen Dinh Chieu, P.Vo Thi Sau, Q.3, TP.HCM';

    $localizeMenuUrl = function (?string $href): string {
        $href = trim((string) $href);
        if ($href === '' || $href === '#' || str_starts_with($href, '#') || preg_match('/^(https?:)?\/\//i', $href) || preg_match('/^(mailto|tel):/i', $href)) {
            return $href !== '' ? $href : '#';
        }
        $path = trim((string) (parse_url($href, PHP_URL_PATH) ?: ''), '/');
        $query = parse_url($href, PHP_URL_QUERY);
        $fragment = parse_url($href, PHP_URL_FRAGMENT);
        if ($path === '') {
            return route('site.home').($fragment ? '#'.$fragment : '');
        }
        $segments = explode('/', $path);
        if (! in_array($segments[0] ?? '', \App\Support\FrontendLocalization::knownLocaleCodes(), true)) {
            array_unshift($segments, app()->getLocale());
        }
        return url('/'.implode('/', $segments)).($query ? '?'.$query : '').($fragment ? '#'.$fragment : '');
    };

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
            ['label' => 'Du an tieu bieu', 'href' => '#du-an', 'children' => []],
            ['label' => 'Dich vu', 'href' => '#dich-vu', 'children' => []],
            ['label' => 'Tin tuc', 'href' => route('site.blog.index'), 'children' => []],
            ['label' => 'Lien he', 'href' => '#footer', 'children' => []],
        ]);
    }

    $hero = $blocks->firstWhere('block_type', 'hero_slider') ?? [];
    $heroSlides = collect(data_get($hero, 'data.content.slides', []))
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect(data_get($hero, 'dynamic_items', []))->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
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

@extends('theme-xd0315::layout')

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
                ? '<button type="button" class="xd-edit-block af15-edit-block" data-xd-edit-block="'.e((string) $block['id']).'">Sua khoi</button>'
                : '';
        @endphp

        @includeIf('theme-xd0315::partials.blocks.'.($block['block_type'] ?? ''))
    @endforeach
</main>
@endsection

@push('scripts')
    @include('theme-xd0315::partials.scripts')
@endpush


