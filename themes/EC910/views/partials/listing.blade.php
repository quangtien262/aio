<main>
    <section class="ec10-inner-hero"><div class="ec10-container"><p>{{ $eyebrow ?? 'TEMPO WATCH STORE' }}</p><h1>{{ $title }}</h1>@if(!empty($summary))<span>{{ $summary }}</span>@endif</div></section>
    <section class="ec10-content"><div class="ec10-container ec10-listing">
        @forelse(collect($entries ?? []) as $item)
            <article>
                @if(data_get($item, 'image'))<a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image') }}" alt="{{ data_get($item, 'title') }}"></a>@endif
                <div><h2><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title') }}</a></h2><p>{{ data_get($item, 'summary') }}</p><a class="ec10-text-link" href="{{ data_get($item, 'url', '#') }}">Xem chi tiết <i class="fa-solid fa-arrow-right"></i></a></div>
            </article>
        @empty
            <p class="ec10-empty">Chưa có nội dung phù hợp.</p>
        @endforelse
    </div></section>
</main>
