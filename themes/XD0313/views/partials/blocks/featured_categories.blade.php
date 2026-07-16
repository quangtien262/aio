@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item) && filled($item['title'] ?? null))
        ->take((int) ($settings['limit'] ?? 4))
        ->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-benefits xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container">
        <div class="rx13-benefits__grid">
            @foreach ($items as $item)
                <article class="rx13-benefit">
                    <span class="rx13-benefit__icon">{{ $item['icon'] ?? $loop->iteration }}</span>
                    <h3>{{ $item['title'] }}</h3>
                    @if (filled($item['summary'] ?? $item['description'] ?? null))
                        <p>{{ $item['summary'] ?? $item['description'] }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
