@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? null))
        ->take($settings['limit'] ?? 6)->values();
@endphp
@if ($items->isNotEmpty())
<section id="{{ $anchor }}" class="xd2-section xd2-services xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="featured_services">
    <div class="xd2-container">
        {!! $editButton !!}
        <p class="xd2-kicker">{{ $data['subtitle'] ?? '' }}</p><h2>{{ $data['title'] ?? '' }}</h2>
        <div data-xd8-slider role="region" aria-label="Dịch vụ tư vấn du học" aria-roledescription="carousel">
            <div class="xd2-service-grid" data-xd8-track tabindex="0" aria-label="Danh sách dịch vụ, cuộn ngang để xem thêm">
                @foreach ($items as $item)
                    <article>
                        @if (filled($item['image'] ?? null))<img src="{{ $item['image'] }}" alt="{{ $item['alt'] ?? $item['title'] }}" loading="lazy">@endif
                        <div><h3>{{ $item['title'] }}</h3><p>{{ \Illuminate\Support\Str::limit(strip_tags($item['summary'] ?? ''), 145) }}</p><a href="{{ $item['url'] ?? '#lien-he' }}">{{ $item['button_label'] ?? $data['button_label'] ?? 'Đọc thêm' }} <span aria-hidden="true">↗</span></a></div>
                    </article>
                @endforeach
            </div>
            @if ($items->count() > 1)
                <div class="xd8-review-controls"><span>Khám phá các dịch vụ</span><div><button type="button" data-xd8-prev aria-label="Dịch vụ trước">←</button><button type="button" data-xd8-next aria-label="Dịch vụ tiếp theo">→</button></div></div>
            @endif
        </div>
    </div>
</section>
@endif
