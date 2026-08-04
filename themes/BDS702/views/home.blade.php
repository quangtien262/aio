@php
    $blocks = collect($landingBlocks ?? []);
    $items = function (array $block) { $dynamic = collect($block['dynamic_items'] ?? [])->filter()->values(); return $dynamic->isNotEmpty() ? $dynamic : collect(data_get($block, 'data.content.items', []))->filter()->values(); };
    $image = fn ($item, $fallback = '') => data_get($item, 'image', data_get($item, 'image_url', $fallback));
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->filter(fn (array $block): bool => filled($block['id'] ?? null))->keyBy('id')->toArray() : [];
    $editorLocales = $canEditLanding ? collect(\App\Support\FrontendLocalization::localeOptions())->filter(fn (array $locale): bool => (bool) ($locale['is_active'] ?? false))->map(fn (array $locale): array => ['code' => (string) $locale['code'], 'label' => (string) (($locale['native_name'] ?? null) ?: $locale['name'])])->values()->all() : [];
@endphp
@extends('theme-bds702::layout')
@section('content')
<main>
@foreach($blocks as $block)
    @php($type = data_get($block, 'block_type'))
    @if($type === 'hero_slider')
        @php($slides = $items($block))
        <section class="b702-hero xd-landing-block" data-block-type="{{ $type }}" data-b702-slider data-delay="{{ data_get($block, 'settings.autoplay_ms', 5600) }}">
            @forelse($slides as $slide)
                <article class="b702-hero-slide {{ $loop->first ? 'is-active' : '' }}" style="background-image:url('{{ $image($slide, data_get($block, 'media.image')) }}')"><div class="b702-hero-shade"></div><div class="b702-container b702-hero-copy"><p>{{ data_get($slide, 'subtitle', data_get($block, 'data.subtitle')) }}</p><h1>{{ data_get($slide, 'title', data_get($block, 'data.title')) }}</h1><span>{{ data_get($slide, 'summary', data_get($block, 'data.description')) }}</span><a href="{{ data_get($slide, 'link_url', '#du-an') }}">{{ data_get($slide, 'button_label', data_get($block, 'data.button_label')) }}</a></div></article>
            @empty
                <article class="b702-hero-slide is-active" style="background-image:url('{{ data_get($block, 'media.image') }}')"><div class="b702-hero-shade"></div><div class="b702-container b702-hero-copy"><p>{{ data_get($block, 'data.subtitle') }}</p><h1>{{ data_get($block, 'data.title') }}</h1><span>{{ data_get($block, 'data.description') }}</span><a href="#du-an">{{ data_get($block, 'data.button_label') }}</a></div></article>
            @endforelse
            @if($slides->count() > 1)<button class="b702-arrow prev" data-b702-prev aria-label="Trước"><i class="fa-solid fa-chevron-left"></i></button><button class="b702-arrow next" data-b702-next aria-label="Sau"><i class="fa-solid fa-chevron-right"></i></button>@endif
        </section>
    @elseif($type === 'bds702_intro')
        <section id="{{ data_get($block, 'anchor_id') }}" class="b702-section b702-intro xd-landing-block" data-block-type="{{ $type }}"><div class="b702-container b702-intro-grid">
            <img class="b702-intro-image" src="{{ data_get($block, 'media.image') }}" alt="{{ data_get($block, 'data.title') }}"><div><header class="b702-heading"><h2>{{ data_get($block, 'data.title') }}</h2><i class="fa-regular fa-handshake"></i><h3>{{ data_get($block, 'data.subtitle') }}</h3><p>{{ data_get($block, 'data.description') }}</p></header><div class="b702-stats">@foreach($items($block) as $item)<div><i class="fa-solid {{ $loop->odd ? 'fa-ruler-combined' : 'fa-seedling' }}"></i><span><b>{{ data_get($item, 'title') }}</b>{{ data_get($item, 'summary') }}</span></div>@endforeach</div></div>
        </div></section>
    @elseif($type === 'bds702_featured_projects')
        <section id="{{ data_get($block, 'anchor_id') }}" class="b702-section b702-soft xd-landing-block" data-block-type="{{ $type }}"><div class="b702-container"><header class="b702-heading center"><h2>{{ data_get($block, 'data.title') }}</h2><i class="fa-regular fa-handshake"></i><p>{{ data_get($block, 'data.subtitle') }}</p></header><div class="b702-project-grid">@foreach($items($block) as $item) @include('theme-bds702::partials.project-card', ['item' => $item]) @endforeach</div></div></section>
    @elseif($type === 'bds702_investment_activities')
        <section id="{{ data_get($block, 'anchor_id') }}" class="b702-section b702-invest xd-landing-block" data-block-type="{{ $type }}" style="background-image:linear-gradient(rgba(9,29,48,.72),rgba(9,29,48,.72)),url('{{ data_get($block, 'settings.background_image') }}')"><div class="b702-container"><header class="b702-heading center light"><h2>{{ data_get($block, 'data.title') }}</h2><i class="fa-regular fa-handshake"></i><p>{{ data_get($block, 'data.description') }}</p></header><div class="b702-activity-grid">@foreach($items($block) as $item)<a href="{{ data_get($item, 'url', '#lien-he') }}" style="background-image:linear-gradient(rgba(4,17,29,.3),rgba(4,17,29,.7)),url('{{ $image($item) }}')"><span>{{ data_get($item, 'title') }}</span></a>@endforeach</div></div></section>
    @elseif($type === 'bds702_recommended_projects')
        <section id="{{ data_get($block, 'anchor_id') }}" class="b702-section xd-landing-block" data-block-type="{{ $type }}"><div class="b702-container"><header class="b702-heading center"><h2>{{ data_get($block, 'data.title') }}</h2><i class="fa-regular fa-handshake"></i><p>{{ data_get($block, 'data.subtitle') }}</p></header><div class="b702-recommend">@foreach($items($block) as $item) @include('theme-bds702::partials.project-card', ['item' => $item, 'compact' => true]) @endforeach</div></div></section>
    @elseif($type === 'bds702_consultation')
        <section id="{{ data_get($block, 'anchor_id') }}" class="b702-section b702-consult xd-landing-block" data-block-type="{{ $type }}" style="background-image:linear-gradient(rgba(9,17,28,.72),rgba(9,17,28,.78)),url('{{ data_get($block, 'settings.background_image') }}')"><div class="b702-container"><header class="b702-heading center light"><h2>{{ data_get($block, 'data.title') }}</h2><i class="fa-regular fa-handshake"></i><p>{{ data_get($block, 'data.description') }}</p></header><form method="POST" action="{{ route('site.contact.submit') }}" class="b702-consult-form">@csrf<input name="name" required placeholder="@themeT('consult.name', 'Họ và tên')"><input name="email" type="email" required placeholder="@themeT('consult.email', 'Email')"><input name="phone" required placeholder="@themeT('consult.phone', 'Số điện thoại')"><input name="address" placeholder="@themeT('consult.address', 'Địa chỉ')"><textarea name="message" required placeholder="@themeT('consult.message', 'Nội dung cần tư vấn')"></textarea><button>{{ data_get($block, 'data.button_label') }}</button></form></div></section>
    @elseif($type === 'bds702_partners')
        <section id="{{ data_get($block, 'anchor_id') }}" class="b702-section b702-partners xd-landing-block" data-block-type="{{ $type }}"><div class="b702-container"><header class="b702-heading center"><h2>{{ data_get($block, 'data.title') }}</h2><i class="fa-regular fa-handshake"></i><p>{{ data_get($block, 'data.subtitle') }}</p></header><div class="b702-partner-rail">
            @forelse($items($block) as $item)
                <a href="{{ data_get($item, 'url', '#') }}"><img src="{{ $image($item) }}" alt="{{ data_get($item, 'title') }}"><span>{{ data_get($item, 'title') }}</span></a>
            @empty
                @foreach(['Aurelia','Horizon','Evergreen','Urbanity','Landmark'] as $name)
                    <span>{{ $name }}</span>
                @endforeach
            @endforelse
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
        const button = document.createElement('button'); button.type = 'button'; button.className = 'xd-edit-block'; button.dataset.xdEditBlock = id; button.textContent = 'Sửa khối'; section.appendChild(button);
    });
})();
</script>
@include('theme-xd0302::partials.scripts')
@endpush
@endif
