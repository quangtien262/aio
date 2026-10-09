@php
    $items = collect($content['items'] ?? [])
        ->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))
        ->take($settings['limit'] ?? 3);
@endphp
@if($items->isNotEmpty())
<section id="{{ $anchor }}" class="xd5-section xd5-testimonial">
    <div class="xd5-container">
        <div class="xd5-testimonial-copy">
            <div>
                <p class="xd5-eyebrow">{{ $data['subtitle'] ?? '' }}</p>
                <h2 class="xd5-title">{{ $data['title'] ?? '' }}</h2>
            </div>
            @if(filled($data['description'] ?? null))<p class="xd10-testimonial-intro">{{ $data['description'] }}</p>@endif
        </div>
        <div class="xd5-testimonial-grid">
            @foreach($items as $item)
                @php($name = $item['name'] ?? $item['title'] ?? '')
                <article class="xd5-quote">
                    <span class="xd10-quote-mark" aria-hidden="true">“</span>
                    <blockquote>{{ $item['quote'] ?? $item['summary'] ?? '' }}</blockquote>
                    <div class="xd10-quote-author">
                        @if(filled($item['image'] ?? null))
                            <img src="{{ $item['image'] }}" alt="" loading="lazy">
                        @else
                            <span class="xd10-author-initial" aria-hidden="true">{{ mb_substr($name, 0, 1) }}</span>
                        @endif
                        <b>{{ $name }}</b>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif
