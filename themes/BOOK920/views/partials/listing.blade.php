<main class="book20-inner">
    <div class="book20-container">
        @include('theme-book920::partials.breadcrumb')
        <header class="book20-inner-hero"><p class="book20-kicker">@themeT('inner.story', 'Cùng mở những trang mới')</p><h1>{{ $pageTitle ?? '' }}</h1><p>{{ $pageDescription ?? '' }}</p></header>
        <div class="book20-listing">
            @forelse($listingItems ?? [] as $item)
                @php
                    $itemUrl = route($detailRoute, ['slug' => data_get($item, 'slug')]);
                    $itemImage = data_get($item, 'featuredMedia.file_url') ?: data_get($item, 'featuredImage.image_url') ?: data_get($item, 'image_url');
                @endphp
                <article><a class="book20-listing-image" href="{{ $itemUrl }}">@if($itemImage)<img src="{{ $itemImage }}" alt="{{ data_get($item, 'title') }}" loading="lazy">@else<i class="fa-solid fa-book-open" aria-hidden="true"></i>@endif</a><div><h2><a href="{{ $itemUrl }}">{{ data_get($item, 'title') }}</a></h2><p>{{ strip_tags(data_get($item, 'excerpt') ?: data_get($item, 'summary', '')) }}</p><a class="book20-text-link" href="{{ $itemUrl }}">@themeT('inner.read_more', 'Đọc tiếp') &rarr;</a></div></article>
            @empty<div class="book20-empty"><i class="fa-regular fa-folder-open" aria-hidden="true"></i><p>@themeT('inner.empty', 'Chưa có nội dung phù hợp.')</p></div>@endforelse
        </div>
        @if(($listingItems ?? null) instanceof \Illuminate\Contracts\Pagination\Paginator)@include('theme-book920::partials.pagination', ['paginator' => $listingItems])@endif
    </div>
</main>
