@extends('theme-ca0050::layout')
@section('title',data_get($landingPage??[],'meta_title',data_get($siteProfile??[],'site_name','Sudes Aquarium')))
@section('content')
@php
$blocks=collect($landingBlocks??[])->values();
$get=fn(string $type):array=>(array)($blocks->firstWhere('block_type',$type)??[]);
$items=fn(array $block)=>collect($block['dynamic_items']??[])->filter()->values()->whenEmpty(fn($list)=>$list->push(...collect(data_get($block,'data.content.items',[]))->filter()->values()->all()));
$image=fn($item,string $fallback)=>data_get($item,'image',data_get($item,'image_url',$fallback));
$hero=$get('hero_slider');$categories=$get('featured_categories');$about=$get('ca0050_about');$fish=$get('ca0050_fish_products');$tiktok=$get('ca0050_tiktok');$setup=$get('ca0050_setup');$accessories=$get('ca0050_accessories');$testimonials=$get('testimonials');$news=$get('latest_posts');$faq=$get('ca0050_faq');$partners=$get('partner_logos');$footerCta=$get('ca0050_footer');
$slides=collect($hero['dynamic_items']??[])->filter()->values();if($slides->isEmpty())$slides=collect(data_get($hero,'data.content.slides',[]))->filter()->values();
$fallbackProducts=collect([
 ['title'=>'Cá Ba Đuôi Oranda Đuôi Lụa','price'=>999000,'original_price'=>1200000,'image'=>'/theme-demo/ca0050/hero-goldfish.png','url'=>'#'],
 ['title'=>'Cá Ba Đuôi Lưu Kim Calico','price'=>50000,'original_price'=>69000,'image'=>'/theme-demo/ca0050/aquascape.png','url'=>'#'],
 ['title'=>'Cá Bảy Màu Koi Đỏ','price'=>12000,'original_price'=>15000,'image'=>'/theme-demo/ca0050/hero-goldfish.png','url'=>'#'],
 ['title'=>'Cá Bảy Màu Red Albino','price'=>8000,'original_price'=>9000,'image'=>'/theme-demo/ca0050/aquascape.png','url'=>'#'],
]);
$products=fn(array $block)=>$items($block)->whenEmpty(fn($list)=>$list->push(...$fallbackProducts->all()));
$categoryItems=$items($categories)->whenEmpty(fn($list)=>$list->push(
 ['title'=>'Cá','summary'=>'9 sản phẩm','icon'=>'fa-solid fa-fish','image'=>'/theme-demo/ca0050/hero-goldfish.png'],
 ['title'=>'Cây','summary'=>'8 sản phẩm','icon'=>'fa-solid fa-seedling','image'=>'/theme-demo/ca0050/aquascape.png'],
 ['title'=>'Đèn','summary'=>'4 sản phẩm','icon'=>'fa-regular fa-lightbulb'],
 ['title'=>'Thức ăn','summary'=>'8 sản phẩm','icon'=>'fa-solid fa-jar'],
 ['title'=>'Hồ','summary'=>'8 sản phẩm','icon'=>'fa-solid fa-water'],
 ['title'=>'Thuốc','summary'=>'0 sản phẩm','icon'=>'fa-solid fa-flask']
));
@endphp
<main class="ca50-main">
<section class="ca50-hero xd-landing-block" data-landing-block-id="{{ data_get($hero,'id') }}" data-block-type="hero_slider" data-ca50-slider>
 @include('theme-ca0050::partials.edit-button',['block'=>$hero])
 @forelse($slides as $i=>$slide)<article class="ca50-hero-slide {{ $i===0?'is-active':'' }}"><img src="{{ $image($slide,'/theme-demo/ca0050/hero-goldfish.png') }}" alt="{{ data_get($slide,'title','Sudes Aquarium') }}"></article>@empty
 <article class="ca50-hero-slide is-active"><img src="/theme-demo/ca0050/hero-goldfish.png" alt="Cá cảnh Sudes Aquarium"></article>@endforelse
 <button data-ca50-prev class="ca50-arrow ca50-prev">←</button><button data-ca50-next class="ca50-arrow ca50-next">→</button><div class="ca50-wave"></div>
</section>

