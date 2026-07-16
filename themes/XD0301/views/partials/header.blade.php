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
