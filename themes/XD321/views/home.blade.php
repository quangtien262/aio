@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = [];
    $block = fn ($type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = fn ($value) => collect($value['dynamic_items'] ?? [])->filter()->isNotEmpty() ? collect($value['dynamic_items'])->filter()->values()->all() : collect(data_get($value, 'data.content.items', []))->filter()->values()->all();
    $hero=$block('hero_slider'); $about=$block('about_experience'); $services=$block('content_mosaic'); $solutions=$block('project_gallery'); $process=$block('process_steps'); $products=$block('featured_products'); $testimonials=$block('testimonials'); $partners=$block('partner_logos'); $news=$block('bizmax_latest_posts');
    $slides=collect(data_get($hero,'data.content.slides',[]))->filter()->values(); if($slides->isEmpty()) $slides=collect([['title'=>'Nhanh chóng - An toàn cùng XD321 Cargo','summary'=>'Tối ưu mọi hành trình vận chuyển từ nội địa đến quốc tế.','image'=>'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=2200&q=90','button_label'=>'Xem thêm','link_url'=>'#dich-vu']]);
    $productItems = $items($products);
    $testimonialItems = $items($testimonials);
    $partnerItems = $items($partners);
    $short=fn($value,$limit=130)=>\Illuminate\Support\Str::limit(strip_tags((string)$value),$limit);
@endphp
@extends('theme-xd321::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'XD321 Cargo')))
@section('content')
<main class="x321-main">
<section class="x321-hero">@foreach($slides as $index=>$slide)<article class="x321-hero__slide {{ $index===0?'is-active':'' }}" data-x321-slide><img src="{{ data_get($slide,'image') }}" alt="{{ data_get($slide,'title') }}"><div></div><div class="x321-container"><p>{{ data_get($hero,'data.subtitle','XD321 Cargo') }}</p><h1>{{ data_get($slide,'title') }}</h1><span>{{ data_get($slide,'summary') }}</span><a href="{{ data_get($slide,'link_url','#dich-vu') }}" class="x321-button">{{ data_get($slide,'button_label','Xem thêm') }} <b>→</b></a></div></article>@endforeach</section>
<section id="gioi-thieu" class="x321-section" data-x321-reveal><div class="x321-container"><header class="x321-head"><h2>{{ data_get($about,'data.title','Giới thiệu về XD321 Cargo') }}</h2><p>{{ data_get($about,'data.description','Giải pháp vận chuyển an toàn, nhanh chóng và hiệu quả.') }}</p></header><div class="x321-about"><div><p>{{ data_get($about,'data.description','') }}</p><img src="{{ data_get($about,'media.image','https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1000&q=85') }}" alt="{{ data_get($about,'data.title') }}"></div><article><img src="https://images.unsplash.com/photo-1494412651409-8963ce7935a7?auto=format&fit=crop&w=1000&q=85" alt="Logistics"><h3>{{ data_get($about,'data.content.years','15+') }} năm kinh nghiệm</h3><p>Kết nối hàng hóa toàn cầu bằng dịch vụ linh hoạt, minh bạch và đáng tin cậy.</p><a class="x321-button" href="#lien-he">Tư vấn ngay →</a></article></div></div></section>
<section id="dich-vu" class="x321-section x321-services" data-x321-reveal><div class="x321-container"><header class="x321-head"><h2>{{ data_get($services,'data.title','Dịch vụ vận chuyển') }}</h2><p>{{ data_get($services,'data.description','Kết nối vận tải toàn cầu, tối ưu chuỗi cung ứng và đảm bảo giao hàng đúng hẹn.') }}</p></header><div class="x321-cards">@forelse($items($services) as $item)<article><img src="{{ data_get($item,'image','https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item,'title') }}"><div><h3>{{ data_get($item,'title','Dịch vụ logistics') }}</h3><p>{{ $short(data_get($item,'summary',data_get($item,'description','')),120) }}</p><a href="{{ data_get($item,'url','#') }}">Xem chi tiết →</a></div></article>@empty <p>Đang cập nhật dịch vụ.</p>@endforelse</div></div></section>
<section id="giai-phap" class="x321-section" data-x321-reveal><div class="x321-container"><header class="x321-head"><h2>{{ data_get($solutions,'data.title','Giải pháp logistics hiện đại') }}</h2><p>{{ data_get($solutions,'data.description','Giải pháp linh hoạt cho kho bãi, đơn hàng và chuỗi cung ứng.') }}</p></header><div class="x321-solution-grid">@forelse($items($solutions) as $index=>$item)<article><img src="{{ data_get($item,'image','https://images.unsplash.com/photo-1586528116493-da8b8d5a6c44?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item,'title') }}"><span>0{{ $index+1 }}</span><h3>{{ data_get($item,'title','Giải pháp logistics') }}</h3><p>{{ $short(data_get($item,'summary',data_get($item,'description','')),120) }}</p><a href="{{ data_get($item,'url','#') }}">Xem chi tiết →</a></article>@empty <p>Đang cập nhật giải pháp.</p>@endforelse</div></div></section>
<section id="quy-trinh" class="x321-process" data-x321-reveal><div class="x321-container"><header><p>{{ data_get($process,'data.subtitle','Quy trình làm việc') }}</p><h2>{{ data_get($process,'data.title','Quy trình vận hành logistics') }}</h2><span>{{ data_get($process,'data.description','') }}</span><a class="x321-button" href="#lien-he">{{ data_get($process,'data.button_label','Liên hệ ngay') }}</a></header><div class="x321-process__steps">@foreach(data_get($process,'data.content.items',[]) as $index=>$item)<article><b>0{{ $index+1 }}</b><section><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p></section></article>@endforeach</div></div></section>
<section id="san-pham" class="x321-section x321-products" data-x321-reveal>
    <div class="x321-container">
        <header class="x321-head"><h2 id="x321-products-title">{{ data_get($products,'data.title','Sản phẩm hỗ trợ logistics') }}</h2><p>{{ data_get($products,'data.description','Vật tư đóng gói, seal niêm phong và thiết bị hỗ trợ vận chuyển.') }}</p></header>
        <div class="x321-carousel x321-carousel--products" data-x321-carousel role="region" aria-labelledby="x321-products-title">
            <div id="x321-products-track" class="x321-carousel__track" data-carousel-track tabindex="0" aria-labelledby="x321-products-title">
                @forelse($productItems as $item)
                    <article class="x321-product-card" data-carousel-item>
                        <a href="{{ data_get($item,'url','#') }}">
                            <div class="x321-product-card__image"><img loading="lazy" src="{{ data_get($item,'image') }}" alt="{{ data_get($item,'alt',data_get($item,'title')) }}"></div>
                            <div class="x321-product-card__body">
                                <h3>{{ data_get($item,'title','Sản phẩm đóng gói') }}</h3>
                                <strong>@if(is_numeric(data_get($item,'price'))){{ number_format((float) data_get($item,'price'), 0, ',', '.').' ₫' }}@elseif(filled(data_get($item,'price_formatted',data_get($item,'price')))){{ data_get($item,'price_formatted',data_get($item,'price')) }}@else @themeT('xd321.products.contact') @endif</strong>
                                <span>@themeT('xd321.carousel.details') <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                            </div>
                        </a>
                    </article>
                @empty
                    <p>@themeT('xd321.products.empty')</p>
                @endforelse
            </div>
            @include('theme-xd321::partials.carousel-controls', ['carouselId' => 'x321-products-track'])
        </div>
    </div>
</section>
@if(count($testimonialItems))
<section id="chia-se" class="x321-testimonial" data-x321-reveal>
    <div class="x321-container">
        <header class="x321-head"><h2 id="x321-testimonials-title">{{ data_get($testimonials,'data.title','Khách hàng nói gì về XD321 Cargo') }}</h2><p>{{ data_get($testimonials,'data.subtitle','Phản hồi từ đối tác') }}</p></header>
        <div class="x321-carousel x321-carousel--testimonials" data-x321-carousel role="region" aria-labelledby="x321-testimonials-title">
            <div id="x321-testimonials-track" class="x321-carousel__track" data-carousel-track tabindex="0" aria-labelledby="x321-testimonials-title">
                @foreach($testimonialItems as $item)
                    <article class="x321-quote-card" data-carousel-item>
                        <i class="fa-solid fa-quote-left x321-quote-card__mark" aria-hidden="true"></i>
                        <blockquote>{{ data_get($item,'quote',data_get($item,'summary')) }}</blockquote>
                        <div class="x321-quote-card__person">
                            @if(filled(data_get($item,'image')))<img loading="lazy" src="{{ data_get($item,'image') }}" alt="{{ data_get($item,'alt',data_get($item,'name',data_get($item,'title'))) }}">@endif
                            <div><h3>{{ data_get($item,'name',data_get($item,'title')) }}</h3><p>{{ data_get($item,'role') }}</p>@if(filled(data_get($item,'company')))<small>{{ data_get($item,'company') }}</small>@endif</div>
                        </div>
                    </article>
                @endforeach
            </div>
            @include('theme-xd321::partials.carousel-controls', ['carouselId' => 'x321-testimonials-track'])
        </div>
    </div>
</section>
@endif
@if(count($partnerItems))
<section id="doi-tac" class="x321-partners" data-x321-reveal>
    <div class="x321-container">
        <header class="x321-head"><h2 id="x321-partners-title">{{ data_get($partners,'data.title','Mạng lưới đối tác toàn cầu') }}</h2></header>
        <div class="x321-carousel x321-carousel--partners" data-x321-carousel role="region" aria-labelledby="x321-partners-title">
            <div id="x321-partners-track" class="x321-carousel__track" data-carousel-track tabindex="0" aria-labelledby="x321-partners-title">
                @foreach($partnerItems as $partner)
                    <a class="x321-partner-card" data-carousel-item href="{{ data_get($partner,'url','#') }}" aria-label="{{ data_get($partner,'title') }}">
                        @if(filled(data_get($partner,'image')))<img loading="lazy" src="{{ data_get($partner,'image') }}" alt="{{ data_get($partner,'alt',data_get($partner,'title')) }}">@else<span>{{ data_get($partner,'title') }}</span>@endif
                    </a>
                @endforeach
            </div>
            @include('theme-xd321::partials.carousel-controls', ['carouselId' => 'x321-partners-track'])
        </div>
    </div>
</section>
@endif
<section id="tin-tuc" class="x321-section" data-x321-reveal><div class="x321-container"><header class="x321-head"><h2>{{ data_get($news,'data.title','Cập nhật tin tức logistics') }}</h2><p>{{ data_get($news,'data.subtitle','Kiến thức và thị trường') }}</p></header><div class="x321-news">@forelse($items($news) as $item)<article><a href="{{ data_get($item,'url','#') }}"><img src="{{ data_get($item,'image','https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item,'title') }}"><div><small>{{ data_get($item,'published_at',data_get($item,'meta','XD321 Cargo')) }}</small><h3>{{ data_get($item,'title','Tin tức logistics') }}</h3><p>{{ $short(data_get($item,'summary',data_get($item,'description','')),120) }}</p></div></a></article>@empty <p>Đang cập nhật tin tức.</p>@endforelse</div></div></section>
</main>
@endsection
