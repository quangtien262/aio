<main class="dl-inner">
    <section class="dl-inner-hero"><div class="dl-wrap"><h1>{{ $pageTitle ?? '' }}</h1><p>{{ $pageDescription ?? '' }}</p></div></section>
    <div class="dl-wrap dl-content">
        <div class="dl-listing">
            @forelse($listingItems ?? [] as $item)
                @php
                    $itemUrl = route($detailRoute, ['locale' => app()->getLocale(), 'slug' => data_get($item, 'slug')]);
                    $itemImage = data_get($item, 'featuredMedia.file_url') ?: data_get($item, 'featuredImage.image_url') ?: data_get($item, 'image_url');
                @endphp
                <article>
                    @if($itemImage)<a href="{{ $itemUrl }}"><img src="{{ $itemImage }}" alt="{{ data_get($item, 'title') }}" loading="lazy"></a>@endif
                    <div>
                        <h2><a href="{{ $itemUrl }}">{{ data_get($item, 'title') }}</a></h2>
                        <p>{{ data_get($item, 'excerpt') ?: data_get($item, 'summary') }}</p>
                        <a class="dl-primary" href="{{ $itemUrl }}">@themeT('read_more', 'Xem thêm')</a>
                    </div>
                </article>
            @empty
                <p>@themeT('content.empty', 'Chưa có nội dung phù hợp.')</p>
            @endforelse
        </div>
        @if(($listingItems ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator)
            @include('theme-dl750::partials.pagination', ['paginator' => $listingItems])
        @endif
    </div>
</main>
