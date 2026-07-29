<main>
    <section class="book20-inner-hero"><div class="book20-container"><h1>{{ $title }}</h1><p>{{ $summary ?? '' }}</p></div></section>
    <section class="book20-content"><div class="book20-container book20-listing">
        @forelse(collect($entries ?? $posts ?? $items ?? []) as $item)
            <article>
                <a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/book920/book-1.webp')) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></a>
                <div><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'excerpt', data_get($item, 'description'))) }}</p></div>
            </article>
        @empty
            <p>Chưa có nội dung phù hợp.</p>
        @endforelse
    </div></section>
</main>

