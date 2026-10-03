@php
    $blocks = collect($landingBlocks ?? []);
    $items = function (array $block) { $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values(); return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', data_get($block, 'data.content.slides', [])))->filter()->values(); };
    $image = fn ($item, $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) $locale['code'], 'label' => (string) (($locale['native_name'] ?? null) ?: $locale['name'])])->values()->all() : [];
@endphp
@extends('theme-shop606::layout')
@section('content')
<main>
@foreach($blocks as $block)
    @php($type = data_get($block, 'block_type'))
    @php($blockItems = $items($block))
    @if($type === 'hero_slider')
        <section class="s606-hero xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}" data-s606-slider data-delay="{{ data_get($block, 'settings.autoplay_ms', 5600) }}">
            @foreach($blockItems as $slide)<article class="s606-slide {{ $loop->first ? 'is-active' : '' }}" style="background-image:linear-gradient(rgba(0,0,0,.18),rgba(0,0,0,.2)),url('{{ $image($slide, data_get($block, 'media.image')) }}')"><div><h1>{{ data_get($slide, 'title', data_get($block, 'data.title')) }}</h1><p>{{ data_get($slide, 'summary', data_get($block, 'data.description')) }}</p><a @if(str_contains(mb_strtolower((string) data_get($slide, 'button_label', data_get($block, 'data.button_label', ''))), 'tư vấn') || str_contains(strtolower((string) data_get($slide, 'button_label', '')), 'consult')) href="#s606-question-dialog" data-s606-consult-open aria-haspopup="dialog" aria-controls="s606-question-dialog" @else href="{{ data_get($slide, 'link_url', '#san-pham') }}" @endif>{{ data_get($slide, 'button_label', data_get($block, 'data.button_label', 'Mua ngay')) }}</a></div></article>@endforeach
            @if($blockItems->count() > 1)<button class="s606-arrow prev" data-s606-prev aria-label="Ảnh trước"><i class="fa-solid fa-chevron-left"></i></button><button class="s606-arrow next" data-s606-next aria-label="Ảnh sau"><i class="fa-solid fa-chevron-right"></i></button>@endif
        </section>
    @elseif($type === 'shop606_collections')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section s606-collections xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap"><header class="s606-collection-heading"><span>PHONG CÁCH CỦA BẠN</span><h2>{{ data_get($block, 'data.title') }}</h2><p>Khám phá những thiết kế phù hợp cho từng khoảnh khắc.</p></header><div class="s606-collection-grid">
            @foreach($blockItems as $item)
                <a href="{{ data_get($item, 'url', route('site.catalog.search')) }}"><img src="{{ $image($item, '/theme-demo/shop604/product-women-knit.png') }}" alt="{{ data_get($item, 'title') }}" loading="lazy"><div><span>{{ data_get($item, 'title', data_get($item, 'name')) }}</span><small>Khám phá bộ sưu tập <b aria-hidden="true">↗</b></small></div></a>
            @endforeach
        </div></div></section>
    @elseif($type === 'shop606_sale')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section s606-sale xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap s606-sale-layout"><div class="s606-sale-copy"><h2>{{ data_get($block, 'data.title') }}</h2><p>{{ data_get($block, 'data.subtitle') }}</p><b>{{ data_get($block, 'data.description') }}</b><div class="s606-countdown" data-s606-countdown="{{ data_get($block, 'settings.countdown_hours', 24) }}"><span><b data-hours>00</b>Giờ</span><span><b data-minutes>00</b>Phút</span><span><b data-seconds>00</b>Giây</span></div></div><div class="s606-product-grid">@foreach($blockItems as $item)@include('theme-shop606::partials.product-card', ['item' => $item])@endforeach</div></div></section>
    @elseif($type === 'shop606_feature')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap s606-feature"><img src="{{ data_get($block, 'media.image') }}" alt="{{ data_get($block, 'data.title') }}"><div><h2>{{ data_get($block, 'data.title') }}</h2><p>{{ data_get($block, 'data.description') }}</p><h3>Ưu điểm nổi bật</h3><ul>@foreach($blockItems as $item)<li>{{ data_get($item, 'title') }}</li>@endforeach</ul><a class="s606-button" href="#san-pham">{{ data_get($block, 'data.button_label') }}</a></div></div></section>
    @elseif($type === 'shop606_new')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section s606-arrivals xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="s606-wrap">
                <header class="s606-arrivals-heading"><div><span class="s606-arrivals-eyebrow">BỘ SƯU TẬP MỚI</span><h2>{{ data_get($block, 'data.title') }}</h2><p>Những lựa chọn mới cho phong cách mỗi ngày.</p></div><a class="s606-arrivals-all" href="{{ route('site.catalog.search') }}">Khám phá tất cả <span aria-hidden="true">↗</span></a></header>
                <nav class="s606-arrivals-categories" aria-label="Danh mục thời trang">
                    @foreach($items((array) ($blocks->firstWhere('block_type', 'shop606_collections') ?? [])) as $category)
                        <a href="{{ data_get($category, 'url', route('site.catalog.search')) }}">{{ data_get($category, 'title', data_get($category, 'name')) }}</a>
                    @endforeach
                </nav>
                <div class="s606-product-grid is-wide">
                    @forelse($blockItems as $item)
                        @include('theme-shop606::partials.product-card', ['item' => $item, 'compact' => true])
                    @empty
                        <p>Sản phẩm mới đang được cập nhật.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @elseif($type === 'shop606_campaign')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-campaign xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}" style="background-image:linear-gradient(90deg,rgba(0,0,0,.1),rgba(0,0,0,.52)),url('{{ data_get($block, 'media.image') }}')"><div><small>{{ data_get($block, 'data.subtitle') }}</small><h2>{{ data_get($block, 'data.title') }}</h2><a href="#bo-suu-tap">{{ data_get($block, 'data.button_label') }}</a></div></section>
    @elseif($type === 'shop606_outfit')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section s606-styling xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap s606-outfit">
            <div class="s606-outfit-image"><img src="{{ $image($blockItems->first(), data_get($block, 'settings.feature_image', data_get($block, 'media.image'))) }}" alt="Gợi ý phong cách" loading="lazy"><div class="s606-outfit-caption">CHỌN ĐỒ THEO PHONG CÁCH<small>Tinh tế trong từng lựa chọn</small></div></div>
            <div class="s606-outfit-copy"><span class="s606-arrivals-eyebrow">GỢI Ý DÀNH CHO BẠN</span><h2>{{ data_get($block, 'data.title') }}</h2><p>Khám phá từng thiết kế và chọn món đồ phù hợp với tủ đồ của bạn.</p><div class="s606-outfit-list">
                @foreach($blockItems as $item)
                    <a class="s606-outfit-item" href="{{ data_get($item, 'url', route('site.catalog.search')) }}"><img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}" loading="lazy"><div><small>LỰA CHỌN {{ $loop->iteration }}</small><h3>{{ data_get($item, 'title') }}</h3><strong>{{ number_format((int) data_get($item, 'price', 0), 0, ',', '.') }}₫</strong></div><span aria-hidden="true">↗</span></a>
                @endforeach
            </div><a class="s606-outfit-link" href="{{ route('site.catalog.search') }}">Khám phá sản phẩm <span aria-hidden="true">→</span></a></div>
        </div></section>
    @elseif($type === 'shop606_news')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap"><h2>{{ data_get($block, 'data.title') }}</h2><div class="s606-news-grid">@foreach($blockItems as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, '/theme-demo/shop604/ad-lac-quan.png') }}" alt="{{ data_get($item, 'title') }}"></a><time>{{ data_get($item, 'date', now()->format('d/m/Y')) }}</time><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3></article>@endforeach</div></div></section>
    @elseif($type === 'shop606_gallery')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-section xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap"><h2>{{ data_get($block, 'data.title') }}</h2><div class="s606-gallery">@foreach($blockItems as $item)<figure><img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}"><figcaption>{{ data_get($item, 'title') }}</figcaption></figure>@endforeach</div></div></section>
    @elseif($type === 'shop606_benefits')
        <section id="{{ data_get($block, 'anchor_id') }}" class="s606-benefits xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="s606-wrap">@foreach($blockItems as $item)<article><i class="{{ data_get($item, 'icon', 'fa-solid fa-check') }}"></i><div><b>{{ data_get($item, 'title') }}</b><span>{{ data_get($item, 'summary') }}</span></div></article>@endforeach</div></section>
    @endif
@endforeach
</main>
@include('theme-shop606::partials.consult-modal')
@endsection
@if($canEditLanding)
@push('scripts')
<script>(()=>{document.querySelectorAll('.xd-landing-block[data-landing-block-id]').forEach(section=>{if(!section.dataset.landingBlockId)return;const button=document.createElement('button');button.type='button';button.className='xd-edit-block';button.dataset.xdEditBlock=section.dataset.landingBlockId;button.textContent='Sửa khối';section.appendChild(button);});})();</script>
@include('theme-xd0302::partials.scripts')
@endpush
@endif
