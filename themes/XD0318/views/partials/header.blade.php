<header class="fg18-header">
    <div class="fg18-container fg18-header__inner">
        <a class="fg18-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="fg18-brand__mark"></span>
                <span><strong>FAST GEAR</strong><small>messenger service</small></span>
            @endif
        </a>

        <nav class="fg18-nav" data-fg18-nav aria-label="Dieu huong chinh">
            @foreach ($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}" target="{{ $item['target'] ?? '_self' }}">
                    {{ $item['label'] ?? 'Menu' }}
                    @if (! empty($item['children']))
                        <span aria-hidden="true">v</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="fg18-actions">
            @guest('customer')
                <button type="button" data-xd-auth-open="login">Dang nhap</button>
                <button type="button" data-xd-auth-open="register">Dang ky</button>
            @else
                <a href="{{ route('customer.account') }}">Tai khoan</a>
            @endguest
            <a class="fg18-search" href="{{ route('site.catalog.search') }}" aria-label="Tim kiem"></a>
            <span class="fg18-flag fg18-flag--en">EN</span>
            <span class="fg18-flag">VN</span>
            <button class="fg18-menu" type="button" data-fg18-menu aria-expanded="false" aria-label="Menu">=</button>
        </div>
    </div>
</header>
