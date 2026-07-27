<main>
    <section class="ec92-inner-hero"><div class="ec92-container"><p>{{ $eyebrow ?? 'NOVAPHONE TECHNOLOGY' }}</p><h1>{{ $title }}</h1>@if(!empty($summary))<span>{{ $summary }}</span>@endif</div></section>
    <section class="ec92-content"><div class="ec92-container ec92-listing">
        @forelse(collect($entries ?? []) as $item)
            <article>
                @if(data_get($item, 'image'))<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"></a>@endif
                <div><h2><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h2><p>{{ data_get($item, 'summary') }}</p><a class="ec92-text-link" href="{{ data_get($item, 'url', '#') }}">Xem chi tiết <i class="fa-solid fa-arrow-right"></i></a></div>
            </article>
        @empty
            <p class="ec92-empty">Chưa có nội dung phù hợp.</p>
        @endforelse
    </div></section>
</main>
