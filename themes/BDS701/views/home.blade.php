@php
    $blocks = collect($landingBlocks ?? []);
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) $locale['code'], 'label' => (string) (($locale['native_name'] ?? null) ?: $locale['name'])])->values()->all() : [];
    $img = fn ($item, $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
@endphp
@extends('theme-bds701::layout')
@section('content')
<main>
@foreach($blocks as $block)
    @php($type = data_get($block, 'block_type'))
    @if($type === 'bds701_hero_search')
        <section class="bds-hero xd-landing-block" data-block-type="{{ $type }}" style="background-image:url('{{ data_get($block, 'media.image') }}')">
            <div class="bds-container bds-hero-content">
                <h1>{{ data_get($block, 'data.title', 'Tìm kiếm nhà đất mơ ước') }}</h1>
                <form class="bds-search" method="GET" action="{{ route('site.real-estate.index', ['locale' => app()->getLocale()]) }}">
                    <select name="transaction_type"><option value="sale">Bán</option><option value="rent">Cho thuê</option></select>
                    <input name="q" placeholder="Tìm dự án, biệt thự, căn hộ...">
                    <span class="bds-search-more"><i class="fa-solid fa-sliders"></i>&nbsp; Nâng cao</span>
                    <button><i class="fa-solid fa-magnifying-glass"></i> {{ data_get($block, 'data.button_label', 'Tìm kiếm nhanh') }}</button>
                </form>
                <p class="bds-quick-label">Tìm nhanh theo kiểu dáng</p>
                <div class="bds-type-shortcuts">
                    @foreach($items($block) as $item)<a href="{{ data_get($item, 'url', '#') }}"><i class="{{ data_get($item, 'icon', 'fa-solid fa-building') }}"></i>{{ data_get($item, 'title') }}</a>@endforeach
                </div>
            </div>
        </section>
    @elseif(in_array($type, ['bds701_latest_listings','bds701_rental_listings']))
        <section id="{{ data_get($block, 'anchor_id') }}" class="bds-section soft xd-landing-block" data-block-type="{{ $type }}">
            <div class="bds-container">
                <header class="bds-heading"><div><h2><em>{{ $type === 'bds701_rental_listings' ? 'Dự án' : 'Dự án' }}</em> {{ $type === 'bds701_rental_listings' ? 'cho thuê' : 'mới nhất' }}</h2><p>{{ data_get($block, 'data.subtitle') }}</p></div>
                    @if($type === 'bds701_latest_listings')<nav class="bds-tabs"><a href="#">Biệt thự</a><a href="#">Căn hộ</a><a href="#">Chung cư</a><a href="#">Nhà vườn</a></nav>@endif
                </header>
                <div class="bds-grid">@foreach($items($block) as $item) @include('theme-bds701::partials.listing-card', ['item' => $item]) @endforeach</div>
            </div>
        </section>
    @elseif($type === 'bds701_property_types')
        <section id="{{ data_get($block, 'anchor_id') }}" class="bds-section xd-landing-block" data-block-type="{{ $type }}"><div class="bds-container">
            <header class="bds-heading" style="justify-content:center;text-align:center"><div><h2><em>Mẫu dự án</em> tiêu biểu</h2><p>{{ data_get($block, 'data.subtitle') }}</p></div></header>
            <div class="bds-mosaic">@foreach($items($block) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $img($item) }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}<small>{{ data_get($item, 'count_label') }}</small></span></a>@endforeach</div>
        </div></section>
    @elseif($type === 'bds701_market_news')
        @php($newsItems = $items($block))
        <section id="{{ data_get($block, 'anchor_id') }}" class="bds-section xd-landing-block" data-block-type="{{ $type }}"><div class="bds-container">
            <header class="bds-heading"><div><h2><em>Tin tức</em> thị trường</h2><p>{{ data_get($block, 'data.subtitle') }}</p></div></header>
            <div class="bds-news-feature">
                @if($lead = $newsItems->first())<article class="bds-lead-news"><img src="{{ $img($lead) }}" alt=""><div><span class="bds-meta">{{ data_get($lead, 'published_at') }}</span><h3><a href="{{ data_get($lead, 'url', '#') }}">{{ data_get($lead, 'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)data_get($lead, 'summary')), 160) }}</p></div></article>@endif
                <div class="bds-news-list">@foreach($newsItems->skip(1) as $item)<article><img src="{{ $img($item) }}" alt=""><div><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><span class="bds-meta">{{ data_get($item, 'published_at') }}</span></div></article>@endforeach</div>
            </div>
        </div></section>
    @elseif($type === 'bds701_latest_news')
        <section id="{{ data_get($block, 'anchor_id') }}" class="bds-section xd-landing-block" data-block-type="{{ $type }}"><div class="bds-container">
            <header class="bds-heading"><div><h2><em>Tin</em> bất động sản mới</h2><p>{{ data_get($block, 'data.subtitle') }}</p></div></header>
            <div class="bds-news-grid">@foreach($items($block) as $item)<article class="bds-news-card"><img src="{{ $img($item) }}" alt=""><div><span class="bds-meta">{{ data_get($item, 'published_at') }}</span><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)data_get($item, 'summary')), 115) }}</p></div></article>@endforeach</div>
        </div></section>
    @elseif($type === 'bds701_newsletter')
        <section id="{{ data_get($block, 'anchor_id') }}" class="bds-newsletter xd-landing-block" data-block-type="{{ $type }}" style="background-image:url('{{ data_get($block, 'media.image') }}')"><div class="bds-container"><div class="bds-newsletter-card">
            <div><h2>{{ data_get($block, 'data.title') }}</h2><p>{{ data_get($block, 'data.subtitle') }}</p></div>
            <form method="POST" action="{{ route('site.newsletter.subscribe') }}">@csrf<input type="hidden" name="source" value="bds701-home"><input type="email" name="email" required placeholder="Nhập địa chỉ email của bạn..."><button>{{ data_get($block, 'data.button_label', 'Nhận tin miễn phí') }}</button></form>
        </div></div></section>
    @endif
@endforeach
</main>
@endsection
@if($canEditLanding)
@push('scripts')
<script>
(() => {
    const blockIds = @json($blocks->filter(fn ($block) => filled($block['id'] ?? null))->mapWithKeys(fn ($block) => [(string)$block['block_type'] => (string)$block['id']])->all());
    document.querySelectorAll('.xd-landing-block[data-block-type]').forEach((section) => {
        const id = blockIds[section.dataset.blockType]; if (!id) return;
        const button = document.createElement('button'); button.type='button'; button.className='xd-edit-block'; button.dataset.xdEditBlock=id; button.textContent='Sửa khối'; section.appendChild(button);
    });
})();
</script>
@include('theme-xd0302::partials.scripts')
@endpush
@endif
