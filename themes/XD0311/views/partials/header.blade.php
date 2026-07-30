<header class="xd5-header">
    <div class="xd5-utility">
        <div class="xd5-container">
            <span>{{ $supportAddress }}</span>
            <span>{{ $supportEmail }}</span>
            <b>{{ $hotline }}</b>
        </div>
    </div>
    <div class="xd5-container xd5-nav-wrap">
        <a class="xd5-brand" href="{{ route('site.home') }}">
            @if(filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>
        <button data-xd5-menu aria-expanded="false">Menu</button>
        <nav data-xd5-nav>
            @foreach($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>
            @endforeach
        </nav>
        @guest('customer')
            <div class="xd11-auth-actions" aria-label="Tài khoản">
                <button type="button" data-xd-auth-open="login">Đăng nhập</button>
                <button type="button" data-xd-auth-open="register">Đăng ký</button>
            </div>
        @else
            <a class="xd11-account-link" href="{{ route('customer.account') }}">Tài khoản</a>
        @endguest
        <a class="xd5-hotline" href="tel:{{ $phoneHref }}">{{ $hotline }}</a>
    </div>
</header>
@include('partials.storefront-language-switcher')
