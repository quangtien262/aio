@php
    $shell=$themeShellData??$themeHomeData??[];
    $branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
    $logo=trim((string)data_get($branding,'logo_url'));
    $siteName=trim((string)data_get($siteProfile??[],'site_name','FOOT409'));
    $nav=collect(data_get($shell,'top_menu',[]))->filter(fn($item)=>is_array($item)&&filled(data_get($item,'label')))->values();
    $cartCount=(int)data_get($shell,'cart_summary.count',data_get($cartSummary??[],'count',0));
@endphp
<header class="f409-header">
    <div class="f409-container f409-header__inner">
        <a class="f409-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span><i class="fa-solid fa-utensils"></i></span><strong>{{ $siteName }}</strong>@endif
        </a>
        <button class="f409-menu-toggle" type="button" data-f409-menu-toggle aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="f409-nav" data-f409-nav>
            @foreach($nav as $item)<a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'label') }}</a>@endforeach
        </nav>
        <div class="f409-actions">
            @include('partials.storefront-language-switcher')
            <span class="f409-live"><i></i> LIVE</span>
            <button type="button" data-f409-search aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
            <button type="button" data-xd-auth-open="login" aria-label="Tài khoản"><i class="fa-regular fa-user"></i></button>
            <a class="f409-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><b>{{ $cartCount }}</b><span>@themeT('FOOT409.cart','Giỏ hàng')</span></a>
        </div>
    </div>
    <form class="f409-search f409-container" data-f409-search-panel method="get" action="{{ route('site.catalog.search') }}"><input type="search" name="q" placeholder="Tìm món ăn, combo, khuyến mãi..."><button>@themeT('FOOT409.search','Tìm kiếm')</button></form>
</header>
