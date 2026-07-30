@php
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $logo = data_get($branding, 'logo_url');
    $hotline = data_get($branding, 'support_hotline', '1900 6760');
    $email = data_get($branding, 'support_email', 'cs@dealvui.vn');
    $nav = collect(data_get($shell, 'top_menu', []))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="ec93-header">
    <div class="ec93-utility"><div class="ec93-container"><span><i class="fa-solid fa-location-dot"></i> Hồ Chí Minh <i class="fa-solid fa-caret-down"></i></span><a href="#newsletter"><i class="fa-regular fa-envelope"></i> Đăng ký bản tin</a><div><a href="tel:{{ preg_replace('/\s+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i> Hotline: <b>{{ $hotline }}</b></a><a href="mailto:{{ $email }}"><i class="fa-solid fa-envelope"></i> Email: {{ $email }}</a><button type="button" data-auth-open="register"><i class="fa-regular fa-user"></i> Đăng ký</button><button type="button" data-auth-open="login">Đăng nhập</button></div></div></div>
    <div class="ec93-head-main"><div class="ec93-container"><a class="ec93-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ data_get($siteProfile ?? [], 'site_name') }}">@else<span>DEAL</span>VUI<small>.vn</small>@endif</a><form action="{{ route('site.catalog.search') }}" method="get"><select aria-label="Danh mục"><option>Tất cả danh mục</option><option>Ẩm thực</option><option>Spa & Làm đẹp</option><option>Du lịch</option></select><input name="q" placeholder="Tìm kiếm sản phẩm / khuyến mãi"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form><a class="ec93-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-cart-shopping"></i><b>{{ collect($cartLines ?? [])->sum('quantity') }}</b><span>GIỎ HÀNG</span></a></div></div>
    <nav class="ec93-nav"><div class="ec93-container"><button type="button" data-ec93-menu><i class="fa-solid fa-bars"></i> DANH MỤC <i class="fa-solid fa-chevron-down"></i></button>@foreach($nav as $item)<a href="{{ data_get($item, 'url') }}" target="{{ data_get($item, 'target', '_self') }}">{{ data_get($item, 'label') }}</a>@endforeach</div></nav>
</header>
