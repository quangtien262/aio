@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? '0399162342'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? 'admin@htvietnam.vn'));
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyá»…n ÄÃ¬nh Chiá»ƒu, Quáº­n 3, TP.HCM'));

    $localizeMenuUrl = function (?string $href): string {
        $href = trim((string) $href);

        if ($href === '' || $href === '#' || str_starts_with($href, '#') || preg_match('/^(https?:)?\/\//i', $href) || preg_match('/^(mailto|tel):/i', $href)) {
            return $href !== '' ? $href : '#';
        }

        $parts = parse_url($href) ?: [];
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? '#'.$parts['fragment'] : '';

        if ($path === '') {
            return route('site.home').$query.$fragment;
        }

        $segments = explode('/', $path);
        $knownLocales = \App\Support\FrontendLocalization::knownLocaleCodes();

        if (! in_array($segments[0] ?? '', $knownLocales, true)) {
            array_unshift($segments, app()->getLocale());
        }

        return url('/'.implode('/', $segments)).$query.$fragment;
    };

    $normalizeNavItem = function (array $item) use (&$normalizeNavItem, $localizeMenuUrl): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
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
    if (! $navItems->contains(fn (array $item): bool => in_array(mb_strtolower(trim($item['label'])), ['trang chá»§', 'home'], true) || rtrim($item['href'], '/') === rtrim($homeUrl, '/'))) {
        $navItems->prepend([
            'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chá»§',
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sáº£n pháº©m', 'san pham', 'products', 'product'], true);
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
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sáº£n pháº©m',
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

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chá»§', 'home'], true));
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

                    if (in_array($label, ['sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m', 'sáº£n pháº©m', 'san pham', 'products', 'product'], true) && empty($item['children'])) {
                        $item['children'] = $productNavigationItems->all();
                    }

                    return $item;
                })
                ->values();
        } else {
            $productMenuItem = [
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sáº£n pháº©m',
                'href' => route('site.catalog.search'),
                'target' => '_self',
                'active' => request()->routeIs('site.catalog.*'),
                'children' => $productNavigationItems->all(),
            ];

            $homeIndex = $navItems->search(fn (array $item): bool => in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['trang chÃ¡Â»Â§', 'trang chá»§', 'home'], true));
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
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    <style>
        :root{--lime:#bdd400;--lime-dark:#8fa900;--ink:#26384a;--muted:#74808a;--line:#e6ebe8;--bg:#fbfcfa;--shadow:0 22px 55px rgba(28,45,60,.13);--font:"Montserrat","Segoe UI",Arial,sans-serif}
        *{box-sizing:border-box}body{margin:0;color:var(--ink);background:var(--bg);font-family:var(--font);font-size:16px;line-height:1.75}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}.xd-container{width:min(1540px,calc(100% - 56px));margin:0 auto}
        .xd-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);box-shadow:0 10px 30px rgba(16,29,40,.08);backdrop-filter:blur(14px)}.xd-header-inner{display:flex;align-items:center;justify-content:space-between;min-height:102px;gap:34px}.xd-logo{display:inline-flex;align-items:center;gap:11px;font-size:34px;font-weight:900;letter-spacing:-.06em;color:var(--ink)}.xd-logo-image{display:block;width:auto;max-width:156px;height:64px;object-fit:contain}.xd-logo-mark{position:relative;width:38px;height:50px;background:linear-gradient(135deg,var(--lime),#d9ec27);clip-path:polygon(50% 0,100% 22%,100% 100%,0 100%,0 22%)}.xd-logo-mark:before,.xd-logo-mark:after{content:"";position:absolute;background:#fff}.xd-logo-mark:before{left:9px;bottom:7px;width:20px;height:30px}.xd-logo-mark:after{left:14px;top:17px;width:10px;height:7px;box-shadow:0 11px 0 #fff}.xd-logo span{color:var(--lime)}
        .xd-nav{display:flex;align-items:center;justify-content:center;gap:0;min-width:0;flex:1}.xd-nav-item{position:relative}.xd-nav-link{display:inline-flex;align-items:center;gap:8px;padding:39px 21px;color:#344354;font-size:15px;font-weight:850;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}.xd-nav-caret{color:var(--lime-dark);font-size:12px;transition:transform .18s ease}.xd-nav-link.is-active,.xd-nav-link:hover,.xd-nav-item:hover>.xd-nav-link,.xd-nav-item:focus-within>.xd-nav-link{color:var(--lime-dark)}.xd-nav-item:hover>.xd-nav-link .xd-nav-caret,.xd-nav-item:focus-within>.xd-nav-link .xd-nav-caret{transform:rotate(180deg)}.xd-dropdown{position:absolute;top:100%;left:0;z-index:90;min-width:250px;padding:12px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateY(12px);transition:.18s}.xd-nav-item:hover>.xd-dropdown,.xd-nav-item:focus-within>.xd-dropdown{opacity:1;visibility:visible;transform:translateY(0)}.xd-dropdown-item{position:relative}.xd-dropdown-link{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 14px;border-left:3px solid transparent;color:#53606b;font-size:14px;font-weight:800;line-height:1.35}.xd-dropdown-link:hover,.xd-dropdown-item:focus-within>.xd-dropdown-link{background:#f7f9ee;border-left-color:var(--lime);color:var(--ink)}.xd-subdropdown{position:absolute;top:0;left:calc(100% + 10px);z-index:91;min-width:230px;padding:10px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateX(-8px);transition:.18s}.xd-dropdown-item:hover>.xd-subdropdown,.xd-dropdown-item:focus-within>.xd-subdropdown{opacity:1;visibility:visible;transform:translateX(0)}
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-cart-link{display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);transition:.2s ease}.xd-cart-link svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.xd-cart-link:hover{border-color:var(--lime);background:var(--lime);color:#fff;transform:translateY(-1px)}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font:inherit;font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap;cursor:pointer}.xd-login-button:hover{border-color:var(--lime);color:var(--lime-dark)}
        .xd-page-main{padding:76px 0 90px}.xd-cms-hero{display:grid;grid-template-columns:minmax(0,.75fr) minmax(340px,.45fr);gap:48px;align-items:end;margin-bottom:54px;padding:56px;border:1px solid var(--line);background:#fff;box-shadow:0 20px 55px rgba(28,45,60,.08)}.xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-cms-hero h1{margin:0;color:var(--ink);font-size:clamp(42px,5vw,72px);line-height:1.08;letter-spacing:-.055em}.xd-cms-hero p{margin:18px 0 0;color:var(--muted);font-size:20px;font-weight:550}.xd-cms-stats{display:grid;gap:12px;color:#fff;background:var(--ink);padding:26px 30px}.xd-cms-stats strong{font-size:46px;line-height:1}.xd-cms-stats span{color:rgba(255,255,255,.75);font-weight:800;text-transform:uppercase}
        .xd-services-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:34px}.xd-service-card{background:#fff;box-shadow:0 5px 20px rgba(16,29,40,.08);transition:.25s}.xd-service-card:hover{transform:translateY(-8px);box-shadow:var(--shadow)}.xd-service-image{display:block;height:300px;overflow:hidden;background:#eef2ef}.xd-service-image img{width:100%;height:100%;object-fit:cover;transition:.4s}.xd-service-card:hover img{transform:scale(1.05)}.xd-service-body{padding:36px 38px 40px}.xd-service-card h2,.xd-service-card h3{margin:0 0 14px;font-size:22px;line-height:1.32;letter-spacing:.015em;text-transform:uppercase}.xd-service-card p{margin:0 0 26px;color:var(--muted);font-size:17px}.xd-text-link{color:var(--lime-dark);font-weight:900;text-transform:uppercase}
        .xd-detail{display:grid;grid-template-columns:minmax(0,.85fr) minmax(300px,.35fr);gap:44px}.xd-detail-card,.xd-side-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}.xd-detail-card{overflow:hidden}.xd-detail-image{width:100%;max-height:520px;object-fit:cover}.xd-detail-body{padding:44px 52px}.xd-detail-body h1{margin:0 0 18px;font-size:clamp(38px,4vw,62px);line-height:1.1;letter-spacing:-.05em}.xd-detail-summary{margin:0 0 28px;color:var(--muted);font-size:20px}.xd-rich-content{color:#465461;font-size:18px}.xd-rich-content :first-child{margin-top:0}.xd-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:28px}.xd-gallery figure{margin:0}.xd-gallery img{width:100%;height:210px;object-fit:cover}.xd-gallery figcaption{margin-top:8px;color:var(--muted);font-size:14px}.xd-side-card{padding:28px}.xd-side-card h3{margin:0 0 18px;font-size:24px}.xd-side-card a{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}.xd-side-card a:hover{color:var(--lime-dark)}
        .xd-contact-page{display:grid;grid-template-columns:minmax(0,.9fr) minmax(420px,.72fr);gap:34px;align-items:stretch}.xd-contact-panel,.xd-contact-form-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}.xd-contact-panel{padding:44px 48px;background:linear-gradient(135deg,#fff 0%,#f7faee 100%)}.xd-contact-panel h2,.xd-contact-form-card h2{margin:0 0 18px;font-size:34px;line-height:1.15;letter-spacing:-.04em}.xd-contact-panel p{margin:0 0 26px;color:var(--muted);font-size:18px;font-weight:600}.xd-contact-methods{display:grid;gap:16px;margin:0;padding:0;list-style:none}.xd-contact-method{display:grid;grid-template-columns:54px minmax(0,1fr);gap:16px;align-items:center;padding:18px;border:1px solid rgba(38,56,74,.1);background:#fff}.xd-contact-icon{display:inline-flex;align-items:center;justify-content:center;width:54px;height:54px;background:var(--ink);color:#fff;font-size:22px;font-weight:900}.xd-contact-method small{display:block;color:var(--lime-dark);font-size:12px;font-weight:950;letter-spacing:.06em;text-transform:uppercase}.xd-contact-method a,.xd-contact-method span{color:var(--ink);font-size:18px;font-weight:850;overflow-wrap:anywhere}.xd-contact-note{margin-top:24px;padding:20px 22px;background:var(--ink);color:#fff}.xd-contact-note strong{display:block;margin-bottom:6px;color:var(--lime)}.xd-contact-note span{color:rgba(255,255,255,.78);font-weight:650}.xd-contact-form-card{padding:44px 48px}.xd-contact-form{display:grid;gap:16px}.xd-contact-field{display:grid;gap:8px}.xd-contact-field label{font-size:13px;font-weight:950;letter-spacing:.04em;text-transform:uppercase}.xd-contact-field input,.xd-contact-field textarea{width:100%;border:1px solid var(--line);border-radius:0;background:#fbfcfa;color:var(--ink);font:inherit;font-weight:650;outline:0;transition:.2s}.xd-contact-field input{height:56px;padding:0 18px}.xd-contact-field textarea{min-height:150px;padding:16px 18px;resize:vertical}.xd-contact-field input:focus,.xd-contact-field textarea:focus{border-color:var(--lime);box-shadow:0 0 0 4px rgba(189,212,0,.14)}.xd-contact-submit{display:inline-flex;align-items:center;justify-content:center;width:max-content;min-height:58px;padding:0 30px;border:0;background:var(--lime);color:#fff;box-shadow:0 15px 30px rgba(189,212,0,.28);font:inherit;font-weight:950;text-transform:uppercase;cursor:pointer}.xd-contact-submit:hover{transform:translateY(-1px)}.xd-contact-alert{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(143,169,0,.25);background:#f7fae5;color:var(--lime-dark);font-weight:850}.xd-contact-errors{margin:0 0 18px;padding:14px 16px;border:1px solid rgba(180,35,24,.22);background:#fff4f2;color:#b42318;font-weight:800}.xd-contact-errors ul{margin:6px 0 0;padding-left:18px}
        .xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);font:inherit;outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}.xd-newsletter-note{margin-top:12px;font-size:14px!important;font-weight:800!important;line-height:1.45}.xd-newsletter-note.is-success{color:var(--lime-dark)!important}.xd-newsletter-note.is-error{color:#b42318!important}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-services-list,.xd-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.xd-cms-hero,.xd-detail,.xd-contact-page{grid-template-columns:1fr}}
        @media (max-width:640px){body{font-size:15px;line-height:1.65;overflow-x:hidden}.xd-container{width:min(100% - 24px,1540px)}.xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}.xd-logo{font-size:25px;gap:8px}.xd-logo-image{max-width:132px;height:52px}.xd-logo-mark{width:29px;height:38px}.xd-header-actions{width:auto;gap:8px;margin-left:auto}.xd-cart-link{width:42px;height:42px;border-radius:999px}.xd-cart-link svg{width:19px;height:19px}.xd-hotline{min-height:42px;padding:0 14px;border-radius:999px;font-size:0}.xd-hotline::after{content:"{{ $hotline }}";font-size:13px}.xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px}.xd-nav{order:3;flex:0 0 100%;display:flex;gap:8px;overflow-x:auto;padding:8px 0 2px}.xd-nav::-webkit-scrollbar{display:none}.xd-nav-item{flex:0 0 auto}.xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;font-size:12px}.xd-dropdown{top:calc(100% + 8px);min-width:220px;max-width:calc(100vw - 24px);padding:8px;border-radius:16px}.xd-subdropdown{position:static;display:none;margin:4px 0 4px 10px;border:0;border-left:2px solid var(--lime);box-shadow:none;opacity:1;visibility:visible;transform:none}.xd-dropdown-item:hover>.xd-subdropdown,.xd-dropdown-item:focus-within>.xd-subdropdown{display:block}.xd-page-main{padding:38px 0 56px}.xd-cms-hero{padding:30px 22px;margin-bottom:26px}.xd-cms-hero h1{font-size:36px}.xd-cms-hero p{font-size:16px}.xd-services-list,.xd-footer-grid,.xd-gallery{grid-template-columns:1fr}.xd-service-card{border-radius:18px;overflow:hidden}.xd-service-image{height:215px}.xd-service-body{padding:26px 22px}.xd-service-card h2,.xd-service-card h3{font-size:19px}.xd-detail-body{padding:28px 22px}.xd-detail-body h1{font-size:34px}.xd-detail-summary,.xd-rich-content{font-size:16px}.xd-contact-panel,.xd-contact-form-card{padding:28px 22px}.xd-contact-panel h2,.xd-contact-form-card h2{font-size:28px}.xd-contact-method{grid-template-columns:44px minmax(0,1fr);padding:14px}.xd-contact-icon{width:44px;height:44px;font-size:18px}.xd-contact-method a,.xd-contact-method span{font-size:15px}.xd-contact-submit{width:100%}.xd-footer{padding:52px 0 42px}.xd-footer-grid{gap:28px}.xd-footer .xd-logo-image{max-width:126px;height:52px}.xd-footer h3{margin-bottom:12px;font-size:24px}.xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}.xd-newsletter{display:grid;border-radius:16px;overflow:hidden}.xd-newsletter input{min-height:52px;padding:0 16px}.xd-newsletter button{width:100%;min-height:52px}}
    </style>
