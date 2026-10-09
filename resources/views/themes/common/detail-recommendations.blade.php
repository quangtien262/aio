@php
    $recommendationGroups = collect([
        ['kind' => 'products', 'heading' => __('news-detail.latest_products'), 'items' => $articleLatestProducts ?? []],
        ['kind' => 'services', 'heading' => __('news-detail.services'), 'items' => $articleServices ?? []],
        ['kind' => 'news', 'heading' => __('news-detail.latest'), 'items' => $serviceSidebarPosts ?? []],
    ])->filter(fn ($group) => in_array($group['kind'], $recommendationKinds ?? ['products', 'services', 'news'], true)
        && collect($group['items'])->isNotEmpty());
@endphp
@once
        <style>
            .xd-detail > article:only-child{grid-column:1/-1}
            .detail-recommendations{display:grid;gap:24px;min-width:0;align-self:start}
            .detail-recommendations--sections{width:var(--storefront-container-width,min(1180px,calc(100% - 32px)));margin:40px auto;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr))}
            .detail-recommendations__group{min-width:0;padding:24px;color:#172033;background:#fff;border:1px solid #e5e7eb;border-radius:12px}
            .detail-recommendations__group h2{margin:0 0 16px;font-size:20px;line-height:1.4;color:inherit}
            .detail-recommendations__item{display:flex!important;align-items:center;gap:14px;padding:12px 0;border-top:1px solid #e5e7eb;color:inherit!important;text-decoration:none!important;font-size:14px;font-weight:600;line-height:1.5}
            .detail-recommendations__item img{flex:0 0 64px;width:64px!important;height:56px!important;object-fit:cover;border-radius:6px;margin:0!important}
            .detail-recommendations__item span{min-width:0;overflow-wrap:anywhere}
            .detail-recommendations__item:hover span{text-decoration:underline}
            @media(max-width:900px){.detail-recommendations--sections{grid-template-columns:minmax(0,1fr)}}
        </style>
@endonce
@if($recommendationGroups->isNotEmpty())
    <div class="detail-recommendations {{ ($recommendationLayout ?? 'sidebar') === 'sections' ? 'detail-recommendations--sections' : '' }}">
        @foreach($recommendationGroups as $group)
            <section class="detail-recommendations__group" data-detail-recommendations="{{ $group['kind'] }}" {{ ($contentType ?? '') === 'post' ? 'data-article-sidebar' : 'data-service-sidebar' }}="{{ $group['kind'] }}">
                <h2>{{ $group['heading'] }}</h2>
                @foreach(collect($group['items'])->take(10) as $item)
                    <a class="detail-recommendations__item" href="{{ $item['url'] }}">
                        @if(filled($item['image'] ?? null))<img src="{{ $item['image'] }}" alt="" loading="lazy">@endif
                        <span>{{ $item['title'] }}</span>
                    </a>
                @endforeach
            </section>
        @endforeach
    </div>
@endif
