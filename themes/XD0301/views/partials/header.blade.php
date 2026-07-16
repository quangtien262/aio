        @php
            $logoUrl = $logoUrl ?? '';
            $logoAlt = $logoAlt ?? 'Arkit';
            $hotline = $hotline ?? '0399162342';
            $phoneHref = $phoneHref ?? (preg_replace('/\D+/', '', (string) $hotline) ?: $hotline);
            $supportEmail = $supportEmail ?? ($email ?? 'admin@htvietnam.vn');
            $navItems = collect($navItems ?? []);
            $topbarLocales = collect(\App\Support\FrontendLocalization::localeOptions())
                ->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false) && (bool) ($locale['is_published'] ?? true))
                ->values();
            $currentLocale = app()->getLocale();
            $knownLocaleCodes = \App\Support\FrontendLocalization::knownLocaleCodes();
            $languageUrl = function (string $locale) use ($knownLocaleCodes): string {
                $segments = request()->segments();

                if (isset($segments[0]) && in_array($segments[0], $knownLocaleCodes, true)) {
                    $segments[0] = $locale;
                } else {
                    array_unshift($segments, $locale);
                }

                $url = url('/'.implode('/', $segments));
                $query = request()->getQueryString();

                return $query ? $url.'?'.$query : $url;
            };
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
                            <a class="xd-top-link is-admin" href="{{ url('/admin') }}" target="_blank" rel="noopener">Admin</a>
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
