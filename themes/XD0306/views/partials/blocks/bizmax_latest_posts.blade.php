@php($items = collect($content['items'] ?? [])->whenEmpty(fn () => collect($block['dynamic_items'] ?? []))->take($settings['limit'] ?? 3))
<section id="{{ $anchor }}" class="xd5-section">
    <div class="xd5-container">
        <header class="xd5-team-head">
            <p class="xd5-eyebrow">{{ $data['subtitle'] ?? '' }}</p>
            <h2 class="xd5-title">{{ $data['title'] ?? '' }}</h2>
        </header>
        <div class="xd5-posts">
            @foreach($items as $item)
                <article class="xd5-post">
                    <a href="{{ $item['url'] ?? '#' }}" aria-label="{{ $item['title'] ?? '' }}"><img src="{{ $item['image'] ?? 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=900&q=85' }}" alt=""></a>
                    <div>
                        <small>{{ now()->format('d/m/Y') }}</small>
                        <h3><a href="{{ $item['url'] ?? '#' }}">{{ $item['title'] ?? '' }}</a></h3>
                        <p>{{ str($item['summary'] ?? '')->limit(160) }}</p>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
