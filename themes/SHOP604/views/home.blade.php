@extends('theme-shop604::layout')
@section('title',data_get($landingPage??[],'meta_title',data_get($siteProfile??[],'site_name','Bean Lingerie')))
@section('content')
@php
$blocks=collect($landingBlocks??[])->values();
$canEditLanding=auth('admin')->check()&&request('mod')==='admin'&&is_array($landingPage??null);
$editorLocales=collect(\App\Support\FrontendLocalization::supportedLocales())->map(fn($locale)=>['code'=>$locale,'label'=>strtoupper($locale)])->all();
$get=fn(string $type):array=>(array)($blocks->firstWhere('block_type',$type)??[]);
$items=fn(array $block)=>collect($block['dynamic_items']??[])->filter()->values()->whenEmpty(fn($list)=>$list->push(...collect(data_get($block,'data.content.items',[]))->filter()->values()->all()));
$image=fn($item,string $fallback)=>data_get($item,'image',data_get($item,'image_url',$fallback));
$hero=$get('hero_slider');$categories=$get('featured_categories');$flash=$get('shop604_flash_sale');$editorialOne=$get('shop604_editorial_one');$new=$get('shop604_new_arrivals');$editorialTwo=$get('shop604_editorial_two');$collections=$get('shop604_collection_tabs');$lookbook=$get('shop604_lookbook');$testimonials=$get('testimonials');$partners=$get('partner_logos');$news=$get('latest_posts');$benefits=$get('shop604_benefits');$gallery=$get('shop604_gallery');$newsletter=$get('shop604_newsletter');
$slides=collect($hero['dynamic_items']??[])->filter()->values();if($slides->isEmpty())$slides=collect(data_get($hero,'data.content.slides',[]))->filter()->values();
$fallbackProducts=collect([
 ['title'=>'Áo ngực nữ thanh lịch','summary'=>'BEAN LINGERIE','price'=>235000,'original_price'=>289000,'image'=>'/theme-demo/shop604/product-women-knit.png','url'=>'#'],
 ['title'=>'Bộ nội y ren dịu dàng','summary'=>'BRA LIVE','price'=>279000,'original_price'=>399000,'image'=>'/theme-demo/shop604/product-women-rose.png','url'=>'#'],
 ['title'=>'Bralette mềm mại','summary'=>'BEAN PUSH','price'=>169000,'original_price'=>199000,'image'=>'/theme-demo/shop604/product-men-green.png','url'=>'#'],
 ['title'=>'Bikini hiện đại','summary'=>'BEAN BIKINI','price'=>439000,'original_price'=>559000,'image'=>'/theme-demo/shop604/ad-lac-quan.png','url'=>'#'],
]);
$productItems=fn(array $block)=>$items($block)->whenEmpty(fn($list)=>$list->push(...$fallbackProducts->all()));
$defaultCategoryItems=[
 ['title'=>'Áo ngực','summary'=>'Thiết kế tôn dáng, mềm mại và nâng đỡ tự nhiên.','image'=>'/theme-demo/shop604/product-women-knit.png','url'=>'#san-pham-moi'],
 ['title'=>'Quần lót','summary'=>'Thoải mái trong từng khoảnh khắc thường ngày.','image'=>'/theme-demo/shop604/product-women-rose.png','url'=>'#san-pham-moi'],
 ['title'=>'Bikini','summary'=>'Tự tin khoe trọn vẻ đẹp quyến rũ và năng động.','image'=>'/theme-demo/shop604/ad-lac-quan.png','url'=>'#bo-suu-tap'],
];
$categoryItems=$items($categories)->whenEmpty(fn($list)=>$list->push(...$defaultCategoryItems));
$galleryItems=$items($gallery)->whenEmpty(fn($list)=>$list->push(...$fallbackProducts->all()));
@endphp
<main class="s604-main">
<section class="s604-hero xd-landing-block" data-landing-block-id="{{ data_get($hero,'id') }}" data-block-type="hero_slider" data-s604-slider data-autoplay="{{ data_get($hero,'settings.autoplay_ms',5600) }}">
    @include('theme-shop604::partials.edit-button',['block'=>$hero])
    @forelse($slides as $index=>$slide)
    <article class="s604-hero-slide {{ $index===0?'is-active':'' }}" data-s604-slide>
        <img src="{{ $image($slide,'/theme-demo/shop604/hero-fashion.png') }}" alt="{{ data_get($slide,'title') }}">
        <div class="s604-hero-shade"></div><div class="s604-hero-copy"><p>{{ data_get($slide,'kicker',data_get($hero,'data.subtitle','Bộ Sưu Tập Mới')) }}</p><h1>{{ data_get($slide,'title',data_get($hero,'data.title','Lingerie Bras')) }}</h1><span>{{ data_get($slide,'summary',data_get($hero,'data.description','Tôn lên vẻ đẹp nữ tính đầy tinh tế.')) }}</span><a href="{{ data_get($slide,'link_url','#san-pham-moi') }}">{{ data_get($slide,'button_label',data_get($hero,'data.button_label','Mua Ngay')) }}</a></div>
    </article>
    @empty
    <article class="s604-hero-slide is-active" data-s604-slide><img src="/theme-demo/shop604/hero-fashion.png" alt="Bean Lingerie"><div class="s604-hero-shade"></div><div class="s604-hero-copy"><p>Bộ Sưu Tập Mới</p><h1>Lingerie Bras</h1><span>Thiết kế hoàn hảo cho vẻ đẹp nữ tính đầy tinh tế.</span><a href="#san-pham-moi">Mua Ngay</a></div></article>
    @endforelse
    <button class="s604-arrow s604-prev" data-s604-prev aria-label="Trước"><i class="fa-solid fa-arrow-left"></i></button><button class="s604-arrow s604-next" data-s604-next aria-label="Sau"><i class="fa-solid fa-arrow-right"></i></button>
