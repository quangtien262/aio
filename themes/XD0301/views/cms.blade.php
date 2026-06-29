@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logoUrl = trim((string) ($branding['logo_url'] ?? ''));
    $logoAlt = trim((string) ($branding['company_name'] ?? data_get($siteProfile ?? [], 'site_name', 'Arkit')));
    $hotline = trim((string) ($branding['support_hotline'] ?? '19009477'));
    $phoneHref = preg_replace('/\D+/', '', $hotline) ?: $hotline;
    $email = trim((string) ($branding['support_email'] ?? 'admin@demo031086.web30s.vn'));
    $address = trim((string) ($branding['support_location'] ?? '196 Nguyễn Đình Chiểu, Quận 3, TP.HCM'));

    $normalizeNavItem = function (array $item) use (&$normalizeNavItem): array {
        $href = (string) ($item['url'] ?? $item['href'] ?? '#');

        return [
            'label' => (string) ($item['label'] ?? $item['title'] ?? 'Menu'),
            'href' => $href !== '' ? $href : '#',
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
            'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chủ',
            'href' => $homeUrl,
            'target' => '_self',
            'active' => request()->routeIs('site.home'),
            'children' => [],
        ]);
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
        .xd-header-actions{display:flex;align-items:center;gap:12px;flex:0 0 auto}.xd-hotline{display:inline-flex;align-items:center;gap:12px;padding:18px 28px;color:#fff;background:var(--lime);border-radius:4px;box-shadow:0 14px 26px rgba(189,212,0,.34);font-weight:900;letter-spacing:.035em;white-space:nowrap}.xd-login-button{display:inline-flex;align-items:center;justify-content:center;min-height:58px;padding:0 22px;border:1px solid rgba(38,56,74,.14);border-radius:4px;background:#fff;color:var(--ink);box-shadow:0 12px 24px rgba(16,29,40,.08);font:inherit;font-size:14px;font-weight:900;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap;cursor:pointer}.xd-login-button:hover{border-color:var(--lime);color:var(--lime-dark)}
        .xd-page-main{padding:76px 0 90px}.xd-cms-hero{display:grid;grid-template-columns:minmax(0,.75fr) minmax(340px,.45fr);gap:48px;align-items:end;margin-bottom:54px;padding:56px;border:1px solid var(--line);background:#fff;box-shadow:0 20px 55px rgba(28,45,60,.08)}.xd-kicker{position:relative;display:inline-block;margin:0 0 14px 18px;font-size:14px;font-weight:900;letter-spacing:.04em;text-transform:uppercase}.xd-kicker:before{content:"";position:absolute;left:-18px;top:-12px;width:34px;height:34px;border:5px solid var(--lime)}.xd-cms-hero h1{margin:0;color:var(--ink);font-size:clamp(42px,5vw,72px);line-height:1.08;letter-spacing:-.055em}.xd-cms-hero p{margin:18px 0 0;color:var(--muted);font-size:20px;font-weight:550}.xd-cms-stats{display:grid;gap:12px;color:#fff;background:var(--ink);padding:26px 30px}.xd-cms-stats strong{font-size:46px;line-height:1}.xd-cms-stats span{color:rgba(255,255,255,.75);font-weight:800;text-transform:uppercase}
        .xd-services-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:34px}.xd-service-card{background:#fff;box-shadow:0 5px 20px rgba(16,29,40,.08);transition:.25s}.xd-service-card:hover{transform:translateY(-8px);box-shadow:var(--shadow)}.xd-service-image{display:block;height:300px;overflow:hidden;background:#eef2ef}.xd-service-image img{width:100%;height:100%;object-fit:cover;transition:.4s}.xd-service-card:hover img{transform:scale(1.05)}.xd-service-body{padding:36px 38px 40px}.xd-service-card h2,.xd-service-card h3{margin:0 0 14px;font-size:22px;line-height:1.32;letter-spacing:.015em;text-transform:uppercase}.xd-service-card p{margin:0 0 26px;color:var(--muted);font-size:17px}.xd-text-link{color:var(--lime-dark);font-weight:900;text-transform:uppercase}
        .xd-detail{display:grid;grid-template-columns:minmax(0,.85fr) minmax(300px,.35fr);gap:44px}.xd-detail-card,.xd-side-card{background:#fff;border:1px solid var(--line);box-shadow:0 18px 48px rgba(16,29,40,.06)}.xd-detail-card{overflow:hidden}.xd-detail-image{width:100%;max-height:520px;object-fit:cover}.xd-detail-body{padding:44px 52px}.xd-detail-body h1{margin:0 0 18px;font-size:clamp(38px,4vw,62px);line-height:1.1;letter-spacing:-.05em}.xd-detail-summary{margin:0 0 28px;color:var(--muted);font-size:20px}.xd-rich-content{color:#465461;font-size:18px}.xd-rich-content :first-child{margin-top:0}.xd-gallery{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:28px}.xd-gallery figure{margin:0}.xd-gallery img{width:100%;height:210px;object-fit:cover}.xd-gallery figcaption{margin-top:8px;color:var(--muted);font-size:14px}.xd-side-card{padding:28px}.xd-side-card h3{margin:0 0 18px;font-size:24px}.xd-side-card a{display:block;padding:12px 0;border-top:1px solid var(--line);color:var(--muted);font-weight:750}.xd-side-card a:hover{color:var(--lime-dark)}
        .xd-footer{padding:88px 0 72px;border-top:1px solid var(--line);background:#fff}.xd-footer-grid{display:grid;grid-template-columns:1.25fr .65fr 1fr 1.25fr;gap:80px}.xd-footer h3{margin:0 0 24px;font-size:30px;line-height:1.2}.xd-footer p,.xd-footer a{color:var(--muted);font-size:20px;font-weight:550}.xd-footer-links,.xd-contact-list{display:grid;gap:8px}.xd-newsletter{display:flex;margin-top:12px;border:1px solid var(--line)}.xd-newsletter input{min-width:0;flex:1;border:0;padding:0 24px;color:var(--ink);font:inherit;outline:0}.xd-newsletter button{width:166px;min-height:74px;color:#fff;background:var(--lime);border:0;font-weight:900;text-transform:uppercase}.xd-newsletter-note{margin-top:12px;font-size:14px!important;font-weight:800!important;line-height:1.45}.xd-newsletter-note.is-success{color:var(--lime-dark)!important}.xd-newsletter-note.is-error{color:#b42318!important}
        @media (max-width:1180px){.xd-header-inner{flex-wrap:wrap;padding:18px 0}.xd-nav{order:3;width:100%;justify-content:flex-start;overflow-x:auto}.xd-nav-link{padding:18px 16px}.xd-services-list,.xd-footer-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.xd-cms-hero,.xd-detail{grid-template-columns:1fr}}
        @media (max-width:640px){body{font-size:15px;line-height:1.65;overflow-x:hidden}.xd-container{width:min(100% - 24px,1540px)}.xd-header-inner{min-height:0;padding:12px 0 10px;gap:10px}.xd-logo{font-size:25px;gap:8px}.xd-logo-image{max-width:132px;height:52px}.xd-logo-mark{width:29px;height:38px}.xd-header-actions{width:auto;gap:8px;margin-left:auto}.xd-hotline{min-height:42px;padding:0 14px;border-radius:999px;font-size:0}.xd-hotline::after{content:"{{ $hotline }}";font-size:13px}.xd-login-button{min-height:42px;padding:0 13px;border-radius:999px;font-size:12px}.xd-nav{order:3;flex:0 0 100%;display:flex;gap:8px;overflow-x:auto;padding:8px 0 2px}.xd-nav::-webkit-scrollbar{display:none}.xd-nav-item{flex:0 0 auto}.xd-nav-link{padding:8px 12px;border:1px solid #e7ece5;border-radius:999px;background:#fff;font-size:12px}.xd-dropdown{top:calc(100% + 8px);min-width:220px;max-width:calc(100vw - 24px);padding:8px;border-radius:16px}.xd-subdropdown{position:static;display:none;margin:4px 0 4px 10px;border:0;border-left:2px solid var(--lime);box-shadow:none;opacity:1;visibility:visible;transform:none}.xd-dropdown-item:hover>.xd-subdropdown,.xd-dropdown-item:focus-within>.xd-subdropdown{display:block}.xd-page-main{padding:38px 0 56px}.xd-cms-hero{padding:30px 22px;margin-bottom:26px}.xd-cms-hero h1{font-size:36px}.xd-cms-hero p{font-size:16px}.xd-services-list,.xd-footer-grid,.xd-gallery{grid-template-columns:1fr}.xd-service-card{border-radius:18px;overflow:hidden}.xd-service-image{height:215px}.xd-service-body{padding:26px 22px}.xd-service-card h2,.xd-service-card h3{font-size:19px}.xd-detail-body{padding:28px 22px}.xd-detail-body h1{font-size:34px}.xd-detail-summary,.xd-rich-content{font-size:16px}.xd-footer{padding:52px 0 42px}.xd-footer-grid{gap:28px}.xd-footer .xd-logo-image{max-width:126px;height:52px}.xd-footer h3{margin-bottom:12px;font-size:24px}.xd-footer p,.xd-footer a{font-size:15px;line-height:1.7;overflow-wrap:anywhere}.xd-newsletter{display:grid;border-radius:16px;overflow:hidden}.xd-newsletter input{min-height:52px;padding:0 16px}.xd-newsletter button{width:100%;min-height:52px}}
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
                        <i class="xd-logo-mark" aria-hidden="true"></i><b>Ar<span>kit</span></b>
                    @endif
                </a>
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
                    <a class="xd-hotline" href="tel:{{ $phoneHref }}"><span aria-hidden="true">&#9742;</span> {{ $hotline }}</a>
                    @if (auth('customer')->check())
                        <a class="xd-login-button" href="{{ route('customer.account') }}">Tài khoản</a>
                    @else
                        <a class="xd-login-button" href="{{ route('customer.auth.login') }}">Đăng nhập</a>
                    @endif
                </div>
            </div>
        </header>

        <main class="xd-page-main">
            <div class="xd-container">
                @if ($isServiceListing)
                    <section class="xd-cms-hero">
                        <div>
                            <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Services' : 'Dịch vụ' }}</span>
                            <h1>{{ $pageTitle ?? (app()->getLocale() === 'en' ? 'Services' : 'Dịch vụ') }}</h1>
                            <p>{{ $pageDescription ?? (app()->getLocale() === 'en' ? 'Explore our construction and design services.' : 'Danh sách dịch vụ thiết kế và thi công nổi bật.') }}</p>
                        </div>
                        <div class="xd-cms-stats">
                            <strong>{{ collect($listingItems ?? [])->count() }}</strong>
                            <span>{{ app()->getLocale() === 'en' ? 'Available services' : 'Dịch vụ đang hiển thị' }}</span>
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
                                    <a class="xd-text-link" href="{{ $serviceUrl }}">{{ app()->getLocale() === 'en' ? 'Learn more' : 'Tìm hiểu ngay' }}</a>
                                </div>
                            </article>
                        @empty
                            <p>{{ app()->getLocale() === 'en' ? 'No services are available yet.' : 'Chưa có dịch vụ nào được xuất bản.' }}</p>
                        @endforelse
                    </section>
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
                                <span class="xd-kicker">{{ app()->getLocale() === 'en' ? 'Service' : 'Dịch vụ' }}</span>
                                <h1>{{ $entry->title }}</h1>
                                @if (!empty($entry->excerpt))
                                    <p class="xd-detail-summary">{{ $entry->excerpt }}</p>
                                @endif
                                <div class="xd-rich-content">
                                    {!! $entry->body ?: '<p>Nội dung đang được cập nhật.</p>' !!}
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
                            <h3>{{ app()->getLocale() === 'en' ? 'Quick links' : 'Liên kết nhanh' }}</h3>
                            <a href="{{ route('site.services.index') }}">{{ app()->getLocale() === 'en' ? 'All services' : 'Tất cả dịch vụ' }}</a>
                            @foreach ($navItems->take(5) as $item)
                                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                            @endforeach
                        </aside>
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
                                {!! $entry->body ?: '<p>Nội dung đang được cập nhật.</p>' !!}
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </main>

        <footer id="lien-he" class="xd-footer">
            <div class="xd-container xd-footer-grid">
                <div>
                    <a class="xd-logo" href="{{ route('site.home') }}" aria-label="{{ $logoAlt }} trang chủ">
                        @if ($logoUrl !== '')
                            <img class="xd-logo-image" src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
                        @else
                            <i class="xd-logo-mark" aria-hidden="true"></i><b>Ar<span>kit</span></b>
                        @endif
                    </a>
                    <p>{{ $logoAlt }} là đơn vị thiết kế và thi công, đồng hành từ ý tưởng đến bàn giao công trình.</p>
                </div>
                <div>
                    <h3>Thông tin</h3>
                    <nav class="xd-footer-links" aria-label="Thông tin">
                        @foreach ($navItems as $item)
                            <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </div>
                <div>
                    <h3>Liên hệ</h3>
                    <div class="xd-contact-list">
                        <a href="https://maps.google.com/?q={{ urlencode($address) }}">&#128205; {{ $address }}</a>
                        <a href="mailto:{{ $email }}">&#9993; {{ $email }}</a>
                        <a href="tel:{{ $phoneHref }}">&#9742; {{ $hotline }}</a>
                    </div>
                </div>
                <div>
                    <h3>Đăng ký nhận tin</h3>
                    <p>Đăng ký email để nhận thông tin mới nhất từ chúng tôi</p>
                    <form class="xd-newsletter" action="{{ route('site.newsletter.subscribe') }}" method="post">
                        @csrf
                        <input type="hidden" name="source" value="theme-footer-xd0301-cms">
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="Địa chỉ email....." aria-label="Địa chỉ email" required>
                        <button type="submit">Đăng ký</button>
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
</body>
</html>
