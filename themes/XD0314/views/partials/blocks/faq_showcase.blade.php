@php
    $whyItems = collect($content['items'] ?? [])->values();
    $bgImage = $media['background'] ?? $content['background'] ?? 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?auto=format&fit=crop&w=1800&q=85';
@endphp

<section id="{{ $anchor }}" class="bb14-why bb14-section xd-landing-block" style="--bb14-why-bg: url('{{ $bgImage }}')" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-container">
        <div class="bb14-section-title">
            <h2>{{ $data['title'] ?? 'Tai sao chon chung toi' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="bb14-why__grid">
            @foreach ($whyItems as $item)
                <article>
                    <span>{{ $item['icon'] ?? 'âœ¦' }}</span>
                    <div>
                        <h3>{{ $item['title'] ?? $item['question'] ?? '' }}</h3>
                        <p>{{ $item['summary'] ?? $item['description'] ?? $item['answer'] ?? '' }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>

