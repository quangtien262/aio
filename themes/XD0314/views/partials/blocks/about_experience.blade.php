@php
    $aboutItems = collect($content['items'] ?? [])->values();
    $stats = collect($content['stats'] ?? [])->values();
    $aboutImage = $media['image'] ?? $content['image'] ?? 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?auto=format&fit=crop&w=1200&q=85';
@endphp

<section id="{{ $anchor }}" class="bb14-about bb14-section xd-landing-block" data-landing-block-id="{{ $block['id'] }}" data-block-type="{{ $block['block_type'] }}">
    {!! $editButton !!}
    <div class="bb14-container">
        <div class="bb14-section-title">
            <h2>{{ $data['title'] ?? 'Ve cong ty' }}</h2>
            @if (filled($data['description'] ?? null))
                <p>{{ $data['description'] }}</p>
            @endif
        </div>
        <div class="bb14-about__grid">
            <div>
                <div class="bb14-about__cards">
                    @foreach ($aboutItems as $item)
                        <article>
                            <span>{{ $item['icon'] ?? '▣' }}</span>
                            <div>
                                <h3>{{ $item['title'] ?? '' }}</h3>
                                <p>{{ $item['summary'] ?? $item['description'] ?? '' }}</p>
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="bb14-about__stats">
                    @foreach ($stats as $stat)
                        <article>
                            <span>{{ $stat['icon'] ?? '▥' }}</span>
                            <strong>{{ $stat['value'] ?? '' }}</strong>
                            <p>{{ $stat['label'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
            <img class="bb14-about__image" src="{{ $aboutImage }}" alt="{{ $data['title'] ?? 'About' }}">
        </div>
    </div>
</section>

