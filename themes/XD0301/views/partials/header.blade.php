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
                    <a class="xd-cart-link" href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng" title="Giỏ hàng">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6h15l-1.5 8.5H8L6 3H3"/><circle cx="9" cy="20" r="1.7"/><circle cx="18" cy="20" r="1.7"/></svg>
                    </a>
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
                        <a class="xd-cart-link" href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng" title="Giỏ hàng">
                            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6h15l-1.5 8.5H8L6 3H3"/><circle cx="9" cy="20" r="1.7"/><circle cx="18" cy="20" r="1.7"/></svg>
                        </a>
                        @if (auth('customer')->check())
                            <a class="xd-login-button" href="{{ route('customer.account') }}">Tài khoản</a>
                        @else
                            <button type="button" class="xd-login-button" data-xd-auth-open="login">Đăng nhập</button>
                        @endif
                    </div>
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
