<header class="rx13-header">
    <div class="rx13-header__inner">
        <a class="rx13-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">@endif
        </a>

        <nav class="rx13-nav" data-rx13-nav aria-label="Dieu huong chinh">
            @foreach ($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                    {{ $item['label'] ?? 'Menu' }}
                    @if (! empty($item['children']))
                        <span aria-hidden="true">v</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="rx13-actions">
            <button class="rx13-search" type="button" aria-label="Tim kiem"></button>
            <span class="rx13-auth">
                @guest('customer')
                    <button type="button" data-xd-auth-open="login">Dang nhap</button>
                    <button type="button" data-xd-auth-open="register">Dang ky</button>
                @else
                    <a href="{{ route('customer.account') }}">Tai khoan</a>
                @endguest
            </span>
            <a class="rx13-appointment" href="#footer">Nhan mot cuoc hen <span>-></span></a>
            <span class="rx13-flag rx13-flag--en">EN</span>
            <span class="rx13-flag">VN</span>
            <button class="rx13-menu" type="button" data-rx13-menu aria-expanded="false" aria-label="Menu">=</button>
        </div>
    </div>
</header>
@include('partials.storefront-language-switcher')
