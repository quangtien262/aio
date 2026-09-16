@php
    $blocks = collect($landingBlocks ?? [])->values();
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $image = fn ($item, string $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $assetImage = fn (string $name) => asset('themes/TOOL751/images/'.$name.'.png');
    $formatPrice = fn ($item) => (float) data_get($item, 'price') > 0 ? number_format((float) data_get($item, 'price'), 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL751', app()->getLocale(), 'price.contact', 'Liên hệ');
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) $locale['code'], 'label' => (string) (($locale['native_name'] ?? null) ?: $locale['name'])])->values()->all() : [];
@endphp
@extends('theme-tool751::layout')

@section('content')
<main>
@foreach($blocks as $block)
    @php($type = data_get($block, 'block_type'))

    @if($type === 'tool751_hero')
        <section class="t751-hero xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t751-container t751-hero-grid">
                <div class="t751-hero-main" style="background-image:url('{{ data_get($block, 'media.image', $assetImage('hero-sale')) }}')">
                    <div class="t751-hero-copy t751-reveal"><small>{{ data_get($block, 'data.subtitle') }}</small><h1>{{ data_get($block, 'data.title') }}</h1><p>{{ data_get($block, 'data.description') }}</p><a class="t751-button" href="#san-pham">{{ data_get($block, 'data.button_label') }} <i class="fa-solid fa-arrow-right"></i></a></div>
                </div>
                <div class="t751-hero-side">@foreach($items($block)->take(2) as $item)<a class="t751-quick-deal t751-reveal" href="{{ data_get($item, 'url', '#san-pham') }}"><div><h3>{{ data_get($item, 'title') }}</h3><strong>{{ $formatPrice($item) }}</strong><span>@themeT('view_all', 'Mua ngay')</span></div><img src="{{ $image($item, $assetImage('product-drill')) }}" alt="{{ data_get($item, 'title') }}"></a>@endforeach</div>
            </div>
            @include('theme-tool751::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool751_category_icons')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t751-section xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="t751-container t751-category-rail">
            @foreach($items($block)->take(7) as $index => $item)<a class="t751-reveal" href="{{ data_get($item, 'url', '#san-pham') }}"><i class="{{ data_get($item, 'icon', ['fa-solid fa-screwdriver-wrench','fa-solid fa-battery-full','fa-solid fa-gauge-high','fa-solid fa-compact-disc','fa-solid fa-gears','fa-solid fa-trowel-bricks','fa-solid fa-house'][$index] ?? 'fa-solid fa-wrench') }}"></i><b>{{ data_get($item, 'title') }}</b></a>@endforeach
        </div>@include('theme-tool751::partials.edit-button', ['block' => $block])</section>

    @elseif($type === 'tool751_sale_products')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t751-section t751-sale xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="t751-container t751-sale-grid">
            <div class="t751-sale-lead"><small>{{ data_get($block, 'data.subtitle') }}</small><h2>{{ data_get($block, 'data.title') }}</h2><div class="t751-sale-poster"><div><b>SALE OFF</b><strong>50%</strong></div></div></div>
            @foreach($items($block)->take(3) as $item)@include('theme-tool751::partials.product-card', ['item' => $item])@endforeach
        </div>@include('theme-tool751::partials.edit-button', ['block' => $block])</section>

    @elseif($type === 'tool751_compact_products')
        @php($compact = $items($block))
        <section id="{{ data_get($block, 'anchor_id') }}" class="t751-section xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="t751-container t751-compact-grid">
            @foreach([[data_get($block, 'data.title'), $compact->take(4)], [data_get($block, 'data.subtitle'), $compact->skip(4)->take(4)]] as [$title, $list])<div><div class="t751-title-line"><h2>{{ $title }}</h2></div><div class="t751-compact-list">@foreach($list as $item)<a href="{{ data_get($item, 'url', '#san-pham') }}"><img src="{{ $image($item, $assetImage('product-drill')) }}" alt="{{ data_get($item, 'title') }}"><span><h3>{{ data_get($item, 'title') }}</h3><span class="t751-price"><strong>{{ $formatPrice($item) }}</strong>@if((float) data_get($item, 'original_price') > (float) data_get($item, 'price'))<del>{{ number_format((float) data_get($item, 'original_price'), 0, ',', '.').'đ' }}</del>@endif</span></span></a>@endforeach</div></div>@endforeach
        </div>@include('theme-tool751::partials.edit-button', ['block' => $block])</section>

    @elseif($type === 'tool751_category_products')
        <section id="{{ data_get($block, 'anchor_id') }}" class="xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t751-category-head" style="background-image:url('{{ data_get($block, 'media.image', $assetImage('workshop-service')) }}')"><div class="t751-container"><h2>{{ data_get($block, 'data.title') }}</h2><div class="t751-tabs" data-t751-tabs>@foreach(['Dụng cụ cầm tay','Dụng cụ dùng pin','Máy nổ','Máy cắt','Dụng cụ cơ khí'] as $label)<button type="button" class="{{ $loop->first ? 'is-active' : '' }}">{{ $label }}</button>@endforeach</div></div></div>
            <div class="t751-container t751-category-products"><div class="t751-five-grid">@foreach($items($block)->take(5) as $item)@include('theme-tool751::partials.product-card', ['item' => $item])@endforeach</div><div class="t751-more"><a href="#san-pham">@themeT('view_all', 'Xem tất cả')</a></div></div>
            @include('theme-tool751::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool751_news')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t751-section t751-news xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="t751-container"><div class="t751-heading"><h2>{{ data_get($block, 'data.title') }}</h2></div><div class="t751-news-grid">
            @foreach($items($block)->take(3) as $item)<article class="t751-news-card t751-reveal"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, $assetImage('workshop-service')) }}" alt="{{ data_get($item, 'title') }}"></a><h3>{{ data_get($item, 'title') }}</h3><time><i class="fa-regular fa-calendar-days"></i> {{ data_get($item, 'date', now()->format('d-m-Y')) }}</time><p>{{ data_get($item, 'summary') }}</p><a href="{{ data_get($item, 'url', '#') }}">@themeT('read_more', 'Đọc tiếp') <i class="fa-solid fa-caret-right"></i></a></article>@endforeach
        </div></div>@include('theme-tool751::partials.edit-button', ['block' => $block])</section>

    @elseif($type === 'tool751_partners')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t751-partners xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}"><div class="t751-container t751-partner-rail">@foreach($items($block)->take(6) as $item)<a href="{{ data_get($item, 'url', '#') }}">@if($image($item))<img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}">@else<span>{{ data_get($item, 'title') }}</span>@endif</a>@endforeach</div>@include('theme-tool751::partials.edit-button', ['block' => $block])</section>
    @endif
@endforeach
</main>
@endsection

@if($canEditLanding)
    @push('scripts')@include('theme-xd0302::partials.scripts')@endpush
@endif
