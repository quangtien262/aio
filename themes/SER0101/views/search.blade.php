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
    $searchQuery = (string) ($searchQuery ?? request('q', ''));
    $productCollection = collect($products ?? []);
    $formatCurrency = fn ($value) => $value === null ? 'Liên hệ' : number_format((float) $value, 0, ',', '.').'đ';
    $resultCountText = str_replace(':count', (string) $productCollection->count(), $t('search.result_count', 'Tìm thấy :count gói dịch vụ.'));
    $searchDossier = [
        ['label' => 'Kết quả hiện có', 'value' => (string) $productCollection->count()],
        ['label' => 'Hotline concierge', 'value' => $contactHotline],
        ['label' => 'Khu vực ưu tiên', 'value' => $contactLocation],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $searchQuery !== '' ? str_replace(':query', $searchQuery, $t('search.title_query', 'Kết quả cho ":query"')) : $t('search.title_default', 'Tìm kiếm dịch vụ') }} | {{ data_get($branding, 'company_name', 'SER0101') }}</title>
        @vite('resources/css/app.css')
        <style>
            @include('theme-ser0101::partials.shell-styles')

            :root {
                --n: #0f172f;
                --night: #08111f;
                --o: #b45309;
                --p: #0f766e;
                --a: #f0b429;
                --l: #d6e2de;
                --m: #5d7288;
            }

            @include('theme-ser0101::partials.palette-tokens', ['branding' => $branding])

            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(15, 118, 110, 0.1), transparent 24%),
                    radial-gradient(circle at top right, rgba(240, 180, 41, 0.16), transparent 30%),
                    linear-gradient(180deg, #fbfcfb 0%, #f3f8f5 42%, #fcf8f2 100%);
                color: #243b53;
            }

            a { color: inherit; text-decoration: none; }
            img { display: block; max-width: 100%; }
            .wrap { width: min(1180px, calc(100% - 24px)); margin: 0 auto; }
            .top, .foot { background: var(--night); color: #d9e2ec; }
            .top .wrap, .head-inner { display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; }
            .top { padding: 10px 0; font-size: 13px; }
            .head { margin-top: 18px; padding: 18px 20px; border-radius: 24px; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(217, 226, 236, 0.92); box-shadow: 0 18px 38px rgba(16, 42, 67, 0.06); }
            .head strong { color: var(--night); }
            .head nav { display: flex; gap: 18px; flex-wrap: wrap; }
            .hero {
                margin: 18px 0;
                padding: 20px;
                border-radius: 34px;
                background: linear-gradient(135deg, rgba(11, 27, 38, 0.98), rgba(15, 118, 110, 0.88) 58%, rgba(180, 83, 9, 0.82));
                color: #fff;
                box-shadow: 0 26px 56px rgba(10, 30, 47, 0.16);
            }
            .hero-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) 300px; gap: 18px; align-items: stretch; }
            .hero-copy { padding: 8px 10px 4px; }
            .eyebrow { display: inline-flex; padding: 8px 12px; border-radius: 999px; background: rgba(255, 255, 255, 0.12); font-size: 12px; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; }
            .hero h1 { margin: 16px 0 10px; color: #fff; font-size: clamp(34px, 4.5vw, 54px); line-height: 1.02; }
            .hero p { max-width: 760px; margin: 0; color: #d9e2ec; line-height: 1.8; }
            .hero-dossier {
                display: grid;
                gap: 12px;
                padding: 18px;
                border-radius: 28px;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid rgba(255, 255, 255, 0.14);
                backdrop-filter: blur(10px);
            }
            .hero-dossier strong { display: block; font-size: 22px; line-height: 1.15; }
            .hero-dossier p { margin: 0; color: #d9e2ec; font-size: 14px; line-height: 1.75; }
            .hero-dossier-list { display: grid; gap: 10px; }
            .hero-dossier-item { padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.12); }
            .hero-dossier-item b { display: block; font-size: 18px; }
            .hero-dossier-item small { color: #cbd5e1; line-height: 1.5; }
            .hero form { display: grid; grid-template-columns: minmax(0, 1fr) 220px 160px; gap: 12px; margin-top: 18px; }
            .hero input, .hero select, .hero button { min-height: 48px; border-radius: 16px; font: inherit; }
            .hero input, .hero select {
                border: 1px solid rgba(255, 255, 255, 0.18);
                padding: 0 14px;
                background: rgba(255, 255, 255, 0.94);
                color: var(--night);
            }
            .hero input::placeholder { color: var(--m); }
            .hero select option {
                color: var(--night);
                background: #fff;
            }
            .hero button { border: 0; background: linear-gradient(135deg, var(--p), var(--p-deep)); color: #fff; font-weight: 800; cursor: pointer; }
            .summary-row { display: flex; justify-content: space-between; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
            .summary-row strong { color: var(--night); font-size: 20px; }
            .summary-row span { color: var(--m); }
            .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; padding-bottom: 28px; }
            .card { position: relative; padding: 18px; border: 1px solid rgba(214, 226, 222, 0.92); border-radius: 26px; background: rgba(255, 255, 255, 0.94); box-shadow: 0 18px 40px rgba(16, 42, 67, 0.08); }
            .card::before { content: ''; position: absolute; inset: 0 0 auto 0; height: 4px; background: linear-gradient(90deg, var(--p), var(--a), var(--o)); }
            .card-media { display: block; border-radius: 20px; overflow: hidden; margin-bottom: 14px; }
            .card img { width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-radius: 20px; background: #edf2f7; transition: transform .25s ease; }
            .card-media:hover img { transform: scale(1.03); }
            .card-tag { display: inline-flex; margin-bottom: 12px; padding: 8px 12px; border-radius: 999px; background: color-mix(in srgb, var(--p) 8%, white); color: var(--p); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
            .card h3 { margin: 0 0 10px; color: var(--night); font-size: 26px; letter-spacing: -0.03em; }
            .card p { margin: 0; color: var(--m); line-height: 1.75; }
            .meta { margin-top: 16px; display: flex; justify-content: flex-start; align-items: center; gap: 12px; }
            .meta strong { font-size: 24px; color: var(--o); }
            .empty { padding: 32px; text-align: center; }
            .footer { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 20px; margin-bottom: 28px; border-radius: 24px; background: rgba(255, 255, 255, 0.94); border: 1px solid rgba(217, 226, 236, 0.92); flex-wrap: wrap; }

            @media (max-width: 980px) {
                .hero-grid, .hero form, .grid { grid-template-columns: 1fr; }
            }

            @media (max-width: 680px) {
                .wrap { width: min(100%, calc(100% - 16px)); }
            }
        </style>
        @include('partials.localized-seo')
</head>
    <body>
        @include('theme-ser0101::partials.shell-header', ['branding' => $branding, 'topMenu' => $topMenu, 'productMenu' => $productMenu, 'cartSummary' => $shell['cart_summary'] ?? ['count' => 0, 'subtotal' => 0], 'customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'presetSwitcher' => $presetSwitcher, 'contactHotline' => $contactHotline, 'contactEmail' => $contactEmail, 'contactLocation' => $contactLocation, 'postLoginRedirect' => $postLoginRedirect, 't' => $t])

        <main class="wrap">
            <section class="hero">
                <div class="hero-grid">
                    <div class="hero-copy">
                        <span class="eyebrow">{{ $t('search.eyebrow', 'Tìm tuyến và dịch vụ') }}</span>
                        <h1>{{ $searchQuery !== '' ? str_replace(':query', $searchQuery, $t('search.title_query', 'Kết quả cho ":query"')) : $t('search.title_default', 'Tìm kiếm dịch vụ') }}</h1>
                        <p>{{ $t('search.intro', 'Tìm nhanh theo loại xe, tuyến đường hoặc nhu cầu di chuyển.') }}</p>

                        <form method="GET" action="{{ route('site.catalog.search') }}">
                            <input type="search" name="q" value="{{ $searchQuery }}" placeholder="{{ $t('common.search_placeholder', 'Tìm gói dịch vụ, tuyến đường, loại xe') }}" data-ser-product-search data-suggest-url="{{ route('site.catalog.search.suggestions') }}">
                            <select name="category">
                                <option value="">{{ $t('search.all_categories', 'Tất cả danh mục') }}</option>
                                @foreach ($searchCategories ?? [] as $item)
                                    <option value="{{ $item['slug'] ?? '' }}" {{ ($searchFilters['category'] ?? '') === ($item['slug'] ?? '') ? 'selected' : '' }}>{{ $item['name'] ?? $item['label'] ?? '' }}</option>
                                @endforeach
                            </select>
                            <button type="submit">{{ $t('common.search_button', 'Tìm') }}</button>
                        </form>
                    </div>
                    <aside class="hero-dossier">
                        <strong>Search concierge</strong>
                        <p>Khối tìm kiếm của SER0101 được trình bày như bàn điều phối nhanh, giúp gom tuyến, loại xe và nhu cầu theo cùng một nhịp nhìn.</p>
                        <div class="hero-dossier-list">
                            @foreach ($searchDossier as $item)
                                <div class="hero-dossier-item">
                                    <b>{{ $item['value'] }}</b>
                                    <small>{{ $item['label'] }}</small>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </div>
            </section>

            <div class="summary-row">
                <strong>{{ $resultCountText }}</strong>
                <span>{{ $t('search.summary_note', 'Trải nghiệm tìm kiếm được tối ưu theo cách route board của website nhà xe.') }}</span>
            </div>

            <section class="grid">
                @forelse ($productCollection as $product)
                    <article class="card">
                        <a class="card-media" href="{{ $product['url'] ?? '#' }}" aria-label="{{ $product['title'] ?? '' }}">
                            <img src="{{ $product['image'] ?? 'https://picsum.photos/seed/ser0101-search/960/720' }}" alt="{{ $product['title'] ?? '' }}">
                        </a>
                        <span class="card-tag">{{ $product['tag'] ?? 'Kết quả tìm kiếm' }}</span>
                        <h3><a href="{{ $product['url'] ?? '#' }}">{{ $product['title'] ?? '' }}</a></h3>
                        <p>{{ \Illuminate\Support\Str::limit((string) ($product['summary'] ?? $product['tag'] ?? ''), 118) }}</p>
                        <div class="meta">
                            <strong>{{ $formatCurrency($product['price'] ?? null) }}</strong>
                        </div>
                    </article>
                @empty
                    <div class="card empty">
                        <h3>{{ $t('search.empty_title', 'Chưa có gói phù hợp') }}</h3>
                        <p>{{ $t('search.empty_summary', 'Hãy thử từ khóa khác hoặc bỏ bớt bộ lọc.') }}</p>
                    </div>
                @endforelse
            </section>

            @if (isset($pagination) && $pagination)
                <div class="footer">
                    @if ($pagination->previousPageUrl())
                        <a href="{{ $pagination->previousPageUrl() }}">{{ $t('search.prev_page', 'Trang trước') }}</a>
                    @else
                        <span>{{ $t('search.prev_page', 'Trang trước') }}</span>
                    @endif

                    <span>{{ $resultCountText }}</span>

                    @if ($pagination->nextPageUrl())
                        <a href="{{ $pagination->nextPageUrl() }}">{{ $t('search.next_page', 'Trang sau') }}</a>
                    @else
                        <span>{{ $t('search.next_page', 'Trang sau') }}</span>
                    @endif
                </div>
            @endif
        </main>

        @include('theme-ser0101::partials.product-search-autocomplete')
        @include('theme-ser0101::partials.engagement-modals', ['customerAuth' => $customerAuth, 'newsletterState' => $newsletterState, 'postLoginRedirect' => $postLoginRedirect])
    </body>
</html>
