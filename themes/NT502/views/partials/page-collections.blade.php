@php
    $pageCollections = collect([
        ['kind' => 'products', 'title' => __('news-detail.latest_products'), 'items' => collect($articleLatestProducts ?? [])->take(10)],
        ['kind' => 'news', 'title' => __('news-detail.latest'), 'items' => collect($serviceSidebarPosts ?? [])->take(10)],
    ])->filter(fn ($group) => $group['items']->isNotEmpty());
@endphp
@foreach($pageCollections as $group)
    <section class="n502-page-collection" data-page-collection="{{ $group['kind'] }}">
        <div class="n502-container">
            <header class="n502-heading"><h2>{{ $group['title'] }}</h2></header>
            <div class="n502-page-collection-grid">
                @foreach($group['items'] as $item)
                    <article class="n502-page-collection-card">
                        <a href="{{ $item['url'] }}">
                            @if(filled($item['image'] ?? null))<img src="{{ $item['image'] }}" alt="" loading="lazy">@endif
                            <h3>{{ $item['title'] }}<span aria-hidden="true">↗</span></h3>
                        </a>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endforeach
