@php
    $customItems = collect($content['items'] ?? []);
    $source = $settings['source'] ?? ($customItems->isNotEmpty() ? 'custom' : 'cms_testimonials');
    $items = ($source === 'custom'
        ? $customItems
        : collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => $customItems))
        ->take($settings['limit'] ?? 3);
@endphp
<section id="{{ $anchor }}" class="xd2-testimonial-showcase xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    <div class="xd2-container xd2-testimonial-showcase__layout">
        {!! $editButton !!}
        <div class="xd2-testimonial-showcase__intro">
            <p class="xd2-kicker">{{ $data['subtitle'] ?? 'Lời chứng thực' }}</p>
            <h2>{{ $data['title'] ?? '' }}</h2>
            @if (filled($data['description'] ?? null))<p>{{ $data['description'] }}</p>@endif
        </div>
        <div class="xd2-testimonial-showcase__slider" data-row-slider>
            <button class="xd2-testimonial-showcase__nav is-prev" type="button" data-row-prev aria-label="Đánh giá trước">←</button>
            <div class="xd2-testimonial-showcase__track" data-row-track>
                @foreach ($items as $item)
                    @php
                        $itemName = $item['name'] ?? $item['title'] ?? '';
                        $itemQuote = $item['quote'] ?? $item['summary'] ?? $item['description'] ?? '';
                        $itemCompany = $item['company'] ?? $item['role'] ?? '';
                    @endphp
                    <article class="xd2-testimonial-showcase__card">
                        <span class="xd2-testimonial-showcase__quote" aria-hidden="true">“</span>
                        <p>{{ \Illuminate\Support\Str::limit(strip_tags($itemQuote), 310) }}</p>
                        <div class="xd2-testimonial-showcase__author">
                            @if (filled($item['image'] ?? null))
                                <img src="{{ $item['image'] }}" alt="{{ $item['alt'] ?? $itemName }}">
                            @else
                                <span class="xd2-testimonial-showcase__avatar" aria-hidden="true">{{ mb_substr($itemName, 0, 1) }}</span>
                            @endif
                            <div>
                                <strong>{{ $itemName }}</strong>
                                @if (filled($itemCompany))<small>{{ $itemCompany }}</small>@endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
            <button class="xd2-testimonial-showcase__nav is-next" type="button" data-row-next aria-label="Đánh giá tiếp theo">→</button>
            <div class="xd2-testimonial-showcase__dots" data-row-dots>
                @foreach ($items as $item)<button class="{{ $loop->first ? 'is-active' : '' }}" type="button" data-row-dot="{{ $loop->index }}" aria-label="Đánh giá {{ $loop->iteration }}"></button>@endforeach
            </div>
        </div>
    </div>
</section>