</section>

<section id="{{ data_get($categories,'anchor_id','danh-muc') }}" class="s604-category-section xd-landing-block" data-landing-block-id="{{ data_get($categories,'id') }}" data-block-type="featured_categories">
    @include('theme-shop604::partials.edit-button',['block'=>$categories])
    <div class="s604-wrap"><header class="s604-section-head s604-light"><h2>{{ data_get($categories,'data.title','Danh Mục Sản Phẩm') }}</h2><span>← &nbsp; →</span></header><div class="s604-category-grid">@foreach($categoryItems->take(3) as $item)<article><a href="{{ data_get($item,'url','#') }}"><img src="{{ $image($item,'/theme-demo/shop604/product-women-knit.png') }}" alt="{{ data_get($item,'title') }}"></a><h3>{{ data_get($item,'title') }}</h3><p>{{ \Illuminate\Support\Str::limit((string)data_get($item,'summary'),110) }}</p><a class="s604-underlink" href="{{ data_get($item,'url','#') }}">@themeT('SHOP604.common.read_more','Xem chi tiết')</a></article>@endforeach</div></div>
</section>

<section id="{{ data_get($flash,'anchor_id','flash-sale') }}" class="s604-products-section s604-blush xd-landing-block" data-landing-block-id="{{ data_get($flash,'id') }}" data-block-type="shop604_flash_sale">
    @include('theme-shop604::partials.edit-button',['block'=>$flash])
    <div class="s604-wrap"><header class="s604-sale-head"><div><p>⚡ {{ data_get($flash,'data.subtitle','ƯU ĐÃI CHỚP NHOÁNG') }}</p><h2>Flash <em>Sale</em></h2></div><div class="s604-countdown"><span>62 Ngày</span><span>12</span><span>46</span><span>29</span></div></header><div class="s604-product-grid">@foreach($productItems($flash)->take(4) as $item)@include('theme-shop604::partials.product-card',['item'=>$item])@endforeach</div></div>
