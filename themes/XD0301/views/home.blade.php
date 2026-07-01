@php
    $blocks = collect($landingBlocks ?? [])->values();

    if ($blocks->isEmpty()) {
        $blocks = collect([
            ['id' => null, 'block_type' => 'hero_slider', 'anchor_id' => 'top', 'is_visible' => true, 'settings' => ['autoplay_ms' => 5200], 'media' => [], 'data' => ['subtitle' => 'Trang chủ', 'title' => 'Xây dựng ngôi nhà mơ ước', 'description' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.', 'button_label' => 'Xem dự án →', 'content' => ['slides' => [
                ['kicker' => 'Residential', 'title' => 'Xây dựng ngôi nhà mơ ước', 'summary' => 'Từ bản vẽ, vật liệu đến thi công hoàn thiện, XD0301 giúp chủ đầu tư kiểm soát chất lượng và tiến độ rõ ràng.', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85'],
                ['kicker' => 'Commercial', 'title' => 'Thi công không gian kinh doanh', 'summary' => 'Đội ngũ kỹ sư và kiến trúc sư phối hợp để bàn giao showroom, văn phòng, khách sạn đúng chuẩn vận hành.', 'image' => 'https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1920&q=85'],
                ['kicker' => 'Planning', 'title' => 'Quản lý dự án minh bạch', 'summary' => 'Quy trình báo cáo theo mốc, nghiệm thu từng hạng mục và tối ưu chi phí ngay từ giai đoạn thiết kế.', 'image' => 'https://images.unsplash.com/photo-1485083269755-a7b559a4fe5e?auto=format&fit=crop&w=1920&q=85'],
            ]]]],
            ['id' => null, 'block_type' => 'about_experience', 'anchor_id' => 'gioi-thieu', 'is_visible' => true, 'settings' => ['years' => 10, 'cta_url' => '/gioi-thieu'], 'media' => ['image' => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?auto=format&fit=crop&w=1000&q=85'], 'data' => ['subtitle' => 'Giới thiệu', 'title' => 'Thiết kế và thi công Nhà ở, Tòa nhà văn phòng.', 'description' => 'ARKIT là công ty chuyên về thiết kế và thi công. Được thành lập và phát triển bởi các kiến trúc sư, kỹ sư nhiều năm kinh nghiệm.', 'button_label' => 'Tìm hiểu thêm', 'content' => []]],
        ]);
    }

    $hero = $blocks->firstWhere('block_type', 'hero_slider') ?? [];
    $heroSlides = collect(data_get($hero, 'data.content.slides', []))
        ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
        ->whenEmpty(fn () => collect(data_get($hero, 'dynamic_items', []))->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
        ->values();
    $heroSlides = $heroSlides->isNotEmpty() ? $heroSlides : collect([['kicker' => 'Residential', 'title' => 'Xây dựng ngôi nhà mơ ước', 'summary' => 'XD0301 Construction Landing', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1920&q=85']]);
    $hasMeaningfulHeroText = function (array $slide, array $data = []): bool {
        $values = [
            $slide['kicker'] ?? $data['subtitle'] ?? null,
            $slide['title'] ?? $data['title'] ?? null,
            $slide['summary'] ?? $data['description'] ?? null,
            $slide['button_label'] ?? $data['button_label'] ?? null,
        ];

        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '' && ! preg_match('/^[\d\s.,:;!?\-+_#]+$/u', $text)) {
                return true;
            }
        }

        return false;
    };

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

    $normalizeNavItem = function (array $item, int $index = 0) use (&$normalizeNavItem, $localizeMenuUrl): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
            'href' => $localizeMenuUrl($href),
            'target' => $item['target'] ?? '_self',
            'active' => false,
            'children' => collect($item['children'] ?? [])
                ->filter(fn ($child): bool => is_array($child) && filled($child['label'] ?? $child['title'] ?? null))
                ->map(fn (array $child, int $childIndex): array => $normalizeNavItem($child, $childIndex))
                ->values()
                ->all(),
        ];
    };

    $cmsNavItems = collect(data_get($themeHomeData ?? $homeData ?? [], 'top_menu', []))
        ->whenEmpty(fn () => collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item, int $index): array => $normalizeNavItem($item, $index))
        ->values();

    $navItems = $cmsNavItems->isNotEmpty()
        ? $cmsNavItems
        : collect($landingMenuItems ?? [])
            ->whenEmpty(fn () => $blocks
                ->filter(fn ($block) => filled($block['anchor_id'] ?? null))
                ->map(fn ($block) => ['label' => data_get($block, 'data.subtitle') ?: data_get($block, 'data.title') ?: \Illuminate\Support\Str::headline($block['block_type']), 'url' => '#'.$block['anchor_id']]))
            ->map(fn ($item, $index) => $normalizeNavItem($item, $index))
            ->values();

    $footerNavItems = collect(data_get($menus ?? [], 'footer', []))
        ->whenEmpty(fn () => collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn (array $item, int $index): array => $normalizeNavItem($item, $index))
        ->values();

    $homeUrl = route('site.home');
    $homeLabel = \Illuminate\Support\Facades\Lang::has('common.home') ? __('common.home') : 'Trang chủ';
    $hasHomeItem = $navItems->contains(function (array $item) use ($homeUrl): bool {
        $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
        $href = rtrim((string) ($item['href'] ?? ''), '/');

        return in_array($label, ['trang chủ', 'home'], true) || $href === rtrim($homeUrl, '/');
    });

    if (! $hasHomeItem) {
        $navItems->prepend([
            'label' => $homeLabel,
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
    }

    $hasProductItem = $navItems->contains(function (array $item): bool {
        return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
    });

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
                'label' => app()->getLocale() === 'en' ? 'Products' : 'Sản phẩm',
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
        $item['active'] = (bool) ($item['active'] ?? false) || ($href !== '#' && $absoluteHref === $currentUrl);

        return $item;
    })->values();

    $footerBlock = $blocks->firstWhere('block_type', 'footer_contact');
    $branding = (array) data_get($themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'XD0301')));
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())
        ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))
        ->map(fn (array $locale): array => [
            'code' => (string) ($locale['code'] ?? ''),
            'label' => (string) (($locale['native_name'] ?? null) ?: ($locale['name'] ?? $locale['code'] ?? '')),
        ])
        ->filter(fn (array $locale): bool => $locale['code'] !== '')
        ->values()
        ->all() : [];
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ data_get($landingPage ?? [], 'meta_title') ?: data_get($landingPage ?? [], 'title', 'XD0301 Construction Landing') }}</title>
    <style>
        :root { --lime:#bdd400; --lime-dark:#9fb500; --ink:#26384a; --muted:#74808a; --line:#e6ebe8; --bg:#fbfcfa; --shadow:0 22px 55px rgba(28,45,60,.13); --font:"Montserrat","Segoe UI",Arial,sans-serif; }
        *{box-sizing:border-box} html{scroll-behavior:smooth} body{margin:0;color:var(--ink);background:#fff;font-family:var(--font);font-size:16px;line-height:1.75} a{text-decoration:none;color:inherit} img{display:block;max-width:100%}
        .xd-container{width:min(1540px,calc(100% - 56px));margin:0 auto}
        .xd-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.96);box-shadow:0 10px 30px rgba(16,29,40,.08);backdrop-filter:blur(14px)}
        .xd-header-inner{display:flex;align-items:center;justify-content:space-between;min-height:102px;gap:34px}
        .xd-logo{display:inline-flex;align-items:center;gap:11px;font-size:34px;font-weight:900;letter-spacing:-.06em;color:var(--ink)}
        .xd-logo-image{display:block;width:auto;max-width:156px;height:64px;object-fit:contain}
        .xd-logo-mark{position:relative;width:38px;height:50px;background:linear-gradient(135deg,var(--lime),#d9ec27);clip-path:polygon(50% 0,100% 22%,100% 100%,0 100%,0 22%)}
        .xd-logo-mark:before,.xd-logo-mark:after{content:"";position:absolute;background:#fff}.xd-logo-mark:before{left:9px;bottom:7px;width:20px;height:30px}.xd-logo-mark:after{left:14px;top:17px;width:10px;height:7px;box-shadow:0 11px 0 #fff}.xd-logo span{color:var(--lime)}
        .xd-nav{display:flex;align-items:center;justify-content:center;gap:0;min-width:0;flex:1}.xd-nav-item{position:relative}.xd-nav-link{display:inline-flex;align-items:center;gap:8px;padding:39px 21px;color:#344354;font-size:15px;font-weight:850;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}.xd-nav-caret{color:var(--lime-dark);font-size:12px;line-height:1;transition:transform .18s ease}.xd-nav-link.is-active,.xd-nav-link:hover,.xd-nav-item:hover>.xd-nav-link,.xd-nav-item:focus-within>.xd-nav-link{color:var(--lime-dark)}.xd-nav-item:hover>.xd-nav-link .xd-nav-caret,.xd-nav-item:focus-within>.xd-nav-link .xd-nav-caret{transform:rotate(180deg)}
        .xd-dropdown{position:absolute;top:100%;left:0;z-index:90;min-width:250px;padding:12px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateY(12px);transition:opacity .18s ease,transform .18s ease,visibility .18s ease}.xd-nav-item:hover>.xd-dropdown,.xd-nav-item:focus-within>.xd-dropdown{opacity:1;visibility:visible;transform:translateY(0)}.xd-dropdown-item{position:relative}.xd-dropdown-link{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 14px;border-left:3px solid transparent;color:#53606b;font-size:14px;font-weight:800;line-height:1.35}.xd-dropdown-link:hover,.xd-dropdown-item:focus-within>.xd-dropdown-link{background:#f7f9ee;border-left-color:var(--lime);color:var(--ink)}.xd-subdropdown{position:absolute;top:0;left:calc(100% + 10px);z-index:91;min-width:230px;padding:10px;background:#fff;border:1px solid var(--line);box-shadow:var(--shadow);opacity:0;visibility:hidden;transform:translateX(-8px);transition:opacity .18s ease,transform .18s ease,visibility .18s ease}.xd-dropdown-item:hover>.xd-subdropdown,.xd-dropdown-item:focus-within>.xd-subdropdown{opacity:1;visibility:visible;transform:translateX(0)}
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font:inherit;font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap;cursor:pointer}.xd-login-button:hover{border-color:var(--lime);color:var(--lime-dark);transform:translateY(-1px)}
        .xd-mobile-menu-toggle{display:none;align-items:center;justify-content:center;gap:9px;min-height:44px;padding:0 16px;border:1px solid rgba(38,56,74,.12);border-radius:999px;background:#101d28;color:#fff;font:inherit;font-size:13px;font-weight:950;letter-spacing:.05em;text-transform:uppercase;box-shadow:0 14px 28px rgba(16,29,40,.16);cursor:pointer}.xd-mobile-menu-toggle:before{content:"";width:16px;height:2px;background:currentColor;box-shadow:0 -6px 0 currentColor,0 6px 0 currentColor}.xd-mobile-panel[hidden]{display:none!important}.xd-mobile-panel{display:none}.xd-mobile-list,.xd-mobile-children{display:grid;gap:8px;margin:0;padding:0;list-style:none}.xd-mobile-link,.xd-mobile-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;min-height:48px;padding:0 14px;border:1px solid #edf0f2;border-radius:14px;background:#fff;color:var(--ink);font-size:14px;font-weight:900;text-transform:uppercase}.xd-mobile-link.is-active,.xd-mobile-summary.is-active{border-color:var(--lime);background:#f8fbde;color:var(--lime-dark)}.xd-mobile-item details{display:grid;gap:8px}.xd-mobile-summary{cursor:pointer;list-style:none}.xd-mobile-summary::-webkit-details-marker{display:none}.xd-mobile-summary:after{content:"+";display:grid;place-items:center;width:24px;height:24px;border-radius:999px;background:#f2f5ed;color:var(--lime-dark);font-size:17px;line-height:1}.xd-mobile-item details[open]>.xd-mobile-summary:after{content:"-";background:var(--lime);color:#fff}.xd-mobile-children{margin:8px 0 0 14px;padding-left:10px;border-left:2px solid #e8efcf}.xd-mobile-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid #edf0f2}.xd-mobile-actions .xd-hotline,.xd-mobile-actions .xd-login-button{width:100%;min-height:48px;justify-content:center;border-radius:14px;padding:0 12px;font-size:13px}
        .xd-landing-block{position:relative}.xd-hero{position:relative;min-height:820px;overflow:hidden;background:#111c24}.xd-slide{position:absolute;inset:0;opacity:0;transition:opacity .7s ease}.xd-slide.is-active{opacity:1}.xd-slide img{width:100%;height:100%;object-fit:cover;filter:grayscale(.15) contrast(1.05)}.xd-slide:after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,15,22,.82),rgba(7,15,22,.42) 42%,rgba(7,15,22,.16))}
        .xd-hero-content{position:relative;z-index:2;display:flex;align-items:center;min-height:820px}.xd-hero-card{position:relative;width:min(560px,92vw);margin-left:90px;padding:88px 78px 82px;color:#fff;border:8px solid rgba(189,212,0,.72)}.xd-hero-card small{display:block;margin-bottom:28px;font-weight:900;text-transform:uppercase}.xd-hero-card h1{margin:0 0 20px;font-size:clamp(42px,3vw,70px);line-height:1.12;letter-spacing:-.05em}.xd-hero-card p{margin:0 0 36px;color:rgba(255,255,255,.88);font-size:18px;font-weight:600}
        .xd-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 34px;color:#fff;background:var(--lime);border:0;border-radius:3px;font-weight:900;text-transform:uppercase;box-shadow:0 15px 30px rgba(189,212,0,.28)}.xd-hero-arrow{position:absolute;z-index:4;top:50%;transform:translateY(-50%);width:54px;height:78px;color:#fff;background:rgba(0,0,0,.35);border:0;font-size:42px;cursor:pointer}.xd-hero-arrow:hover{background:var(--lime)}.xd-hero-arrow.prev{left:0}.xd-hero-arrow.next{right:0}.xd-hero-dots{position:absolute;z-index:4;left:50%;bottom:30px;display:flex;gap:12px;transform:translateX(-50%)}.xd-dot{width:13px;height:13px;border:0;border-radius:999px;background:rgba(255,255,255,.35);cursor:pointer}.xd-dot.is-active{background:var(--lime);box-shadow:0 0 0 7px rgba(189,212,0,.15)}
        .xd-featured-cats{position:relative;padding:56px 0 76px;background:linear-gradient(180deg,#fff 0,#fbfcfa 100%);overflow:hidden}.xd-featured-cats:before{content:"";position:absolute;right:-90px;top:-120px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(189,212,0,.18),transparent 66%)}.xd-featured-cats-head{position:relative;display:flex;align-items:flex-end;justify-content:space-between;gap:28px;margin-bottom:28px}.xd-featured-cats-copy{max-width:720px}.xd-featured-cats-copy .xd-kicker{margin-bottom:6px}.xd-featured-cats h2{margin:0;color:var(--ink);font-size:clamp(30px,2.6vw,46px);line-height:1.15;letter-spacing:-.045em}.xd-featured-cats p{margin:10px 0 0;color:var(--muted);font-size:17px;font-weight:650;line-height:1.6}.xd-featured-cat-grid{position:relative;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.xd-featured-cat{position:relative;min-height:190px;overflow:hidden;border:1px solid rgba(38,56,74,.08);border-radius:22px;background:#101d28;color:#fff;box-shadow:0 12px 32px rgba(16,29,40,.1);isolation:isolate;transition:transform .24s ease,box-shadow .24s ease}.xd-featured-cat:hover{transform:translateY(-6px);box-shadow:0 24px 54px rgba(16,29,40,.18)}.xd-featured-cat img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.74;transition:transform .45s ease,opacity .25s ease}.xd-featured-cat:hover img{transform:scale(1.07);opacity:.88}.xd-featured-cat:before{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(7,15,22,.12),rgba(7,15,22,.84))}.xd-featured-cat-body{position:absolute;z-index:2;left:20px;right:20px;bottom:18px;display:grid;gap:8px}.xd-featured-cat-icon{display:grid;place-items:center;width:46px;height:46px;border-radius:15px;background:rgba(189,212,0,.94);color:#fff;font-size:18px;font-weight:950;box-shadow:0 12px 28px rgba(189,212,0,.28)}.xd-featured-cat h3{margin:0;font-size:19px;line-height:1.22;letter-spacing:-.02em}.xd-featured-cat span{color:rgba(255,255,255,.78);font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.08em}.xd-featured-cat.is-compact{min-height:150px}.xd-featured-cat.is-compact .xd-featured-cat-icon{width:40px;height:40px;border-radius:13px}
        .xd-section{padding:112px 0}.is-dotted,.xd-partners{background:radial-gradient(circle,rgba(38,56,74,.12) 1.6px,transparent 1.8px) 0 0/16px 16px,var(--bg)}.xd-section-title{margin-bottom:68px;text-align:center}.xd-section-title h2,.xd-intro-copy h2{margin:0;color:var(--ink);font-size:clamp(36px,3.2vw,58px);line-height:1.18;letter-spacing:-.045em}.xd-kicker{position:relative;display:inline-block;margin-bottom:8px;font-size:15px;font-weight:900;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:36px;height:36px;border:4px solid var(--lime)}a.xd-kicker{color:var(--ink);text-decoration:none}a.xd-kicker:hover{color:var(--lime-dark)}
        .xd-intro{display:grid;grid-template-columns:minmax(0,.95fr) minmax(420px,.85fr);align-items:center;gap:90px}.xd-intro-copy p{max-width:760px;color:var(--muted);font-size:20px;font-weight:550}.xd-intro-visual{position:relative;min-height:620px}.xd-intro-visual:before{content:"";position:absolute;left:-185px;top:215px;width:470px;height:360px;background:radial-gradient(circle,rgba(38,56,74,.18) 1.6px,transparent 1.8px) 0 0/16px 16px}.xd-intro-visual img{position:absolute;right:0;top:0;width:78%;height:100%;object-fit:cover}.xd-years{position:absolute;z-index:2;left:5%;top:42px;width:176px;height:176px;border:8px solid var(--lime)}.xd-years strong{position:absolute;left:42px;top:92px;color:var(--ink);font-size:140px;line-height:.72;letter-spacing:-.08em}.xd-years span{position:absolute;left:15px;top:245px;min-width:260px;font-size:18px;font-weight:900;text-transform:uppercase}
        .xd-services{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:36px}.xd-service-card{background:#fff;box-shadow:0 5px 20px rgba(16,29,40,.08);transition:.25s}.xd-service-card:hover{transform:translateY(-8px);box-shadow:var(--shadow)}.xd-service-image{display:block;position:relative;height:300px;overflow:hidden;background:linear-gradient(135deg,#edf2f0,#f8faf5)}.xd-service-image:empty:before{content:"No image";position:absolute;inset:0;display:grid;place-items:center;color:var(--muted);font-weight:900;text-transform:uppercase}.xd-service-image img{display:block;width:100%;height:100%;object-fit:cover;transition:.4s}.xd-service-image img.is-broken{display:none}.xd-service-card:hover img:not(.is-broken){transform:scale(1.05)}.xd-service-body{position:relative;padding:40px 42px 46px}.xd-service-card h3{margin:0 0 16px;font-size:22px;line-height:1.32;letter-spacing:.015em;text-transform:uppercase}.xd-service-card h3 a{color:inherit;text-decoration:none}.xd-service-card h3 a:hover{color:var(--lime-dark)}.xd-service-card p{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin:0 0 28px;color:var(--muted);font-size:18px;line-height:1.65}.xd-text-link{color:var(--lime-dark);font-weight:900;text-transform:uppercase}
        .xd-service-slider{position:relative}.xd-service-slider .xd-services{display:flex;grid-template-columns:none;gap:32px;overflow-x:auto;overflow-y:hidden;scroll-behavior:smooth;scroll-snap-type:x mandatory;padding:6px 4px 34px;margin:0 -4px;scrollbar-width:none}.xd-service-slider .xd-services::-webkit-scrollbar{display:none}.xd-service-slider .xd-service-card{flex:0 0 calc((100% - 64px)/3);scroll-snap-align:start}.xd-service-nav{position:absolute;z-index:4;top:35%;display:grid;place-items:center;width:46px;height:46px;border:0;border-radius:999px;background:#fff;color:var(--ink);box-shadow:0 16px 34px rgba(16,29,40,.18);font-size:26px;font-weight:900;cursor:pointer}.xd-service-nav:hover{background:var(--lime);color:#fff}.xd-service-nav.prev{left:-23px}.xd-service-nav.next{right:-23px}.xd-service-dots{display:flex;justify-content:center;gap:9px;margin-top:4px}.xd-service-dot{width:10px;height:10px;border:0;border-radius:999px;background:#d8dfd9;cursor:pointer}.xd-service-dot.is-active{background:var(--lime);box-shadow:0 0 0 6px rgba(189,212,0,.14)}
        .xd-projects{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.xd-project-card{position:relative;min-height:540px;overflow:hidden;color:#fff;background:#101d28}.xd-project-card img{width:100%;height:100%;object-fit:cover;transition:.5s}.xd-project-card:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 45%,rgba(6,13,18,.88))}.xd-project-card:hover img{transform:scale(1.06)}.xd-project-caption{position:absolute;z-index:2;left:36px;right:28px;bottom:34px}.xd-project-caption small{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:12px;font-size:15px}.xd-project-caption h3{margin:0;font-size:28px;line-height:1.2}
        .xd-team{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:44px;align-items:end}.xd-team-card{position:relative;min-height:450px;overflow:hidden;background:linear-gradient(180deg,#f4f6f2,#fff)}.xd-team-card a{display:block;color:inherit}.xd-team-card img{width:100%;height:450px;object-fit:cover;object-position:top center}.xd-name-tab{position:absolute;left:18px;top:115px;padding:18px 13px;background:#fff;color:var(--ink);font-weight:700;writing-mode:vertical-rl}.xd-role{position:absolute;right:32px;bottom:28px;min-width:126px;padding:12px 18px;color:#fff;background:var(--lime);font-size:14px;font-weight:900;text-align:center;text-transform:uppercase}
        .xd-testimonials{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:30px}.xd-testimonial{position:relative;display:grid;grid-template-columns:140px 1fr;gap:34px;min-height:238px;padding:48px 58px 44px;background:#fff;border-left:6px solid var(--lime);box-shadow:0 12px 32px rgba(16,29,40,.08)}.xd-avatar{width:118px;height:118px;border-radius:50%;object-fit:cover}.xd-testimonial p{margin:0 0 18px;color:var(--muted);font-size:19px;font-weight:600}.xd-quote{position:absolute;right:42px;bottom:10px;color:rgba(189,212,0,.56);font-size:118px;font-weight:900;line-height:1}
        .xd-row-slider{position:relative}.xd-row-track{display:flex!important;grid-template-columns:none!important;align-items:stretch;gap:var(--xd-row-gap,30px);overflow-x:auto;overflow-y:hidden;scroll-behavior:smooth;scroll-snap-type:x mandatory;padding-bottom:38px;scrollbar-width:none}.xd-row-track::-webkit-scrollbar{display:none}.xd-row-track>*{flex:0 0 var(--xd-row-card,calc((100% - 90px)/4));scroll-snap-align:start}.xd-project-slider{--xd-row-gap:8px;--xd-row-card:calc((100% - 24px)/4);padding-bottom:10px}.xd-team-slider{--xd-row-gap:44px;--xd-row-card:calc((100% - 132px)/4)}.xd-testimonial-slider{--xd-row-gap:30px;--xd-row-card:calc((100% - 30px)/2)}.xd-row-nav{position:absolute;z-index:5;top:42%;display:grid;place-items:center;width:48px;height:48px;border:0;border-radius:999px;background:#fff;color:var(--ink);box-shadow:0 16px 34px rgba(16,29,40,.18);font-size:28px;font-weight:900;cursor:pointer}.xd-row-nav:hover{background:var(--lime);color:#fff}.xd-row-nav.prev{left:-24px}.xd-row-nav.next{right:-24px}.xd-project-slider .xd-row-nav.prev{left:18px}.xd-project-slider .xd-row-nav.next{right:18px}.xd-row-dots{display:flex;justify-content:center;gap:9px;margin-top:2px}.xd-row-dot{width:10px;height:10px;border:0;border-radius:999px;background:#d8dfd9;cursor:pointer}.xd-row-dot.is-active{background:var(--lime);box-shadow:0 0 0 6px rgba(189,212,0,.14)}
        .xd-partners{padding:74px 0}.xd-partner-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:24px;align-items:center}.xd-partner{display:grid;place-items:center;min-height:104px;color:#9da5aa;background:rgba(255,255,255,.7);font-size:22px;font-weight:950;text-align:center;filter:grayscale(1);transition:.2s}.xd-partner img{max-width:150px;max-height:58px;object-fit:contain}.xd-partner:hover{color:var(--ink);border-color:var(--lime);filter:grayscale(0);transform:translateY(-3px)}
        .xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);font:inherit;outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}.xd-newsletter-note{margin-top:12px;font-size:14px!important;font-weight:800!important;line-height:1.45}.xd-newsletter-note.is-success{color:var(--lime-dark)!important}.xd-newsletter-note.is-error{color:#b42318!important}
        .xd-auth-modal[hidden]{display:none!important}.xd-auth-modal{position:fixed;inset:0;z-index:220;display:grid;place-items:center;padding:24px}.xd-auth-backdrop{position:absolute;inset:0;background:rgba(12,22,30,.66);backdrop-filter:blur(5px)}.xd-auth-card{position:relative;width:min(520px,calc(100vw - 32px));max-height:92vh;overflow:auto;background:#fff;border-radius:24px;padding:30px;box-shadow:0 32px 90px rgba(0,0,0,.34)}.xd-auth-close{position:absolute;right:18px;top:16px;display:grid;place-items:center;width:38px;height:38px;border:0;border-radius:999px;background:#f3f6ef;color:var(--ink);font-size:24px;cursor:pointer}.xd-auth-title{margin:0 48px 8px 0;font-size:30px;line-height:1.2}.xd-auth-note{margin:0 0 22px;color:var(--muted);font-size:15px}.xd-auth-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-bottom:22px;padding:6px;border:1px solid var(--line);border-radius:999px;background:#f8faf5}.xd-auth-tab{min-height:42px;border:0;border-radius:999px;background:transparent;color:var(--ink);font:inherit;font-size:13px;font-weight:900;text-transform:uppercase;cursor:pointer}.xd-auth-tab.is-active{background:var(--lime);color:#fff;box-shadow:0 10px 20px rgba(189,212,0,.26)}.xd-auth-panel{display:none}.xd-auth-panel.is-active{display:block}.xd-auth-form{display:grid;gap:14px}.xd-auth-field{display:grid;gap:7px;color:var(--ink);font-size:13px;font-weight:900;text-transform:uppercase}.xd-auth-field input{width:100%;min-height:50px;border:1px solid #dfe5df;border-radius:14px;padding:0 15px;color:var(--ink);font:inherit;font-size:15px;font-weight:600;outline:0;text-transform:none}.xd-auth-field input:focus{border-color:var(--lime);box-shadow:0 0 0 4px rgba(189,212,0,.15)}.xd-auth-check{display:flex;align-items:center;gap:8px;color:var(--muted);font-size:14px;font-weight:700}.xd-auth-submit{min-height:54px;border:0;border-radius:14px;background:var(--ink);color:#fff;font:inherit;font-weight:900;text-transform:uppercase;cursor:pointer}.xd-auth-submit:hover{background:var(--lime);color:#fff}.xd-auth-feedback{margin:16px 0 0;padding:12px 14px;border-radius:12px;background:#fff4f2;color:#b42318;font-size:14px;font-weight:800}.xd-auth-feedback.is-success{background:#f3f9dc;color:var(--lime-dark)}
        .xd-edit-block{position:absolute;right:18px;top:18px;z-index:20;border:0;border-radius:999px;padding:10px 16px;background:#dc2626;color:#fff;font-weight:900;box-shadow:0 12px 34px rgba(220,38,38,.38);cursor:pointer}.xd-edit-block:hover{background:#b91c1c;box-shadow:0 14px 40px rgba(220,38,38,.5);transform:translateY(-1px)}.xd-editor[hidden]{display:none}.xd-editor{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:20px;background:rgba(10,18,25,.58)}.xd-editor-card{width:min(860px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:20px;padding:24px;box-shadow:0 30px 90px rgba(0,0,0,.28)}.xd-editor-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px}.xd-editor-head h3{margin:0;font-size:26px}.xd-editor-close{border:0;background:#f1f3ef;border-radius:999px;width:40px;height:40px;font-size:24px;cursor:pointer}.xd-editor-locale-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px;padding:6px;border:1px solid #dfe5df;border-radius:999px;background:#fbfcfa}.xd-editor-locale-tab{min-height:38px;padding:0 16px;border:0;border-radius:999px;background:transparent;color:var(--ink);font:inherit;font-size:13px;font-weight:900;cursor:pointer}.xd-editor-locale-tab.is-active{background:var(--lime);color:#fff;box-shadow:0 9px 18px rgba(189,212,0,.22)}.xd-editor-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.xd-editor-field{display:grid;gap:6px}.xd-editor-field span{font-weight:800;color:var(--ink)}.xd-editor-field input,.xd-editor-field textarea{width:100%;border:1px solid #dfe5df;border-radius:12px;padding:12px 14px;font:inherit}.xd-editor-field textarea{min-height:120px}.xd-editor-field.is-wide{grid-column:1/-1}.xd-editor-hidden-json{display:none!important}.xd-editor-items{grid-column:1/-1;display:grid;gap:12px;padding:16px;border:1px solid #dfe5df;border-radius:16px;background:#fbfcfa}.xd-editor-items[hidden]{display:none}.xd-editor-items-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px}.xd-editor-items-head h4{margin:0;font-size:18px}.xd-editor-items-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px}.xd-editor-help{margin:4px 0 0;color:var(--muted);font-size:13px;font-weight:700;line-height:1.5}.xd-editor-item-list{display:grid;gap:10px}.xd-editor-item{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:14px;padding:14px;border:1px solid #e7ece5;border-radius:14px;background:#fff}.xd-editor-item-summary{min-width:0}.xd-editor-item-summary small{display:block;margin-bottom:4px;color:var(--lime-dark);font-size:11px;font-weight:950;text-transform:uppercase;letter-spacing:.06em}.xd-editor-item-summary strong{display:block;overflow:hidden;color:var(--ink);font-size:15px;font-weight:950;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}.xd-editor-item-summary span{display:-webkit-box;overflow:hidden;margin-top:4px;color:var(--muted);font-size:13px;font-weight:700;line-height:1.45;-webkit-box-orient:vertical;-webkit-line-clamp:2}.xd-editor-item-actions{display:flex;align-items:center;gap:8px}.xd-editor-edit,.xd-editor-remove,.xd-editor-add,.xd-editor-manage{border:1px solid #dfe5df;border-radius:999px;background:#fff;color:var(--ink);font-weight:900;cursor:pointer}.xd-editor-edit,.xd-editor-remove{padding:7px 12px}.xd-editor-edit{border-color:rgba(189,212,0,.45);background:#f8fbde;color:var(--lime-dark)}.xd-editor-add,.xd-editor-manage{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:0 14px;text-decoration:none}.xd-editor-manage{background:#101d28;color:#fff;border-color:#101d28}.xd-editor-actions{display:flex;justify-content:flex-end;gap:12px;margin-top:18px}.xd-editor-actions button{min-height:44px;border-radius:12px;padding:0 18px;border:1px solid #dfe5df;background:#fff;font-weight:900;cursor:pointer}.xd-editor-actions button[type=submit]{border-color:var(--lime);background:var(--lime);color:#fff}.xd-item-modal[hidden]{display:none!important}.xd-item-modal{position:fixed;inset:0;z-index:130;display:grid;place-items:center;padding:20px;background:rgba(10,18,25,.56)}.xd-item-card{width:min(660px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:20px;padding:24px;box-shadow:0 30px 90px rgba(0,0,0,.3)}.xd-item-card-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px}.xd-item-card-head h3{margin:0;font-size:24px}.xd-item-close{border:0;background:#f1f3ef;border-radius:999px;width:38px;height:38px;font-size:22px;cursor:pointer}.xd-item-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.xd-item-form label{display:grid;gap:6px;color:var(--ink);font-size:12px;font-weight:900;text-transform:uppercase}.xd-item-form input,.xd-item-form textarea{width:100%;border:1px solid #dfe5df;border-radius:12px;padding:11px 13px;font:inherit;font-size:14px;text-transform:none}.xd-item-form textarea{min-height:112px}.xd-item-form .is-wide{grid-column:1/-1}.xd-item-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}.xd-item-actions button{min-height:42px;border:1px solid #dfe5df;border-radius:12px;background:#fff;padding:0 16px;font-weight:900;cursor:pointer}.xd-item-actions button[type=submit]{border-color:var(--lime);background:var(--lime);color:#fff}
        .xd-editor-item-main{display:grid;grid-template-columns:78px minmax(0,1fr);align-items:center;gap:14px;min-width:0}.xd-editor-item-thumb{display:grid;place-items:center;width:78px;height:58px;overflow:hidden;border:1px solid #dfe5df;border-radius:12px;background:#eef3ee;color:var(--muted);font-size:10px;font-weight:950;text-transform:uppercase}.xd-editor-item-thumb img{width:100%;height:100%;object-fit:cover}.xd-editor-item-thumb.is-empty{background:linear-gradient(135deg,#f7f9ee,#eef3ee)}
        .xd-item-image-field{grid-column:1/-1}.xd-image-mode{display:flex;flex-wrap:wrap;gap:10px;margin:2px 0 6px}.xd-image-mode label{display:inline-flex!important;grid-template-columns:none!important;align-items:center;gap:7px;min-height:34px;padding:0 12px;border:1px solid #dfe5df;border-radius:999px;background:#fff;color:var(--ink);font-size:12px;font-weight:900;text-transform:none;cursor:pointer}.xd-image-mode input{width:auto!important;margin:0}.xd-item-image-field.is-upload-mode>[data-xd-item-modal-field="image"]{display:none}.xd-item-upload{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:8px}.xd-item-upload[hidden]{display:none!important}.xd-item-upload button{min-height:38px;border:1px solid #dfe5df;border-radius:999px;background:#f8fbde;color:var(--lime-dark);padding:0 14px;font-size:12px;font-weight:900;cursor:pointer}.xd-item-upload button:disabled{opacity:.55;cursor:wait}.xd-item-upload small{color:var(--muted);font-size:12px;font-weight:800;text-transform:none}
        .xd-editor-source{grid-column:1/-1;display:grid;gap:12px;padding:16px;border:1px solid #dfe5df;border-radius:16px;background:#fff}.xd-editor-source[hidden],.xd-editor-source label[hidden]{display:none!important}.xd-editor-source h4{margin:0;font-size:18px}.xd-editor-source-grid{display:grid;grid-template-columns:1.15fr .55fr .7fr .6fr;gap:12px;align-items:end}.xd-editor-source label{display:grid;gap:6px;color:var(--ink);font-size:13px;font-weight:900}.xd-editor-source select,.xd-editor-source input{width:100%;min-height:44px;border:1px solid #dfe5df;border-radius:12px;background:#fff;padding:0 12px;color:var(--ink);font:inherit;font-size:14px}.xd-editor-source-check{align-self:center;display:flex!important;grid-template-columns:none!important;align-items:center;gap:9px;padding-top:22px}.xd-editor-source-check input{width:20px;min-height:20px}.xd-editor-source-note{margin:0;color:var(--muted);font-size:13px;font-weight:700;line-height:1.5}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-dropdown{top:calc(100% + 2px)}.xd-hero-card{margin-left:0}.xd-services,.xd-team,.xd-footer-grid,.xd-projects,.xd-featured-cat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.xd-service-slider .xd-service-card{flex-basis:calc((100% - 32px)/2)}.xd-project-slider,.xd-team-slider{--xd-row-card:calc((100% - var(--xd-row-gap))/2)}.xd-testimonial-slider{--xd-row-card:min(720px,82vw)}.xd-intro{grid-template-columns:1fr}}
        @media (max-width:760px){.xd-container{width:min(100% - 28px,1540px)}.xd-header{position:relative}.xd-header-actions{width:100%;justify-content:center}.xd-hotline{justify-content:center}.xd-login-button{min-height:48px}.xd-hero,.xd-hero-content{min-height:690px}.xd-hero-card{padding:48px 34px;border-width:5px}.xd-featured-cats{padding:46px 0 56px}.xd-featured-cats-head{display:grid}.xd-section{padding:72px 0}.xd-services,.xd-projects,.xd-team,.xd-testimonials,.xd-partner-grid,.xd-footer-grid,.xd-editor-grid,.xd-featured-cat-grid{grid-template-columns:1fr}.xd-featured-cat{min-height:170px}.xd-service-slider .xd-service-card{flex-basis:min(86vw,420px)}.xd-project-slider,.xd-team-slider,.xd-testimonial-slider{--xd-row-card:min(86vw,420px)}.xd-service-nav,.xd-row-nav{display:none!important}.xd-project-card{min-height:430px}.xd-testimonial{grid-template-columns:1fr}.xd-intro-visual{min-height:420px}.xd-years strong{font-size:96px}}
        @media (max-width:640px){
            body{font-size:15px;line-height:1.65;overflow-x:hidden}
            .xd-container{width:min(100% - 24px,1540px)}
            .xd-header{position:sticky}
            .xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}
            .xd-logo{font-size:25px;gap:8px;letter-spacing:-.055em}
            .xd-logo-image{max-width:132px;height:52px}
            .xd-logo-mark{width:29px;height:38px;flex:0 0 auto}
            .xd-logo-mark:before{left:7px;bottom:5px;width:15px;height:23px}
            .xd-logo-mark:after{left:11px;top:13px;width:8px;height:5px;box-shadow:0 8px 0 #fff}
            .xd-header-actions{display:none}.xd-hotline{width:auto;min-height:42px;padding:0 14px;border-radius:999px;font-size:0;box-shadow:0 10px 22px rgba(189,212,0,.26)}
            .xd-hotline::after{content:"19009477";font-size:13px;letter-spacing:.03em}
            .xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px;box-shadow:0 10px 22px rgba(16,29,40,.07)}
            .xd-nav{display:none}
            .xd-mobile-menu-toggle{display:inline-flex;margin-left:auto}
            .xd-mobile-panel{position:fixed;left:12px;right:12px;top:76px;z-index:120;max-height:calc(100dvh - 92px);overflow:auto;padding:14px;border:1px solid rgba(38,56,74,.1);border-radius:22px;background:rgba(255,255,255,.98);box-shadow:0 28px 80px rgba(16,29,40,.24);backdrop-filter:blur(14px)}
            .xd-mobile-panel.is-open{display:block}
            .xd-mobile-actions .xd-hotline{font-size:0}
            .xd-mobile-actions .xd-login-button{font-size:12px;box-shadow:none}
            .xd-mobile-actions .xd-hotline:after{content:"19009477";font-size:13px}
            .xd-nav::-webkit-scrollbar{display:none}
            .xd-nav-item{scroll-snap-align:start;flex:0 0 auto}
            .xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;color:#2d3c4b;font-size:12px;font-weight:900;letter-spacing:.035em;box-shadow:0 8px 18px rgba(16,29,40,.06)}
            .xd-nav-link.is-active{background:var(--lime);border-color:var(--lime);color:#fff}
            .xd-dropdown{top:calc(100% + 8px);left:0;min-width:220px;max-width:calc(100vw - 24px);padding:8px;border-radius:16px}
            .xd-dropdown-link{padding:10px 12px;font-size:13px}
            .xd-subdropdown{position:static;display:none;min-width:0;margin:4px 0 4px 10px;padding:4px;border:0;border-left:2px solid var(--lime);box-shadow:none;opacity:1;visibility:visible;transform:none}
            .xd-dropdown-item:hover>.xd-subdropdown,.xd-dropdown-item:focus-within>.xd-subdropdown{display:block}
            .xd-hero,.xd-hero-content{min-height:560px}
            .xd-slide img{object-position:center}
            .xd-slide:after{background:linear-gradient(180deg,rgba(7,15,22,.45),rgba(7,15,22,.82))}
            .xd-hero-content{align-items:flex-end;padding-bottom:54px}
            .xd-hero-card{width:100%;margin:0;padding:28px 24px 30px;border-width:4px;background:rgba(10,18,25,.22);backdrop-filter:blur(2px)}
            .xd-hero-card small{margin-bottom:14px;font-size:12px;letter-spacing:.08em}
            .xd-hero-card h1{font-size:clamp(34px,10vw,46px);line-height:1.05}
            .xd-hero-card p{margin-bottom:24px;font-size:15px;line-height:1.65}
            .xd-button{width:100%;min-height:50px;padding:0 18px;font-size:13px}
            .xd-hero-arrow{top:auto;bottom:14px;width:40px;height:44px;font-size:30px}
            .xd-hero-arrow.prev{left:12px}
            .xd-hero-arrow.next{right:12px}
            .xd-hero-dots{bottom:27px;gap:8px}
            .xd-dot{width:9px;height:9px}
            .xd-dot.is-active{box-shadow:0 0 0 5px rgba(189,212,0,.16)}
            .xd-section{padding:58px 0}
            .xd-section-title{margin-bottom:34px;text-align:left}
            .xd-section-title h2,.xd-intro-copy h2{font-size:clamp(30px,9vw,40px);line-height:1.08;letter-spacing:-.055em}
            .xd-kicker{margin-left:18px;font-size:12px}
            .xd-kicker:before{left:-15px;top:-9px;width:27px;height:27px;border-width:4px}
            .xd-intro{gap:30px}
            .xd-intro-copy p{font-size:16px;line-height:1.75}
            .xd-intro-visual{min-height:360px;order:-1}
            .xd-intro-visual:before{left:-40px;top:120px;width:220px;height:180px;background-size:13px 13px}
            .xd-intro-visual img{position:absolute;right:0;width:86%;border-radius:18px}
            .xd-years{left:0;top:24px;width:112px;height:112px;border-width:5px;background:rgba(255,255,255,.72)}
            .xd-years strong{left:25px;top:57px;font-size:78px}
            .xd-years span{left:8px;top:149px;min-width:170px;font-size:12px}
            .xd-services{gap:18px}
            .xd-service-card{border-radius:18px;overflow:hidden}
            .xd-service-image{height:210px}
            .xd-service-body{padding:28px 22px 28px}
            .xd-service-card h3{font-size:19px}
            .xd-service-card p{font-size:15px;line-height:1.7}
            .xd-projects{gap:12px;padding:0 12px}
            .xd-project-card{min-height:320px;border-radius:18px}
            .xd-project-caption{left:20px;right:18px;bottom:22px}
            .xd-project-caption small{font-size:11px;line-height:1.5}
            .xd-project-caption h3{font-size:22px}
            .xd-team{gap:18px}
            .xd-team-card{min-height:360px;border-radius:18px}
            .xd-team-card img{height:360px}
            .xd-name-tab{top:82px;left:12px;padding:14px 10px;font-size:13px}
            .xd-role{right:18px;bottom:18px;min-width:108px;padding:10px 14px;font-size:12px}
            .xd-testimonials{gap:16px}
            .xd-testimonial{grid-template-columns:78px 1fr;gap:16px;min-height:0;padding:24px 22px;border-radius:18px}
            .xd-avatar{width:72px;height:72px}
            .xd-testimonial p{font-size:15px;line-height:1.65}
            .xd-quote{right:18px;bottom:0;font-size:64px}
            .xd-partners{padding:46px 0}
            .xd-partner-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
            .xd-partner{min-height:74px;padding:10px;font-size:15px;border-radius:14px}
            .xd-footer{padding:52px 0 42px}
            .xd-footer-grid{gap:28px}
            .xd-footer .xd-logo{font-size:28px}
            .xd-footer .xd-logo-image{max-width:126px;height:52px}
            .xd-footer h3{margin-bottom:12px;font-size:24px}
            .xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}
            .xd-newsletter{display:grid;border-radius:16px;overflow:hidden}
            .xd-newsletter input{min-height:52px;padding:0 16px}
            .xd-newsletter button{width:100%;min-height:52px}
            .xd-edit-block{right:12px;top:12px;padding:8px 12px;font-size:12px}
            .xd-auth-modal{align-items:end;padding:0}.xd-auth-card{width:100%;max-height:88vh;border-radius:22px 22px 0 0;padding:24px 18px}.xd-auth-title{font-size:24px}.xd-auth-tabs{border-radius:18px}.xd-auth-tab{border-radius:14px}.xd-editor{align-items:end;padding:0;background:rgba(10,18,25,.62)}
            .xd-editor-card{width:100%;max-height:88vh;border-radius:22px 22px 0 0;padding:18px}
            .xd-editor-head h3{font-size:20px}
            .xd-editor-source-grid{grid-template-columns:1fr}
            .xd-editor-source-check{padding-top:0}
            .xd-editor-item{grid-template-columns:1fr}
            .xd-editor-item-main{grid-template-columns:64px minmax(0,1fr);gap:12px}
            .xd-editor-item-thumb{width:64px;height:50px;border-radius:10px}
            .xd-editor-item-actions{justify-content:flex-end}
            .xd-item-modal{align-items:end;padding:0}
            .xd-item-card{width:100%;max-height:86vh;border-radius:22px 22px 0 0;padding:18px}
            .xd-item-card-head h3{font-size:20px}
            .xd-item-form{grid-template-columns:1fr}
            .xd-item-actions{position:sticky;bottom:-18px;margin:18px -18px -18px;padding:12px 18px;background:#fff;border-top:1px solid var(--line)}
            .xd-item-actions button{flex:1}
            .xd-editor-actions{position:sticky;bottom:-18px;margin:18px -18px -18px;padding:12px 18px;background:#fff;border-top:1px solid var(--line)}
            .xd-editor-actions button{flex:1}
        }
        @media (max-width:380px){
            .xd-container{width:min(100% - 18px,1540px)}
            .xd-logo{font-size:22px}
            .xd-logo-image{max-width:120px;height:46px}
            .xd-hotline::after{font-size:12px}
            .xd-hero,.xd-hero-content{min-height:520px}
            .xd-hero-card{padding:24px 20px}
            .xd-hero-card h1{font-size:32px}
            .xd-projects{padding:0 9px}
        }
    </style>
</head>
<body>
    <div id="top" class="xd-page">
        <header class="xd-header">
            <div class="xd-container xd-header-inner">
                <a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt }} trang chủ">
                    @if ($logoUrl !== '')
                        <img class="xd-logo-image" src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
                    @else
                        <i class="xd-logo-mark" aria-hidden="true"></i><b>ar<span>kit</span>.</b>
                    @endif
                </a>
                <button type="button" class="xd-mobile-menu-toggle" data-xd-mobile-menu-toggle aria-expanded="false" aria-controls="xd-mobile-menu">Menu</button>
                <nav class="xd-nav" aria-label="Menu chính">
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
                    <a class="xd-hotline" href="tel:19009477"><span aria-hidden="true">&#9742;</span> 19009477</a>
                    @if (auth('customer')->check())
                        <a class="xd-login-button" href="{{ route('customer.account') }}">Tài khoản</a>
                    @else
                        <button type="button" class="xd-login-button" data-xd-auth-open="login">Đăng nhập</button>
                    @endif
                </div>
                <div id="xd-mobile-menu" class="xd-mobile-panel" data-xd-mobile-menu hidden>
                    <ul class="xd-mobile-list">
                        @foreach ($navItems as $item)
                            <li class="xd-mobile-item">
                                @if (!empty($item['children']))
                                    <details>
                                        <summary class="xd-mobile-summary {{ ($item['active'] ?? false) ? 'is-active' : '' }}">{{ $item['label'] }}</summary>
                                        <ul class="xd-mobile-children">
                                            <li><a class="xd-mobile-link" href="{{ $item['href'] }}" target="{{ $item['target'] ?? '_self' }}">Xem {{ $item['label'] }}</a></li>
                                            @foreach (collect($item['children'])->take(12) as $child)
                                                <li class="xd-mobile-item">
                                                    @if (!empty($child['children']))
                                                        <details>
                                                            <summary class="xd-mobile-summary">{{ $child['label'] ?? 'Menu' }}</summary>
                                                            <ul class="xd-mobile-children">
                                                                <li><a class="xd-mobile-link" href="{{ $child['href'] ?? ($item['href'] ?? '#') }}" target="{{ $child['target'] ?? '_self' }}">Xem {{ $child['label'] ?? 'Menu' }}</a></li>
                                                                @foreach (collect($child['children'])->take(12) as $grandChild)
                                                                    <li><a class="xd-mobile-link" href="{{ $grandChild['href'] ?? ($child['href'] ?? '#') }}" target="{{ $grandChild['target'] ?? '_self' }}">{{ $grandChild['label'] ?? 'Menu' }}</a></li>
                                                                @endforeach
                                                            </ul>
                                                        </details>
                                                    @else
                                                        <a class="xd-mobile-link" href="{{ $child['href'] ?? ($item['href'] ?? '#') }}" target="{{ $child['target'] ?? '_self' }}">{{ $child['label'] ?? 'Menu' }}</a>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @else
                                    <a class="xd-mobile-link {{ ($item['active'] ?? false) ? 'is-active' : '' }}" href="{{ $item['href'] }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <div class="xd-mobile-actions">
                        <a class="xd-hotline" href="tel:19009477"><span aria-hidden="true">&#9742;</span> 19009477</a>
                        @if (auth('customer')->check())
                            <a class="xd-login-button" href="{{ route('customer.account') }}">Tài khoản</a>
                        @else
                            <button type="button" class="xd-login-button" data-xd-auth-open="login">Đăng nhập</button>
                        @endif
                    </div>
                </div>
            </div>
        </header>

        <main>
            @foreach ($blocks->where('block_type', '!=', 'footer_contact') as $block)
                @php
                    $data = $block['data'] ?? [];
                    $content = $data['content'] ?? [];
                    $settings = $block['settings'] ?? [];
                    $media = $block['media'] ?? [];
                    $anchor = $block['anchor_id'] ?: $block['block_type'];
                    $editButton = $canEditLanding && filled($block['id'] ?? null)
                        ? '<button type="button" class="xd-edit-block" data-xd-edit-block="'.e((string) $block['id']).'">Sửa khối</button>'
                        : '';
                @endphp

                @switch($block['block_type'])
                    @case('hero_slider')
                        @php
                            $slides = collect($content['slides'] ?? [])
                                ->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null))
                                ->whenEmpty(fn () => collect($block['dynamic_items'] ?? [])->filter(fn ($slide) => is_array($slide) && filled($slide['image'] ?? null)))
                                ->values();
                            $firstSlide = $slides->first() ?? [];
                            $showHeroCard = is_array($firstSlide) && $hasMeaningfulHeroText($firstSlide, $data);
                        @endphp
                        <section id="{{ $anchor }}" class="xd-hero xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            @foreach ($slides as $slide)
                                <article class="xd-slide {{ $loop->first ? 'is-active' : '' }}"><img src="{{ $slide['image'] }}" alt="{{ $slide['alt'] ?? $slide['title'] ?? $data['title'] ?? 'Banner' }}"></article>
                            @endforeach
                            @if ($showHeroCard)
                                <div class="xd-container xd-hero-content"><div class="xd-hero-card"><small data-hero-kicker>{{ $firstSlide['kicker'] ?? $data['subtitle'] ?? '' }}</small><h1 data-hero-title>{{ $firstSlide['title'] ?? $data['title'] ?? '' }}</h1><p data-hero-summary>{{ $firstSlide['summary'] ?? $data['description'] ?? '' }}</p><a class="xd-button" href="{{ $firstSlide['link_url'] ?? '#du-an' }}" data-hero-link>{{ $firstSlide['button_label'] ?? $data['button_label'] ?? 'Xem dự án →' }}</a></div></div>
                            @endif
                            <button class="xd-hero-arrow prev" type="button" data-slide-prev aria-label="Slide trước">&#8249;</button><button class="xd-hero-arrow next" type="button" data-slide-next aria-label="Slide sau">&#8250;</button>
                            <div class="xd-hero-dots" aria-label="Chọn slide">@foreach ($slides as $slide)<button class="xd-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-slide-dot="{{ $loop->index }}" aria-label="Slide {{ $loop->iteration }}"></button>@endforeach</div>
                        </section>
                        @break

                    @case('featured_categories')
                        @php
                            $categoryItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? []))->values();
                        @endphp
                        <section id="{{ $anchor }}" class="xd-featured-cats xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container">
                                <div class="xd-featured-cats-head">
                                    <div class="xd-featured-cats-copy">
                                        <span class="xd-kicker">{{ $data['subtitle'] ?? 'Khám phá nhanh' }}</span>
                                        <h2>{{ $data['title'] ?? 'Danh mục trọng tâm' }}</h2>
                                        @if(filled($data['description'] ?? null))
                                            <p>{{ $data['description'] }}</p>
                                        @endif
                                    </div>
                                    @if(filled($data['button_label'] ?? null))
                                        <a class="xd-text-link" href="#dich-vu">{{ $data['button_label'] }}</a>
                                    @endif
                                </div>
                                <div class="xd-featured-cat-grid">
                                    @foreach ($categoryItems as $category)
                                        <a class="xd-featured-cat {{ $categoryItems->count() > 4 ? 'is-compact' : '' }}" href="{{ $category['url'] ?? '#dich-vu' }}">
                                            @if(filled($category['image'] ?? null))
                                                <img src="{{ $category['image'] }}" alt="{{ $category['alt'] ?? $category['title'] ?? '' }}">
                                            @endif
                                            <span class="xd-featured-cat-body">
                                                <i class="xd-featured-cat-icon">{{ $category['icon'] ?? mb_substr((string) ($category['title'] ?? 'C'), 0, 1) }}</i>
                                                <h3>{{ $category['title'] ?? $category['name'] ?? '' }}</h3>
                                                @if(filled($category['count_label'] ?? $category['summary'] ?? null))
                                                    <span>{{ $category['count_label'] ?? $category['summary'] }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                        @break

                    @case('about_experience')
                        @php $aboutCtaUrl = $localizeMenuUrl($settings['cta_url'] ?? '/gioi-thieu'); @endphp
                        <section id="{{ $anchor }}" class="xd-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container xd-intro"><div class="xd-intro-copy"><h2>{!! nl2br(e($data['title'] ?? '')) !!}</h2><p>{!! nl2br(e($data['description'] ?? '')) !!}</p><a class="xd-button" href="{{ $aboutCtaUrl }}">{{ $data['button_label'] ?? 'Tìm hiểu thêm' }}</a></div><div class="xd-intro-visual"><div class="xd-years"><strong>{{ $settings['years'] ?? 10 }}</strong><span>Năm kinh nghiệm</span></div><img src="{{ $media['image'] ?? '' }}" alt="{{ $data['title'] ?? 'Giới thiệu' }}"></div></div>
                        </section>
                        @break

                    @case('featured_services')
                        @php $serviceItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? [])); @endphp
                        <section id="{{ $anchor }}" class="xd-section is-dotted xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container"><div class="xd-section-title"><a class="xd-kicker" href="{{ route('site.services.index') }}">{{ $data['subtitle'] ?? 'Dịch vụ' }}</a><h2>{{ $data['title'] ?? '' }}</h2></div><div class="xd-service-slider" data-service-slider><button class="xd-service-nav prev" type="button" data-service-prev aria-label="Dịch vụ trước">&#8249;</button><div class="xd-services" data-service-track>@foreach ($serviceItems as $service)<article class="xd-service-card"><a class="xd-service-image" href="{{ $service['url'] ?? '#lien-he' }}"><img src="{{ $service['image'] ?? '' }}" alt="{{ $service['alt'] ?? $service['title'] ?? '' }}"></a><div class="xd-service-body"><h3><a href="{{ $service['url'] ?? '#lien-he' }}">{{ $service['title'] ?? '' }}</a></h3><p>{{ $service['summary'] ?? '' }}</p><a class="xd-text-link" href="{{ $service['url'] ?? '#lien-he' }}">{{ $service['button_label'] ?? $data['button_label'] ?? 'Tìm hiểu ngay' }}</a></div></article>@endforeach</div><button class="xd-service-nav next" type="button" data-service-next aria-label="Dịch vụ tiếp theo">&#8250;</button><div class="xd-service-dots" data-service-dots>@foreach ($serviceItems as $service)<button class="xd-service-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-service-dot="{{ $loop->index }}" aria-label="Nhóm dịch vụ {{ $loop->iteration }}"></button>@endforeach</div></div></div>
                        </section>
                        @break

                    @case('project_gallery')
                        @php $projectItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? [])); @endphp
                        <section id="{{ $anchor }}" class="xd-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container"><div class="xd-section-title"><span class="xd-kicker">{{ $data['subtitle'] ?? 'Dự án' }}</span><h2>{{ $data['title'] ?? '' }}</h2></div></div><div class="xd-row-slider xd-project-slider" data-row-slider><button class="xd-row-nav prev" type="button" data-row-prev aria-label="Dự án trước">&#8249;</button><div class="xd-projects xd-row-track" data-row-track>@foreach ($projectItems as $project)<a class="xd-project-card" href="{{ $project['url'] ?? '#lien-he' }}"><img src="{{ $project['image'] ?? '' }}" alt="{{ $project['alt'] ?? $project['title'] ?? '' }}"><span class="xd-project-caption"><small>{{ $project['tag'] ?? $project['summary'] ?? '' }}</small><h3>{{ $project['title'] ?? '' }}</h3></span></a>@endforeach</div><button class="xd-row-nav next" type="button" data-row-next aria-label="Dự án tiếp theo">&#8250;</button><div class="xd-row-dots" data-row-dots>@foreach ($projectItems as $project)<button class="xd-row-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-row-dot="{{ $loop->index }}" aria-label="Dự án {{ $loop->iteration }}"></button>@endforeach</div></div>
                        </section>
                        @break

                    @case('team_members')
                        @php $teamItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? [])); @endphp
                        <section id="{{ $anchor }}" class="xd-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container"><div class="xd-section-title"><span class="xd-kicker">{{ $data['subtitle'] ?? 'Đội ngũ' }}</span><h2>{{ $data['title'] ?? '' }}</h2></div><div class="xd-row-slider xd-team-slider" data-row-slider><button class="xd-row-nav prev" type="button" data-row-prev aria-label="Nhân sự trước">&#8249;</button><div class="xd-team xd-row-track" data-row-track>@foreach ($teamItems as $member)<article class="xd-team-card"><a href="{{ $member['url'] ?? '#lien-he' }}"><img src="{{ $member['image'] ?? '' }}" alt="{{ $member['alt'] ?? $member['name'] ?? '' }}"><span class="xd-name-tab">{{ $member['name'] ?? '' }}</span><strong class="xd-role">{{ $member['role'] ?? $member['department'] ?? '' }}</strong></a></article>@endforeach</div><button class="xd-row-nav next" type="button" data-row-next aria-label="Nhân sự tiếp theo">&#8250;</button><div class="xd-row-dots" data-row-dots>@foreach ($teamItems as $member)<button class="xd-row-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-row-dot="{{ $loop->index }}" aria-label="Nhân sự {{ $loop->iteration }}"></button>@endforeach</div></div></div>
                        </section>
                        @break

                    @case('testimonials')
                        @php $testimonialItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? [])); @endphp
                        <section id="{{ $anchor }}" class="xd-section is-dotted xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container"><div class="xd-section-title"><span class="xd-kicker">{{ $data['subtitle'] ?? 'Đánh giá' }}</span><h2>{{ $data['title'] ?? '' }}</h2></div><div class="xd-row-slider xd-testimonial-slider" data-row-slider><button class="xd-row-nav prev" type="button" data-row-prev aria-label="Đánh giá trước">&#8249;</button><div class="xd-testimonials xd-row-track" data-row-track>@foreach ($testimonialItems as $testimonial)<article class="xd-testimonial"><img class="xd-avatar" src="{{ $testimonial['image'] ?? '' }}" alt="{{ $testimonial['alt'] ?? $testimonial['name'] ?? '' }}"><div><p>{{ $testimonial['quote'] ?? '' }}</p><strong>{{ $testimonial['name'] ?? '' }}</strong>@if(filled($testimonial['company'] ?? $testimonial['role'] ?? null))<small>{{ $testimonial['company'] ?? $testimonial['role'] }}</small>@endif</div><span class="xd-quote">&rdquo;</span></article>@endforeach</div><button class="xd-row-nav next" type="button" data-row-next aria-label="Đánh giá tiếp theo">&#8250;</button><div class="xd-row-dots" data-row-dots>@foreach ($testimonialItems as $testimonial)<button class="xd-row-dot {{ $loop->first ? 'is-active' : '' }}" type="button" data-row-dot="{{ $loop->index }}" aria-label="Đánh giá {{ $loop->iteration }}"></button>@endforeach</div></div></div>
                        </section>
                        @break

                    @case('partner_logos')
                        @php $partnerItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? [])); @endphp
                        <section id="{{ $anchor }}" class="xd-partners xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
                            {!! $editButton !!}
                            <div class="xd-container xd-partner-grid">@foreach ($partnerItems as $partner)<a class="xd-partner" href="{{ $partner['href'] ?? $partner['url'] ?? '#' }}" aria-label="Đối tác {{ $partner['name'] ?? $partner['title'] ?? '' }}">@if(filled($partner['image'] ?? null))<img src="{{ $partner['image'] }}" alt="{{ $partner['alt'] ?? $partner['name'] ?? $partner['title'] ?? '' }}">@else{{ $partner['name'] ?? $partner['title'] ?? '' }}@endif</a>@endforeach</div>
                        </section>
                        @break
                @endswitch
            @endforeach
        </main>

        @php
            $footerData = data_get($footerBlock, 'data', []);
            $footerContent = data_get($footerData, 'content', []);
            $footerEdit = $canEditLanding && filled(data_get($footerBlock, 'id')) ? '<button type="button" class="xd-edit-block" data-xd-edit-block="'.e((string) data_get($footerBlock, 'id')).'">Sửa khối</button>' : '';
        @endphp
        <footer id="{{ data_get($footerBlock, 'anchor_id', 'lien-he') }}" class="xd-footer xd-landing-block" data-landing-block-id="{{ data_get($footerBlock, 'id') }}" data-block-type="footer_contact">
            {!! $footerEdit !!}
            <div class="xd-container xd-footer-grid">
                <div><a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt }} trang chủ">@if ($logoUrl !== '')<img class="xd-logo-image" src="{{ $logoUrl }}" alt="{{ $logoAlt }}">@else<i class="xd-logo-mark" aria-hidden="true"></i><b>ar<span>kit</span>.</b>@endif</a><p>{{ $footerData['description'] ?? 'Arkit là công ty chuyên về thiết kế và thi công.' }}</p></div>
                <div><h3>Thông tin</h3><nav class="xd-footer-links" aria-label="Thông tin">@foreach ($footerNavItems as $item)<a href="{{ $item['href'] }}" target="{{ $item['target'] ?? '_self' }}" @if (($item['target'] ?? '_self') === '_blank') rel="noopener noreferrer" @endif>{{ $item['label'] }}</a>@endforeach</nav></div>
                <div><h3>{{ $footerData['subtitle'] ?? 'Liên hệ' }}</h3><div class="xd-contact-list"><a href="https://maps.google.com/?q={{ urlencode($footerContent['address'] ?? '') }}">&#128205; {{ $footerContent['address'] ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM' }}</a><a href="mailto:{{ $footerContent['email'] ?? 'admin@demo031086.web30s.vn' }}">&#9993; {{ $footerContent['email'] ?? 'admin@demo031086.web30s.vn' }}</a><a href="tel:{{ $footerContent['phone'] ?? '19009477' }}">&#9742; {{ $footerContent['phone'] ?? '19009477' }}</a></div></div>
                <div>
                    <h3>{{ $footerData['title'] ?? 'Đăng ký nhận tin' }}</h3>
                    <p>Đăng ký email để nhận thông tin mới nhất từ chúng tôi</p>
                    <form class="xd-newsletter" action="{{ route('site.newsletter.subscribe') }}" method="post">
                        @csrf
                        <input type="hidden" name="source" value="theme-footer-xd0301">
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="Địa chỉ email....." aria-label="Địa chỉ email" required>
                        <button type="submit">{{ $footerData['button_label'] ?? 'Đăng ký' }}</button>
                    </form>
                    @if (session('cart_success'))
                        <p class="xd-newsletter-note is-success">{{ session('cart_success') }}</p>
                    @endif
                    @if ($errors->has('email'))
                        <p class="xd-newsletter-note is-error">{{ $errors->first('email') }}</p>
                    @endif
                </div>
            </div>
        </footer>
    </div>

    @unless (auth('customer')->check())
        <div class="xd-auth-modal" data-xd-auth-modal hidden>
            <div class="xd-auth-backdrop" data-xd-auth-close></div>
            <section class="xd-auth-card" role="dialog" aria-modal="true" aria-labelledby="xd-auth-title">
                <button type="button" class="xd-auth-close" aria-label="Đóng" data-xd-auth-close>&times;</button>
                <h2 id="xd-auth-title" class="xd-auth-title">Tài khoản khách hàng</h2>
                <p class="xd-auth-note">Đăng nhập để lưu thông tin tư vấn, hoặc đăng ký nhanh nếu bạn chưa có tài khoản.</p>
                <div class="xd-auth-tabs" role="tablist" aria-label="Tài khoản khách hàng">
                    <button type="button" class="xd-auth-tab is-active" role="tab" aria-selected="true" data-xd-auth-tab="login">Đăng nhập</button>
                    <button type="button" class="xd-auth-tab" role="tab" aria-selected="false" data-xd-auth-tab="register">Đăng ký</button>
                </div>
                <div class="xd-auth-panel is-active" data-xd-auth-panel="login">
                    <form class="xd-auth-form" action="{{ route('customer.auth.store') }}" method="post" data-xd-auth-form="login">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                        <label class="xd-auth-field">
                            <span>Email hoặc username</span>
                            <input type="text" name="login" autocomplete="username" required>
                        </label>
                        <label class="xd-auth-field">
                            <span>Mật khẩu</span>
                            <input type="password" name="password" autocomplete="current-password" required>
                        </label>
                        <label class="xd-auth-check">
                            <input type="checkbox" name="remember" value="1">
                            <span>Ghi nhớ đăng nhập</span>
                        </label>
                        <button type="submit" class="xd-auth-submit">Đăng nhập</button>
                    </form>
                </div>
                <div class="xd-auth-panel" data-xd-auth-panel="register">
                    <form class="xd-auth-form" action="{{ route('customer.auth.register.store') }}" method="post" data-xd-auth-form="register">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                        <label class="xd-auth-field">
                            <span>Họ tên</span>
                            <input type="text" name="name" autocomplete="name" required>
                        </label>
                        <label class="xd-auth-field">
                            <span>Email</span>
                            <input type="email" name="email" autocomplete="email" required>
                        </label>
                        <label class="xd-auth-field">
                            <span>Số điện thoại</span>
                            <input type="tel" name="phone" autocomplete="tel">
                        </label>
                        <label class="xd-auth-field">
                            <span>Mật khẩu</span>
                            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                        </label>
                        <label class="xd-auth-field">
                            <span>Nhập lại mật khẩu</span>
                            <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                        </label>
                        <button type="submit" class="xd-auth-submit">Tạo tài khoản</button>
                    </form>
                </div>
                <p class="xd-auth-feedback" data-xd-auth-feedback hidden></p>
            </section>
        </div>
    @endunless

    @if ($canEditLanding)
        <div class="xd-editor" data-xd-editor hidden>
            <form class="xd-editor-card" data-xd-editor-form>
                <div class="xd-editor-head"><h3>Sửa khối landing</h3><button type="button" class="xd-editor-close" data-xd-editor-close>&times;</button></div>
                <input type="hidden" name="block_id" data-xd-field="block_id">
                <input type="hidden" data-xd-field="locale" value="{{ app()->getLocale() }}">
                <div class="xd-editor-locale-tabs" data-xd-locale-tabs>
                    @foreach ($editorLocales as $locale)
                        <button type="button" class="xd-editor-locale-tab {{ $locale['code'] === app()->getLocale() ? 'is-active' : '' }}" data-xd-locale-tab="{{ $locale['code'] }}">
                            {{ $locale['label'] }}
                        </button>
                    @endforeach
                </div>
                <div class="xd-editor-grid">
                    <label class="xd-editor-field"><span>Anchor</span><input data-xd-field="anchor_id"></label>
                    <label class="xd-editor-field"><span>Tiêu đề</span><input data-xd-field="title"></label>
                    <label class="xd-editor-field"><span>Nhãn phụ</span><input data-xd-field="subtitle"></label>
                    <label class="xd-editor-field is-wide"><span>Mô tả</span><textarea data-xd-field="description"></textarea></label>
                    <label class="xd-editor-field"><span>Nút CTA</span><input data-xd-field="button_label"></label>
                    <label class="xd-editor-field"><span>Link CTA</span><input data-xd-field="cta_url" placeholder="/gioi-thieu hoặc https://..."></label>
                    <label class="xd-editor-field"><span>Hiển thị</span><input data-xd-field="is_visible" type="checkbox"></label>
                    <section class="xd-editor-source" data-xd-source-editor hidden>
                        <div>
                            <h4>Nguồn nội dung</h4>
                            <p class="xd-editor-source-note">Chọn bảng dữ liệu dùng để tự động lấy danh sách item cho khối này.</p>
                        </div>
                        <div class="xd-editor-source-grid">
                            <label><span>Lấy từ bảng</span><select data-xd-setting-field="source"></select></label>
                            <label><span>Số lượng</span><input type="number" min="1" max="12" data-xd-setting-field="limit"></label>
                            <label><span>Danh mục</span><select data-xd-setting-field="category_id"></select></label>
                            <label class="xd-editor-source-check"><input type="checkbox" data-xd-setting-field="featured_only"><span>Chỉ nổi bật</span></label>
                        </div>
                    </section>
                    <section class="xd-editor-items" data-xd-items-editor hidden>
                        <div class="xd-editor-items-head">
                            <div>
                                <h4>Danh sách nội dung</h4>
                                <p class="xd-editor-help" data-xd-items-help>Chỉnh từng mục bằng form, không cần nhập JSON.</p>
                            </div>
                            <div class="xd-editor-items-actions">
                                <a class="xd-editor-manage" data-xd-manage-source href="#" target="_blank" rel="noopener" hidden>Quản lý nội dung</a>
                                <button type="button" class="xd-editor-add" data-xd-add-item>Thêm mục</button>
                            </div>
                        </div>
                        <div class="xd-editor-item-list" data-xd-item-list></div>
                    </section>
                    <textarea class="xd-editor-hidden-json" data-xd-field="content" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="settings" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="media" aria-hidden="true" tabindex="-1"></textarea>
                </div>
                <div class="xd-editor-actions"><button type="button" data-xd-editor-close>Hủy</button><button type="submit">Lưu</button></div>
            </form>
        </div>
        <div class="xd-item-modal" data-xd-item-modal hidden>
            <form class="xd-item-card" data-xd-item-form>
                <div class="xd-item-card-head">
                    <h3 data-xd-item-title>Thêm mục</h3>
                    <button type="button" class="xd-item-close" data-xd-item-close>&times;</button>
                </div>
                <input type="hidden" data-xd-item-index>
                <div class="xd-item-form" data-xd-item-form-fields></div>
                <div class="xd-item-actions">
                    <button type="button" data-xd-item-close>Hủy</button>
                    <button type="submit">Lưu mục</button>
                </div>
            </form>
        </div>
    @endif

    <script>
        (() => {
            const mobileToggle = document.querySelector('[data-xd-mobile-menu-toggle]');
            const mobileMenu = document.querySelector('[data-xd-mobile-menu]');
            const closeMobileMenu = () => {
                if (!mobileToggle || !mobileMenu) return;
                mobileMenu.hidden = true;
                mobileMenu.classList.remove('is-open');
                mobileToggle.setAttribute('aria-expanded', 'false');
            };
            mobileToggle?.addEventListener('click', () => {
                const willOpen = mobileMenu?.hidden;
                if (!mobileMenu) return;
                mobileMenu.hidden = !willOpen;
                mobileMenu.classList.toggle('is-open', Boolean(willOpen));
                mobileToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });
            document.addEventListener('click', (event) => {
                if (!mobileMenu || mobileMenu.hidden) return;
                if (mobileMenu.contains(event.target) || mobileToggle?.contains(event.target)) return;
                closeMobileMenu();
            });
            document.querySelectorAll('.xd-mobile-link').forEach((link) => {
                link.addEventListener('click', closeMobileMenu);
            });
            const slides = Array.from(document.querySelectorAll('.xd-slide'));
            const dots = Array.from(document.querySelectorAll('.xd-dot'));
            const copy = @json($heroSlides);
            const title = document.querySelector('[data-hero-title]');
            const kicker = document.querySelector('[data-hero-kicker]');
            const summary = document.querySelector('[data-hero-summary]');
            const heroLink = document.querySelector('[data-hero-link]');
            const heroCard = document.querySelector('.xd-hero-card');
            let index = 0;
            let timer = null;
            const hasMeaningfulHeroText = (item = {}) => {
                return ['kicker', 'title', 'summary', 'button_label'].some((key) => {
                    const value = String(item?.[key] || '').trim();
                    return value !== '' && !/^[\d\s.,:;!?\-+_#]+$/u.test(value);
                });
            };
            document.querySelectorAll('.xd-service-image img').forEach((image) => {
                if (!image.currentSrc && !image.getAttribute('src')) image.classList.add('is-broken');
                image.addEventListener('error', () => image.classList.add('is-broken'), {once: true});
            });
            const show = (next) => {
                if (!slides.length) return;
                index = (next + slides.length) % slides.length;
                slides.forEach((slide, slideIndex) => slide.classList.toggle('is-active', slideIndex === index));
                dots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === index));
                if (heroCard) heroCard.hidden = !hasMeaningfulHeroText(copy[index] || {});
                if (title) title.textContent = copy[index]?.title || '';
                if (kicker) kicker.textContent = copy[index]?.kicker || '';
                if (summary) summary.textContent = copy[index]?.summary || '';
                if (heroLink) {
                    heroLink.href = copy[index]?.link_url || '#du-an';
                    heroLink.textContent = copy[index]?.button_label || heroLink.textContent;
                }
            };
            const restart = () => { window.clearInterval(timer); timer = window.setInterval(() => show(index + 1), Number(@json(data_get($hero, 'settings.autoplay_ms', 5200)))); };
            document.querySelector('[data-slide-prev]')?.addEventListener('click', () => { show(index - 1); restart(); });
            document.querySelector('[data-slide-next]')?.addEventListener('click', () => { show(index + 1); restart(); });
            dots.forEach((dot) => dot.addEventListener('click', () => { show(Number(dot.dataset.slideDot || 0)); restart(); }));
            restart();

            document.querySelectorAll('[data-service-slider]').forEach((slider) => {
                const track = slider.querySelector('[data-service-track]');
                const cards = Array.from(slider.querySelectorAll('.xd-service-card'));
                const prev = slider.querySelector('[data-service-prev]');
                const next = slider.querySelector('[data-service-next]');
                const serviceDots = Array.from(slider.querySelectorAll('[data-service-dot]'));
                if (!track || cards.length <= 1) return;

                let serviceTimer = null;
                const cardStep = () => {
                    const first = cards[0];
                    const second = cards[1];
                    if (!first) return track.clientWidth;
                    if (!second) return first.getBoundingClientRect().width;
                    return second.getBoundingClientRect().left - first.getBoundingClientRect().left;
                };
                const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
                const isScrollable = () => maxScroll() > 6;
                const setServiceControls = () => {
                    const visible = isScrollable();
                    [prev, next, slider.querySelector('[data-service-dots]')].forEach((element) => {
                        if (element) element.style.display = visible ? '' : 'none';
                    });
                };
                const activeIndex = () => Math.round(track.scrollLeft / Math.max(1, cardStep()));
                const updateServiceDots = () => {
                    const current = activeIndex();
                    serviceDots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === current));
                };
                const goService = (direction = 1) => {
                    if (!isScrollable()) return;
                    const nextLeft = track.scrollLeft + (cardStep() * direction);
                    track.scrollTo({left: nextLeft > maxScroll() + 2 ? 0 : Math.max(0, nextLeft), behavior: 'smooth'});
                };
                const restartService = () => {
                    window.clearInterval(serviceTimer);
                    if (isScrollable()) serviceTimer = window.setInterval(() => goService(1), 4200);
                };

                prev?.addEventListener('click', () => { goService(-1); restartService(); });
                next?.addEventListener('click', () => { goService(1); restartService(); });
                serviceDots.forEach((dot) => dot.addEventListener('click', () => {
                    track.scrollTo({left: cardStep() * Number(dot.dataset.serviceDot || 0), behavior: 'smooth'});
                    restartService();
                }));
                track.addEventListener('scroll', () => window.requestAnimationFrame(updateServiceDots), {passive: true});
                window.addEventListener('resize', () => { setServiceControls(); updateServiceDots(); restartService(); });
                setServiceControls();
                updateServiceDots();
                restartService();
            });

            document.querySelectorAll('[data-row-slider]').forEach((slider) => {
                const track = slider.querySelector('[data-row-track]');
                const cards = Array.from(track?.children || []);
                const prev = slider.querySelector('[data-row-prev]');
                const next = slider.querySelector('[data-row-next]');
                const rowDots = Array.from(slider.querySelectorAll('[data-row-dot]'));
                const dotsWrap = slider.querySelector('[data-row-dots]');
                if (!track || cards.length <= 1) return;

                let rowTimer = null;
                const cardStep = () => {
                    const first = cards[0];
                    const second = cards[1];
                    if (!first) return track.clientWidth;
                    if (!second) return first.getBoundingClientRect().width;
                    return second.getBoundingClientRect().left - first.getBoundingClientRect().left;
                };
                const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);
                const isScrollable = () => maxScroll() > 6;
                const setControls = () => {
                    const visible = isScrollable();
                    [prev, next, dotsWrap].forEach((element) => {
                        if (element) element.style.display = visible ? '' : 'none';
                    });
                };
                const activeIndex = () => Math.round(track.scrollLeft / Math.max(1, cardStep()));
                const updateDots = () => {
                    const current = activeIndex();
                    rowDots.forEach((dot, dotIndex) => dot.classList.toggle('is-active', dotIndex === current));
                };
                const go = (direction = 1) => {
                    if (!isScrollable()) return;
                    const nextLeft = track.scrollLeft + (cardStep() * direction);
                    track.scrollTo({left: nextLeft > maxScroll() + 2 ? 0 : Math.max(0, nextLeft), behavior: 'smooth'});
                };
                const restartRow = () => {
                    window.clearInterval(rowTimer);
                    if (isScrollable()) rowTimer = window.setInterval(() => go(1), 4600);
                };

                prev?.addEventListener('click', () => { go(-1); restartRow(); });
                next?.addEventListener('click', () => { go(1); restartRow(); });
                rowDots.forEach((dot) => dot.addEventListener('click', () => {
                    track.scrollTo({left: cardStep() * Number(dot.dataset.rowDot || 0), behavior: 'smooth'});
                    restartRow();
                }));
                track.addEventListener('scroll', () => window.requestAnimationFrame(updateDots), {passive: true});
                window.addEventListener('resize', () => { setControls(); updateDots(); restartRow(); });
                setControls();
                updateDots();
                restartRow();
            });

            const authModal = document.querySelector('[data-xd-auth-modal]');
            const authFeedback = document.querySelector('[data-xd-auth-feedback]');
            const authTabs = Array.from(document.querySelectorAll('[data-xd-auth-tab]'));
            const authPanels = Array.from(document.querySelectorAll('[data-xd-auth-panel]'));
            const setAuthFeedback = (message = '', isSuccess = false) => {
                if (!authFeedback) return;
                authFeedback.textContent = message;
                authFeedback.hidden = message === '';
                authFeedback.classList.toggle('is-success', isSuccess);
            };
            const setAuthTab = (tab) => {
                authTabs.forEach((button) => {
                    const isActive = button.dataset.xdAuthTab === tab;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                authPanels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.xdAuthPanel === tab));
                setAuthFeedback();
            };
            const openAuthModal = (tab = 'login') => {
                if (!authModal) return;
                setAuthTab(tab);
                authModal.hidden = false;
                document.body.style.overflow = 'hidden';
                window.setTimeout(() => authModal.querySelector('input:not([type="hidden"])')?.focus(), 30);
            };
            const closeAuthModal = () => {
                if (!authModal) return;
                authModal.hidden = true;
                document.body.style.overflow = '';
                setAuthFeedback();
            };
            document.querySelectorAll('[data-xd-auth-open]').forEach((button) => {
                button.addEventListener('click', () => openAuthModal(button.dataset.xdAuthOpen || 'login'));
            });
            document.querySelectorAll('[data-xd-auth-close]').forEach((button) => button.addEventListener('click', closeAuthModal));
            authTabs.forEach((button) => button.addEventListener('click', () => setAuthTab(button.dataset.xdAuthTab || 'login')));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && authModal && !authModal.hidden) closeAuthModal();
            });
            document.querySelectorAll('[data-xd-auth-form]').forEach((form) => {
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const submit = form.querySelector('[type="submit"]');
                    const originalLabel = submit?.textContent || '';
                    setAuthFeedback();
                    if (submit) {
                        submit.disabled = true;
                        submit.textContent = 'Đang xử lý...';
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: new FormData(form),
                        });
                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = Object.values(payload.errors || {}).flat()[0];
                            throw new Error(firstError || payload.message || 'Không xử lý được yêu cầu.');
                        }
                        setAuthFeedback(payload.message || 'Thành công. Đang chuyển trang...', true);
                        window.location.href = payload.data?.redirect_to || window.location.href;
                    } catch (error) {
                        setAuthFeedback(error.message || 'Không xử lý được yêu cầu.');
                    } finally {
                        if (submit) {
                            submit.disabled = false;
                            submit.textContent = originalLabel;
                        }
                    }
                });
            });

            const canEdit = @json($canEditLanding);
            if (!canEdit) return;
            const blocks = @json($blockPayload);
            const editorLocales = @json($editorLocales);
            const editorOptions = @json($landingEditorOptions ?? []);
            const updateUrlTemplate = @json($blockUpdateUrlTemplate);
            const sourcePreviewUrlTemplate = @json($blockSourcePreviewUrlTemplate);
            const mediaUploadUrl = '/admin/api/cms/media';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const editor = document.querySelector('[data-xd-editor]');
            const form = document.querySelector('[data-xd-editor-form]');
            const field = (name) => form?.querySelector(`[data-xd-field="${name}"]`);
            const pretty = (value) => JSON.stringify(value || {}, null, 2);
            const parseJson = (value, fallback) => {
                try { return value.trim() ? JSON.parse(value) : fallback; } catch (error) { throw new Error('JSON không hợp lệ: ' + error.message); }
            };
            const itemsEditor = document.querySelector('[data-xd-items-editor]');
            const itemList = document.querySelector('[data-xd-item-list]');
            const itemHelp = document.querySelector('[data-xd-items-help]');
            const addItemButton = document.querySelector('[data-xd-add-item]');
            const itemModal = document.querySelector('[data-xd-item-modal]');
            const itemForm = document.querySelector('[data-xd-item-form]');
            const itemFormFields = document.querySelector('[data-xd-item-form-fields]');
            const itemModalTitle = document.querySelector('[data-xd-item-title]');
            const itemIndexInput = document.querySelector('[data-xd-item-index]');
            const sourceEditor = document.querySelector('[data-xd-source-editor]');
            const manageSourceLink = document.querySelector('[data-xd-manage-source]');
            const sourceSettingFields = Array.from(document.querySelectorAll('[data-xd-setting-field]'));
            const localeTabs = Array.from(document.querySelectorAll('[data-xd-locale-tab]'));
            let activeItemKey = '';
            let activeBlock = null;
            let activeEditorLocale = @json(app()->getLocale());
            let localeDrafts = {};
            let sourcePreviewController = null;
            let sourcePreviewTimer = null;
            const sourceLabels = {
                custom: 'Nội dung tùy chỉnh',
                catalog_categories: 'Danh mục sản phẩm',
                cms_service_categories: 'Danh mục dịch vụ',
                cms_categories: 'Danh mục tin tức',
                cms_services: 'Dịch vụ',
                cms_products: 'Sản phẩm',
                catalog_products: 'Sản phẩm',
                featured_products: 'Sản phẩm nổi bật',
                cms_posts: 'Tin tức',
                latest_posts: 'Tin mới nhất',
                cms_projects: 'Dự án',
                cms_team_members: 'Đội ngũ',
                cms_testimonials: 'Đánh giá',
            };
            const sourceManageUrls = {
                cms_services: '/admin/cms/services',
                catalog_categories: '/admin/cms/products',
                cms_service_categories: '/admin/cms/services',
                cms_categories: '/admin/cms/posts',
                cms_posts: '/admin/cms/posts',
                latest_posts: '/admin/cms/posts',
                cms_products: '/admin/cms/products',
                catalog_products: '/admin/cms/products',
                featured_products: '/admin/cms/products',
                cms_projects: '/admin/cms/projects',
                cms_team_members: '/admin/cms/team',
                cms_testimonials: '/admin/cms/testimonials',
            };
            const defaultBlockLimits = {
                hero_slider: 3,
                featured_categories: 6,
                featured_services: 3,
                project_gallery: 4,
                team_members: 4,
                testimonials: 2,
                partner_logos: 6,
            };
            const categoryOptionsBySource = editorOptions.categories_by_source || {};

            const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));
            const uploadItemImage = async (file, targetInput, statusNode, triggerButton) => {
                if (!file || !targetInput) return;

                const payload = new FormData();
                payload.append('file', file);
                payload.append('title', file.name.replace(/\.[^.]+$/, ''));
                payload.append('alt_text', targetInput.closest('form')?.querySelector('[data-xd-item-modal-field="title"], [data-xd-item-modal-field="name"]')?.value || file.name);

                if (statusNode) statusNode.textContent = 'Đang upload...';
                if (triggerButton) triggerButton.disabled = true;

                try {
                    const response = await fetch(mediaUploadUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrf ? {'X-CSRF-TOKEN': csrf} : {}),
                        },
                        body: payload,
                    });

                    if (!response.ok) {
                        const errorPayload = await response.json().catch(() => ({}));
                        throw new Error(errorPayload.message || 'Upload ảnh không thành công.');
                    }

                    const result = await response.json();
                    targetInput.value = result?.data?.file_url || '';
                    targetInput.dispatchEvent(new Event('input', {bubbles: true}));
                    if (statusNode) statusNode.textContent = 'Đã upload ảnh.';
                } catch (error) {
                    if (statusNode) statusNode.textContent = error.message || 'Không upload được ảnh.';
                } finally {
                    if (triggerButton) triggerButton.disabled = false;
                }
            };
            const parseItemData = (row) => {
                try { return JSON.parse(row?.dataset.xdItem || '{}'); } catch (error) { return {}; }
            };
            const normalizeContentObject = (value) => {
                return value && typeof value === 'object' && !Array.isArray(value) ? value : {};
            };
            const settingField = (name) => sourceSettingFields.find((input) => input.dataset.xdSettingField === name);
            const sourceControlWrap = (name) => settingField(name)?.closest('label');
            const currentSourceValue = () => settingField('source')?.value || activeBlock?.settings?.source || '';
            const isCustomSource = () => !sourceEditor || sourceEditor.hidden || currentSourceValue() === 'custom' || currentSourceValue() === '';
            const syncSourceModeUi = () => {
                const customMode = isCustomSource();
                ['limit', 'category_id', 'featured_only'].forEach((name) => {
                    const wrap = sourceControlWrap(name);
                    const input = settingField(name);
                    if (wrap) wrap.hidden = customMode;
                    if (input) input.disabled = customMode;
                });
                if (addItemButton) addItemButton.hidden = !customMode;
                if (manageSourceLink) {
                    const source = currentSourceValue();
                    const url = sourceManageUrls[source] || '';
                    manageSourceLink.hidden = customMode || url === '';
                    manageSourceLink.href = url || '#';
                    manageSourceLink.textContent = `Quản lý ${sourceLabels[source] || 'nội dung'}`;
                }
            };
            const normalizeSourceOptions = (options = []) => options
                .map((option) => typeof option === 'string' ? {value: option, label: sourceLabels[option] || option} : {
                    value: option.value || option.key || '',
                    label: option.label || sourceLabels[option.value || option.key] || option.value || option.key || '',
                })
                .filter((option) => option.value !== '');
            const renderCategorySelect = (source, selectedValue = '') => {
                const categorySelect = settingField('category_id');
                if (!categorySelect) return;

                const options = categoryOptionsBySource[source] || [];
                categorySelect.innerHTML = [
                    '<option value="">Tất cả danh mục</option>',
                    ...options.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`),
                ].join('');
                categorySelect.value = selectedValue ? String(selectedValue) : '';
                categorySelect.disabled = options.length === 0;
                categorySelect.onchange = () => scheduleSourcePreview();
            };
            const renderSourceEditor = (block) => {
                if (!sourceEditor) return;
                const schema = block?.settings_schema || {};
                const sourceSchema = schema.source || null;
                const options = normalizeSourceOptions(sourceSchema?.options || []);

                if (!sourceSchema || options.length === 0) {
                    sourceEditor.hidden = true;
                    return;
                }

                const settings = parseJson(field('settings').value, {});
                const sourceSelect = settingField('source');
                if (sourceSelect) {
                    sourceSelect.innerHTML = options
                        .map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`)
                        .join('');
                    sourceSelect.value = settings.source || options[0]?.value || '';
                    sourceSelect.onchange = () => {
                        renderCategorySelect(sourceSelect.value, '');
                        syncSourceModeUi();
                        if (sourceSelect.value === 'custom') {
                            renderItemsEditor(activeBlock, parseJson(field('content').value, {}));
                        } else {
                            scheduleSourcePreview();
                        }
                    };
                }

                const limitInput = settingField('limit');
                if (limitInput) {
                    limitInput.value = settings.limit ?? schema.limit?.default ?? defaultBlockLimits[block.block_type] ?? 3;
                    limitInput.oninput = () => scheduleSourcePreview();
                }

                renderCategorySelect(sourceSelect?.value || settings.source || options[0]?.value || '', settings.category_id ?? '');

                const featuredInput = settingField('featured_only');
                if (featuredInput) {
                    featuredInput.checked = settings.featured_only !== false;
                    featuredInput.onchange = () => scheduleSourcePreview();
                }

                sourceEditor.hidden = false;
                syncSourceModeUi();
            };
            const collectSourceSettings = (settings) => {
                if (!sourceEditor || sourceEditor.hidden) return settings;

                const next = {...settings};
                const source = settingField('source')?.value || '';
                const limit = Number(settingField('limit')?.value || 0);
                const categoryId = Number(settingField('category_id')?.value || 0);
                const featuredOnly = settingField('featured_only');

                if (source !== '') next.source = source;
                if (source === 'custom') {
                    delete next.limit;
                    delete next.category_id;
                    delete next.featured_only;
                    return next;
                }
                if (limit > 0) next.limit = Math.max(1, Math.min(12, limit));
                else delete next.limit;
                if (categoryId > 0) next.category_id = categoryId;
                else delete next.category_id;
                if (featuredOnly) next.featured_only = Boolean(featuredOnly.checked);

                return next;
            };

            const editorItemKey = (blockType) => blockType === 'hero_slider' ? 'slides' : 'items';
            const editorItemFields = (blockType) => {
                if (blockType === 'hero_slider') {
                    return [
                        ['kicker', 'Nhãn nhỏ'],
                        ['title', 'Tiêu đề'],
                        ['summary', 'Mô tả', 'textarea'],
                        ['image', 'Ảnh'],
                        ['link_url', 'Link'],
                        ['button_label', 'Nút bấm'],
                    ];
                }

                if (blockType === 'featured_categories') {
                    return [
                        ['title', 'Tiêu đề'],
                        ['summary', 'Mô tả / nhãn phụ', 'textarea'],
                        ['image', 'Ảnh'],
                        ['icon', 'Icon / ký tự'],
                        ['url', 'Link'],
                        ['count_label', 'Nhãn số lượng'],
                    ];
                }

                if (blockType === 'testimonials') {
                    return [
                        ['name', 'Tên khách hàng'],
                        ['company', 'Công ty / vai trò'],
                        ['quote', 'Nhận xét', 'textarea'],
                        ['image', 'Ảnh đại diện'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'team_members') {
                    return [
                        ['name', 'Tên nhân sự'],
                        ['role', 'Chức vụ'],
                        ['image', 'Ảnh'],
                        ['url', 'Link'],
                    ];
                }

                if (blockType === 'partner_logos') {
                    return [
                        ['name', 'Tên đối tác'],
                        ['image', 'Logo / ảnh'],
                        ['url', 'Link'],
                        ['alt', 'Alt ảnh'],
                    ];
                }

                return [
                    ['title', 'Tiêu đề'],
                    ['summary', 'Mô tả', 'textarea'],
                    ['image', 'Ảnh'],
                    ['url', 'Link'],
                    ['button_label', 'Nút bấm'],
                ];
            };
            const renderEditorItem = (item = {}, index = 0, blockType = '', canEditItem = true) => {
                const row = document.createElement('article');
                row.className = 'xd-editor-item';
                row.dataset.xdItemRow = '1';
                row.dataset.xdItem = JSON.stringify(item || {});
                const title = item.title || item.name || item.kicker || `Mục ${index + 1}`;
                const summary = item.summary || item.description || item.quote || item.role || item.company || item.url || item.link_url || 'Chưa có mô tả.';
                const image = item.image || item.image_url || item.thumbnail || item.logo || item.avatar || '';
                const imageAlt = item.alt || title;
                const thumb = image
                    ? `<span class="xd-editor-item-thumb"><img src="${escapeHtml(image)}" alt="${escapeHtml(imageAlt)}"></span>`
                    : '<span class="xd-editor-item-thumb is-empty">No img</span>';
                row.innerHTML = `
                    <div class="xd-editor-item-main">
                        ${thumb}
                        <div class="xd-editor-item-summary">
                            <small>Mục ${index + 1}</small>
                            <strong>${escapeHtml(title)}</strong>
                            <span>${escapeHtml(summary)}</span>
                        </div>
                    </div>
                    ${canEditItem ? `<div class="xd-editor-item-actions">
                        <button type="button" class="xd-editor-edit" data-xd-edit-item>Sửa</button>
                        <button type="button" class="xd-editor-remove" data-xd-remove-item>Xóa</button>
                    </div>` : ''}
                `;
                if (canEditItem) {
                    row.querySelector('[data-xd-edit-item]')?.addEventListener('click', () => {
                        const currentIndex = Array.from(itemList.querySelectorAll('[data-xd-item-row]')).indexOf(row);
                        openItemModal(currentIndex, parseItemData(row));
                    });
                    row.querySelector('[data-xd-remove-item]')?.addEventListener('click', () => {
                        row.remove();
                        syncItemNumbers();
                    });
                }
                return row;
            };
            const syncItemNumbers = () => {
                itemList?.querySelectorAll('[data-xd-item-row]').forEach((row, index) => {
                    const badge = row.querySelector('.xd-editor-item-summary small');
                    if (badge) badge.textContent = `Mục ${index + 1}`;
                });
            };
            const closeItemModal = () => {
                if (!itemModal) return;
                itemModal.hidden = true;
                if (itemFormFields) itemFormFields.innerHTML = '';
                if (itemIndexInput) itemIndexInput.value = '';
            };
            const openItemModal = (index = null, item = {}) => {
                if (!itemModal || !itemFormFields || !activeBlock) return;
                const blockType = activeBlock.block_type || addItemButton?.dataset.xdBlockType || '';
                itemModalTitle.textContent = index === null ? 'Thêm mục' : `Sửa mục ${index + 1}`;
                itemIndexInput.value = index === null ? '' : String(index);
                itemFormFields.innerHTML = '';
                editorItemFields(blockType).forEach(([key, label, type]) => {
                    const control = document.createElement('label');
                    control.className = type === 'textarea' ? 'is-wide' : '';
                    if (key === 'image') control.className = `${control.className} xd-item-image-field`.trim();
                    control.innerHTML = type === 'textarea'
                        ? `<span>${escapeHtml(label)}</span><textarea data-xd-item-modal-field="${escapeHtml(key)}"></textarea>`
                        : `<span>${escapeHtml(label)}</span><input data-xd-item-modal-field="${escapeHtml(key)}">`;
                    const input = control.querySelector('[data-xd-item-modal-field]');
                    input.value = item?.[key] ?? '';
                    if (key === 'image') {
                        const modeWrap = document.createElement('div');
                        modeWrap.className = 'xd-image-mode';
                        modeWrap.innerHTML = `
                            <label><input type="radio" name="xd_item_image_mode" value="url" checked> Nhập liên kết hình ảnh</label>
                            <label><input type="radio" name="xd_item_image_mode" value="upload"> Upload ảnh</label>
                        `;
                        const uploadWrap = document.createElement('div');
                        uploadWrap.className = 'xd-item-upload';
                        uploadWrap.hidden = true;
                        uploadWrap.innerHTML = '<input type="file" accept="image/*" data-xd-item-upload hidden><button type="button" data-xd-item-upload-trigger>Upload ảnh</button><small data-xd-item-upload-status></small>';
                        const fileInput = uploadWrap.querySelector('[data-xd-item-upload]');
                        const triggerButton = uploadWrap.querySelector('[data-xd-item-upload-trigger]');
                        const statusNode = uploadWrap.querySelector('[data-xd-item-upload-status]');
                        const syncImageMode = () => {
                            const mode = modeWrap.querySelector('input[name="xd_item_image_mode"]:checked')?.value || 'url';
                            uploadWrap.hidden = mode !== 'upload';
                            control.classList.toggle('is-upload-mode', mode === 'upload');
                        };
                        modeWrap.querySelectorAll('input[name="xd_item_image_mode"]').forEach((radio) => radio.addEventListener('change', syncImageMode));
                        triggerButton?.addEventListener('click', () => fileInput?.click());
                        fileInput?.addEventListener('change', () => {
                            const file = fileInput.files?.[0];
                            if (file) uploadItemImage(file, input, statusNode, triggerButton);
                        });
                        control.insertBefore(modeWrap, input);
                        control.appendChild(uploadWrap);
                        syncImageMode();
                    }
                    itemFormFields.appendChild(control);
                });
                itemModal.hidden = false;
            };
            const blockHasDynamicItems = (block) => Array.isArray(block?.dynamic_items) && block.dynamic_items.length > 0;
            const renderItemsEditor = (block, contentOverride = null) => {
                if (!itemsEditor || !itemList) return;
                const blockType = block.block_type || '';
                activeItemKey = editorItemKey(blockType);
                const content = normalizeContentObject(contentOverride || block.data?.content || {});
                let items = Array.isArray(content[activeItemKey]) ? content[activeItemKey] : [];
                const canEditList = ['hero_slider', 'featured_categories', 'featured_services', 'project_gallery', 'team_members', 'testimonials', 'partner_logos'].includes(blockType);

                itemList.innerHTML = '';
                if (!canEditList) {
                    itemsEditor.hidden = true;
                    return;
                }

                if (!items.length && blockHasDynamicItems(block)) {
                    items = block.dynamic_items;
                }

                itemsEditor.hidden = false;
                const customMode = isCustomSource();
                syncSourceModeUi();
                itemHelp.textContent = customMode
                    ? 'Nội dung tùy chỉnh: có thể thêm, sửa hoặc xóa từng mục ngay tại đây.'
                    : `Danh sách đang lấy tự động từ ${sourceLabels[currentSourceValue()] || 'CMS'}. Muốn sửa từng item, mở trang quản lý tương ứng.`;

                items.forEach((item, index) => itemList.appendChild(renderEditorItem(item, index, blockType, customMode)));
                if (!items.length && customMode) itemList.appendChild(renderEditorItem({}, 0, blockType, true));
                addItemButton.dataset.xdBlockType = blockType;
            };
            const alwaysCollectItemBlocks = ['hero_slider', 'partner_logos'];
            const shouldCollectEditorItems = () => {
                const blockType = activeBlock?.block_type || addItemButton?.dataset.xdBlockType || '';
                return alwaysCollectItemBlocks.includes(blockType) || isCustomSource();
            };
            const previewSourceItems = async () => {
                if (!activeBlock || !sourcePreviewUrlTemplate || !sourceEditor || sourceEditor.hidden) return;
                if (isCustomSource()) {
                    renderItemsEditor(activeBlock, parseJson(field('content').value, {}));
                    return;
                }

                const settingsPayload = collectSourceSettings(parseJson(field('settings').value, {}));
                field('settings').value = pretty(settingsPayload);
                if (itemHelp) itemHelp.textContent = 'Đang tải lại danh sách theo nguồn nội dung...';

                sourcePreviewController?.abort();
                sourcePreviewController = new AbortController();

                const params = new URLSearchParams({locale: activeEditorLocale});
                Object.entries(settingsPayload).forEach(([key, value]) => {
                    if (value !== null && value !== '' && value !== undefined) {
                        params.set(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
                    }
                });

                try {
                    const response = await fetch(`${sourcePreviewUrlTemplate.replace('__BLOCK_ID__', activeBlock.id)}?${params.toString()}`, {
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        signal: sourcePreviewController.signal,
                    });
                    if (!response.ok) throw new Error('Không tải được danh sách nội dung.');

                    const payload = await response.json();
                    const items = payload.data?.items || [];
                    activeBlock = {...activeBlock, settings: settingsPayload, dynamic_items: items};
                    blocks[activeBlock.id] = activeBlock;

                    const previewContent = {};
                    previewContent[activeItemKey || editorItemKey(activeBlock.block_type)] = items;
                    renderItemsEditor(activeBlock, previewContent);
                } catch (error) {
                    if (error.name === 'AbortError') return;
                    if (itemHelp) itemHelp.textContent = error.message || 'Không tải được danh sách nội dung.';
                }
            };
            const scheduleSourcePreview = () => {
                window.clearTimeout(sourcePreviewTimer);
                sourcePreviewTimer = window.setTimeout(() => {
                    previewSourceItems();
                }, 250);
            };
            const collectEditorItems = () => {
                if (!shouldCollectEditorItems()) return null;
                if (!itemsEditor || itemsEditor.hidden || !activeItemKey) return null;
                return Array.from(itemList.querySelectorAll('[data-xd-item-row]'))
                    .map(parseItemData)
                    .filter((item) => Object.keys(item).length > 0);
            };
            const syncEditorItemsIntoContentField = () => {
                const editorItems = collectEditorItems();
                if (!editorItems) return;

                const content = normalizeContentObject(parseJson(field('content').value, {}));
                content[activeItemKey] = editorItems;
                field('content').value = pretty(content);

                if (localeDrafts[activeEditorLocale]) {
                    localeDrafts[activeEditorLocale].content = content;
                }
            };
            const collectCurrentLocaleDraft = () => {
                const content = normalizeContentObject(parseJson(field('content').value, {}));
                const editorItems = collectEditorItems();
                if (editorItems) content[activeItemKey] = editorItems;

                return {
                    locale: activeEditorLocale,
                    title: field('title').value,
                    subtitle: field('subtitle').value,
                    description: field('description').value,
                    button_label: field('button_label').value,
                    content,
                };
            };
            const loadLocaleDraft = (locale) => {
                if (!activeBlock) return;
                const draft = localeDrafts[locale] || {locale, content: {}};
                activeEditorLocale = locale;
                field('locale').value = locale;
                field('title').value = draft.title || '';
                field('subtitle').value = draft.subtitle || '';
                field('description').value = draft.description || '';
                field('button_label').value = draft.button_label || '';
                field('content').value = pretty(normalizeContentObject(draft.content || {}));
                renderItemsEditor(activeBlock, normalizeContentObject(draft.content || {}));
                if (!isCustomSource()) scheduleSourcePreview();
                localeTabs.forEach((button) => button.classList.toggle('is-active', button.dataset.xdLocaleTab === locale));
            };
            const switchEditorLocale = (locale) => {
                if (!activeBlock || locale === activeEditorLocale) return;
                localeDrafts[activeEditorLocale] = collectCurrentLocaleDraft();
                loadLocaleDraft(locale);
            };
            addItemButton?.addEventListener('click', () => {
                openItemModal(null, {});
            });
            itemForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                if (!itemList || !activeBlock) return;
                const item = {};
                itemForm.querySelectorAll('[data-xd-item-modal-field]').forEach((input) => {
                    const value = input.value.trim();
                    if (value !== '') item[input.dataset.xdItemModalField] = value;
                });
                const indexValue = itemIndexInput?.value ?? '';
                const rows = Array.from(itemList.querySelectorAll('[data-xd-item-row]'));
                const blockType = activeBlock.block_type || addItemButton?.dataset.xdBlockType || '';
                if (indexValue === '') {
                    itemList.appendChild(renderEditorItem(item, rows.length, blockType, true));
                } else {
                    const index = Number(indexValue);
                    rows[index]?.replaceWith(renderEditorItem(item, index, blockType, true));
                }
                syncItemNumbers();
                syncEditorItemsIntoContentField();
                closeItemModal();
            });
            document.querySelectorAll('[data-xd-item-close]').forEach((button) => button.addEventListener('click', closeItemModal));
            localeTabs.forEach((button) => button.addEventListener('click', () => switchEditorLocale(button.dataset.xdLocaleTab || activeEditorLocale)));

            document.querySelectorAll('[data-xd-edit-block]').forEach((button) => {
                button.addEventListener('click', () => {
                    const block = blocks[button.dataset.xdEditBlock];
                    if (!block || !editor) return;
                    activeBlock = block;
                    localeDrafts = {};
                    const availableLocales = editorLocales.length ? editorLocales.map((locale) => locale.code) : [block.data?.locale || activeEditorLocale];
                    availableLocales.forEach((locale) => {
                        const localeContent = block.data_by_locale?.[locale]?.content || block.data?.content || {};
                        localeDrafts[locale] = {
                            locale,
                            ...(block.data_by_locale?.[locale] || block.data || {}),
                            content: normalizeContentObject(localeContent),
                        };
                    });
                    field('block_id').value = block.id;
                    field('anchor_id').value = block.anchor_id || '';
                    field('is_visible').checked = Boolean(block.is_visible);
                    field('settings').value = pretty(block.settings || {});
                    if (field('cta_url')) field('cta_url').value = block.settings?.cta_url || '';
                    field('media').value = pretty(block.media || {});
                    renderSourceEditor(block);
                    loadLocaleDraft(block.data?.locale || activeEditorLocale);
                    editor.hidden = false;
                });
            });

            document.querySelectorAll('[data-xd-editor-close]').forEach((button) => button.addEventListener('click', () => { editor.hidden = true; closeItemModal(); }));
            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                const blockId = field('block_id').value;
                try {
                    localeDrafts[activeEditorLocale] = collectCurrentLocaleDraft();
                    const localePayloads = Object.values(localeDrafts);
                    const settingsPayload = collectSourceSettings(parseJson(field('settings').value, {}));
                    const ctaUrl = field('cta_url')?.value.trim() || '';
                    if (ctaUrl !== '') settingsPayload.cta_url = ctaUrl;
                    else delete settingsPayload.cta_url;
                    const mediaPayload = parseJson(field('media').value, {});

                    for (const draft of localePayloads) {
                        const payload = {
                            locale: draft.locale,
                            anchor_id: field('anchor_id').value,
                            is_visible: field('is_visible').checked,
                            settings: settingsPayload,
                            media: mediaPayload,
                            data: {
                                title: draft.title || '',
                                subtitle: draft.subtitle || '',
                                description: draft.description || '',
                                button_label: draft.button_label || '',
                                content: draft.content || {},
                            },
                        };
                        const response = await fetch(updateUrlTemplate.replace('__BLOCK_ID__', blockId), {
                            method: 'PUT',
                            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                            body: JSON.stringify(payload),
                        });
                        if (!response.ok) throw new Error('Không lưu được khối landing.');
                    }
                    window.location.reload();
                } catch (error) {
                    alert(error.message);
                }
            });
        })();
    </script>
</body>
</html>