<section class="ca50-categories xd-landing-block" data-landing-block-id="{{ data_get($categories,'id') }}" data-block-type="featured_categories">
 @include('theme-ca0050::partials.edit-button',['block'=>$categories])
 <div class="ca50-wrap"><h2>{{ data_get($categories,'data.title','KHÁM PHÁ SUDES AQUARIUM') }}</h2><div class="ca50-category-grid">@foreach($categoryItems->take(6) as $item)<article><a href="{{ data_get($item,'url','#the-gioi-ca-canh') }}"><div class="ca50-category-image">@if($image($item,''))<img src="{{ $image($item,'') }}" alt="{{ data_get($item,'title') }}">@else<i class="{{ data_get($item,'icon','fa-solid fa-fish') }}"></i>@endif</div><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p></a></article>@endforeach</div></div>
</section>

<section class="ca50-about xd-landing-block" data-landing-block-id="{{ data_get($about,'id') }}" data-block-type="ca0050_about">
 @include('theme-ca0050::partials.edit-button',['block'=>$about])
 <div class="ca50-about-copy"><h2>GIỚI THIỆU VỀ <b>SUDES AQUARIUM</b></h2><p>{{ data_get($about,'data.description','Sudes Aquarium là thế giới thu nhỏ dành cho những ai yêu thích vẻ đẹp của thủy sinh và cá cảnh. Chúng tôi cung cấp đa dạng cá cảnh, cây thủy sinh, bể cá và phụ kiện chuyên dụng.') }}</p><p>{{ data_get($about,'data.subtitle','Với niềm đam mê và kinh nghiệm, Sudes cam kết sản phẩm chất lượng, dịch vụ tận tâm và tư vấn chuyên nghiệp.') }}</p><a href="#lien-he">Xem chi tiết →</a><div class="ca50-stats">@foreach($items($about)->take(4) as $item)<span><i class="{{ data_get($item,'icon','fa-solid fa-fish') }}"></i><strong>{{ data_get($item,'value',data_get($item,'title')) }}</strong><small>{{ data_get($item,'summary') }}</small></span>@endforeach</div></div><div class="ca50-about-image"><img src="{{ data_get($about,'media.image','/theme-demo/ca0050/aquascape.png') }}" alt="Sudes Aquarium"></div>
</section>

<section id="the-gioi-ca-canh" class="ca50-products xd-landing-block" data-landing-block-id="{{ data_get($fish,'id') }}" data-block-type="ca0050_fish_products">
 @include('theme-ca0050::partials.edit-button',['block'=>$fish])
 <div class="ca50-wrap"><h2>{{ data_get($fish,'data.title','THẾ GIỚI CÁ CẢNH') }}</h2><div class="ca50-tabs"><button class="is-active">Cá Thủy Sinh</button><button>Cá Ba Đuôi</button><button>Cá Beta</button><button>Cá Koi</button></div><div class="ca50-product-grid">@foreach($products($fish)->take(8) as $item)@include('theme-ca0050::partials.product-card',['item'=>$item])@endforeach</div></div>
</section>

<section class="ca50-tiktok xd-landing-block" data-landing-block-id="{{ data_get($tiktok,'id') }}" data-block-type="ca0050_tiktok">
 @include('theme-ca0050::partials.edit-button',['block'=>$tiktok])
 <div class="ca50-wrap"><div><h2>{{ data_get($tiktok,'data.title','SUDES AQUARIUM ON TIKTOK') }}</h2><p>{{ data_get($tiktok,'data.description','Nếu bạn yêu thích thế giới thủy sinh, muốn tìm một góc nhỏ để thư giãn giữa nhịp sống bận rộn, TikTok của Sudes Aquarium chính là nơi dành cho bạn!') }}</p><a href="#">Theo dõi ngay →</a></div><div class="ca50-social-mock"><header><span>◉</span><b>sudesaquarium</b><small>63.7K Follower · 769.8K Thích</small></header><div>@foreach(range(1,4) as $i)<img src="/theme-demo/ca0050/aquascape.png" alt="Video thủy sinh">@endforeach</div><footer>TikTok <button>Mở TikTok</button></footer></div></div>
</section>

<section class="ca50-setup xd-landing-block" data-landing-block-id="{{ data_get($setup,'id') }}" data-block-type="ca0050_setup">
 @include('theme-ca0050::partials.edit-button',['block'=>$setup])
 <div class="ca50-wrap"><h2>{{ data_get($setup,'data.title','SETUP BỂ CÁ THỦY SINH') }}</h2><div class="ca50-setup-grid"><div>@foreach($items($setup)->take(3) as $item)<article><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p><i class="{{ data_get($item,'icon','fa-solid fa-pen-ruler') }}"></i></article>@endforeach</div><img src="{{ data_get($setup,'media.image','/theme-demo/ca0050/aquascape.png') }}" alt="Setup bể cá"><div>@foreach($items($setup)->skip(3)->take(3) as $item)<article><i class="{{ data_get($item,'icon','fa-solid fa-gears') }}"></i><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p></article>@endforeach</div></div><div class="ca50-setup-cta">☎ Hotline tư vấn <b>0399162342</b> · Xem chi tiết <b>DỊCH VỤ SET UP</b></div></div>