</section>

<section id="{{ data_get($editorialOne,'anchor_id','phong-cach') }}" class="s604-editorial xd-landing-block" data-landing-block-id="{{ data_get($editorialOne,'id') }}" data-block-type="shop604_editorial_one">
    @include('theme-shop604::partials.edit-button',['block'=>$editorialOne])
    <div class="s604-editorial-copy"><p>{{ data_get($editorialOne,'data.subtitle','Gợi Cảm Cuốn Hút') }}</p><h2>{{ data_get($editorialOne,'data.title','Nét Quyến Rũ Dịu Dàng') }}</h2><i>〰</i><span>{{ data_get($editorialOne,'data.description','Thiết kế hoàn hảo cho những đường cắt tinh tế, tôn lên vẻ đẹp nữ tính đầy quyến rũ và thanh lịch.') }}</span><a href="{{ data_get($editorialOne,'settings.cta_url','#san-pham-moi') }}">{{ data_get($editorialOne,'data.button_label','Mua Ngay') }}</a></div><img src="{{ data_get($editorialOne,'media.image','/theme-demo/shop604/product-women-rose.png') }}" alt="{{ data_get($editorialOne,'data.title') }}">
</section>

<section id="{{ data_get($new,'anchor_id','san-pham-moi') }}" class="s604-products-section xd-landing-block" data-landing-block-id="{{ data_get($new,'id') }}" data-block-type="shop604_new_arrivals">
    @include('theme-shop604::partials.edit-button',['block'=>$new])
    <div class="s604-wrap"><header class="s604-section-head"><h2>{{ data_get($new,'data.title','Sản Phẩm Mới') }}</h2><span>← &nbsp; →</span></header><div class="s604-product-grid">@foreach($productItems($new)->take(4) as $item)@include('theme-shop604::partials.product-card',['item'=>$item])@endforeach</div></div>
</section>

<section class="s604-editorial s604-editorial-reverse xd-landing-block" data-landing-block-id="{{ data_get($editorialTwo,'id') }}" data-block-type="shop604_editorial_two">
    @include('theme-shop604::partials.edit-button',['block'=>$editorialTwo])
    <img src="{{ data_get($editorialTwo,'media.image','/theme-demo/shop604/product-men-green.png') }}" alt="{{ data_get($editorialTwo,'data.title') }}"><div class="s604-editorial-copy"><p>{{ data_get($editorialTwo,'data.subtitle','Thanh Lịch Hiện Đại') }}</p><h2>{{ data_get($editorialTwo,'data.title','Nét Đẹp Tự Nhiên') }}</h2><i>〰</i><span>{{ data_get($editorialTwo,'data.description','Chất liệu cao cấp mang lại cảm giác êm ái và vừa vặn, giúp bạn tự tin suốt cả ngày.') }}</span><a href="{{ data_get($editorialTwo,'settings.cta_url','#bo-suu-tap') }}">{{ data_get($editorialTwo,'data.button_label','Mua Ngay') }}</a></div>
</section>

<section id="{{ data_get($collections,'anchor_id','bo-suu-tap') }}" class="s604-products-section xd-landing-block" data-landing-block-id="{{ data_get($collections,'id') }}" data-block-type="shop604_collection_tabs">
    @include('theme-shop604::partials.edit-button',['block'=>$collections])
    <div class="s604-wrap"><div class="s604-tabs"><button class="is-active">Đồ bơi sexy</button><button>Đồ ngủ xinh</button><button>Phụ kiện</button></div><div class="s604-product-grid s604-product-grid-rows">@foreach($productItems($collections)->take(8) as $item)@include('theme-shop604::partials.product-card',['item'=>$item])@endforeach</div></div>
</section>

