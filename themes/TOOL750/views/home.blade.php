@php
    $blocks = collect($landingBlocks ?? [])->values();
    $items = function (array $block) {
        $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values();
    };
    $image = fn ($item, string $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $assetImage = fn (string $name) => asset('themes/TOOL750/images/'.$name.'.png');
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) $locale['code'], 'label' => (string) (($locale['native_name'] ?? null) ?: $locale['name'])])->values()->all() : [];
@endphp
@extends('theme-tool750::layout')

@section('content')
<main>
@foreach($blocks as $block)
    @php($type = data_get($block, 'block_type'))

    @if($type === 'tool750_hero')
        <section class="t750-hero xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}" style="background-image:linear-gradient(90deg,rgba(7,12,15,.98) 0%,rgba(7,12,15,.88) 40%,rgba(7,12,15,.12) 74%),url('{{ data_get($block, 'media.image', $assetImage('hero-tools')) }}')">
            <div class="t750-container t750-hero-inner">
                <div class="t750-hero-copy t750-reveal">
                    <p>{{ data_get($block, 'data.subtitle') }}</p>
                    <div class="t750-hero-title"><small>Engineered to perform</small><h1>{{ data_get($block, 'data.title') }}</h1></div>
                    <span>{{ data_get($block, 'data.description') }}</span>
                    <a class="t750-button" href="#san-pham">{{ data_get($block, 'data.button_label') }}<i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_promo_categories')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-section t750-promos xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container t750-promo-grid">
                @foreach($items($block)->take(3) as $index => $item)
                    <a class="t750-promo-card t750-reveal tone-{{ $index + 1 }}" href="{{ data_get($item, 'url', '#san-pham') }}">
                        <div><small>{{ data_get($item, 'summary') }}</small><h2>{{ data_get($item, 'title') }}</h2><span>@themeT('view_product', 'Xem sản phẩm') <i class="fa-solid fa-arrow-right"></i></span></div>
                        <img src="{{ $image($item, $assetImage('tool-collection')) }}" alt="{{ data_get($item, 'title') }}" loading="lazy">
                    </a>
                @endforeach
            </div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_sale_products')
        @php($saleItems = $items($block))
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-section t750-sale xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container">
                <div class="t750-sale-layout">
                    @if($featured = $saleItems->first())
                        <article class="t750-featured-deal t750-reveal">
                            <div class="t750-section-title"><h2>{{ data_get($block, 'data.title') }}</h2></div>
                            <div class="t750-featured-media">
                                <span class="t750-discount">-{{ max(10, (int) round((1 - ((float) data_get($featured, 'price', 0) / max(1, (float) data_get($featured, 'original_price', 1)))) * 100)) }}%</span>
                                <img src="{{ $image($featured, $assetImage('product-drill')) }}" alt="{{ data_get($featured, 'title') }}">
                            </div>
                            <div class="t750-countdown"><span><b>02</b>Ngày</span><span><b>11</b>Giờ</span><span><b>45</b>Phút</span><span><b>28</b>Giây</span></div>
                            <h3>{{ data_get($featured, 'title') }}</h3>
                            <div class="t750-price"><strong>{{ (float) data_get($featured, 'price') > 0 ? number_format((float) data_get($featured, 'price'), 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong>@if((float) data_get($featured, 'original_price') > 0)<del>{{ number_format((float) data_get($featured, 'original_price'), 0, ',', '.').'đ' }}</del>@endif</div>
                        </article>
                    @endif
                    <div class="t750-best-sellers">
                        <div class="t750-section-title"><h2>{{ data_get($block, 'data.subtitle') }}</h2><a href="#san-pham">@themeT('view_all', 'Xem tất cả')</a></div>
                        <div class="t750-product-grid">@foreach($saleItems->skip(1)->take(8) as $item)@include('theme-tool750::partials.product-card', ['item' => $item])@endforeach</div>
                    </div>
                </div>
            </div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_category_grid')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-category-zone xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}" style="background-image:linear-gradient(rgba(7,11,14,.9),rgba(7,11,14,.94)),url('{{ data_get($block, 'media.image', $assetImage('workshop-service')) }}')">
            <div class="t750-container">
                <div class="t750-heading t750-heading-light"><p>{{ data_get($block, 'data.subtitle') }}</p><h2><i>//</i> {{ data_get($block, 'data.title') }} <i>//</i></h2></div>
                <div class="t750-category-grid">
                    @foreach($items($block)->take(6) as $item)
                        <a href="{{ data_get($item, 'url', '#san-pham') }}" class="t750-category-card t750-reveal">
                            <div class="t750-category-image"><img src="{{ $image($item, $assetImage('product-drill')) }}" alt="{{ data_get($item, 'title') }}" loading="lazy"></div>
                            <div><i class="{{ data_get($item, 'icon', 'fa-solid fa-screwdriver-wrench') }}"></i><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p><span>@themeT('view_all', 'Xem tất cả') <i class="fa-solid fa-arrow-right"></i></span></div>
                        </a>
                    @endforeach
                </div>
            </div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_weekly_products')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-section t750-weekly xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container">
                <div class="t750-section-title"><h2>{{ data_get($block, 'data.title') }}</h2></div>
                <div class="t750-weekly-layout">
                    <aside data-t750-tabs>
                        @foreach(['Dụng cụ điện', 'Dụng cụ cầm tay', 'Phụ kiện', 'Ngoài trời', 'Đồ bảo hộ', 'Ốc vít', 'Cơ khí neo'] as $index => $label)
                            <button type="button" class="{{ $loop->first ? 'is-active' : '' }}"><i class="{{ ['fa-solid fa-screwdriver', 'fa-solid fa-screwdriver-wrench', 'fa-solid fa-gears', 'fa-solid fa-tractor', 'fa-solid fa-helmet-safety', 'fa-solid fa-wrench', 'fa-solid fa-industry'][$index] }}"></i>{{ $label }}</button>
                        @endforeach
                    </aside>
                    <div class="t750-product-grid t750-product-grid-large">@foreach($items($block)->take(8) as $item)@include('theme-tool750::partials.product-card', ['item' => $item])@endforeach</div>
                </div>
            </div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_reasons')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-reasons xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-reasons-copy">
                <div class="t750-heading t750-heading-light"><h2><i>//</i> {{ data_get($block, 'data.title') }} <i>//</i></h2></div>
                @foreach($items($block)->take(3) as $item)
                    <article class="t750-reveal"><i class="{{ data_get($item, 'icon', 'fa-solid fa-circle-check') }}"></i><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div></article>
                @endforeach
            </div>
            <div class="t750-reasons-image" style="background-image:url('{{ data_get($block, 'media.image', $assetImage('workshop-service')) }}')"></div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_compact_products')
        @php($compact = $items($block))
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-section t750-compact xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container t750-compact-grid">
                @foreach([[data_get($block, 'data.title'), $compact->take(3)], [data_get($block, 'data.subtitle'), $compact->skip(3)->take(3)]] as [$title, $list])
                    <div><div class="t750-section-title"><h2>{{ $title }}</h2></div><div class="t750-compact-list">
                        @foreach($list as $item)<a href="{{ data_get($item, 'url', '#san-pham') }}"><img src="{{ $image($item, $assetImage('product-measure')) }}" alt="{{ data_get($item, 'title') }}"><span><b>{{ data_get($item, 'title') }}</b><strong>{{ (float) data_get($item, 'price') > 0 ? number_format((float) data_get($item, 'price'), 0, ',', '.').'đ' : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('TOOL750', app()->getLocale(), 'price.contact', 'Liên hệ') }}</strong></span><i class="fa-solid fa-arrow-right"></i></a>@endforeach
                    </div></div>
                @endforeach
            </div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_promo_banner')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-section t750-campaign xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container"><div class="t750-campaign-inner" style="background-image:url('{{ data_get($block, 'media.image', $assetImage('promo-outdoor')) }}')"><div><small>{{ data_get($block, 'data.subtitle') }}</small><h2>{{ data_get($block, 'data.title') }}</h2><p>{{ data_get($block, 'data.description') }}</p><a class="t750-button" href="#san-pham">{{ data_get($block, 'data.button_label') }}<i class="fa-solid fa-arrow-right"></i></a></div></div></div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_news')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-section t750-news xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container"><div class="t750-section-title"><h2>{{ data_get($block, 'data.title') }}</h2><a href="{{ route('site.blog.index', ['locale' => app()->getLocale()]) }}">@themeT('view_all', 'Xem tất cả')</a></div><div class="t750-news-grid">
                @foreach($items($block)->take(2) as $item)<article class="t750-reveal"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item, $assetImage('workshop-service')) }}" alt="{{ data_get($item, 'title') }}"></a><div><time>{{ data_get($item, 'date') }}</time><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p><a href="{{ data_get($item, 'url', '#') }}">@themeT('read_more', 'Đọc tiếp') <i class="fa-solid fa-arrow-right"></i></a></div></article>@endforeach
            </div></div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_testimonials')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-testimonials xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}" style="background-image:linear-gradient(rgba(5,9,12,.88),rgba(5,9,12,.93)),url('{{ data_get($block, 'media.image', $assetImage('hero-tools')) }}')">
            <div class="t750-container"><div class="t750-heading t750-heading-light"><h2><i>//</i> {{ data_get($block, 'data.title') }} <i>//</i></h2></div><div class="t750-testimonial-grid">
                @foreach($items($block)->take(3) as $item)<blockquote class="t750-reveal"><i class="fa-solid fa-quote-left"></i><p>{{ data_get($item, 'summary', data_get($item, 'quote')) }}</p><footer>@if($image($item))<img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}">@endif<span><b>{{ data_get($item, 'title', data_get($item, 'name')) }}</b><small>{{ data_get($item, 'role') }}</small></span></footer></blockquote>@endforeach
            </div></div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>

    @elseif($type === 'tool750_partners')
        <section id="{{ data_get($block, 'anchor_id') }}" class="t750-partners xd-landing-block" data-block-type="{{ $type }}" data-landing-block-id="{{ data_get($block, 'id') }}">
            <div class="t750-container t750-partner-rail">@foreach($items($block)->take(6) as $item)<a href="{{ data_get($item, 'url', '#') }}">@if($image($item))<img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}">@else<span>{{ data_get($item, 'title') }}</span>@endif</a>@endforeach</div>
            @include('theme-tool750::partials.edit-button', ['block' => $block])
        </section>
    @endif
@endforeach
</main>
@endsection

@if($canEditLanding)
    @push('scripts')
        @include('theme-xd0302::partials.scripts')
    @endpush
@endif
