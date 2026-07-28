@php
    $profile=(array)($siteProfile??[]);$shell=$themeShellData??$themeHomeData??[];
    $branding=(array)data_get($shell,'branding',data_get($profile,'branding',[]));$logo=trim((string)data_get($branding,'logo_url',''));
    $siteName=trim((string)data_get($branding,'company_name',data_get($profile,'site_name','EGA Gear')))?:'EGA Gear';$hotline=data_get($branding,'support_hotline','0999 999 998');$nav=collect($primaryMenu??[])->filter()->values();
@endphp
<header class="ec97-header" id="top">
    <div class="ec97-container ec97-head-top">
        <a class="ec97-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@else<span><i>E</i><b>EGA</b><strong>GEAR</strong></span>@endif</a>
        <form class="ec97-search" action="{{ route('site.catalog.search') }}"><label>Danh mục sản phẩm <i class="fa-solid fa-angle-down"></i></label><input name="q" placeholder="Tìm theo tên sản phẩm..."><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="ec97-account">@guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>Tài khoản<b>Đăng nhập</b></span></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i><span>Tài khoản<b>{{ auth('customer')->user()?->name }}</b></span></a>@endguest<a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int)data_get($cart??[],'count',0) }}</em><span>Giỏ hàng</span></a></div>
    </div>
    <nav class="ec97-nav"><div class="ec97-container"><button type="button" data-ec97-menu><i class="fa-solid fa-bars"></i> Danh mục sản phẩm</button><div data-ec97-nav>
        @forelse($nav as $item)<a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'label') }}</a>@empty<a href="#khuyen-mai">Khuyến mãi</a><a href="#dich-vu">Dịch vụ</a><a href="#tin-tuc">Tin tức</a><a href="{{ route('site.contact') }}">Liên hệ</a><a href="{{ route('site.catalog.search') }}">Kiểm tra đơn hàng</a>@endforelse
    </div><a href="{{ route('site.contact') }}"><i class="fa-solid fa-store"></i> Hệ thống cửa hàng</a><a href="tel:{{ preg_replace('/\s+/','',$hotline) }}"><i class="fa-solid fa-phone"></i> Hotline: <b>{{ $hotline }}</b></a></div></nav>
</header>