<section class="s604-lookbook xd-landing-block" data-landing-block-id="{{ data_get($lookbook,'id') }}" data-block-type="shop604_lookbook">
    @include('theme-shop604::partials.edit-button',['block'=>$lookbook])
    <img src="{{ data_get($lookbook,'media.image','/theme-demo/shop604/hero-fashion.png') }}" alt="{{ data_get($lookbook,'data.title','Lookbook') }}"><span style="left:11%;top:33%"></span><span style="left:40%;top:55%"></span><span style="left:50%;top:73%"></span>
</section>

<section class="s604-testimonials xd-landing-block" data-landing-block-id="{{ data_get($testimonials,'id') }}" data-block-type="testimonials">
    @include('theme-shop604::partials.edit-button',['block'=>$testimonials])
    <div class="s604-wrap"><header class="s604-section-head"><h2>{{ data_get($testimonials,'data.title','Đánh Giá Khách Hàng') }}</h2><span>← &nbsp; →</span></header><div class="s604-testimonial-grid">@foreach($items($testimonials)->take(3) as $item)<article><img src="{{ $image($item,'/theme-demo/shop604/product-women-rose.png') }}" alt="{{ data_get($item,'name',data_get($item,'title')) }}"><div><b>★★★★★</b><p>{{ data_get($item,'quote',data_get($item,'summary','Sản phẩm mềm mại, vừa vặn và rất thoải mái.')) }}</p><h3>{{ data_get($item,'name',data_get($item,'title','Khách hàng Bean')) }}</h3><span>{{ data_get($item,'role','Khách hàng thân thiết') }}</span></div></article>@endforeach</div></div>
</section>

<section class="s604-partners xd-landing-block" data-landing-block-id="{{ data_get($partners,'id') }}" data-block-type="partner_logos">
    @include('theme-shop604::partials.edit-button',['block'=>$partners])
    <div class="s604-partner-track">@foreach($items($partners) as $item)<a href="{{ data_get($item,'url','#') }}">@if($image($item,''))<img src="{{ $image($item,'') }}" alt="{{ data_get($item,'title') }}">@else<strong>{{ data_get($item,'title',data_get($item,'name','BEAN')) }}</strong>@endif</a>@endforeach</div>
</section>

<section class="s604-news xd-landing-block" data-landing-block-id="{{ data_get($news,'id') }}" data-block-type="latest_posts">
    @include('theme-shop604::partials.edit-button',['block'=>$news])
    <div class="s604-wrap"><header class="s604-section-head"><h2>{{ data_get($news,'data.title','Tin Tức') }}</h2><span>← &nbsp; →</span></header><div class="s604-news-grid">@foreach($items($news)->take(4) as $item)<article><a href="{{ data_get($item,'url','#') }}"><img src="{{ $image($item,'/theme-demo/shop604/ad-lac-quan.png') }}" alt="{{ data_get($item,'title') }}"></a><h3><a href="{{ data_get($item,'url','#') }}">{{ data_get($item,'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)data_get($item,'summary')),105) }}</p><small>Đăng bởi: <b>Bean Lingerie</b></small></article>@endforeach</div></div>
</section>

<section class="s604-benefits xd-landing-block" data-landing-block-id="{{ data_get($benefits,'id') }}" data-block-type="shop604_benefits">
    @include('theme-shop604::partials.edit-button',['block'=>$benefits])
    <div class="s604-wrap">@foreach($items($benefits) as $item)<article><i class="{{ data_get($item,'icon','fa-solid fa-box') }}"></i><div><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p></div></article>@endforeach</div>
</section>

<section class="s604-gallery xd-landing-block" data-landing-block-id="{{ data_get($gallery,'id') }}" data-block-type="shop604_gallery">
    @include('theme-shop604::partials.edit-button',['block'=>$gallery])
    @foreach($galleryItems->take(4) as $item)<a href="{{ data_get($item,'url','#') }}"><img src="{{ $image($item,'/theme-demo/shop604/product-women-knit.png') }}" alt="{{ data_get($item,'title') }}"></a>@endforeach
</section>
</main>
@endsection
