@php
$shell=$themeShellData??$themeHomeData??[];
$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
$t=fn(string $key):string=>app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SHOP603',app()->getLocale(),$key);
$name=trim((string)($branding['company_name']??''))?:$t('SHOP603.brand.default');
$logo=trim((string)($branding['logo_url']??''));
$phone=trim((string)($branding['support_hotline']??''));
$nav=collect(data_get($shell,'top_menu',data_get($menus??[],'primary-navigation',[])))->filter(fn($x)=>is_array($x)&&filled($x['label']??null))->values();
if($nav->isEmpty())$nav=collect([
 ['label'=>$t('SHOP603.nav.home'),'url'=>route('site.home')],['label'=>$t('SHOP603.nav.men'),'url'=>route('site.catalog.search',['q'=>'nam'])],['label'=>$t('SHOP603.nav.products'),'url'=>'#san-pham-hot'],['label'=>$t('SHOP603.nav.boys'),'url'=>route('site.catalog.search',['q'=>'bé trai'])],['label'=>$t('SHOP603.nav.girls'),'url'=>route('site.catalog.search',['q'=>'bé gái'])],['label'=>$t('SHOP603.nav.news'),'url'=>route('site.blog.index')],['label'=>$t('SHOP603.nav.contact'),'url'=>route('site.contact')]
]);
@endphp
<header class="s603-header">
    <div class="s603-top"><div class="s603-container s603-top-grid">
        <a class="s603-logo" href="{{ route('site.home') }}">@if($logo)<img src="{{ $logo }}" alt="{{ $name }}">@endif</a>
        <form class="s603-search" action="{{ route('site.catalog.search') }}" method="GET"><span>{{ $t('SHOP603.search.all') }} <i class="fa-solid fa-chevron-down"></i></span><input name="q" value="{{ request('q') }}" placeholder="{{ $t('SHOP603.search.placeholder') }}"><button aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button></form>
        <div class="s603-account">@guest('customer')<button type="button" data-xd-auth-open="login"><i class="fa-regular fa-user"></i> <span>{{ $t('SHOP603.auth.label') }}</span></button>@else<a href="{{ route('customer.account') }}"><i class="fa-regular fa-user"></i> <span>{{ auth('customer')->user()?->name }}</span></a>@endguest<i class="s603-divider"></i><a class="s603-cart" href="{{ route('site.cart.index') }}"><i class="fa-solid fa-basket-shopping"></i><em>{{ (int)data_get($cart??[],'count',0) }}</em></a></div>
    </div></div>
    <div class="s603-nav-row"><div class="s603-container s603-nav-inner"><button type="button" data-s603-menu aria-label="Menu"><i class="fa-solid fa-bars"></i></button><nav data-s603-nav>@foreach($nav as $index=>$item)<a class="{{ $index===0?'is-active':'' }}" href="{{ $item['url']??'#' }}">{{ $item['label'] }}</a>@endforeach</nav><a class="s603-hotline" href="tel:{{ preg_replace('/\s+/','',$phone) }}"><i class="fa-solid fa-phone-volume"></i><b>{{ $t('SHOP603.hotline') }}: {{ $phone }}</b></a></div></div>
</header>
@include('partials.storefront-language-switcher')
