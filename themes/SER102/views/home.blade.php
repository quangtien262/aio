@extends('theme-ser102::layout')

@php
    $blocks = collect($landingBlocks ?? [])->values();
    $canEditLanding = auth('admin')->check() && request('mod') === 'admin' && is_array($landingPage ?? null);
    $blockUpdateUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.update', ['block' => '__BLOCK_ID__']) : '';
    $blockSourcePreviewUrlTemplate = $canEditLanding ? route('admin.api.landing.blocks.source-preview', ['block' => '__BLOCK_ID__']) : '';
    $blockPayload = $canEditLanding ? $blocks->keyBy('id')->toArray() : [];
    $editorLocales = collect(data_get($landingEditorOptions ?? [], 'locales', []))->all();
    $block = fn (string $type, int $offset = 0) => $blocks->where('block_type', $type)->values()->get($offset, []);
    $items = function (array $entry): array {
        $dynamic = collect($entry['dynamic_items'] ?? [])->filter()->values();
        return $dynamic->isNotEmpty() ? $dynamic->all() : collect(data_get($entry, 'data.content.items', data_get($entry, 'data.content.slides', [])))->filter()->values()->all();
    };
    $sectionHeading = fn (array $entry) => [
        'kicker' => data_get($entry, 'data.subtitle', ''),
        'title' => data_get($entry, 'data.title', ''),
        'summary' => data_get($entry, 'data.description', ''),
    ];
    $hero = $block('hero_slider');
    $heroSlides = $items($hero);
    if ($heroSlides === []) {
        $heroSlides = [[
            'kicker' => data_get($hero, 'data.subtitle', 'Chăm sóc xe chuẩn chuyên gia'),
            'title' => data_get($hero, 'data.title', 'Đánh thức vẻ đẹp nguyên bản'),
            'summary' => data_get($hero, 'data.description', 'Bảo vệ toàn diện, hoàn thiện từng chi tiết.'),
            'button_label' => data_get($hero, 'data.button_label', 'Khám phá ngay'),
            'link_url' => '#dich-vu',
            'image' => '/theme-previews/SER102/cover-ser102.png',
        ]];
    }
@endphp

@section('title', data_get($landingPage ?? [], 'title', 'SER102 Auto Detailing'))

