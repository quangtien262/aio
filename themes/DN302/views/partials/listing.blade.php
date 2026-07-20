@php $entries = collect($entries ?? $products ?? $posts ?? $services ?? $projects ?? []); @endphp
<main>
    <section class="dn-inner-hero"><div class="dn-container" data-dn-reveal="up"><p class="dn-eyebrow">Janelas Windows &amp; Doors</p><h1>{{ $title ?? 'Khám phá' }}</h1></div></section>
    <section class="dn-section"><div class="dn-container dn-list-grid">
        @forelse($entries as $index => $item)
            <article class="dn-list-card" style="--dn-delay:{{ ($index % 3) * 90 }}ms" data-dn-reveal="up"><a href="{{ data_get($item, 'url', '#') }}"><img src="{{ data_get($item, 'image', data_get($item, 'image_url', '/theme-demo/dn302/dn302-villa.png')) }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"><div><h3>{{ data_get($item, 'title', data_get($item, 'name')) }}</h3><p>{{ data_get($item, 'summary', data_get($item, 'description')) }}</p></div></a></article>
        @empty
            <p>Nội dung đang được cập nhật.</p>
        @endforelse
    </div></section>
</main>
