@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? ''));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? ''));
    $address = trim((string) ($branding['support_location'] ?? ''));

    $localizeMenuUrl = static fn (?string $href): string => \App\Support\FrontendRouteUrl::localized($href);

    $repairXdLabel = static function (string $label): string {
        $label = trim($label);

        return strtr($label, [
            'Trang chủ' => 'Trang chủ',
            'TRANG CHÁ»§' => 'TRANG CHỦ',
            'trang chủ' => 'trang chủ',
            'Sản phẩm' => 'Sản phẩm',
            'SảN PHÁº©M' => 'SẢN PHẨM',
            'SÁº£N PHÁº©M' => 'SẢN PHẨM',
            'sản phẩm' => 'sản phẩm',
            'sản phẩm' => 'sản phẩm',
            'Sản phẩm' => 'Sản phẩm',
            'Tài khoản' => 'Tài khoản',
            'Tà I KHOảN' => 'TÀI KHOẢN',
        ]);
    };

    $normalizeNavItem = function (array $item) use (&$normalizeNavItem, $localizeMenuUrl, $repairXdLabel): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => $repairXdLabel((string) ($item['label'] ?? $item['title'] ?? 'Menu')),
            'href' => $localizeMenuUrl($href),
            'target' => $item['target'] ?? '_self',
            'active' => false,
            'children' => collect($item['children'] ?? [])
                ->filter(fn ($child): bool => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))
                ->map(fn (array $child): array => $normalizeNavItem($child))
                ->values()
                ->all(),
        ];
    };

    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    $homeUrl = route('site.home');
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chủ', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.4c23dc9bef7f79b4', 'Trang chủ'),
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

    if (false && ! $hasProductItem && \Illuminate\Support\Facades\Schema::hasTable('catalog_categories') && \Illuminate\Support\Facades\Schema::hasTable('catalog_products')) {
        $productCategories = \App\Models\CatalogCategory::query()
            ->with(['children' => fn ($query) => $query
                ->where('is_active', true)
                ->withCount(['products' => fn ($productQuery) => $productQuery->where('is_active', true)])
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->withCount(['products' => fn ($query) => $query->where('is_active', true)])
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn ($category): bool => (int) $category->products_count > 0 || $category->children->contains(fn ($child): bool => (int) $child->products_count > 0))
            ->take(8)
            ->values();

        if ($productCategories->isNotEmpty()) {
            $productMenuItem = [
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productCategories
                    ->map(fn ($category): array => [
                        'label' => (string) $category->name,
                        'href' => route('site.catalog.category', ['slug' => $category->slug]),
                        'target' => '_self',
                        'active' => false,
                        'children' => $category->children
                            ->filter(fn ($child): bool => (int) $child->products_count > 0)
                            ->take(8)
                            ->map(fn ($child): array => [
                                'label' => (string) $child->name,
                                'href' => route('site.catalog.category', ['slug' => $child->slug]),
                                'target' => '_self',
                                'active' => false,
                                'children' => [],
                            ])
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
            $navItems = collect($navArray);
        }
    }

    $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', []))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item): array => $normalizeNavItem($item))
        ->values();

    if ($productNavigationItems->isNotEmpty()) {
        if ($hasProductItem) {
            $navItems = $navItems
                ->map(function (array $item) use ($productNavigationItems): array {
                    $label = mb_strtolower(trim((string) ($item['label'] ?? '')));

                    if (in_array($label, ['sản phẩm', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                        $item['children'] = $productNavigationItems->all();
                    }

                    return $item;
                })
                ->values();
        } else {
            $productMenuItem = [
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.571ef44479d97bfd', 'Sản phẩm'),
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chủ', 'home'], true));
            $navArray = $navItems->values()->all();
            array_splice($navArray, $homeIndex === false ? 0 : $homeIndex + 1, 0, [$productMenuItem]);
            $navItems = collect($navArray);
        }
    }

    $currentUrl = rtrim(url()->current(), '/');
    $navItems = $navItems->map(function (array $item) use ($currentUrl): array {
        $href = (string) ($item['href'] ?? '#');
        $absoluteHref = str_starts_with($href, 'http') ? rtrim($href, '/') : rtrim(url($href), '/');
        $item['active'] = $href !== '#' && $absoluteHref === $currentUrl;

        return $item;
    })->values();

    $isServiceListing = ($contentType ?? null) === 'services';
    $isServiceDetail = ($contentType ?? null) === 'service';
    $isPostListing = ($contentType ?? null) === 'posts';
    $entrySlug = (string) ($entry->slug ?? '');
    $isContactPage = ! $isServiceListing
        && ! $isServiceDetail
        && ! $isPostListing
        && in_array($entrySlug, ['lien-he', 'contact'], true);
    $title = $pageTitle ?? ($entry->title ?? data_get($siteProfile, 'site_name', 'Arkit'));
    $description = $pageDescription ?? ($entry->excerpt ?? '');
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-xd0320-cms';
@endphp

@extends('theme-xd0320::layout')

@section('title', $title)

@if (!empty($description))
    @push('head')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif

@push('head')
    <style>
.xd20-contact{padding:36px 0 64px;background:#f5f5f4;color:#202325}.xd20-contact .xd-container{width:min(1240px,calc(100% - 48px));margin:auto}.xd20-contact .xd-cms-hero{padding:0 0 26px;margin:0 0 28px;border-bottom:1px solid #dfe1e2}.xd20-contact .xd-kicker{display:block;color:#c82716;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;margin:0 0 12px}.xd20-contact .xd-cms-hero .xd-kicker{display:none}.xd20-contact h1{margin:0;font-size:clamp(28px,3vw,42px);line-height:1.2}.xd20-contact .xd-cms-hero p{margin:12px 0 0;font-size:15px;line-height:1.7;color:#62686d;max-width:760px}.xd20-contact .xd-contact-page{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);gap:28px;align-items:start}.xd20-contact .xd-contact-panel,.xd20-contact .xd-contact-form-card{padding:30px;border-radius:8px;min-width:0}.xd20-contact .xd-contact-panel{background:#202325;color:#fff}.xd20-contact .xd-contact-panel .xd-kicker{color:#ff9489}.xd20-contact h2{font-size:25px;line-height:1.35;margin:0 0 16px}.xd20-contact .xd-contact-panel p{font-size:14px;line-height:1.8;color:#cbd0d3;margin:0 0 24px}.xd20-contact .xd-contact-methods{list-style:none;padding:0;margin:0}.xd20-contact .xd-contact-method{display:grid;grid-template-columns:42px minmax(0,1fr);gap:14px;align-items:center;padding:18px 0;border-top:1px solid #ffffff24}.xd20-contact .xd-contact-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:6px;background:#ffffff0d;color:#ff9489}.xd20-contact .xd-contact-icon svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.xd20-contact .xd-contact-method small{display:block;color:#b8bec3;font-size:12px;margin-bottom:5px}.xd20-contact .xd-contact-method a,.xd20-contact .xd-contact-method div>span{color:#fff;font-size:15px;line-height:1.6;font-weight:500;text-decoration:none;overflow-wrap:anywhere}.xd20-contact .xd-contact-method a:hover{text-decoration:underline}.xd20-contact .xd-contact-note{border-top:1px solid #ffffff24;padding-top:20px;margin-top:4px;font-size:13px;line-height:1.7}.xd20-contact .xd-contact-note strong{display:block;margin-bottom:8px;font-weight:600}.xd20-contact .xd-contact-note span{color:#b8bec3}.xd20-contact .xd-contact-form-card{background:#fff;border:1px solid #e2e3e4}.xd20-contact .xd-contact-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.xd20-contact .xd-contact-field{display:flex;flex-direction:column;gap:8px;min-width:0;font-size:13px;font-weight:600}.xd20-contact .xd-contact-field:nth-of-type(n+3){grid-column:1/-1}.xd20-contact input:not([type=hidden]),.xd20-contact textarea{box-sizing:border-box;width:100%;border:1px solid #cdd2d5;border-radius:5px;background:#fff;color:#202325;font:inherit;font-size:15px;font-weight:400;padding:11px 13px;outline:none}.xd20-contact input:not([type=hidden]){height:46px}.xd20-contact textarea{min-height:140px;resize:vertical;line-height:1.6}.xd20-contact input:focus,.xd20-contact textarea:focus{border-color:#c82716;box-shadow:0 0 0 3px #eb29161a}.xd20-contact .xd-contact-submit{grid-column:1/-1;justify-self:start;min-height:46px;padding:12px 26px;border:0;border-radius:5px;background:#c82716;color:#fff;font:inherit;font-size:14px;font-weight:700;cursor:pointer}.xd20-contact .xd-contact-submit:hover{background:#aa2012}.xd20-contact .xd-contact-submit:focus-visible,.xd20-contact a:focus-visible{outline:3px solid #eb2916;outline-offset:3px}.xd20-contact .xd-contact-alert,.xd20-contact .xd-contact-errors{padding:14px 16px;margin:0 0 20px;border-radius:5px;font-size:14px;line-height:1.6}.xd20-contact .xd-contact-alert{background:#edf8f1;color:#175536}.xd20-contact .xd-contact-errors{background:#fff0ed;color:#a32317}.xd20-contact .xd-contact-errors ul{padding-left:20px;margin:8px 0 0}
@media(max-width:900px){.xd20-contact .xd-contact-page{grid-template-columns:1fr}}@media(max-width:600px){.xd20-contact{padding:24px 0 40px}.xd20-contact .xd-container{width:calc(100% - 32px)}.xd20-contact .xd-contact-panel,.xd20-contact .xd-contact-form-card{padding:22px}.xd20-contact .xd-contact-form{grid-template-columns:1fr}.xd20-contact .xd-contact-submit{width:100%}.xd20-contact h2{font-size:23px}}
    </style>
@endpush

@section('content')
<main class="xd-page-main xd20-contact">
            <div class="xd-container">
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.416dc399394e8648', 'Liên hệ') }}</span>
                            <h1>{{ $entry->title }}</h1>
                            @if (!empty($entry->excerpt))
                                <p>{{ $entry->excerpt }}</p>
                            @else
                                <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.31d615a8a0d9d930', 'Gửi nhu cầu tư vấn, thiết kế hoặc thi công. Đội ngũ XD0320 sẽ phản hồi trong thời gian sớm nhất.') }}</p>
                            @endif
                        </div>

                    </section>

                    <section class="xd-contact-page">
                        <aside class="xd-contact-panel">
                            <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.6e174ab7cf77aa95', 'Thông tin liên hệ') }}</span>
                            <h2>{{ $branding['company_name'] ?? $logoAlt }}</h2>
                            <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.4ef88df2d78887d4', 'Hãy cho chúng tôi biết nhu cầu, quy mô và thời gian dự kiến. Đội ngũ tư vấn sẽ kiểm tra và đề xuất hướng triển khai phù hợp.') }}</p>
                            <ul class="xd-contact-methods">
                                <li class="xd-contact-method">
                                    <span class="xd-contact-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.35 1.9.66 2.81a2 2 0 0 1-.45 2.11L8.05 9.91a16 16 0 0 0 6.04 6.04l1.27-1.27a2 2 0 0 1 2.11-.45c.91.31 1.85.53 2.81.66A2 2 0 0 1 22 16.92z"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <small>Hotline</small>
                                        <a href="tel:{{ $phoneHref }}">{{ $hotline }}</a>
                                    </div>
                                </li>
                                <li class="xd-contact-method">
                                    <span class="xd-contact-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                                            <path d="m3 7 9 6 9-6"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <small>Email</small>
                                        <a href="mailto:{{ $email }}">{{ $email }}</a>
                                    </div>
                                </li>
                                <li class="xd-contact-method">
                                    <span class="xd-contact-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24">
                                            <path d="M12 21s7-5.3 7-12a7 7 0 1 0-14 0c0 6.7 7 12 7 12z"/>
                                            <circle cx="12" cy="9" r="2.5"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <small>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.5c983b845ed1f9a8', 'Địa chỉ') }}</small>
                                        <span>{{ $address }}</span>
                                    </div>
                                </li>
                            </ul>
                            <div class="xd-contact-note">
                                <strong>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.09fee4c7fe5eff4e', 'Chia sẻ nhu cầu, chúng tôi tư vấn đúng giải pháp.') }}</strong>
                                <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.6cedf167e76834b6', 'Hãy gửi thêm địa điểm, diện tích, tiến độ mong muốn hoặc yêu cầu kỹ thuật để đội ngũ chuẩn bị phương án phù hợp ngay từ lần phản hồi đầu tiên.') }}</span>
                            </div>
                        </aside>

                        <article class="xd-contact-form-card">
                            <h2>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.1e77514570be5cdf', 'Gửi yêu cầu liên hệ') }}</h2>
                            @if (session('contact_status'))
                                <div class="xd-contact-alert">{{ session('contact_status') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="xd-contact-errors">
                                    {{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.be3354f6dcd97e9a', 'Vui lòng kiểm tra lại thông tin.') }}
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            <form class="xd-contact-form" method="POST" action="{{ route('site.contact.submit') }}">
                                @csrf
                                <input type="hidden" name="source" value="contact">
                                <input type="hidden" name="subject" value="{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.a00b0d7367f96375', 'Yêu cầu liên hệ từ website') }}">
                                <label class="xd-contact-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.33e59b3ff0831c9e', 'Họ tên') }}</span>
                                    <input name="name" value="{{ old('name') }}" required autocomplete="name">
                                </label>
                                <label class="xd-contact-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.84b329c821a5b27e', 'Số điện thoại') }}</span>
                                    <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                                </label>
                                <label class="xd-contact-field">
                                    <span>Email</span>
                                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                                </label>
                                <label class="xd-contact-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.d8109b33388f7ada', 'Nội dung') }}</span>
                                    <textarea name="message" required>{{ old('message') }}</textarea>
                                </label>
                                <button class="xd-contact-submit" type="submit">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0320', app()->getLocale(), 'legacy_inline.72ecdde11b76357d', 'Gửi liên hệ') }}</button>
                            </form>
                        </article>
                    </section>
            </div>
</main>
@endsection
