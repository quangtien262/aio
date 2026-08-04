@php
    $shell=$themeShellData??$themeHomeData??[];
    $branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
    $logo=trim((string)data_get($branding,'logo_url'));
    $siteName=trim((string)data_get($siteProfile??[],'site_name','FOOT408'));
    $hotline=trim((string)data_get($branding,'support_hotline'));
    $hours=trim((string)data_get($branding,'business_hours'));
    $nav=collect(data_get($shell,'top_menu',[]))->filter(fn($item)=>is_array($item)&&filled(data_get($item,'label')))->values();
    $cartCount=(int)data_get($shell,'cart_summary.count',data_get($cartSummary??[],'count',0));
@endphp
<header class="f408-header">
    <div class="f408-utility">
        <div class="f408-container">
            @if($hours)<span><i class="fa-regular fa-clock"></i> @themeT('FOOT408.opening_hours','Giờ mở cửa'): {{ $hours }}</span>@endif
            <div><button type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i> @themeT('FOOT408.account','Tài khoản')</button><a href="{{ route('site.cart.index') }}"><i class="fa-regular fa-heart"></i> @themeT('FOOT408.cart','Giỏ hàng')</a>@include('partials.storefront-language-switcher')</div>
        </div>
    </div>
    <div class="f408-mainbar">
        <div class="f408-container f408-mainbar__grid">
            <nav class="f408-nav f408-nav--left">@foreach($nav->take((int)ceil(max(1,$nav->count())/2)) as $item)<a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'label') }}</a>@endforeach</nav>
            <a class="f408-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<i class="fa-solid fa-utensils"></i><strong>{{ $siteName }}</strong>@endif</a>
            <div class="f408-main-actions"><nav class="f408-nav">@foreach($nav->skip((int)ceil(max(1,$nav->count())/2)) as $item)<a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'label') }}</a>@endforeach</nav>@if($hotline)<a class="f408-phone" href="tel:{{ preg_replace('/\s+/','',$hotline) }}"><i class="fa-solid fa-phone"></i>{{ $hotline }}</a>@endif<a class="f408-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><span>{{ $cartCount }}</span></a><button type="button" data-f408-search><i class="fa-solid fa-magnifying-glass"></i></button><a class="f408-booking" href="{{ route('site.contact') }}">@themeT('FOOT408.booking','Liên hệ đặt bàn')</a><button class="f408-menu-toggle" type="button" data-f408-menu-toggle><i class="fa-solid fa-bars"></i></button></div>
        </div>
        <nav class="f408-nav f408-mobile-nav f408-container" data-f408-nav>@foreach($nav as $item)<a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'label') }}</a>@endforeach<button type="button" data-xd-auth-open="login">@themeT('FOOT408.login','Đăng nhập')</button><button type="button" data-xd-auth-open="register">@themeT('FOOT408.register','Đăng ký')</button></nav>
        <form class="f408-search f408-container" data-f408-search-panel method="get" action="{{ route('site.catalog.search') }}"><input type="search" name="q" placeholder="Tìm món ăn, sản phẩm..."><button><i class="fa-solid fa-magnifying-glass"></i></button></form>
    </div>
</header>