</head>
<body>
    <div id="top" class="xd-page">
        <header class="xd-header">
            <div class="xd-container xd-header-inner">
                <a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt }} trang chá»§">
                    @if ($logoUrl !== '')
                        <img class="xd-logo-image" src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
                    @else
                        <i class="xd-logo-mark" aria-hidden="true"></i><b>Ar<span>kit</span></b>
                    @endif
                </a>
                <nav class="xd-nav" aria-label="Menu chÃ­nh">
                    @foreach ($navItems as $item)
                        <div class="xd-nav-item {{ !empty($item['children']) ? 'has-children' : '' }}">
                            <a class="xd-nav-link {{ ($item['active'] ?? false) ? 'is-active' : '' }}" href="{{ $item['href'] }}" target="{{ $item['target'] ?? '_self' }}">
                                <span>{{ $item['label'] }}</span>
                                @if (!empty($item['children']))
                                    <span class="xd-nav-caret" aria-hidden="true">&#9662;</span>
                                @endif
                            </a>
                            @if (!empty($item['children']))
                                <div class="xd-dropdown" role="menu">
                                    @foreach (collect($item['children'])->take(10) as $child)
                                        <div class="xd-dropdown-item {{ !empty($child['children']) ? 'has-children' : '' }}">
                                            <a class="xd-dropdown-link" href="{{ $child['href'] ?? ($item['href'] ?? '#') }}" target="{{ $child['target'] ?? '_self' }}" role="menuitem">
                                                <span>{{ $child['label'] ?? 'Menu' }}</span>
                                                @if (!empty($child['children']))
                                                    <span class="xd-nav-caret" aria-hidden="true">&#8250;</span>
                                                @endif
                                            </a>
                                            @if (!empty($child['children']))
                                                <div class="xd-subdropdown" role="menu">
                                                    @foreach (collect($child['children'])->take(10) as $grandChild)
                                                        <a class="xd-dropdown-link" href="{{ $grandChild['href'] ?? ($child['href'] ?? '#') }}" target="{{ $grandChild['target'] ?? '_self' }}" role="menuitem">{{ $grandChild['label'] ?? 'Menu' }}</a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </nav>
                <div class="xd-header-actions">
                    <a class="xd-hotline" href="tel:{{ $phoneHref }}"><span aria-hidden="true">&#9742;</span> {{ $hotline }}</a>
                    <a class="xd-cart-link" href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng" title="Giỏ hàng">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6h15l-1.5 8.5H8L6 3H3"/><circle cx="9" cy="20" r="1.7"/><circle cx="18" cy="20" r="1.7"/></svg>
                    </a>
                    @if (auth('customer')->check())
                        <a class="xd-login-button" href="{{ route('customer.account') }}">TÃ i khoáº£n</a>
                    @else
                        <button type="button" class="xd-login-button" data-xd-auth-open="login">Đăng nhập</button>
                    @endif
                </div>
            </div>
        </header>

        <main class="xd-page-main">
            <div class="xd-container">
                @if ($isServiceListing)
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Services' : 'Dá»‹ch vá»¥' }}</span>
                            <h1>{{ $pageTitle ?? (app()->getLocale() === 'en' ? 'Services' : 'Dá»‹ch vá»¥') }}</h1>
                            <p>{{ $pageDescription ?? (app()->getLocale() === 'en' ? 'Explore our construction and design services.' : 'Danh sÃ¡ch dá»‹ch vá»¥ thiáº¿t káº¿ vÃ  thi cÃ´ng ná»•i báº­t.') }}</p>
                        </div>
                        <div class="xd-cms-stats">
                            <strong>{{ collect($listingItems ?? [])->count() }}</strong>
                            <span>{{ app()->getLocale() === 'en' ? 'Available services' : 'Dá»‹ch vá»¥ Ä‘ang hiá»ƒn thá»‹' }}</span>
                        </div>
                    </section>

                    <section class="xd-services-list">
                        @forelse ($listingItems as $service)
                            @php
                                $serviceUrl = route('site.services.show', ['slug' => $service->slug]);
                                $image = $service->featuredImage?->image_url;
                                $alt = $service->featuredImage?->alt_text ?: $service->title;
                            @endphp
                            <article class="xd-service-card">
                                <a class="xd-service-image" href="{{ $serviceUrl }}" aria-label="{{ $service->title }}">
                                    @if ($image)
                                        <img src="{{ $image }}" alt="{{ $alt }}">
                                    @endif
                                </a>
                                <div class="xd-service-body">
                                    <h2><a href="{{ $serviceUrl }}">{{ $service->title }}</a></h2>
                                    <p>{{ $service->summary ?: \Illuminate\Support\Str::limit(strip_tags($service->content ?? ''), 150) }}</p>
                                    <a class="xd-text-link" href="{{ $serviceUrl }}">{{ app()->getLocale() === 'en' ? 'Learn more' : 'TÃ¬m hiá»ƒu ngay' }}</a>
                                </div>
                            </article>
                        @empty
                            <p>{{ app()->getLocale() === 'en' ? 'No services are available yet.' : 'ChÆ°a cÃ³ dá»‹ch vá»¥ nÃ o Ä‘Æ°á»£c xuáº¥t báº£n.' }}</p>
                        @endforelse
                    </section>
                @elseif ($isPostListing)
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'News' : 'Tin tá»©c' }}</span>
                            <h1>{{ $pageTitle ?? (app()->getLocale() === 'en' ? 'News' : 'Tin tá»©c') }}</h1>
                            <p>{{ $pageDescription ?? (app()->getLocale() === 'en' ? 'Latest company updates and construction insights.' : 'Danh sÃ¡ch bÃ i viáº¿t, tin tá»©c vÃ  kinh nghiá»‡m xÃ¢y dá»±ng má»›i nháº¥t.') }}</p>
                        </div>
                        <div class="xd-cms-stats">
                            <strong>{{ method_exists($listingItems, 'total') ? $listingItems->total() : collect($listingItems ?? [])->count() }}</strong>
                            <span>{{ app()->getLocale() === 'en' ? 'Published posts' : 'BÃ i viáº¿t Ä‘Ã£ xuáº¥t báº£n' }}</span>
                        </div>
                    </section>

                    <section class="xd-services-list">
                        @forelse ($listingItems as $post)
                            @php
                                $postUrl = route('site.blog.show', ['slug' => $post->slug]);
                                $image = $post->featuredMedia?->url ?: $post->featuredMedia?->file_url;
                                $summary = $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) ($post->body ?? '')), 150);
                            @endphp
                            <article class="xd-service-card">
                                <a class="xd-service-image" href="{{ $postUrl }}" aria-label="{{ $post->title }}">
                                    <img src="{{ $image ?: 'https://picsum.photos/seed/xd0301-post-'.($post->id ?? 'default').'/960/720' }}" alt="{{ $post->title }}">
                                </a>
                                <div class="xd-service-body">
                                    <h2><a href="{{ $postUrl }}">{{ $post->title }}</a></h2>
                                    <p>{{ $summary }}</p>
                                    <a class="xd-text-link" href="{{ $postUrl }}">{{ app()->getLocale() === 'en' ? 'Read more' : 'Äá»c tiáº¿p' }}</a>
                                </div>
                            </article>
                        @empty
                            <p>{{ app()->getLocale() === 'en' ? 'No posts are available yet.' : 'ChÆ°a cÃ³ bÃ i viáº¿t nÃ o Ä‘Æ°á»£c xuáº¥t báº£n.' }}</p>
                        @endforelse
                    </section>

                    @if (method_exists($listingItems, 'links'))
                        <div style="margin-top:32px">
                            {{ $listingItems->links() }}
                        </div>
                    @endif
                @elseif ($isServiceDetail)
                    @php
                        $featuredImage = $entry->featuredImage?->image_url;
                        $featuredAlt = $entry->featuredImage?->alt_text ?: $entry->title;
                    @endphp
                    <section class="xd-detail">
                        <article class="xd-detail-card">
                            @if ($featuredImage)
                                <img class="xd-detail-image" src="{{ $featuredImage }}" alt="{{ $featuredAlt }}">
                            @endif
                            <div class="xd-detail-body">
                                <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Service' : 'Dá»‹ch vá»¥' }}</span>
                                <h1>{{ $entry->title }}</h1>
                                @if (!empty($entry->excerpt))
                                    <p class="xd-detail-summary">{{ $entry->excerpt }}</p>
                                @endif
                                <div class="xd-rich-content">
                                    {!! $entry->body ?: '<p>Ná»™i dung Ä‘ang Ä‘Æ°á»£c cáº­p nháº­t.</p>' !!}
                                </div>

                                @if (!empty($entry->images) && $entry->images->count() > 1)
                                    <div class="xd-gallery">
                                        @foreach ($entry->images as $image)
                                            <figure>
                                                <img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}">
                                                @if (!empty($image->caption))
                                                    <figcaption>{{ $image->caption }}</figcaption>
                                                @endif
                                            </figure>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                        <aside class="xd-side-card">
                            <h3>{{ app()->getLocale() === 'en' ? 'Quick links' : 'LiÃªn káº¿t nhanh' }}</h3>
                            <a href="{{ route('site.services.index') }}">{{ app()->getLocale() === 'en' ? 'All services' : 'Táº¥t cáº£ dá»‹ch vá»¥' }}</a>
                            @foreach ($navItems->take(5) as $item)
                                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                            @endforeach
                        </aside>
                    </section>
                @elseif ($isContactPage)
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Contact' : 'Liên hệ' }}</span>
                            <h1>{{ $entry->title }}</h1>
                            @if (!empty($entry->excerpt))
                                <p>{{ $entry->excerpt }}</p>
                            @else
                                <p>{{ app()->getLocale() === 'en' ? 'Send your project request and our team will contact you shortly.' : 'Gửi nhu cầu tư vấn, thiết kế hoặc thi công. Đội ngũ XD0301 sẽ phản hồi trong thời gian sớm nhất.' }}</p>
                            @endif
                        </div>
                        <div class="xd-cms-stats">
                            <strong>24h</strong>
                            <span>{{ app()->getLocale() === 'en' ? 'Response target' : 'Thời gian phản hồi' }}</span>
                        </div>
                    </section>

                    <section class="xd-contact-page">
                        <aside class="xd-contact-panel">
                            <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Contact info' : 'Thông tin liên hệ' }}</span>
                            <h2>{{ $branding['company_name'] ?? $logoAlt }}</h2>
                            <p>{{ app()->getLocale() === 'en' ? 'Tell us about your project, timeline and expected scope. We will review and advise the next practical step.' : 'Hãy cho chúng tôi biết nhu cầu, quy mô và thời gian dự kiến. Đội ngũ tư vấn sẽ kiểm tra và đề xuất hướng triển khai phù hợp.' }}</p>
                            <ul class="xd-contact-methods">
                                <li class="xd-contact-method">
                                    <span class="xd-contact-icon" aria-hidden="true">&#9742;</span>
                                    <div>
                                        <small>Hotline</small>
                                        <a href="tel:{{ $phoneHref }}">{{ $hotline }}</a>
                                    </div>
                                </li>
                                <li class="xd-contact-method">
                                    <span class="xd-contact-icon" aria-hidden="true">&#9993;</span>
                                    <div>
                                        <small>Email</small>
                                        <a href="mailto:{{ $email }}">{{ $email }}</a>
                                    </div>
                                </li>
                                <li class="xd-contact-method">
                                    <span class="xd-contact-icon" aria-hidden="true">&#9906;</span>
                                    <div>
                                        <small>{{ app()->getLocale() === 'en' ? 'Address' : 'Địa chỉ' }}</small>
                                        <span>{{ $address }}</span>
                                    </div>
                                </li>
                            </ul>
                            <div class="xd-contact-note">
                                <strong>{{ app()->getLocale() === 'en' ? 'Project information helps us reply faster.' : 'Thông tin càng rõ, tư vấn càng nhanh.' }}</strong>
                                <span>{{ app()->getLocale() === 'en' ? 'You can include site location, area, expected budget and desired handover date.' : 'Có thể ghi thêm địa điểm công trình, diện tích, ngân sách dự kiến và thời gian cần bàn giao.' }}</span>
                            </div>
                        </aside>

                        <article class="xd-contact-form-card">
                            <h2>{{ app()->getLocale() === 'en' ? 'Send a request' : 'Gửi yêu cầu liên hệ' }}</h2>
                            @if (session('contact_status'))
                                <div class="xd-contact-alert">{{ session('contact_status') }}</div>
                            @endif
                            @if ($errors->any())
                                <div class="xd-contact-errors">
                                    {{ app()->getLocale() === 'en' ? 'Please check the form information.' : 'Vui lòng kiểm tra lại thông tin.' }}
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
                                <input type="hidden" name="subject" value="{{ app()->getLocale() === 'en' ? 'Contact request from website' : 'Yêu cầu liên hệ từ website' }}">
                                <label class="xd-contact-field">
                                    <span>{{ app()->getLocale() === 'en' ? 'Full name' : 'Họ tên' }}</span>
                                    <input name="name" value="{{ old('name') }}" required autocomplete="name">
                                </label>
                                <label class="xd-contact-field">
                                    <span>{{ app()->getLocale() === 'en' ? 'Phone number' : 'Số điện thoại' }}</span>
                                    <input name="phone" value="{{ old('phone') }}" autocomplete="tel">
                                </label>
                                <label class="xd-contact-field">
                                    <span>Email</span>
                                    <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                                </label>
                                <label class="xd-contact-field">
                                    <span>{{ app()->getLocale() === 'en' ? 'Message' : 'Nội dung' }}</span>
                                    <textarea name="message" required>{{ old('message') }}</textarea>
                                </label>
                                <button class="xd-contact-submit" type="submit">{{ app()->getLocale() === 'en' ? 'Send request' : 'Gửi liên hệ' }}</button>
                            </form>
                        </article>
                    </section>
                @else
                    <section class="xd-detail-card">
                        <div class="xd-detail-body">
                            <span class="xd-kicker">{{ strtoupper($contentType ?? 'PAGE') }}</span>
                            <h1>{{ $entry->title }}</h1>
                            @if (!empty($entry->excerpt))
                                <p class="xd-detail-summary">{{ $entry->excerpt }}</p>
                            @endif
                            <div class="xd-rich-content">
                                {!! $entry->body ?: '<p>Ná»™i dung Ä‘ang Ä‘Æ°á»£c cáº­p nháº­t.</p>' !!}
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </main>

        @include('theme-xd0301::partials.footer', ['footerNewsletterSource' => 'theme-footer-xd0301-cms'])
        @include('theme-xd0301::partials.auth-modal')
    </div>
</body>
</html>

