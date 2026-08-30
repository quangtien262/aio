@php
  $shell=$themeShellData??$themeHomeData??[];$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
  $t=fn(string $key):string=>app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('E804',app()->getLocale(),$key);
  $name=trim((string)($branding['company_name']??data_get($siteProfile??[],'site_name','')))?:'E804 Mall';$logo=trim((string)($branding['logo_url']??''));
  $nav=collect(data_get($shell,'top_menu',data_get($menus??[],'primary-navigation',[])))->filter(fn($item)=>is_array($item)&&filled($item['label']??null))->values();
  if($nav->isEmpty())$nav=collect([['label'=>'Trang chủ','url'=>route('site.home')],['label'=>'Giới thiệu','url'=>'#gioi-thieu'],['label'=>'Sản phẩm','url'=>'#san-pham'],['label'=>'Yêu thích','url'=>'#goi-y'],['label'=>'Liên hệ','url'=>route('site.contact')],['label'=>'Tin tức','url'=>'#tin-tuc'],['label'=>'Hệ thống cửa hàng','url'=>'#footer'],['label'=>'Đăng ký Affiliate','url'=>'#footer']]);
@endphp
<header class="e804-header">
  <div class="e804-head e804-container"><a class="e804-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@else<span><i class="fa-solid fa-bag-shopping"></i></span><b>E804<em>MALL</em></b>@endif</a><a class="e804-stores" href="#footer"><i class="fa-solid fa-store"></i><span>Hệ thống cửa hàng<br><b>40 cửa hàng</b></span></a><form class="e804-search" action="{{ route('site.catalog.search') }}"><i class="fa-solid fa-magnifying-glass"></i><input name="q" placeholder="Tìm kiếm sản phẩm..."><button>Tìm kiếm</button></form><div class="e804-quick"><a href="#goi-y"><i class="fa-regular fa-heart"></i><span>Yêu thích</span></a><button data-xd-auth-open="login"><i class="fa-regular fa-user"></i><span>Tài khoản</span></button><a href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><span>Giỏ hàng</span><em>{{ (int)data_get($shell,'cart_count',0) }}</em></a></div><button class="e804-toggle" data-e804-toggle aria-label="Menu"><i class="fa-solid fa-bars"></i></button></div>
  <div class="e804-nav"><nav class="e804-container" data-e804-nav>@foreach($nav as $item)<a href="{{ $item['url']??'#' }}">{{ $item['label'] }}</a>@endforeach</nav></div>
</header>
@include('partials.storefront-language-switcher')
