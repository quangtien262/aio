@php
    $categoryItems = collect($block['dynamic_items'] ?? [])->whenEmpty(fn () => collect($content['items'] ?? []))->values();
@endphp

<section id="{{ $anchor }}" class="bb14-service-strip xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-container">
        <div class="bb14-section-title">
            <h2>{{ $data['title'] ?? 'Danh muc dich vu' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="bb14-horizontal" data-bb14-row>
            @foreach ($categoryItems as $category)
                <a class="bb14-category-card" href="{{ $category['url'] ?? '#dich-vu' }}">
                    @if (filled($category['image'] ?? null))
                        <img src="{{ $category['image'] }}" alt="{{ $category['alt'] ?? $category['title'] ?? $category['name'] ?? '' }}">
                    @else
                        <span>{{ $category['icon'] ?? 'â–£' }}</span>
                    @endif
                    <strong>{{ $category['title'] ?? $category['name'] ?? '' }}</strong>
                    @if (filled($category['summary'] ?? $category['count_label'] ?? null))
                        <small>{{ $category['summary'] ?? $category['count_label'] }}</small>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>

