@php
    $items = collect($content['items'] ?? [])->filter(fn ($item) => is_array($item))->take(6)->values();
    $image = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1500835556837-99ac94a94552?auto=format&fit=crop&w=900&q=85';
@endphp

<section id="{{ $anchor }}" class="rx13-section rx13-process xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="rx13-container rx13-process__grid">
        <div>
            <p class="rx13-eyebrow">{{ $data['subtitle'] ?? 'Quy trinh tu van' }}</p>
            <h2 class="rx13-title">{{ $data['title'] ?? 'Cac Buoc Lam Visa Tai RouteX' }}</h2>
            <div class="rx13-step-list">
                @foreach ($items as $item)
                    <article class="rx13-step">
                        <strong>{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</strong>
                        <div>
                            <h3>{{ $item['title'] ?? '' }}</h3>
                            @if (filled($item['summary'] ?? $item['description'] ?? null))
                                <p>{{ $item['summary'] ?? $item['description'] }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
        <div class="rx13-process__image">
            <img src="{{ $image }}" alt="{{ $data['title'] ?? 'Quy trinh visa' }}">
        </div>
    </div>
</section>
