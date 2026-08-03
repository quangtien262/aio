@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = [];
    $block = fn (string $type) => $blocks->firstWhere('block_type', $type) ?? [];
    $items = function (array $block): array {
        $custom = collect(data_get($block, 'data.content.items', []))->filter()->values();
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();

        return data_get($block, 'settings.source') === 'custom' || $dynamic->isEmpty()
            ? $custom->all()
            : $dynamic->all();
    };
    $hero = $block('hero_slider'); $quality = $block('featured_categories'); $about = $block('about_experience'); $feature = $block('logistics_feature_panel'); $projects = $block('content_mosaic'); $team = $block('team_members'); $partners = $block('partner_logos');
    $slides = collect(data_get($hero, 'data.content.slides', []))->whenEmpty(fn () => collect($hero['dynamic_items'] ?? []))->values();
    if ($slides->isEmpty()) $slides = collect([['title' => 'Giải pháp công nghiệp cho vận hành bền vững', 'summary' => 'Đồng hành cùng doanh nghiệp từ tư vấn kỹ thuật đến thi công và bảo trì.', 'image' => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=2200&q=90', 'button_label' => 'Nhận báo giá', 'link_url' => '#lien-he']]);
@endphp
@extends('theme-xd0320::layout')
@section('title', data_get($landingPage ?? [], 'meta_title', data_get($siteProfile ?? [], 'site_name', 'XD0320 Industrial')))
@section('content')
<main class="xd20-main">
<section class="xd20-hero">@foreach($slides as $index=>$slide)<article class="xd20-hero__slide {{ $index===0?'is-active':'' }}" data-xd20-hero-slide><img src="{{ data_get($slide,'image') }}" alt="{{ data_get($slide,'title') }}"><div></div><div class="xd20-container xd20-hero__copy"><p>{{ data_get($hero,'data.subtitle','XD0320 Industrial') }}</p><h1>{{ data_get($slide,'title') }}</h1><p>{{ data_get($slide,'summary',data_get($hero,'data.description','')) }}</p><a href="{{ data_get($slide,'url',data_get($slide,'link_url','#lien-he')) }}" class="xd20-button">{{ data_get($slide,'button_label','Nhận báo giá') }} <i class="fa-solid fa-arrow-right"></i></a></div></article>@endforeach</section>
<section id="dich-vu" class="xd20-quality"><div class="xd20-container">@foreach($items($quality) as $item)<article><i class="{{ data_get($item,'icon','fa-solid fa-gear') }}"></i><h3>{{ data_get($item,'title') }}</h3><p>{{ data_get($item,'summary') }}</p></article>@endforeach</div></section>
<section id="gioi-thieu" class="xd20-section"><div class="xd20-container xd20-about"><div class="xd20-about__image"><img src="{{ data_get($about,'media.image','https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1100&q=85') }}" alt="{{ data_get($about,'data.title') }}"><strong>{{ data_get($about,'data.content.years','20+') }}<small>{{ data_get($about,'data.content.years_label','Năm kinh nghiệm') }}</small></strong></div><div class="xd20-copy"><p>{{ data_get($about,'data.subtitle') }}</p><h2>{{ data_get($about,'data.title') }}</h2><div>{{ data_get($about,'data.description') }}</div><ul>@foreach(data_get($about,'data.content.items',[]) as $item)<li><i class="fa-regular fa-circle-check"></i>{{ data_get($item,'title') }}</li>@endforeach</ul></div></div></section>
<section class="xd20-split"><div class="xd20-split__copy"><p>{{ data_get($feature,'data.subtitle','Năng lực kỹ thuật') }}</p><h2>{{ data_get($feature,'data.title','Dịch vụ tốt nhất cho tiến bộ bền vững') }}</h2><div>{{ data_get($feature,'data.description','') }}</div><ul>@foreach(data_get($feature,'data.content.items',[]) as $item)<li><i class="fa-solid fa-check-square"></i>{{ data_get($item,'title') }}</li>@endforeach</ul></div><img src="{{ data_get($feature,'media.image','https://images.unsplash.com/photo-1516939884455-1445c8652f83?auto=format&fit=crop&w=1500&q=85') }}" alt="{{ data_get($feature,'data.title') }}"></section>
<section id="du-an" class="xd20-section xd20-section--gray"><div class="xd20-container"><header class="xd20-heading"><p>{{ data_get($projects,'data.subtitle','Dự án tiêu biểu') }}</p><h2>{{ data_get($projects,'data.title','Những công trình đã thực hiện') }}</h2><div>{{ data_get($projects,'data.description','') }}</div></header><div class="xd20-rail">@foreach($items($projects) as $item)<article><a href="{{ data_get($item,'url','#') }}"><img src="{{ data_get($item,'image','https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=900&q=85') }}" alt="{{ data_get($item,'title') }}"><h3>{{ data_get($item,'title') }}</h3></a></article>@endforeach</div></div></section>
<section id="doi-ngu" class="xd20-section"><div class="xd20-container"><header class="xd20-heading"><p>{{ data_get($team,'data.subtitle','Thành viên chuyên gia') }}</p><h2>{{ data_get($team,'data.title','Đội ngũ kỹ sư năng lực') }}</h2><div>{{ data_get($team,'data.description','') }}</div></header><div class="xd20-team">@foreach($items($team) as $member)<article><img src="{{ data_get($member,'image','https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=700&q=85') }}" alt="{{ data_get($member,'name') }}"><h3>{{ data_get($member,'name') }}</h3><p>{{ data_get($member,'role') }}</p></article>@endforeach</div></div></section>
<section class="xd20-partners"><div class="xd20-container"><h2>{{ data_get($partners,'data.title','Đối tác của chúng tôi') }}</h2><div>@foreach($items($partners) as $partner)<a href="{{ data_get($partner,'url','#') }}">@if(filled(data_get($partner,'image')))<img src="{{ data_get($partner,'image') }}" alt="{{ data_get($partner,'name',data_get($partner,'title','Đối tác')) }}">@else<strong>{{ data_get($partner,'name',data_get($partner,'title','Đối tác')) }}</strong>@endif</a>@endforeach</div></div></section>
</main>
@endsection
