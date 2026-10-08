@php
    $shell = $themeShellData ?? [];
    $normalizeNavItem = function (array $item) use (&$normalizeNavItem): array {
        return [
            'label' => $item['label'] ?? $item['title'] ?? '',
            'href' => \App\Support\FrontendRouteUrl::localized($item['url'] ?? $item['href'] ?? '#'),
            'target' => $item['target'] ?? '_self',
            'children' => collect($item['children'] ?? [])->filter(fn ($child) => is_array($child))->map($normalizeNavItem)->all(),
        ];
    };
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map($normalizeNavItem)->values();
    if (! $navItems->contains(fn ($item) => rtrim($item['href'], '/') === rtrim(route('site.home'), '/'))) {
        $navItems->prepend(['label' => __('theme_contact_page.home'), 'href' => route('site.home'), 'target' => '_self', 'children' => []]);
    }
    $productNav = collect(data_get($menus ?? [], 'product-navigation', []))->filter(fn ($item) => is_array($item))->map($normalizeNavItem)->all();
    if ($productNav !== []) {
        $productIndex = $navItems->search(fn ($item) => in_array(mb_strtolower($item['label']), ['sản phẩm', 'san pham', 'products', 'product'], true));
        if ($productIndex === false) {
            $navItems->splice(1, 0, [['label' => __('XD0313.service.products'), 'href' => route('site.catalog.search'), 'target' => '_self', 'children' => $productNav]]);
        } else {
            $navItems = $navItems->map(fn ($item, $index) => $index === $productIndex && empty($item['children']) ? array_replace($item, ['children' => $productNav]) : $item);
        }
    }
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-XD0313-cms';
@endphp

@extends('theme-xd0313::layout')

@push('head')
    @include('theme-xd0313::partials.contact-page-styles')
@endpush

@section('content')
    @include('themes.common.contact')
@endsection

@push('scripts')
<script>
    document.querySelector('.tc-contact-notice, .tc-contact-error')?.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center'});
</script>
@endpush
