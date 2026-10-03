@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item))
        ->values();
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-testimonials xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container">
        <header class="rx13-feedback-heading"><p class="rx13-eyebrow">{{ $data['subtitle'] ?? 'Cảm nhận khách hàng' }}</p><h2>{{ $data['title'] ?? 'Chia sẻ từ khách hàng' }}</h2></header>
        <div class="rx13-feedback-grid">
            @foreach($items as $item)
                <figure class="rx13-feedback-card"><span class="rx13-feedback-mark" aria-hidden="true">“</span><blockquote>{{ $item['quote'] ?? $item['summary'] ?? '' }}</blockquote><figcaption>
                    @if(filled($item['avatar'] ?? $item['image'] ?? null))
                        <img src="{{ $item['avatar'] ?? $item['image'] }}" alt="" loading="lazy">
                    @endif
                    <div><strong>{{ $item['name'] ?? '' }}</strong><small>{{ $item['role'] ?? $item['company'] ?? '' }}</small></div>
                </figcaption></figure>
            @endforeach
        </div>
    </div>
</section>
