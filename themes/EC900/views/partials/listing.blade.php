@php $entries = collect($entries ?? []); @endphp
<main>
    <section class="ec9-inner-hero"><div class="ec9-container"><p>ECOMAX SMART HOME</p><h1>{{ $title ?? 'Khám phá' }}</h1></div></section>
    <section class="ec9-content"><div class="ec9-container ec9-listing">@forelse($entries as $item)<article><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/ec900/air-purifier.webp')) }}" alt="{{ data_get($item, 'title') }}"></a><h3><a href="{{ data_get($item, 'url', '#') }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h3><p>{{ data_get($item, 'summary', data_get($item, 'short_description')) }}</p><a href="{{ data_get($item, 'url', '#') }}">Xem chi tiết <i class="fa-solid fa-arrow-right"></i></a></article>@empty<p class="ec9-empty">Chưa có nội dung phù hợp.</p>@endforelse</div></section>
</main>
