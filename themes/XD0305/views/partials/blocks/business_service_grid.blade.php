@php
    $items = collect($content['items'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []));
    if (isset($settings['limit'])) $items = $items->take(max(1, (int) $settings['limit']));
@endphp
@include('theme-xd0305::partials.card-slider-assets')
@if($items->isNotEmpty())
<section id="{{ $anchor }}" class="xd5-section xd5-compact-section ">
    <div class="xd5-container">
        <header><p class="xd5-eyebrow">{{ $data['subtitle'] ?? '' }}</p><h2 class="xd5-title">{{ $data['title'] ?? '' }}</h2></header>
        <div class="xd5-card-slider" data-xd5-card-slider>
            <div class="xd5-card-track" data-card-track tabindex="0" role="region" aria-label="{{ $data['title'] ?? '' }}">
                @foreach($items as $item)
                    @php($cardTitle = $item['title'] ?? '')
                    <article class="xd5-slide-card ">
                        <a href="{{ $item['url'] ?? '#' }}">
                        @if(filled($item['image'] ?? null))<img src="{{ $item['image'] }}" alt="{{ $item['alt'] ?? $cardTitle }}" loading="lazy">@endif
                        <h3>{{ $cardTitle }}</h3>
                        </a>
                    </article>
                @endforeach
            </div>
            <div class="xd5-card-controls" hidden><button type="button" data-card-prev aria-label="{{ __('xd0305-slider.previous') }}">←</button><button type="button" data-card-next aria-label="{{ __('xd0305-slider.next') }}">→</button></div>
        </div>
    </div>
</section>
@endif
