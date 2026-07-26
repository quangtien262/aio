@php
$shell=$themeShellData??$themeHomeData??[];$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
$phone=$branding['support_hotline']??'1900 6750';$name=trim((string)($branding['company_name']??''))?:'Sudes Aquarium';$logo=trim((string)($branding['logo_url']??''));$cartCount=(int)data_get($cartSummary??[],'count',0);
@endphp
<div class="ca50-topbar"><div class="ca50-top-inner"><span><i class="fa-solid fa-headset"></i> {{ $phone }}</span><span><i class="fa-regular fa-clock"></i> 9h - 23h, Từ thứ 2 - thứ 7</span><b>[24.12 – 10.01] Mua Combo Hồ và Cá <em>Giảm đến 30%</em></b>@guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-solid fa-user"></i> Đăng nhập | Đăng ký</button>@else<a href="{{ route('customer.account') }}"><i class="fa-solid fa-user"></i> {{ auth('customer')->user()?->name }}</a>@endguest</div></div>
<header class="ca50-header">
 <div class="ca50-header-inner">
  <a class="ca50-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<span><i class="fa-solid fa-fish-fins"></i></span><b>SUDES<br><strong>AQUARIUM</strong></b>@endif</a>
  <button class="ca50-menu-toggle" data-ca50-menu><i class="fa-solid fa-bars"></i></button>
  <nav class="ca50-nav" data-ca50-nav><a href="{{ route('site.home') }}">Trang chủ</a><a href="#gioi-thieu">Giới thiệu</a><a href="#the-gioi-ca-canh">Sản phẩm⌄</a><a href="#setup">Bộ sưu tập</a><a href="#tin-tuc">Tin tức</a><a href="#faq">FAQ</a><a href="{{ route('site.contact') }}">Liên hệ</a></nav>
  <form class="ca50-search" method="GET" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Tìm kiếm..."><button><i class="fa-solid fa-magnifying-glass"></i></button></form>
  <div class="ca50-tools"><a href="{{ route('customer.account') }}"><i class="fa-regular fa-heart"></i><em>0</em></a><a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ $cartCount }}</em></a></div>
 </div>
</header>
