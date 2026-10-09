@php
    $contentGroups = collect([
        ['kind' => 'products', 'heading' => __('news-detail.latest_products'), 'items' => collect($articleLatestProducts ?? [])->take(10)],
        ['kind' => 'services', 'heading' => __('news-detail.services'), 'items' => collect($articleServices ?? [])->take(10)],
        ['kind' => 'news', 'heading' => __('news-detail.latest'), 'items' => collect($serviceSidebarPosts ?? [])->take(10)],
    ])->filter(fn ($group) => $group['items']->isNotEmpty());
@endphp
@foreach($contentGroups as $group)
    <section class="xd305-content-collection" data-content-collection="{{ $group['kind'] }}">
        <h2>{{ $group['heading'] }}</h2>
        <div class="xd305-content-grid">
            @foreach($group['items'] as $item)
                <article class="xd305-content-card">
                    @if(!empty($item['image']))<a class="xd305-content-image" href="{{ $item['url'] }}" aria-label="{{ $item['title'] }}"><img src="{{ $item['image'] }}" alt="" loading="lazy"></a>@endif
                    <h3><a href="{{ $item['url'] }}"><span>{{ $item['title'] }}</span><span aria-hidden="true">↗</span></a></h3>
                </article>
            @endforeach
        </div>
    </section>
@endforeach
