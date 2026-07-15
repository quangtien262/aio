<header class="rx13-header">
    <div class="rx13-container rx13-header__inner">
        <a class="rx13-brand" href="{{ route('site.home') }}" aria-label="{{ $companyName }}">
            @if (filled($logoUrl ?? null))
                <img src="{{ $logoUrl }}" alt="{{ $companyName }}">
            @else
                <span class="rx13-brand__mark">R</span><strong>RouteX</strong>
            @endif
        </a>

        <button class="rx13-menu-toggle" type="button" data-rx13-menu-toggle aria-expanded="false" aria-label="Mo menu">Menu</button>

        <nav class="rx13-nav" data-rx13-nav aria-label="Dieu huong chinh">
            @foreach($navItems ?? [] as $item)
                <a href="{{ $item['href'] ?? '#' }}">{{ $item['label'] ?? 'Menu' }}</a>
            @endforeach
        </nav>

        <div class="rx13-header__actions">
            @guest('customer')
                <button class="rx13-auth-link" type="button" data-xd-auth-open="login">Dang nhap</button>
                <button class="rx13-auth-link" type="button" data-xd-auth-open="register">Dang ky</button>
            @else
                <a class="rx13-auth-link" href="{{ route('customer.account') }}">Tai khoan</a>
            @endguest
            <a class="rx13-search" href="{{ route('site.search') }}" aria-label="Tim kiem">&#9906;</a>
            <a class="rx13-cta" href="#lien-he">Nhan mot cuoc hen <span aria-hidden="true">&rarr;</span></a>
        </div>
    </div>
</header>
