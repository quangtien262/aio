@php
$shell=$themeShellData??$themeHomeData??[];$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
$phone=$branding['support_hotline']??'0399162342';$name=trim((string)($branding['company_name']??''))?:'Sudes Aquarium';$logo=trim((string)($branding['logo_url']??''));$cartCount=(int)data_get($cartSummary??[],'count',0);$nav=collect(data_get($shell,'top_menu',[]))->filter(fn($item)=>is_array($item)&&filled(data_get($item,'label')))->values();
@endphp
<div class="ca50-topbar"><div class="ca50-top-inner"><span><i class="fa-solid fa-headset"></i> {{ $phone }}</span><span><i class="fa-regular fa-clock"></i> 9h - 23h, Từ thứ 2 - thứ 7</span><b>[24.12 – 10.01] Mua Combo Hồ và Cá <em>Giảm đến 30%</em></b>@guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-solid fa-user"></i> Đăng nhập | Đăng ký</button>@else<a href="{{ route('customer.account') }}"><i class="fa-solid fa-user"></i> {{ auth('customer')->user()?->name }}</a>@endguest</div></div>
<header class="ca50-header">
 <div class="ca50-header-inner">
  <a class="ca50-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<span><i class="fa-solid fa-fish-fins"></i></span><b>SUDES<br><strong>AQUARIUM</strong></b>@endif</a>
  <button class="ca50-menu-toggle" data-ca50-menu><i class="fa-solid fa-bars"></i></button>
  <nav class="ca50-nav" data-ca50-nav>@foreach($nav as $item)<a href="{{ data_get($item,'url') }}" target="{{ data_get($item,'target','_self') }}">{{ data_get($item,'label') }}</a>@endforeach</nav>
  <form class="ca50-search" method="GET" action="{{ route('site.catalog.search') }}"><input name="q" placeholder="Tìm kiếm..."><button><i class="fa-solid fa-magnifying-glass"></i></button></form>
  <div class="ca50-tools"><a href="{{ route('customer.account') }}"><i class="fa-regular fa-heart"></i><em>0</em></a><a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ $cartCount }}</em></a></div>
 </div>
</header>
@include('partials.storefront-language-switcher')