</section>

<section class="ca50-products ca50-accessories xd-landing-block" data-landing-block-id="{{ data_get($accessories,'id') }}" data-block-type="ca0050_accessories">
 @include('theme-ca0050::partials.edit-button',['block'=>$accessories])
 <div class="ca50-wrap"><h2>{{ data_get($accessories,'data.title','HỒ VÀ PHỤ KIỆN') }}</h2><div class="ca50-product-grid">@foreach($products($accessories)->take(4) as $item)@include('theme-ca0050::partials.product-card',['item'=>$item])@endforeach</div></div>
</section>

<section class="ca50-review xd-landing-block" data-landing-block-id="{{ data_get($testimonials,'id') }}" data-block-type="testimonials">
 @include('theme-ca0050::partials.edit-button',['block'=>$testimonials])
 <div class="ca50-review-image"><img src="/theme-demo/ca0050/aquascape.png" alt="Hồ thủy sinh"></div><div class="ca50-review-copy"><h2>{{ data_get($testimonials,'data.title','REVIEW CÓNG TÂM') }}</h2><b>”</b>@php($review=$items($testimonials)->first())<p>{{ data_get($review,'quote',data_get($review,'summary','Cá khỏe, màu đẹp và thích nghi rất nhanh. Shop tư vấn kỹ cách nuôi nên mình mới chơi mà vẫn nuôi ổn.')) }}</p><strong>{{ data_get($review,'name','Ngọc Vy') }}</strong><small>{{ data_get($review,'role','Kế toán') }}</small></div>
</section>

<section class="ca50-news xd-landing-block" data-landing-block-id="{{ data_get($news,'id') }}" data-block-type="latest_posts">
 @include('theme-ca0050::partials.edit-button',['block'=>$news])
 <div class="ca50-wrap"><h2>{{ data_get($news,'data.title','TIN TỨC MỚI NHẤT TỪ SUDES CRAFT') }}</h2><div class="ca50-news-grid">@foreach($items($news)->take(4) as $item)<article><a href="{{ data_get($item,'url','#') }}"><img src="{{ $image($item,'/theme-demo/ca0050/aquascape.png') }}" alt="{{ data_get($item,'title') }}"><h3>{{ data_get($item,'title') }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)data_get($item,'summary')),135) }}</p><em>Đọc thêm →</em></a></article>@endforeach</div></div>
</section>

<section class="ca50-faq xd-landing-block" data-landing-block-id="{{ data_get($faq,'id') }}" data-block-type="ca0050_faq">
 @include('theme-ca0050::partials.edit-button',['block'=>$faq])
 <div class="ca50-faq-images"><img src="/theme-demo/ca0050/hero-goldfish.png" alt="Cá cảnh"><img src="/theme-demo/ca0050/aquascape.png" alt="Thủy sinh"></div><div><h2>SUDES AQUARIUM<br><b>{{ data_get($faq,'data.title','GIẢI ĐÁP THẮC MẮC') }}</b></h2><p>{{ data_get($faq,'data.description','Có thắc mắc về cách nuôi cá, chăm bể hay chọn phụ kiện thủy sinh? Sudes Aquarium luôn sẵn sàng hỗ trợ bạn mọi lúc!') }}</p><div class="ca50-accordion">@foreach($items($faq)->take(5) as $i=>$item)<article class="{{ $i===0?'is-open':'' }}"><button>{{ $i+1 }}. {{ data_get($item,'title') }} <span>⌄</span></button><p>{{ data_get($item,'summary') }}</p></article>@endforeach</div></div>
</section>

<section class="ca50-partners xd-landing-block" data-landing-block-id="{{ data_get($partners,'id') }}" data-block-type="partner_logos">
 @include('theme-ca0050::partials.edit-button',['block'=>$partners])
 <div class="ca50-wrap"><h2>{{ data_get($partners,'data.title','ĐỐI TÁC CỦA SUDES AQUARIUM') }}</h2><div>@foreach($items($partners)->take(6) as $item)<a href="{{ data_get($item,'url','#') }}">@if($image($item,''))<img src="{{ $image($item,'') }}" alt="{{ data_get($item,'title') }}">@else<strong>{{ data_get($item,'title','SUDES PARTNER') }}</strong>@endif</a>@endforeach</div></div>
</section>
</main>
@endsection