@section('content')
<main class="ser102-main">
    <section class="ser102-hero" id="{{ $hero['anchor_id'] ?? 'trang-chu' }}" data-landing-block-id="{{ $hero['id'] ?? '' }}" data-block-type="hero_slider" data-xd3-hero data-autoplay="{{ data_get($hero, 'settings.autoplay_ms', 6000) }}">
        @foreach($heroSlides as $index => $slide)
            <article class="ser102-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-xd3-slide>
                <img src="{{ data_get($slide, 'image', '/theme-previews/SER102/cover-ser102.png') }}" alt="{{ data_get($slide, 'title', 'Auto detailing') }}">
                <span class="ser102-hero__veil"></span>
                <div class="ser102-container ser102-hero__content"><span>{{ data_get($slide, 'kicker', data_get($hero, 'data.subtitle')) }}</span><h1>{{ data_get($slide, 'title', data_get($hero, 'data.title')) }}</h1><p>{{ data_get($slide, 'summary', data_get($hero, 'data.description')) }}</p><div class="ser102-hero__trust"><b><i class="fa-solid fa-shield-halved"></i> Chính hãng</b><b><i class="fa-solid fa-car"></i> An toàn cho xe</b><b><i class="fa-solid fa-sparkles"></i> Hiệu quả vượt trội</b></div><a class="ser102-primary" href="{{ data_get($slide, 'link_url', '#dich-vu') }}">{{ data_get($slide, 'button_label', data_get($hero, 'data.button_label', 'Khám phá ngay')) }} <i class="fa-solid fa-arrow-right"></i></a></div>
            </article>
        @endforeach
        @if(count($heroSlides) > 1)<button class="ser102-hero__arrow is-prev" data-xd3-prev type="button"><i class="fa-solid fa-chevron-left"></i></button><button class="ser102-hero__arrow is-next" data-xd3-next type="button"><i class="fa-solid fa-chevron-right"></i></button><div class="ser102-hero__dots">@foreach($heroSlides as $index => $slide)<button type="button" class="{{ $index === 0 ? 'is-active' : '' }}" data-xd3-dot="{{ $index }}"></button>@endforeach</div>@endif
        @if($canEditLanding && filled($hero['id'] ?? null))<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $hero['id'] }}">Sửa khối</button>@endif
    </section>

    @foreach($blocks->reject(fn ($entry) => ($entry['block_type'] ?? '') === 'hero_slider') as $entry)
        @php $type = $entry['block_type'] ?? ''; $entryItems = $items($entry); $heading = $sectionHeading($entry); @endphp

        @if($type === 'featured_categories')
            <section class="ser102-section ser102-services" id="{{ $entry['anchor_id'] ?? 'dich-vu' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}">
                <div class="ser102-container"><x-ser102-heading :heading="$heading" />
                    <div class="ser102-service-grid">@foreach($entryItems as $index => $item)<a class="ser102-service-card" href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', '/theme-previews/SER102/appointment.png') }}" alt="{{ data_get($item, 'alt', data_get($item, 'title')) }}"><span class="ser102-service-card__number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p></div><i class="fa-solid fa-arrow-right"></i></a>@endforeach</div>
                </div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif
            </section>
        @elseif($type === 'process_steps')
            <section class="ser102-section ser102-process" id="{{ $entry['anchor_id'] ?? 'quy-trinh' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}"><div class="ser102-container"><x-ser102-heading :heading="$heading" /><div class="ser102-process__line">@foreach($entryItems as $index => $item)<article><span><i class="{{ data_get($item, 'icon', ['fa-solid fa-clipboard-check','fa-regular fa-comments','fa-solid fa-car-on','fa-solid fa-shield-halved','fa-solid fa-key'][$index % 5]) }}"></i></span><small>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</small><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'description', data_get($item, 'summary')) }}</p></article>@endforeach</div></div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif</section>
        @elseif($type === 'collection_gallery')
            <section class="ser102-section ser102-promos" id="{{ $entry['anchor_id'] ?? 'uu-dai' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}"><div class="ser102-container"><div class="ser102-promo-grid">@foreach($entryItems as $item)<a href="{{ data_get($item, 'url', '#bang-gia') }}"><img src="{{ data_get($item, 'image', '/theme-previews/SER102/cover-ser102.png') }}" alt="{{ data_get($item, 'title') }}"><span><small>{{ data_get($item, 'badge', 'Ưu đãi giới hạn') }}</small><strong>{{ data_get($item, 'title') }}</strong><b>{{ data_get($item, 'summary', 'Đặt lịch ngay') }} <i class="fa-solid fa-arrow-right"></i></b></span></a>@endforeach</div></div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif</section>
        @elseif($type === 'service_pricing')
            <section class="ser102-section ser102-pricing" id="{{ $entry['anchor_id'] ?? 'bang-gia' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}"><div class="ser102-container"><x-ser102-heading :heading="$heading" /><div class="ser102-pricing__grid">@foreach($entryItems as $item)@php $features = data_get($item, 'features', []); if(is_string($features)) $features = array_filter(array_map('trim', preg_split('/[\r\n|]+/', $features))); @endphp<article class="{{ data_get($item, 'featured') ? 'is-featured' : '' }}">@if(data_get($item, 'featured'))<span class="ser102-pricing__badge">Phổ biến nhất</span>@endif<div class="ser102-pricing__icon"><i class="{{ data_get($item, 'icon', 'fa-solid fa-car-side') }}"></i></div><h3>{{ data_get($item, 'title') }}</h3><strong>{{ data_get($item, 'price', 'Liên hệ') }}</strong><ul>@foreach($features as $feature)<li><i class="fa-regular fa-circle-check"></i>{{ is_array($feature) ? data_get($feature, 'title') : $feature }}</li>@endforeach</ul><button type="button" data-ser102-booking-open data-service="{{ data_get($item, 'title') }}">Đặt lịch ngay <i class="fa-solid fa-arrow-right"></i></button></article>@endforeach</div><p class="ser102-pricing__note">* Giá tham khảo có thể thay đổi tùy dòng xe và tình trạng thực tế.</p></div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif</section>
        @elseif($type === 'business_service_grid')
            @php $source = data_get($entry, 'settings.source', 'custom'); $serviceMode = $source === 'cms_services'; @endphp
            <section class="ser102-section ser102-products" id="{{ $entry['anchor_id'] ?? 'san-pham' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}"><div class="ser102-container"><x-ser102-heading :heading="$heading" /><div class="ser102-product-grid">@foreach($entryItems as $item)@php $isService = $serviceMode || data_get($item, 'type') === 'service'; $price = data_get($item, 'price'); @endphp<article><a class="ser102-product-card__image" href="{{ $isService ? '#' : data_get($item, 'url', '#') }}" @if($isService) data-ser102-booking-open data-service="{{ data_get($item, 'title') }}" @endif><img src="{{ data_get($item, 'image', '/theme-previews/SER102/avatar.png') }}" alt="{{ data_get($item, 'alt', data_get($item, 'title')) }}"></a><div><h3>{{ data_get($item, 'title') }}</h3><p>{{ data_get($item, 'summary') }}</p>@unless($isService)<strong>{{ is_numeric($price) ? number_format((float) $price, 0, ',', '.').'đ' : ($price ?: 'Liên hệ') }}</strong>@endunless
                @if($isService)<button type="button" data-ser102-booking-open data-service="{{ data_get($item, 'title') }}"><i class="fa-regular fa-calendar-check"></i> Đặt lịch</button>@else<a href="{{ data_get($item, 'url', '#') }}"><i class="fa-solid fa-layer-group"></i> Chi tiết</a>@endif</div></article>@endforeach</div></div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif</section>
        @elseif($type === 'latest_posts')
            <section class="ser102-section ser102-insights" id="{{ $entry['anchor_id'] ?? 'tin-tuc' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}"><div class="ser102-container"><x-ser102-heading :heading="$heading" />@if($entryItems !== [])<div class="ser102-insights__grid">@php $lead = $entryItems[0]; @endphp<a class="ser102-insights__lead" href="{{ data_get($lead, 'url', '#') }}"><img src="{{ data_get($lead, 'image', '/theme-previews/SER102/appointment.png') }}" alt="{{ data_get($lead, 'title') }}"><span><small>{{ data_get($lead, 'date', 'Kiến thức chăm xe') }}</small><strong>{{ data_get($lead, 'title') }}</strong><p>{{ data_get($lead, 'summary') }}</p><b>Đọc tiếp <i class="fa-solid fa-arrow-right"></i></b></span></a><div class="ser102-insights__list">@foreach(array_slice($entryItems, 1, 3) as $item)<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', '/theme-previews/SER102/avatar.png') }}" alt="{{ data_get($item, 'title') }}"><span><strong>{{ data_get($item, 'title') }}</strong><small>{{ data_get($item, 'summary') }}</small></span><i class="fa-solid fa-arrow-right"></i></a>@endforeach</div></div>@endif</div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif</section>
        @elseif($type === 'landing_contact')
            <section class="ser102-section ser102-contact" id="{{ $entry['anchor_id'] ?? 'lien-he' }}" data-landing-block-id="{{ $entry['id'] ?? '' }}" data-block-type="{{ $type }}"><div class="ser102-container ser102-contact__inner"><div><span>{{ $heading['kicker'] }}</span><h2>{{ $heading['title'] }}</h2><p>{{ $heading['summary'] }}</p><div class="ser102-contact__points"><b><i class="fa-solid fa-phone"></i> Tư vấn nhanh trong giờ làm việc</b><b><i class="fa-regular fa-calendar-check"></i> Chủ động chọn lịch phù hợp</b><b><i class="fa-solid fa-shield-halved"></i> Bảo mật thông tin khách hàng</b></div></div><form action="{{ route('site.contact.submit') }}" method="post">@csrf<input type="hidden" name="source" value="contact"><input name="name" placeholder="Họ và tên *" required><input name="phone" placeholder="Số điện thoại"><input type="email" name="email" placeholder="Email *" required><textarea name="message" rows="4" minlength="10" placeholder="Nội dung cần tư vấn *" required></textarea><button type="submit">Gửi yêu cầu <i class="fa-solid fa-arrow-right"></i></button></form></div>@if($canEditLanding)<button class="xd-edit-block" type="button" data-xd-edit-block="{{ $entry['id'] }}">Sửa khối</button>@endif</section>
        @endif
    @endforeach
</main>
@endsection
