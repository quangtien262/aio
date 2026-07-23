        @php
            $logoUrl = $logoUrl ?? '';
            $logoAlt = $logoAlt ?? 'Arkit';
            $hotline = $hotline ?? '0399162342';
            $phoneHref = $phoneHref ?? (preg_replace('/\D+/', '', (string) $hotline) ?: $hotline);
            $supportEmail = $supportEmail ?? ($email ?? 'admin@htvietnam.vn');
            $topbarLocales = collect(\App\Support\FrontendLocalization::localeOptions())
                ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false) && (bool) ($locale['is_published'] ?? true))
                ->values();
            $currentLocale = app()->getLocale();
            $languageUrl = static fn (string $locale): string => \App\Support\FrontendRouteUrl::switchLocale($locale);
            $localizeMenuUrl = static fn (?string $href): string => \App\Support\FrontendRouteUrl::localized($href);
            $repairXdLabel = static function (string $label): string {
                return strtr(trim($label), [
                    'Trang chá»§' => 'Trang chủ',
                    'TRANG CHÁ»§' => 'TRANG CHỦ',
                    'trang chá»§' => 'trang chủ',
                    'Sáº£n pháº©m' => 'Sản phẩm',
                    'SÁº£N PHÁº©M' => 'SẢN PHẨM',
                    'sáº£n pháº©m' => 'sản phẩm',
                    'sÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' => 'sản phẩm',
                    'SÃ¡ÂºÂ£n phÃ¡ÂºÂ©m' => 'Sản phẩm',
                    'TÃ i khoáº£n' => 'Tài khoản',
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
            $legacyPageNavItems = $navItems ?? [];
            $headerMenuSource = collect(data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', [])));
            $headerMenuSource = $headerMenuSource->isNotEmpty()
                ? $headerMenuSource
                : collect(data_get($themeHomeData ?? [], 'top_menu',
                    data_get($homeData ?? [], 'top_menu',
                    data_get($themeShellData ?? [], 'top_menu',
                    $legacyPageNavItems))));

            if ($headerMenuSource->isEmpty() && isset($blocks)) {
                $headerMenuSource = collect($blocks)
                    ->filter(fn ($block): bool => filled($block['anchor_id'] ?? null))
                    ->map(fn ($block): array => [
                        'label' => data_get($block, 'data.subtitle') ?: data_get($block, 'data.title') ?: \Illuminate\Support\Str::headline((string) ($block['block_type'] ?? 'Menu')),
                        'url' => '#'.$block['anchor_id'],
                    ]);
            }

            $navItems = $headerMenuSource
                ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
                ->map(fn (array $item): array => $normalizeNavItem($item))
                ->values();

            $homeUrl = route('site.home');
            $hasHomeItem = $navItems->contains(function (array $item) use ($homeUrl): bool {
                $label = mb_strtolower(trim((string) ($item['label'] ?? '')));
                $href = rtrim((string) ($item['href'] ?? ''), '/');

                return in_array($label, ['trang chủ', 'home'], true) || $href === rtrim($homeUrl, '/');
            });

            if (! $hasHomeItem) {
                $navItems->prepend([
                    'label' => app()->getLocale() === 'en' ? 'Home' : 'Trang chủ',
                    'href' => $homeUrl,
                    'target' => '_self',
                    'active' => request()->routeIs('site.home'),
                    'children' => [],
                ]);
            }

            $productNavigationItems = collect(data_get($menus ?? [], 'product-navigation', data_get($themeShellData ?? [], 'product_menu', [])))
                ->filter(fn ($item): bool => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
                ->map(fn (array $item): array => $normalizeNavItem($item))
                ->values();

            if ($productNavigationItems->isNotEmpty()) {
                $hasProductItem = $navItems->contains(function (array $item): bool {
                    return in_array(mb_strtolower(trim((string) ($item['label'] ?? ''))), ['sản phẩm', 'san pham', 'products', 'product'], true);
                });

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
                }
            }

            $currentUrl = rtrim(url()->current(), '/');
            $markActive = function (array $items) use (&$markActive, $currentUrl): array {
                return collect($items)
                    ->map(function (array $item) use (&$markActive, $currentUrl): array {
                        $href = (string) ($item['href'] ?? '#');
                        $absoluteHref = str_starts_with($href, 'http') ? rtrim($href, '/') : rtrim(url($href), '/');
                        $children = $markActive($item['children'] ?? []);
                        $childActive = collect($children)->contains(fn (array $child): bool => (bool) ($child['active'] ?? false));

                        $item['children'] = $children;
                        $item['active'] = (bool) ($item['active'] ?? false) || $childActive || ($href !== '#' && $absoluteHref === $currentUrl);

                        return $item;
                    })
                    ->values()
                    ->all();
            };

            $navItems = collect($markActive($navItems->all()));
            $adminLoggedIn = auth('admin')->check();
            $customerLoggedIn = auth('customer')->check();
            $menuHref = fn (array $item, string $fallback = '#'): string => (string) ($item['href'] ?? $item['url'] ?? $fallback);
            $menuTarget = fn (array $item): string => (string) ($item['target'] ?? '_self');
            $renderDesktopChildren = function ($items, int $level = 1, string $fallbackHref = '#') use (&$renderDesktopChildren, $menuHref, $menuTarget): string {
                $items = collect($items ?? [])->filter(fn ($item): bool => is_array($item))->take(24);

                if ($items->isEmpty()) {
                    return '';
                }

                $html = '<div class="'.($level === 1 ? 'xd-dropdown' : 'xd-subdropdown').'" role="menu">';

                foreach ($items as $item) {
                    $children = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child));
                    $href = $menuHref($item, $fallbackHref);
                    $target = $menuTarget($item);
                    $label = (string) ($item['label'] ?? 'Menu');

                    $html .= '<div class="xd-dropdown-item '.($children->isNotEmpty() ? 'has-children' : '').'">';
                    $html .= '<a class="xd-dropdown-link" href="'.e($href).'" target="'.e($target).'" role="menuitem"><span>'.e($label).'</span>';

                    if ($children->isNotEmpty()) {
                        $html .= '<span class="xd-nav-caret" aria-hidden="true">&#8250;</span>';
                    }

                    $html .= '</a>';
                    $html .= $renderDesktopChildren($children, $level + 1, $href);
                    $html .= '</div>';
                }

                return $html.'</div>';
            };
            $renderMobileItems = function ($items, string $fallbackHref = '#') use (&$renderMobileItems, $menuHref, $menuTarget): string {
                return collect($items ?? [])
                    ->filter(fn ($item): bool => is_array($item))
                    ->take(32)
                    ->map(function (array $item) use (&$renderMobileItems, $menuHref, $menuTarget, $fallbackHref): string {
                        $children = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child));
                        $href = $menuHref($item, $fallbackHref);
                        $target = $menuTarget($item);
                        $label = (string) ($item['label'] ?? 'Menu');

                        if ($children->isEmpty()) {
                            return '<li><a class="xd-mobile-link" href="'.e($href).'" target="'.e($target).'">'.e($label).'</a></li>';
                        }

                        return '<li class="xd-mobile-item"><details><summary class="xd-mobile-summary">'.e($label).'</summary><ul class="xd-mobile-children"><li><a class="xd-mobile-link" href="'.e($href).'" target="'.e($target).'">Xem '.e($label).'</a></li>'.$renderMobileItems($children, $href).'</ul></details></li>';
                    })
                    ->implode('');
            };
        @endphp

        <header class="xd-header">
            <div class="xd-header-top">
                <div class="xd-container xd-header-top-inner">
                    <div class="xd-header-contact">
                        <a href="tel:{{ $phoneHref }}">Hotline: {{ $hotline }}</a>
                        <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                    </div>
                    <div class="xd-header-tools" aria-label="Tác vụ nhanh">
                        @if ($adminLoggedIn)
                            <a class="xd-top-link is-admin" href="{{ route('admin.index') }}" target="_blank" rel="noopener">Admin</a>
                        @endif
                        @if ($customerLoggedIn)
                            <a class="xd-top-link" href="{{ route('customer.account') }}">Tài khoản</a>
                        @else
                            <button type="button" class="xd-top-link" data-xd-auth-open="login">Đăng nhập</button>
                            <button type="button" class="xd-top-link" data-xd-auth-open="register">Đăng ký</button>
                        @endif
                        <a class="xd-top-link" href="{{ route('site.cart.index') }}">Giỏ hàng</a>
                        @if ($topbarLocales->count() > 1)
                            <div class="xd-language-switcher" aria-label="Ngôn ngữ">
                                @foreach ($topbarLocales as $locale)
                                    @php
                                        $localeCode = (string) ($locale['code'] ?? '');
                                        $localeLabel = strtoupper($localeCode);
                                    @endphp
                                    @if ($localeCode !== '')
                                        <a class="{{ $localeCode === $currentLocale ? 'is-active' : '' }}" href="{{ $languageUrl($localeCode) }}">{{ $localeLabel }}</a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
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
                        @php
                            $children = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child));
                            $href = $menuHref((array) $item);
                        @endphp
                        <div class="xd-nav-item {{ $children->isNotEmpty() ? 'has-children' : '' }}">
                            <a class="xd-nav-link {{ ($item['active'] ?? false) ? 'is-active' : '' }}" href="{{ $href }}" target="{{ $menuTarget((array) $item) }}">
                                <span>{{ $item['label'] }}</span>
                                @if ($children->isNotEmpty())
                                    <span class="xd-nav-caret" aria-hidden="true">&#9662;</span>
                                @endif
                            </a>
                            @if ($children->isNotEmpty())
                                {!! $renderDesktopChildren($children, 1, $href) !!}
                            @endif
                        </div>
                    @endforeach
                </nav>
                <div id="xd-mobile-menu" class="xd-mobile-panel" data-xd-mobile-menu hidden>
                    <ul class="xd-mobile-list">
                        @foreach ($navItems as $item)
                            <li class="xd-mobile-item">
                                @php
                                    $children = collect($item['children'] ?? [])->filter(fn ($child): bool => is_array($child));
                                    $href = $menuHref((array) $item);
                                @endphp
                                @if ($children->isNotEmpty())
                                    <details>
                                        <summary class="xd-mobile-summary {{ ($item['active'] ?? false) ? 'is-active' : '' }}">{{ $item['label'] }}</summary>
                                        <ul class="xd-mobile-children">
                                            <li><a class="xd-mobile-link" href="{{ $href }}" target="{{ $menuTarget((array) $item) }}">Xem {{ $item['label'] }}</a></li>
                                            {!! $renderMobileItems($children, $href) !!}
                                        </ul>
                                    </details>
                                @else
                                    <a class="xd-mobile-link {{ ($item['active'] ?? false) ? 'is-active' : '' }}" href="{{ $href }}" target="{{ $menuTarget((array) $item) }}">{{ $item['label'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </header>
        <a class="xd-floating-hotline" href="tel:{{ $phoneHref }}" aria-label="Hotline {{ $hotline }}">
            <span class="xd-floating-hotline__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.91.32 1.8.59 2.65a2 2 0 0 1-.45 2.11L8 9.73a16 16 0 0 0 6.27 6.27l1.25-1.25a2 2 0 0 1 2.11-.45c.85.27 1.74.47 2.65.59A2 2 0 0 1 22 16.92Z"/>
                </svg>
            </span>
            <span class="xd-floating-hotline__copy">
                <span>Hotline</span>
                <strong>{{ $hotline }}</strong>
            </span>
        </a>
