@php
    $profile=$siteProfile??[];$shell=$themeShellData??$themeHomeData??[];$branding=(array)data_get($shell,'branding',data_get($profile,'branding',[]));
    $siteName=trim((string)data_get($branding,'company_name',data_get($profile,'site_name','Ego Fitness')))?:'Ego Fitness';
    $logo=trim((string)data_get($branding,'logo_url',''));$hotline=data_get($branding,'support_hotline','');$nav=collect(data_get($shell,'top_menu',[]))->filter(fn($item)=>is_array($item)&&filled(data_get($item,'label')))->values();
@endphp
<header class="ec98-header" id="top">
    <div class="ec98-welcome"><div class="ec98-container"><span>Chào mừng bạn đến với Ego fitness</span><nav><a href="tel:{{ preg_replace('/\s+/','',$hotline) }}"><i class="fa-solid fa-phone"></i> Hotline: {{ $hotline }}</a><a href="{{ route('site.contact') }}">Hệ thống cửa hàng</a><a href="{{ route('site.contact') }}">Tuyển dụng</a></nav></div></div>
    <div class="ec98-container ec98-head-main">
        <a class="ec98-logo" href="{{ route('site.home') }}" aria-label="{{ $siteName }}">@if($logo)<img src="{{ $logo }}" alt="{{ $siteName }}">@endif</a>
        <form class="ec98-search" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Tìm kiếm sản phẩm" aria-label="Tìm kiếm sản phẩm"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <button class="ec98-login" type="button" data-xd-auth-open="login"><span>Đăng nhập / Đăng ký</span><b>Xin chào! Khách</b></button>
        <a class="ec98-head-icon" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-bag-shopping"></i><em>{{ data_get($cartSummary??[],'count',0) }}</em><span>Giỏ hàng<br>của bạn</span></a>
        <a class="ec98-head-icon" href="{{ route('site.catalog.search') }}"><i class="fa-regular fa-heart"></i><em>0</em><span>Yêu thích</span></a>
    </div>
    <nav class="ec98-nav"><div class="ec98-container"><button type="button" data-ec98-menu><i class="fa-solid fa-bars"></i> DANH MỤC SẢN PHẨM</button><div data-ec98-nav>
        @foreach($nav as $index=>$item)<a class="{{ $index===0?'is-active':'' }}" href="{{ data_get($item,'url') }}" target="{{ data_get($item,'target','_self') }}">{{ data_get($item,'label') }}</a>@endforeach
    </div></div></nav>
</header>
@include('partials.storefront-language-switcher')
