@php
    $blocks=collect($landingBlocks??[])->filter(fn($block)=>(bool)data_get($block,'is_visible',true))->values();
    $byAnchor=fn(string $anchor):array=>(array)($blocks->first(fn($block)=>data_get($block,'anchor_id')===$anchor)??[]);
    $items=function(array $block){$dynamic=collect(data_get($block,'dynamic_items',[]))->filter()->values();return $dynamic->isNotEmpty()?$dynamic:collect(data_get($block,'data.content.items',[]))->filter()->values();};
    $hero=$byAnchor('top');$categories=$byAnchor('thuc-don');$pizza=$byAnchor('pizza');$featured=$byAnchor('mon-moi');$dual=$byAnchor('uu-dai-doi');$recommendations=$byAnchor('goi-y');$triple=$byAnchor('uu-dai-ba');$posts=$byAnchor('tin-tuc');$suppliers=$byAnchor('doi-tac');$benefits=$byAnchor('quyen-loi');
    $slides=collect(data_get($hero,'dynamic_items',[]))->filter()->values();if($slides->isEmpty())$slides=collect(data_get($hero,'data.content.slides',[]))->filter()->values();
@endphp
@extends('theme-foot409::layout')
@section('title',data_get($landingPage??[],'meta_title',data_get($siteProfile??[],'site_name','FOOT409')))
@section('content')
<main class="f409-main">
    <section class="f409-hero xd-landing-block" data-landing-block-id="{{ data_get($hero,'id') }}" data-block-type="hero_slider">
        @include('theme-foot409::partials.edit-button',['block'=>$hero])
        <div class="f409-slider" data-f409-slider>
            @forelse($slides as $index=>$slide)
                <article class="f409-slide {{ $index===0?'is-active':'' }}" data-f409-slide style="--hero-image:url('{{ data_get($slide,'image','/theme-demo/foot409/hero-fried-chicken.png') }}')"><div class="f409-container"><div class="f409-hero__copy" data-f409-reveal><small>{{ data_get($slide,'kicker',data_get($slide,'badge',data_get($hero,'data.subtitle','TỚI FOOT409'))) }}</small><h1>{{ data_get($slide,'title',data_get($hero,'data.title','Gà cay giòn tan')) }}</h1><p>{{ data_get($slide,'summary',data_get($hero,'data.description')) }}</p><a class="f409-button" href="{{ data_get($slide,'link_url','#mon-moi') }}">{{ data_get($slide,'button_label',data_get($hero,'data.button_label','Đặt ngay')) }} <i class="fa-solid fa-arrow-right"></i></a></div></div></article>
            @empty
                <article class="f409-slide is-active" data-f409-slide style="--hero-image:url('/theme-demo/foot409/hero-fried-chicken.png')"><div class="f409-container"><div class="f409-hero__copy"><small>TỚI FOOT409</small><h1>Gà cay<br>giòn tan</h1><a class="f409-button" href="#mon-moi">Đặt ngay</a></div></div></article>
            @endforelse
            @if($slides->count()>1)<button class="f409-arrow f409-arrow--prev" type="button" data-f409-prev><i class="fa-solid fa-chevron-left"></i></button><button class="f409-arrow f409-arrow--next" type="button" data-f409-next><i class="fa-solid fa-chevron-right"></i></button>@endif
        </div>
    </section>

    <section class="f409-section f409-categories xd-landing-block" id="thuc-don" data-landing-block-id="{{ data_get($categories,'id') }}" data-block-type="foot409_categories">
        @include('theme-foot409::partials.edit-button',['block'=>$categories])
        <div class="f409-container"><h2 class="f409-heading" data-f409-reveal>{{ data_get($categories,'data.title','Lựa chọn thực đơn') }}</h2><div class="f409-category-grid">@foreach($items($categories) as $item)<a href="{{ data_get($item,'url','#mon-moi') }}" data-f409-reveal><span><img src="{{ data_get($item,'image','/theme-demo/foot409/promo-feast.png') }}" alt="{{ data_get($item,'title') }}"></span><strong>{{ data_get($item,'title') }}</strong></a>@endforeach</div></div>
    </section>

    <section class="f409-section f409-promo-wrap xd-landing-block" id="pizza" data-landing-block-id="{{ data_get($pizza,'id') }}" data-block-type="foot409_promo_banner">
        @include('theme-foot409::partials.edit-button',['block'=>$pizza])
        <div class="f409-container"><article class="f409-promo f409-promo--wide" style="--promo:url('{{ data_get($pizza,'settings.background_image','/theme-demo/foot409/promo-pizza.png') }}')" data-f409-reveal><div><small>{{ data_get($pizza,'data.subtitle','ƯU ĐÃI 45%') }}</small><h2>{{ data_get($pizza,'data.title','PIZZA HÔM NAY') }}</h2><p>{{ data_get($pizza,'data.description','Giá sốc bất ngờ') }}</p><a class="f409-button" href="{{ data_get($pizza,'data.button_url','#mon-moi') }}">{{ data_get($pizza,'data.button_label','Chốt đơn liền tay') }} <i class="fa-solid fa-arrow-right"></i></a></div></article></div>
    </section>

    <section class="f409-section f409-featured xd-landing-block" id="mon-moi" data-landing-block-id="{{ data_get($featured,'id') }}" data-block-type="foot409_featured_products">
        @include('theme-foot409::partials.edit-button',['block'=>$featured])
        <div class="f409-container"><h2 class="f409-heading" data-f409-reveal>{{ data_get($featured,'data.title','Chào ngày mới') }}</h2><div class="f409-featured__grid"><div class="f409-products">@foreach($items($featured)->take(4) as $item)@include('theme-foot409::partials.product-card',['item'=>$item])@endforeach</div><a class="f409-tall-ad" href="#goi-y" style="--ad:url('/theme-demo/foot409/promo-burger.png')"><span>THỨ 5</span><strong>BURGER<br>DEAL</strong><small>Mua 1 tặng 1</small></a></div></div>
    </section>

    <section class="f409-section xd-landing-block" id="uu-dai-doi" data-landing-block-id="{{ data_get($dual,'id') }}" data-block-type="foot409_dual_promos">
        @include('theme-foot409::partials.edit-button',['block'=>$dual])
        <div class="f409-container f409-promo-grid f409-promo-grid--two">@foreach($items($dual)->take(2) as $item)<article class="f409-promo" style="--promo:url('{{ data_get($item,'image') }}')" data-f409-reveal><div><small>{{ data_get($item,'subtitle') }}</small><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p><a class="f409-button" href="{{ data_get($item,'url','#mon-moi') }}">Đặt ngay <i class="fa-solid fa-arrow-right"></i></a></div></article>@endforeach</div>
    </section>

    <section class="f409-section xd-landing-block" id="goi-y" data-landing-block-id="{{ data_get($recommendations,'id') }}" data-block-type="foot409_recommendations">
        @include('theme-foot409::partials.edit-button',['block'=>$recommendations])
        <div class="f409-container f409-recommend"><h2 class="f409-heading">{{ data_get($recommendations,'data.title','Gợi ý cho bạn') }}</h2><div class="f409-recommend__grid">@foreach($items($recommendations)->take(8) as $item)<article data-f409-reveal><img src="{{ data_get($item,'image') }}" alt="{{ data_get($item,'title') }}"><div><h3>{{ data_get($item,'title') }}</h3><div class="f409-stars">★★★★★</div><strong>{{ data_get($item,'price')?number_format((float)data_get($item,'price'),0,',','.').'đ':'Liên hệ' }}</strong></div><a href="{{ data_get($item,'url','#') }}" aria-label="Chọn món"><i class="fa-solid fa-sliders"></i></a></article>@endforeach</div><a class="f409-view-all" href="{{ route('site.catalog.search') }}">Xem tất cả <i class="fa-solid fa-chevron-right"></i></a></div>
    </section>

    <section class="f409-section xd-landing-block" id="uu-dai-ba" data-landing-block-id="{{ data_get($triple,'id') }}" data-block-type="foot409_triple_promos">
        @include('theme-foot409::partials.edit-button',['block'=>$triple])
        <div class="f409-container f409-promo-grid f409-promo-grid--three">@foreach($items($triple)->take(3) as $item)<article class="f409-promo f409-promo--small" style="--promo:url('{{ data_get($item,'image') }}')" data-f409-reveal><div><small>{{ data_get($item,'subtitle') }}</small><h3>{{ data_get($item,'title') }}</h3><a class="f409-button" href="{{ data_get($item,'url','#mon-moi') }}">Đặt ngay <i class="fa-solid fa-arrow-right"></i></a></div></article>@endforeach</div>
    </section>

    <section class="f409-section xd-landing-block" id="tin-tuc" data-landing-block-id="{{ data_get($posts,'id') }}" data-block-type="foot409_blog_posts">
        @include('theme-foot409::partials.edit-button',['block'=>$posts])
        <div class="f409-container"><h2 class="f409-heading">{{ data_get($posts,'data.title','Bảng tin khuyến mãi') }}</h2><div class="f409-posts">@foreach($items($posts)->take(4) as $item)<article data-f409-reveal><a href="{{ data_get($item,'url','#') }}"><img src="{{ data_get($item,'image','/theme-demo/ec903/food-brunch.webp') }}" alt="{{ data_get($item,'title') }}"></a><div><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary',data_get($item,'excerpt')) }}</p><footer><small><i class="fa-regular fa-calendar"></i> {{ data_get($item,'published_at',data_get($item,'date')) }}</small><a href="{{ data_get($item,'url','#') }}">Xem chi tiết</a></footer></div></article>@endforeach</div></div>
    </section>

    <section class="f409-section f409-suppliers xd-landing-block" id="doi-tac" data-landing-block-id="{{ data_get($suppliers,'id') }}" data-block-type="foot409_suppliers">
        @include('theme-foot409::partials.edit-button',['block'=>$suppliers])
        <div class="f409-container"><h2 class="f409-heading">{{ data_get($suppliers,'data.title','Nhà cung cấp uy tín') }}</h2><div class="f409-supplier-list">@foreach($items($suppliers) as $item)<span>{{ data_get($item,'title') }}</span>@endforeach</div></div>
    </section>

    <section class="f409-benefits xd-landing-block" id="quyen-loi" data-landing-block-id="{{ data_get($benefits,'id') }}" data-block-type="foot409_benefits">
        @include('theme-foot409::partials.edit-button',['block'=>$benefits])
        <div class="f409-container">@foreach($items($benefits) as $index=>$item)<article><i class="fa-solid {{ ['fa-truck-fast','fa-arrows-rotate','fa-thumbs-up','fa-ticket'][$index%4] }}"></i><div><strong>{{ data_get($item,'title') }}</strong><small>{{ data_get($item,'summary') }}</small></div></article>@endforeach</div>
    </section>
</main>
@endsection
