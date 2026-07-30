<header class="xd12-header">
    <div class="xd12-utility">
        <div class="xd5-container">
            <a href="tel:{{ $phoneHref }}">{{ $hotline }}</a>
            <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
            <span class="xd12-utility-spacer"></span>
            @guest('customer')
                <button type="button" data-xd-auth-open="login">Đăng nhập</button>
                <button type="button" data-xd-auth-open="register">Đăng ký</button>
            @else
                <a href="{{ route('customer.account') }}">Tài khoản</a>
            @endguest
            <a class="xd12-quote-small" href="#lien-he">Báo giá miễn phí</a>
        </div>
    </div>
    <div class="xd5-container xd12-navigation">
        <a class="xd5-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>
        <button data-xd5-menu type="button" aria-expanded="false">Menu</button>
        <nav data-xd5-nav aria-label="Điều hướng chính">
            @foreach($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>
            @endforeach
        </nav>
        <a class="xd12-quote" href="#lien-he">Nhận báo giá</a>
    </div>
</header>
@include('partials.storefront-language-switcher')
