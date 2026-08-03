@php
    $items = collect($block['dynamic_items'] ?? [])
        ->whenEmpty(fn () => collect($content['items'] ?? []))
        ->filter(fn ($item) => is_array($item))
        ->values();
    $item = $items->first() ?? [];
    $image = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1501555088652-021faa106b9b?auto=format&fit=crop&w=900&q=85';
    $avatar = $item['avatar'] ?? $item['image'] ?? 'https://images.unsplash.com/photo-1502685104226-ee32379fefbe?auto=format&fit=crop&w=300&q=85';
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-testimonials xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container rx13-testimonials__grid">
        <div class="rx13-testimonials__image">
            <img src="{{ $image }}" alt="Khách hàng RouteX">
        </div>
        <article class="rx13-quote">
            <p>{{ $item['quote'] ?? $item['summary'] ?? $data['description'] ?? '' }}</p>
            <div class="rx13-person">
                <img src="{{ $avatar }}" alt="{{ $item['name'] ?? 'Khách hàng' }}">
                <div>
                    <strong>{{ $item['name'] ?? 'Trần Minh Hoàng' }}</strong>
                    <span>{{ $item['role'] ?? $item['company'] ?? 'Giám đốc công ty ABC' }}</span>
                </div>
            </div>
        </article>
    </div>
</section>
