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
            'Sáº£N PHÁº©M' => 'SẢN PHẨM',
            'SÁº£N PHÁº©M' => 'SẢN PHẨM',
            'sản phẩm' => 'sản phẩm',
            'sản phẩm' => 'sản phẩm',
            'Sản phẩm' => 'Sản phẩm',
            'Tài khoản' => 'Tài khoản',
            'TÃ I KHOáº£N' => 'TÀI KHOẢN',
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
            'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.95a078fb35ea9444', 'Trang chủ'),
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
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.76ffae1228bd585e', 'Sản phẩm'),
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
                'label' => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.76ffae1228bd585e', 'Sản phẩm'),
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
    $footerNewsletterSource = 'theme-footer-XD0313-cms';
@endphp

@extends('theme-xd0313::layout')

@section('title', $title)

@if (!empty($description))
    @push('head')
        <meta name="description" content="{{ $description }}">
    @endpush
@endif

@push('head')
    <style>
        .xd-page-main{padding:76px 0 90px}
        .xd-cms-hero{display:grid;grid-template-columns:minmax(0,.75fr) minmax(340px,.45fr);gap:48px;align-items:end;margin-bottom:54px;padding:56px;border:1px solid var(--line);background:#fff;box-shadow:0 20px 55px rgba(28,45,60,.08)}
        .xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}
        .xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}
        .xd-cms-hero h1{margin:0;color:var(--ink);font-size:clamp(42px,5vw,72px);line-height:1.08;letter-spacing:-.055em}
        .xd-cms-hero p{margin:18px 0 0;color:var(--muted);font-size:20px;font-weight:550}
        .xd-cms-stats{display:grid;gap:12px;color:#fff;background:var(--ink);padding:26px 30px}
        .xd-cms-stats strong{font-size:46px;line-height:1}
        .xd-cms-stats span{color:rgba(255,255,255,.75);font-weight:800;text-transform:uppercase}
        .xd-services-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:34px}
        .xd-service-card{background:#fff;box-shadow:0 5px 20px rgba(16,29,40,.08);transition:.25s}
        .xd-service-card:hover{transform:translateY(-8px);box-shadow:var(--shadow)}
        .xd-service-image{display:block;height:300px;overflow:hidden;background:#eef2ef}
        .xd-service-image img{width:100%;height:100%;object-fit:cover;transition:.4s}
        .xd-service-card:hover img{transform:scale(1.05)}
        .xd-service-body{padding:36px 38px 40px}
        .xd-service-card h2,.xd-service-card h3{margin:0 0 14px;font-size:22px;line-height:1.32;letter-spacing:.015em;text-transform:uppercase}
        .xd-service-card p{margin:0 0 26px;color:var(--muted);font-size:17px}
        .xd-text-link{color:var(--lime-dark);font-weight:900;text-transform:uppercase}
        .xd-detail{display:grid;grid-template-columns:minmax(0,.85fr) minmax(300px,.35fr);gap:44px}
        .xd-detail-card,.xd-side-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}
        .xd-detail-card{overflow:hidden}
        .xd-detail-image{width:100%;max-height:520px;object-fit:cover}
        .xd-detail-body{padding:44px 52px}
        .xd-detail-body h1{margin:0 0 18px;font-size:clamp(38px,4vw,62px);line-height:1.1;letter-spacing:-.05em}
        .xd-detail-summary{margin:0 0 28px;color:var(--muted);font-size:20px}
        .xd-rich-content{color:#465461;font-size:18px}
        .xd-rich-content :first-child{margin-top:0}
        .xd-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:28px}
        .xd-gallery figure{margin:0}
        .xd-gallery img{width:100%;height:210px;object-fit:cover}
        .xd-gallery figcaption{margin-top:8px;color:var(--muted);font-size:14px}
        .xd-side-card{padding:28px}
        .xd-side-card h3{margin:0 0 18px;font-size:24px}
        .xd-side-card a{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}
        .xd-side-card a:hover{color:var(--lime-dark)}
        .xd-contact-page{display:grid;grid-template-columns:minmax(0,.9fr) minmax(420px,.72fr);gap:34px;align-items:stretch}
        .xd-contact-panel,.xd-contact-form-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}
        .xd-contact-panel{padding:44px 48px;background:linear-gradient(135deg,#fff 0%,#f7faee 100%)}
        .xd-contact-panel h2,.xd-contact-form-card h2{margin:0 0 18px;font-size:34px;line-height:1.15;letter-spacing:-.04em}
        .xd-contact-panel p{margin:0 0 26px;color:var(--muted);font-size:18px;font-weight:600}
        .xd-contact-methods{display:grid;gap:16px;margin:0;padding:0;list-style:none}
        .xd-contact-method{display:grid;grid-template-columns:54px minmax(0,1fr);gap:16px;align-items:center;padding:18px;border:1px solid rgba(38,56,74,.1);background:#fff}
        .xd-contact-icon{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;background:#bdd400;color:#fff}
        .xd-contact-icon svg{width:25px;height:25px;display:block;stroke:#fff;stroke-width:2.4;fill:none;stroke-linecap:round;stroke-linejoin:round}
        .xd-contact-method small{display:block;color:var(--lime-dark);font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase}
        .xd-contact-method a,.xd-contact-method span{color:var(--ink);font-size:18px;font-weight:850;overflow-wrap:anywhere}
        .xd-contact-note{margin-top:24px;padding:20px 22px;background:var(--ink);color:#fff}
        .xd-contact-note strong{display:block;margin-bottom:6px;color:var(--lime)}
        .xd-contact-note span{color:rgba(255,255,255,.78);font-weight:650}
        .xd-contact-form-card{padding:44px 48px}
        .xd-contact-form{display:grid;gap:16px}
        .xd-contact-field{display:grid;gap:8px}
        .xd-contact-field label{font-size:13px;font-weight:950;letter-spacing:.04em;text-transform:uppercase}
        .xd-contact-field input,.xd-contact-field textarea{width:100%;border:1px solid var(--line);border-radius:0;background:#fbfcfa;color:var(--ink);font:inherit;font-weight:650;outline:0;transition:.2s}
        .xd-contact-field input{height:56px;padding:0 18px}
        .xd-contact-field textarea{min-height:150px;padding:16px 18px;resize:vertical}
        .xd-contact-field input:focus,.xd-contact-field textarea:focus{border-color:var(--lime);box-shadow:0 0 0 4px rgba(189,212,0,.14)}
        .xd-contact-submit{display:inline-flex;align-items:center;justify-content:center;width:max-content;min-height:58px;padding:0 30px;border:0;background:var(--lime);color:#fff;box-shadow:0 15px 30px rgba(189,212,0,.28);font:inherit;font-weight:950;text-transform:uppercase;cursor:pointer}
        .xd-contact-submit:hover{transform:translateY(-1px)}
        .xd-contact-alert{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(143,169,0,.25);background:#f7fae5;color:var(--lime-dark);font-weight:850}
        .xd-contact-errors{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(180,35,24,.22);background:#fff4f2;color:#b42318;font-weight:800}
        .xd-contact-errors ul{margin:6px 0 0;padding-left:18px}
        @media (max-width:1180px){.xd-cms-hero,.xd-detail,.xd-contact-page{grid-template-columns:1fr}}
        @media (max-width:640px){.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-page-main{padding:38px 0 56px}.xd-cms-hero{padding:30px 22px;margin-bottom:26px}.xd-cms-hero h1{font-size:36px}.xd-cms-hero p{font-size:16px}.xd-service-card{border-radius:18px;overflow:hidden}.xd-service-image{height:215px}.xd-service-body{padding:26px 22px}.xd-service-card h2,.xd-service-card h3{font-size:19px}.xd-detail-body{padding:28px 22px}.xd-detail-body h1{font-size:34px}.xd-detail-summary,.xd-rich-content{font-size:16px}.xd-contact-panel,.xd-contact-form-card{padding:28px 22px}.xd-contact-panel h2,.xd-contact-form-card h2{font-size:28px}.xd-contact-method{grid-template-columns:44px minmax(0,1fr);padding:14px}.xd-contact-icon{width:44px;height:44px;font-size:18px}.xd-contact-method a,.xd-contact-method span{font-size:15px}.xd-contact-submit{width:100%}}
    </style>
@endpush

@section('content')
<main class="xd-page-main">
            <div class="xd-container">
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.3320c47802c074f4', 'Liên hệ') }}</span>
                            <h1>{{ $entry->title }}</h1>
                            @if (!empty($entry->excerpt))
                                <p>{{ $entry->excerpt }}</p>
                            @else
                                <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.f22eedb37583a4f9', 'Gửi nhu cầu tư vấn, thiết kế hoặc thi công. Đội ngũ XD0313 sẽ phản hồi trong thời gian sớm nhất.') }}</p>
                            @endif
                        </div>
                        <div class="xd-cms-stats">
                            <strong>24h</strong>
                            <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.a57fe22824ad954e', 'Thời gian phản hồi') }}</span>
                        </div>
                    </section>

                    <section class="xd-contact-page">
                        <aside class="xd-contact-panel">
                            <span class="xd-kicker">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.07c97badbd540233', 'Thông tin liên hệ') }}</span>
                            <h2>{{ $branding['company_name'] ?? $logoAlt }}</h2>
                            <p>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.60e0d5f7b82df22a', 'Hãy cho chúng tôi biết nhu cầu, quy mô và thời gian dự kiến. Đội ngũ tư vấn sẽ kiểm tra và đề xuất hướng triển khai phù hợp.') }}</p>
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
                                        <small>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.39f1595b075abbb4', 'Địa chỉ') }}</small>
                                        <span>{{ $address }}</span>
                                    </div>
                                </li>
                            </ul>
                            <div class="xd-contact-note">
                                <strong>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.6b20eb393015ec65', 'Chia sẻ nhu cầu, chúng tôi tư vấn đúng giải pháp.') }}</strong>
                                <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.d1a5dfffedb6e0e5', 'Hãy gửi thêm địa điểm, diện tích, tiến độ mong muốn hoặc yêu cầu kỹ thuật để đội ngũ chuẩn bị phương án phù hợp ngay từ lần phản hồi đầu tiên.') }}</span>
                            </div>
                        </aside>

                        <article class="xd-contact-form-card">
                            <h2>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.17a86d6788887a67', 'Gửi yêu cầu liên hệ') }}</h2>
                            @if (session('contact_status'))
                                <div class="xd-contact-alert">{{ session('contact_status') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="xd-contact-errors">
                                    {{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.ca610cc75d04b584', 'Vui lòng kiểm tra lại thông tin.') }}
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
                                <input type="hidden" name="subject" value="{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.f6ba18550b431f61', 'Yêu cầu liên hệ từ website') }}">
                                <label class="xd-contact-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.2da0a765563692ba', 'Họ tên') }}</span>
                                    <input name="name" value="{{ old('name') }}" required autocomplete="name">
                                </label>
                                <label class="xd-contact-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.57629094dd2370ca', 'Số điện thoại') }}</span>
                                    <input name="phone" value="{{ old('phone') }}" autocomplete="tel">
                                </label>
                                <label class="xd-contact-field">
                                    <span>Email</span>
                                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                                </label>
                                <label class="xd-contact-field">
                                    <span>{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.bfc4253cd375512d', 'Nội dung') }}</span>
                                    <textarea name="message" required>{{ old('message') }}</textarea>
                                </label>
                                <button class="xd-contact-submit" type="submit">{{ app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('XD0313', app()->getLocale(), 'legacy_inline.345403bcaa8eba97', 'Gửi liên hệ') }}</button>
                            </form>
                        </article>
                    </section>
            </div>
</main>
@endsection
