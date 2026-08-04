@php
    $blocks = collect($landingBlocks ?? []);
    $items = function (array $block) { $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values(); return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values(); };
    $image = fn ($item, $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $branding = (array) data_get($themeHomeData ?? [], 'branding', data_get($siteProfile ?? [], 'branding', []));
    $hotline = trim((string) data_get($branding, 'support_hotline', ''));
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) $locale['code'], 'label' => (string) (($locale['native_name'] ?? null) ?: $locale['name'])])->values()->all() : [];
@endphp
@extends('theme-dl750::layout')
@section('content')
<main>
@foreach($blocks as $block)
    @php($type = data_get($block, 'block_type'))
    @if($type === 'hero_slider')
        @php($slides = $items($block))
        <section class="dl-hero xd-landing-block" data-block-type="{{ $type }}" data-dl-slider data-delay="{{ data_get($block, 'settings.autoplay_ms', 5600) }}">
            @foreach($slides as $slide)<article class="dl-slide {{ $loop->first ? 'is-active' : '' }}" style="background-image:linear-gradient(90deg,rgba(4,16,9,.84),rgba(4,16,9,.18)),url('{{ $image($slide, data_get($block, 'media.image')) }}')"><div class="dl-wrap dl-hero-copy"><p>{{ data_get($slide, 'subtitle', data_get($block, 'data.subtitle')) }}</p><h1>{{ data_get($slide, 'title', data_get($block, 'data.title')) }}</h1><span>{{ data_get($slide, 'summary', data_get($block, 'data.description')) }}</span><a href="{{ data_get($slide, 'link_url', '#dich-vu') }}"><i class="fa-regular fa-circle-play"></i>{{ data_get($slide, 'button_label', data_get($block, 'data.button_label')) }}</a></div></article>@endforeach
            @if($slides->count() > 1)<button class="dl-arrow prev" data-dl-prev aria-label="Trước"><i class="fa-solid fa-chevron-left"></i></button><button class="dl-arrow next" data-dl-next aria-label="Sau"><i class="fa-solid fa-chevron-right"></i></button>@endif
        </section>
    @elseif($type === 'dl750_categories')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block"/><div class="dl-category-grid">@foreach($items($block) as $item)<a href="{{ data_get($item, 'url', '#san-pham') }}"><span><i class="{{ data_get($item, 'icon', 'fa-solid fa-campground') }}"></i></span><b>{{ data_get($item, 'title', data_get($item, 'name')) }}</b><small>{{ data_get($item, 'summary', 'Khám phá ngay') }}</small><i class="fa-solid fa-arrow-right"></i></a>@endforeach</div></div></section>
    @elseif($type === 'dl750_about')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-about xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap dl-about-grid"><div><x-dl750-heading :block="$block" align="left"/><p class="dl-lead">{{ data_get($block, 'data.description') }}</p><div class="dl-about-note">Mỗi sản phẩm và dịch vụ đều được lựa chọn để hành trình của bạn an toàn, thoải mái và gần gũi thiên nhiên hơn.</div><div class="dl-benefits">@foreach($items($block) as $item)<span><i class="{{ data_get($item, 'icon', 'fa-solid fa-check') }}"></i>{{ data_get($item, 'title') }}</span>@endforeach</div><a class="dl-primary" href="#dich-vu">{{ data_get($block, 'data.button_label') }}</a></div><div class="dl-about-media"><img src="{{ data_get($block, 'media.image') }}" alt="{{ data_get($block, 'data.title') }}"><img src="{{ data_get($block, 'media.image_secondary') }}" alt="{{ data_get($block, 'data.subtitle') }}">@if($hotline)<a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><i class="fa-solid fa-phone"></i><span>Hotline tư vấn<b>{{ $hotline }}</b></span></a>@endif</div></div></section>
    @elseif($type === 'dl750_services')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-services xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block" light/><div class="dl-service-grid">@foreach($items($block) as $item)<a href="{{ data_get($item, 'url', '#lien-he') }}" style="background-image:linear-gradient(0deg,rgba(3,15,8,.94),rgba(3,15,8,.08)),url('{{ $image($item) }}')"><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p><span><i class="fa-solid fa-person-hiking"></i> Trải nghiệm an toàn</span></div></a>@endforeach</div></div></section>
    @elseif($type === 'dl750_reasons')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-reasons xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block"/><div class="dl-reason-grid">@foreach($items($block) as $item)<article><i class="{{ data_get($item, 'icon', 'fa-solid fa-leaf') }}"></i><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></article>@endforeach</div>@if($hotline)<div class="dl-hotline"><span>Liên hệ chúng tôi</span><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}">{{ $hotline }}</a></div>@endif</div></section>
    @elseif($type === 'dl750_products')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-products xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block"/><div class="dl-product-layout"><div class="dl-product-promo" style="background-image:linear-gradient(rgba(4,18,10,.18),rgba(4,18,10,.76)),url('{{ data_get($block, 'settings.feature_image') }}')"><b>OUTDOOR SALE</b><span>Trang bị tốt, hành trình trọn vẹn</span></div><div class="dl-product-grid">@foreach($items($block)->take(3) as $item)@include('theme-dl750::partials.product-card',['item'=>$item])@endforeach</div></div></div></section>
    @elseif($type === 'dl750_gallery')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-gallery xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block"/><div class="dl-gallery-grid">@foreach($items($block) as $item)<figure><img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}"><figcaption>{{ data_get($item, 'title') }}</figcaption></figure>@endforeach</div></div></section>
    @elseif($type === 'dl750_news')
        @php($posts = $items($block))
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-news xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block"/><div class="dl-news-grid">@if($posts->first())<article class="dl-news-main"><img src="{{ $image($posts->first()) }}" alt="{{ data_get($posts->first(), 'title') }}"><time>{{ data_get($posts->first(), 'date') }}</time><h3>{{ data_get($posts->first(), 'title') }}</h3><p>{{ data_get($posts->first(), 'summary') }}</p><a href="{{ data_get($posts->first(), 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right"></i></a></article>@endif<div class="dl-news-list">@foreach($posts->skip(1) as $post)<article><img src="{{ $image($post) }}" alt="{{ data_get($post, 'title') }}"><div><time>{{ data_get($post, 'date') }}</time><h3>{{ data_get($post, 'title') }}</h3><p>{{ data_get($post, 'summary') }}</p><a href="{{ data_get($post, 'url', '#') }}">Xem thêm <i class="fa-solid fa-arrow-right"></i></a></div></article>@endforeach</div></div></div></section>
    @elseif($type === 'dl750_faq')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-faq xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block" light/><div class="dl-faq-grid"><div class="dl-accordion">@foreach($items($block) as $item)<article class="{{ $loop->first ? 'is-open' : '' }}"><button data-dl-faq><span>{{ data_get($item, 'title') }}</span><i class="fa-solid fa-chevron-down"></i></button><div><p>{{ data_get($item, 'summary') }}</p></div></article>@endforeach</div><div class="dl-faq-media"><img src="{{ data_get($block, 'media.image') }}" alt="{{ data_get($block, 'data.title') }}"><a href="{{ route('site.contact',['locale'=>app()->getLocale()]) }}">{{ data_get($block, 'data.button_label') }}</a></div></div></div></section>
    @elseif($type === 'dl750_partners')
        <section id="{{ data_get($block, 'anchor_id') }}" class="dl-section dl-partners xd-landing-block" data-block-type="{{ $type }}"><div class="dl-wrap"><x-dl750-heading :block="$block"/><div class="dl-partner-rail">@forelse($items($block) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>@empty @foreach(['Trail Works','Wild Camp','North Peak','Outdoor Pro','Forest Gear'] as $name)<span>{{ $name }}</span>@endforeach @endforelse</div></div></section>
    @endif
@endforeach
</main>
@endsection
@if($canEditLanding)
@push('scripts')
<script>(()=>{const ids=@json($blocks->filter(fn($block)=>filled($block['id']??null))->mapWithKeys(fn($block)=>[(string)$block['block_type']=>(string)$block['id']])->all());document.querySelectorAll('.xd-landing-block[data-block-type]').forEach(section=>{const id=ids[section.dataset.blockType];if(!id)return;const button=document.createElement('button');button.type='button';button.className='xd-edit-block';button.dataset.xdEditBlock=id;button.textContent='Sửa khối';section.appendChild(button);});})();</script>
@include('theme-xd0302::partials.scripts')
@endpush
@endif
