@php
$shell=$themeShellData??$themeHomeData??[];
$branding=(array)data_get($shell,'branding',data_get($siteProfile??[],'branding',[]));
$t=fn(string $key):string=>app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('SHOP604',app()->getLocale(),$key);
$name=trim((string)($branding['company_name']??''))?:$t('SHOP604.brand.default');
$logo=trim((string)($branding['logo_url']??''));
$nav=collect(data_get($shell,'top_menu',data_get($menus??[],'primary-navigation',[])))->filter(fn($x)=>is_array($x)&&filled($x['label']??null))->values();
if($nav->isEmpty())$nav=collect([
 ['label'=>$t('SHOP604.nav.home'),'url'=>route('site.home')],
 ['label'=>$t('SHOP604.nav.about'),'url'=>route('site.home').'#phong-cach'],
 ['label'=>$t('SHOP604.nav.lingerie'),'url'=>route('site.catalog.search',['q'=>'đồ lót'])],
 ['label'=>$t('SHOP604.nav.swim'),'url'=>route('site.catalog.search',['q'=>'bikini'])],
 ['label'=>$t('SHOP604.nav.sale'),'url'=>route('site.home').'#flash-sale'],
 ['label'=>$t('SHOP604.nav.combo'),'url'=>route('site.catalog.search',['q'=>'combo'])],
 ['label'=>$t('SHOP604.nav.stores'),'url'=>route('site.contact')],
 ['label'=>$t('SHOP604.nav.faq'),'url'=>route('site.contact').'#faq'],
]);
@endphp
<header class="s604-header" id="top">
    <div class="s604-header-inner">
        <a class="s604-logo" href="{{ route('site.home') }}">
            @if($logo)<img src="{{ $logo }}" alt="{{ $name }}">
            @else<span>B</span><strong>ean</strong><small>LINGERIE</small>@endif
        </a>
        <button class="s604-menu-toggle" type="button" data-s604-menu aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
        <nav class="s604-nav" data-s604-nav>
            @foreach($nav as $index=>$item)<a class="{{ $index===0?'is-active':'' }}" href="{{ $item['url']??'#' }}">{{ $item['label'] }}</a>@endforeach
        </nav>
        <div class="s604-tools">
            <button type="button" data-s604-search aria-label="Tìm kiếm"><i class="fa-solid fa-magnifying-glass"></i></button>
            @guest('customer')<button type="button" data-xd-auth-open="login" aria-label="{{ $t('SHOP604.auth.label') }}"><i class="fa-regular fa-user"></i></button>
            @else<a href="{{ route('customer.account') }}" aria-label="{{ auth('customer')->user()?->name }}"><i class="fa-regular fa-user"></i></a>@endguest
            <a href="{{ route('customer.account') }}" aria-label="Yêu thích"><i class="fa-regular fa-heart"></i><em>0</em></a>
            <a href="{{ route('site.cart.index') }}" aria-label="Giỏ hàng"><i class="fa-solid fa-bag-shopping"></i><em>{{ (int)data_get($cart??[],'count',0) }}</em></a>
        </div>
    </div>
    <form class="s604-search-panel" data-s604-search-panel action="{{ route('site.catalog.search') }}" method="GET">
        <input name="q" value="{{ request('q') }}" placeholder="{{ $t('SHOP604.search.placeholder') }}">
        <button aria-label="Tìm"><i class="fa-solid fa-arrow-right"></i></button>
    </form>
</header>
@include('partials.storefront-language-switcher')
