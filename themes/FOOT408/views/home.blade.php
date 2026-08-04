@php
    $blocks=collect($landingBlocks??[])->filter(fn($block)=>(bool)data_get($block,'is_visible',true))->values();
    $byAnchor=fn(string $anchor):array=>(array)($blocks->first(fn($block)=>data_get($block,'anchor_id')===$anchor)??[]);
    $items=function(array $block){$dynamic=collect(data_get($block,'dynamic_items',[]))->filter()->values();return $dynamic->isNotEmpty()?$dynamic:collect(data_get($block,'data.content.items',[]))->filter()->values();};
    $hero=$byAnchor('top');$welcome=$byAnchor('gioi-thieu');$menu=$byAnchor('thuc-don');$combos=$byAnchor('combo');$testimonials=$byAnchor('phan-hoi');$posts=$byAnchor('tin-tuc');
    $slides=collect(data_get($hero,'dynamic_items',[]))->filter()->values();if($slides->isEmpty())$slides=collect(data_get($hero,'data.content.slides',[]))->filter()->values();
    $canEditLanding=auth('admin')->check()&&request('mod')==='admin'&&is_array($landingPage??null);
    $blockUpdateUrlTemplate=$canEditLanding?route('admin.api.landing.blocks.update',['block'=>'__BLOCK_ID__']):'';
    $blockSourcePreviewUrlTemplate=$canEditLanding?route('admin.api.landing.blocks.source-preview',['block'=>'__BLOCK_ID__']):'';
    $blockPayload=$canEditLanding?$blocks->filter(fn($block)=>filled(data_get($block,'id')))->keyBy('id')->toArray():[];
