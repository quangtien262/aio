<header class="xd3-header">
    <div class="xd3-container xd3-contact-row">
        <a class="xd3-logo" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="xd3-logo__mark">SYS</span><strong>{{ $companyName }}</strong>
            @endif
        </a>
        <div class="xd3-contact-item"><b>Giờ mở cửa</b><span>Thứ Hai - Thứ Sáu: 9 AM - 6 PM</span></div>
        <a class="xd3-contact-item" href="tel:{{ $phoneHref }}"><b>Gọi chúng tôi</b><span>{{ $hotline }}</span></a>
        <a class="xd3-contact-item" href="mailto:{{ $supportEmail }}"><b>Email</b><span>{{ $supportEmail }}</span></a>
        @guest('customer')
            <div class="xd3-auth-actions"><button type="button" data-xd-auth-open="login">Đăng nhập</button><button type="button" data-xd-auth-open="register">Đăng ký</button></div>
        @endguest
    </div>
    <div class="xd3-nav-wrap"><div class="xd3-container xd3-nav-shell">
        <button class="xd3-menu-toggle" type="button" data-xd3-menu-toggle aria-expanded="false">Menu</button>
        <nav class="xd3-nav" data-xd3-nav aria-label="Điều hướng chính">
            @foreach ($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}" class="{{ !empty($item['children']) ? 'has-children' : '' }}">{{ $item['label'] ?? 'Menu' }}</a>
            @endforeach
            @guest('customer')<span class="xd3-mobile-auth"><button type="button" data-xd-auth-open="login">Đăng nhập</button><button type="button" data-xd-auth-open="register">Đăng ký</button></span>@endguest
        </nav>
        <form class="xd3-search" method="GET" action="{{ route('site.catalog.search') }}"><input type="search" name="q" placeholder="Nhập từ khóa..." aria-label="Tìm kiếm"><button type="submit" aria-label="Tìm kiếm">⌕</button></form>
    </div></div>
</header>
