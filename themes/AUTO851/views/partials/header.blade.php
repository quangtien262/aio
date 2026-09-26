@php
    $homeUrl = route('site.home', ['locale' => app()->getLocale()]); $shell = $themeShellData ?? $themeHomeData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $company = trim((string) data_get($branding, 'company_name', 'AUTO851 OH!Car')); $logo = trim((string) data_get($branding, 'logo_url', ''));
    $nav = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', [])))->filter(fn ($item) => is_array($item) && filled(data_get($item, 'label')))->values();
@endphp
<header class="a851-header"><div class="a851-wrap a851-navrow">
<a class="a851-logo" href="{{ $homeUrl }}">@if($logo)<img src="{{ $logo }}" alt="{{ $company }}">@else<span><i class="fa-solid fa-car-side"></i><b>OH!Car</b></span><small>CAR &amp; SERVICE</small>@endif</a>
<button class="a851-menu" type="button" data-a851-menu aria-label="@themeT('menu.open', 'Mở menu')"><i class="fa-solid fa-bars"></i></button>
<nav data-a851-nav>@forelse($nav as $item)<a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'label') }}</a>@empty<a href="{{ $homeUrl }}">Trang chủ</a><a href="#mua-xe">Mua xe</a><a href="#ban-xe">Bán xe</a><a href="#phu-kien">Phụ kiện ô tô</a><a href="#tin-tuc">Tin tức</a><a href="#faq">FAQ</a>@endforelse</nav>
<form class="a851-search" action="{{ route('site.catalog.search', ['locale' => app()->getLocale()]) }}"><input name="q" placeholder="Tìm kiếm sản phẩm"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
@guest('customer')<button class="a851-icon" type="button" data-xd-auth-open="login" aria-label="Đăng nhập"><i class="fa-regular fa-user"></i></button>@else<a class="a851-icon" href="{{ route('customer.account', ['locale' => app()->getLocale()]) }}"><i class="fa-regular fa-user"></i></a>@endguest
<a class="a851-icon" href="{{ route('site.cart.index', ['locale' => app()->getLocale()]) }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int) ($cartCount ?? 0) }}</em></a>

            @include('partials.storefront-language-switcher')
        </div></header>
