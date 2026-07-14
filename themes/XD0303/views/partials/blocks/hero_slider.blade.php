@php
    $slides = collect(data_get($data, 'content.slides', []))->filter(fn ($item) => is_array($item) && filled($item['image'] ?? null))->values();
    if ($slides->isEmpty()) $slides = collect($block['dynamic_items'] ?? [])->filter(fn ($item) => filled($item['image'] ?? null))->values();
    if ($slides->isEmpty()) $slides = collect([['title' => $data['title'] ?? 'Dịch vụ chuyên nghiệp, chất lượng nhanh gọn', 'summary' => $data['description'] ?? '', 'kicker' => $data['subtitle'] ?? '', 'image' => $media['image'] ?? 'https://images.unsplash.com/photo-1600518464441-9154a4dea21b?auto=format&fit=crop&w=1920&q=85', 'button_label' => $data['button_label'] ?? 'Liên hệ ngay', 'link_url' => '#lien-he']]);
@endphp
<section id="{{ $anchor }}" class="xd3-hero xd-landing-block">
    @foreach ($slides as $index => $slide)<article class="xd3-hero__slide {{ $index === 0 ? 'is-active' : '' }}" data-xd3-slide><img src="{{ $slide['image'] }}" alt="{{ $slide['title'] ?? '' }}"></article>@endforeach
    <div class="xd3-container xd3-hero__content"><div class="xd3-hero__copy"><p class="xd3-kicker" data-xd3-kicker>{{ $slides->first()['kicker'] ?? $data['subtitle'] ?? '' }}</p><h1 data-xd3-title>{{ $slides->first()['title'] ?? $data['title'] ?? '' }}</h1><p data-xd3-summary>{{ $slides->first()['summary'] ?? $data['description'] ?? '' }}</p><div class="xd3-hero__highlights"><span><b>01</b>Nhanh hơn</span><span><b>02</b>Thiết bị</span><span><b>03</b>Kinh nghiệm</span></div></div></div>
    @if($slides->count() > 1)<button class="xd3-hero__arrow xd3-hero__arrow--prev" data-xd3-prev aria-label="Slide trước">‹</button><button class="xd3-hero__arrow xd3-hero__arrow--next" data-xd3-next aria-label="Slide sau">›</button>@endif
    @if($canEditLanding && filled($block['id'] ?? null))<button type="button" class="xd-edit-block" data-xd-edit-block="{{ $block['id'] }}">Sửa khối</button>@endif
</section>