@endphp
@extends('theme-foot408::layout')
@section('title',data_get($landingPage??[],'meta_title',data_get($siteProfile??[],'site_name','FOOT408')))
@section('content')
<main class="f408-main">
    <section class="f408-hero xd-landing-block" data-landing-block-id="{{ data_get($hero,'id') }}" data-block-type="hero_slider">
        @include('theme-foot408::partials.edit-button',['block'=>$hero])
        <div class="f408-slider" data-f408-slider>
            @forelse($slides as $index=>$slide)
                <article class="f408-slide {{ $index===0?'is-active':'' }}" data-f408-slide style="--hero-image:url('{{ data_get($slide,'image','/theme-demo/ec903/deal-restaurant.webp') }}')"><div data-f408-reveal><small>{{ data_get($slide,'kicker',data_get($hero,'data.subtitle')) }}</small><h1>{{ data_get($slide,'title',data_get($hero,'data.title')) }}</h1><p>{{ data_get($slide,'summary',data_get($hero,'data.description')) }}</p><a href="{{ data_get($slide,'link_url','#thuc-don') }}">{{ data_get($slide,'button_label',data_get($hero,'data.button_label','Xem thực đơn')) }}</a></div></article>
            @empty
                <article class="f408-slide is-active" data-f408-slide style="--hero-image:url('/theme-demo/ec903/deal-restaurant.webp')"><div><h1>{{ data_get($hero,'data.title','Bữa ngon thêm gắn kết') }}</h1></div></article>
            @endforelse
            @if($slides->count()>1)<button type="button" data-f408-prev><i class="fa-solid fa-chevron-left"></i></button><button type="button" data-f408-next><i class="fa-solid fa-chevron-right"></i></button>@endif
        </div>
    </section>
    <section class="f408-section f408-welcome xd-landing-block" id="gioi-thieu" data-landing-block-id="{{ data_get($welcome,'id') }}" data-block-type="foot408_welcome">
        @include('theme-foot408::partials.edit-button',['block'=>$welcome])
        <div class="f408-container f408-welcome__grid"><div data-f408-reveal><small>{{ data_get($welcome,'data.subtitle') }}</small><h2>{{ data_get($welcome,'data.title','Chào mừng đến với nhà hàng') }}</h2><h3>{{ data_get($welcome,'data.description') }}</h3><p>Không gian gần gũi, phong cách phục vụ tận tâm và thực đơn linh hoạt cho mọi cuộc hẹn.</p><a href="{{ route('site.contact') }}">{{ data_get($welcome,'data.button_label','Tìm hiểu thêm') }}</a></div><div class="f408-welcome__cards">@foreach($items($welcome) as $item)<a href="{{ data_get($item,'url','#thuc-don') }}" data-f408-reveal><img src="{{ data_get($item,'image') }}" alt="{{ data_get($item,'title') }}"><strong>{{ data_get($item,'title') }}</strong></a>@endforeach</div></div>
    </section>
    <section class="f408-section f408-menu-section xd-landing-block" id="thuc-don" data-landing-block-id="{{ data_get($menu,'id') }}" data-block-type="foot408_menu_products">
        @include('theme-foot408::partials.edit-button',['block'=>$menu])
        <div class="f408-container"><header class="f408-heading" data-f408-reveal><small>{{ data_get($menu,'data.subtitle') }}</small><h2>{{ data_get($menu,'data.title','Thực đơn') }}</h2><p>{{ data_get($menu,'data.description') }}</p></header><div class="f408-tabs"><button class="is-active">Tất cả</button><button>Món chính</button><button>Món ăn kèm</button><button>Thức uống</button><button>Tráng miệng</button></div><div class="f408-products">@foreach($items($menu) as $item)@include('theme-foot408::partials.product-card',['item'=>$item])@endforeach</div></div>
    </section>
    <section class="f408-combos xd-landing-block" id="combo" data-landing-block-id="{{ data_get($combos,'id') }}" data-block-type="foot408_combo_mosaic">
        @include('theme-foot408::partials.edit-button',['block'=>$combos])
        <div class="f408-combo-grid">@foreach($items($combos) as $index=>$item)<article class="f408-combo f408-combo--{{ $index+1 }}" style="--combo-image:url('{{ data_get($item,'image') }}')" data-f408-reveal><div><small>{{ data_get($item,'subtitle') }}</small><h2>{{ data_get($item,'title') }}</h2><p>{{ data_get($item,'summary') }}</p><a href="{{ data_get($item,'url','#thuc-don') }}">Xem ngay</a></div></article>@endforeach</div>
    </section>
    <section class="f408-testimonials xd-landing-block" id="phan-hoi" data-landing-block-id="{{ data_get($testimonials,'id') }}" data-block-type="foot408_testimonials" style="--reviews-bg:url('{{ data_get($testimonials,'settings.background_image','/theme-demo/ec903/food-grill.webp') }}')">
        @include('theme-foot408::partials.edit-button',['block'=>$testimonials])
        <div class="f408-container"><header class="f408-heading f408-heading--light" data-f408-reveal><h2>{{ data_get($testimonials,'data.title','Phản hồi của khách hàng') }}</h2></header><div class="f408-reviews">@foreach($items($testimonials) as $item)<article data-f408-reveal><img src="{{ data_get($item,'image','/theme-demo/ec903/food-source.png') }}" alt="{{ data_get($item,'name') }}"><div><p>{{ data_get($item,'quote',data_get($item,'summary')) }}</p><strong>{{ data_get($item,'name',data_get($item,'title')) }}</strong><small>{{ data_get($item,'role') }}</small></div></article>@endforeach</div></div>
    </section>
    <section class="f408-section xd-landing-block" id="tin-tuc" data-landing-block-id="{{ data_get($posts,'id') }}" data-block-type="foot408_blog_posts">
        @include('theme-foot408::partials.edit-button',['block'=>$posts])
        <div class="f408-container"><header class="f408-heading" data-f408-reveal><h2>{{ data_get($posts,'data.title','Blog - Tin tức') }}</h2><p>{{ data_get($posts,'data.description') }}</p></header><div class="f408-posts">@foreach($items($posts) as $item)<article data-f408-reveal><a href="{{ data_get($item,'url','#') }}"><img src="{{ data_get($item,'image','/theme-demo/ec903/food-brunch.webp') }}" alt="{{ data_get($item,'title') }}"></a><small><i class="fa-regular fa-calendar"></i> {{ data_get($item,'published_at',data_get($item,'date')) }}</small><h3><a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'title') }}</a></h3><p>{{ data_get($item,'summary',data_get($item,'excerpt')) }}</p></article>@endforeach</div></div>
    </section>
</main>
@endsection
