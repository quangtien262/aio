@php
    $shell = $themeShellData ?? [];
    $branding = $shell['branding'] ?? [];
    $topMenu = $shell['top_menu'] ?? [];
    $productMenu = $shell['product_menu'] ?? [];
    $customerAuth = $shell['customer_auth'] ?? ['is_authenticated' => false, 'customer' => null];
    $newsletterState = $shell['newsletter'] ?? ['is_subscribed' => false];
    $presetSwitcher = $shell['preset_switcher'] ?? ['enabled' => false, 'current_label' => null, 'options' => []];
    $themeTranslator = app(\App\Core\Themes\ThemeTranslationService::class);
    $t = fn (string $key, string $default) => $themeTranslator->bladeText('SER0101', app()->getLocale(), $key, $default);
    $contactHotline = data_get($branding, 'support_hotline', '1900 6760');
    $contactEmail = data_get($branding, 'support_email', 'hello@ser0101.demo');
    $contactLocation = data_get($branding, 'support_location', 'Hồ Chí Minh');
    $postLoginRedirect = session('post_login_redirect', request()->fullUrl());
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $productCollection = collect($products ?? []);
    $categoryLinks = collect($sidebarCategories ?? []);
    $dispatchHighlights = $productCollection->take(3)->pluck('title')->filter()->values();
    $conciergeSummary = [
        ['label' => 'Số gói đang hiển thị', 'value' => (string) $productCollection->count()],
        ['label' => 'Hotline concierge', 'value' => $contactHotline],
        ['label' => 'Khu vực ưu tiên', 'value' => $contactLocation],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $category->name }} | {{ data_get($branding, 'company_name', 'SER0101') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

            :root {
                --n: #0f172f;
                --night: #08111f;
                --p: #0f766e;
                --o: #b45309;
                --a: #f0b429;
                --l: #d6e2de;
                --m: #5d7288;
                --bg: #fbfcfb;
            }

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.1), transparent 22%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.16), transparent 30%),
                    linear-gradient(180deg, #fbfcfb 0%, #f3f8f5 42%, #fcf8f2 100%);
                color: #243b53;
            }

            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .topbar { background: var(--night); color: #d9e2ec; }
            .topbar .wrap, .header-inner, .hero-meta, .footer-inner { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
            .topbar .wrap { padding: 10px 0; font-size: 13px; }
            .header { padding: 18px 0; background: rgba(255, 255, 255, 0.92); border-bottom: 1px solid rgba(217, 226, 236, 0.9); backdrop-filter: blur(18px); }
            .brand strong { display: block; color: var(--night); font-size: 18px; }
            .brand span { color: var(--m); font-size: 13px; }
            .menu { display: flex; gap: 20px; flex-wrap: wrap; font-size: 14px; font-weight: 700; }
            .layout { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: 20px; padding: 24px 0 36px; align-items: start; }
            .hero {
                margin-top: 24px;
                padding: 20px;
                border-radius: 36px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
                box-shadow: 0 28px 60px rgba(10, 30, 47, 0.16);
            }
            .eyebrow { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) 300px; gap: 18px; align-items: stretch; }
            .hero-copy { padding: 8px 10px 4px; }
            .hero h1 { margin: 16px 0 12px; font-size: clamp(34px, 4.2vw, 56px); line-height: 0.98; letter-spacing: -0.03em; }
            .hero p { max-width: 760px; margin: 0; color: #d9e2ec; line-height: 1.85; }
            .hero-meta { margin-top: 18px; }
            .hero-chip { display: inline-flex; padding: 10px 14px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 13px; font-weight: 700; }
            .hero-brief {
                display: grid;
                gap: 12px;
                padding: 18px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.14);
                backdrop-filter: blur(10px);
            }
            .hero-brief span { color: #fce7b2; font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .hero-brief h3 { margin: 0; font-size: 26px; line-height: 1.12; }
            .hero-brief p { margin: 0; color: #d9e2ec; font-size: 14px; line-height: 1.75; }
            .hero-brief-list { display: grid; gap: 10px; }
            .hero-brief-item { padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.12); }
            .hero-brief-item strong { display: block; font-size: 18px; }
            .hero-brief-item small { color: #cbd5e1; line-height: 1.5; }
            .stack { display: grid; gap: 16px; }
            .panel, .promo, .card { border: 1px solid rgba(217, 226, 236, 0.92); border-radius: 26px; background: rgba(255, 255, 255, 0.94); box-shadow: 0 20px 46px rgba(16, 42, 67, 0.08); }
            .panel, .promo { padding: 22px; }
            .panel h3, .promo h3 { margin: 0 0 12px; color: var(--night); }
            .side-heading {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 14px;
            }
            .side-heading span {
                display: inline-flex;
                align-self: flex-start;
                padding: 6px 12px;
                border-radius: 999px;
                background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(194, 65, 12, 0.08));
                color: var(--o);
                font-size: 11px;
                font-weight: 800;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }
            .side-heading h3 {
                margin: 0;
                color: var(--night);
                font-size: 28px;
                line-height: 1.05;
                letter-spacing: -0.03em;
            }
            .side-heading::after {
                content: '';
                width: 72px;
                height: 4px;
                border-radius: 999px;
                background: linear-gradient(90deg, var(--o), rgba(245, 158, 11, 0.3));
                box-shadow: 0 8px 18px rgba(194, 65, 12, 0.16);
            }
            .side-link { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; color: #486581; border-bottom: 1px dashed rgba(217, 226, 236, 0.9); }
            .side-link:last-child { border-bottom: 0; }
            .side-link.active { color: var(--o); font-weight: 800; }
            .promo { background: linear-gradient(180deg, #fffaf1, #f5fbf8); }
            .promo p, .panel p { margin: 0; color: var(--m); line-height: 1.75; }
            .toolbar { padding: 24px; background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(245, 250, 248, 0.98)); }
            .toolbar h2 { margin: 0 0 10px; color: var(--night); font-size: 32px; letter-spacing: -0.03em; }
            .toolbar p { margin: 0; color: var(--m); line-height: 1.8; }
            .chip-row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
            .chip { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--p) 8%, white); color: var(--p); font-size: 13px; font-weight: 700; }
            .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
            .card { position: relative; overflow: hidden; padding: 18px; }
            .card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, var(--p), var(--a), var(--o)); }
            .card-media { display: block; border-radius: 20px; overflow: hidden; margin-bottom: 16px; }
            .card img { width: 100%; aspect-ratio: 16/10; object-fit: cover; border-radius: 20px; background: #edf2f7; transition: transform .25s ease; }
            .card-media:hover img { transform: scale(1.03); }
            .card h3 { margin: 0 0 10px; color: var(--night); font-size: 28px; letter-spacing: -0.03em; }
            .card p { margin: 0; color: var(--m); line-height: 1.75; }
            .card-meta { display: inline-flex; margin: 12px 0 0; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--a) 16%, white); color: var(--o); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
            .price { margin-top: 16px; display: flex; justify-content: flex-start; align-items: center; gap: 12px; }
            .price strong { font-size: 28px; color: var(--o); }
            .empty { padding: 36px; text-align: center; }
            .footer-wrap { background: linear-gradient(180deg, var(--night), #071421); color: #d9e2ec; }
            .footer-inner { padding: 24px 0 30px; }

            @media (max-width: 980px) {
                .layout, .grid, .hero-grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
            }
        </style>
    </head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="hero">
                <div class="hero-grid">
                    <div class="hero-copy">
                        <span class="eyebrow">{{ $t('category.hero_eyebrow', 'Danh mục dịch vụ') }}</span>
                        <h1>{{ $category->name }}</h1>
                        <p>{{ $category->description ?: $t('category.hero_description_fallback', 'Danh mục dịch vụ này được map trực tiếp từ CatalogCategory để vẫn có thể chuyển giữa theme service và commerce trên cùng một data model.') }}</p>
                        <div class="hero-meta">
                            <span class="hero-chip">{{ str_replace(':count', (string) $productCollection->count(), $t('category.visible_count', ':count gói đang hiển thị')) }}</span>
                            <span class="hero-chip">{{ str_replace(':phone', $contactHotline, $t('category.hotline_chip', 'Hotline :phone')) }}</span>
                            @foreach ($dispatchHighlights as $dispatchHighlight)
                                <span class="hero-chip">{{ $dispatchHighlight }}</span>
                            @endforeach
                        </div>
                    </div>
                    <aside class="hero-brief">
                        <span>{{ $t('category.sidebar_eyebrow', 'Điều phối') }}</span>
                        <h3>{{ $t('category.promo_title', 'Danh mục sẵn sàng điều phối') }}</h3>
                        <p>{{ $t('category.promo_body', 'Category page của SER0101 được tối ưu để giống trang tuyến hoặc nhóm xe của nhà xe: rõ lộ trình, rõ giá tham khảo và CTA nhanh để qua trang chi tiết.') }}</p>
                        <div class="hero-brief-list">
                            @foreach ($conciergeSummary as $summary)
                                <div class="hero-brief-item">
                                    <strong>{{ $summary['value'] }}</strong>
                                    <small>{{ $summary['label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </div>
            </section>

            <section class="layout">
                <aside class="stack">
                    <div class="panel">
                        <div class="side-heading">
                            <span>{{ $t('category.sidebar_eyebrow', 'Điều hướng') }}</span>
                            <h3>{{ $t('common.categories', 'NHÓM DỊCH VỤ') }}</h3>
                        </div>
                        @foreach ($categoryLinks as $item)
                            <a href="{{ $item['url'] ?? '#' }}" class="side-link {{ !empty($item['active']) ? 'active' : '' }}">
                                <span>{{ $item['label'] ?? '' }}</span>
                                <small>{{ $item['count'] ?? '' }}</small>
                            </a>
                        @endforeach
                    </div>

                    <div class="promo">
                        <div class="side-heading">
                            <span>SER0101</span>
                            <h3>{{ $t('category.promo_title', 'Danh mục sẵn sàng điều phối') }}</h3>
                        </div>
                        <p>{{ $t('category.promo_body', 'Category page của SER0101 được tối ưu để giống trang tuyến hoặc nhóm xe của nhà xe: rõ lộ trình, rõ giá tham khảo và CTA nhanh để qua trang chi tiết.') }}</p>
                    </div>
                </aside>

                <div class="stack">
                    <div class="panel toolbar">
                        <h2>{{ $category->name }}</h2>
                        <p>{{ $category->description ?: $t('category.toolbar_description_fallback', 'Danh mục dịch vụ đang được map trực tiếp từ CatalogCategory hiện có.') }}</p>
                        <div class="chip-row">
                            @foreach (($filters['sort_options'] ?? []) as $option)
                                <span class="chip">{{ $option['label'] ?? '' }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid">
                        @forelse ($productCollection as $product)
                            <article class="card">
                                <a class="card-media" href="{{ $product['url'] ?? '#' }}" aria-label="{{ $product['title'] ?? '' }}">
                                    <img src="{{ $product['image'] ?? 'https://picsum.photos/seed/ser0101-category/960/720' }}" alt="{{ $product['title'] ?? '' }}">
                                </a>
                                <span class="card-meta">{{ $product['tag'] ?? $t('category.card_tag_fallback', 'Gói tuyến dịch vụ') }}</span>
                                <h3><a href="{{ $product['url'] ?? '#' }}">{{ $product['title'] ?? '' }}</a></h3>
                                <p>{{ \Illuminate\Support\Str::limit((string) ($product['summary'] ?? $product['tag'] ?? ''), 118) }}</p>
                                <div class="price">
                                    <strong>{{ $formatCurrency($product['price'] ?? null) }}</strong>
                                </div>
                            </article>
                        @empty
                            <div class="card empty">
                                <h3>{{ $t('search.empty_title', 'Chưa có gói phù hợp') }}</h3>
                                <p>{{ $t('search.empty_summary', 'Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </main>

        <footer class="footer-wrap">
            <div class="wrap footer-inner">
                <span>{{ data_get($branding, 'company_name', 'SER0101') }}</span>
                <span>{{ $contactHotline }}</span>
            </div>
        </footer>

        @include('theme-ser0101::partials.product-search-autocomplete')
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
