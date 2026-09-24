@php
    $recommendationGroups = [];
    if ($showRelatedRecommendations ?? true) {
        $recommendationGroups['related'] = $relatedProducts ?? [];
    }
    $recommendationGroups['latest'] = $latestProducts ?? [];
@endphp
@if(collect($recommendationGroups)->contains(fn ($items) => count($items) > 0))
    @once
        <style>
            .catalog-recommendations{width:min(1240px,calc(100% - 40px));margin:48px auto 64px;font-family:inherit;color:inherit}
            .catalog-recommendations header{display:flex;justify-content:space-between;align-items:center;gap:24px;margin-bottom:28px}
            .catalog-recommendations h2{font-size:clamp(24px,3vw,32px);line-height:1.3;margin:0 0 10px;color:inherit}
            .catalog-recommendations header p{margin:0;line-height:1.7;font-size:14px;opacity:.75}
            .catalog-recommendations a{color:inherit;text-decoration:none}
            .catalog-recommendations .catalog-browse{flex-shrink:0;border:1px solid currentColor;border-radius:8px;padding:12px 18px;font-size:14px;font-weight:600}
            .catalog-recommendation-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:24px}
            .catalog-recommendation-card{display:flex;flex-direction:column;min-width:0;border:1px solid #8899aa40;border-radius:14px;overflow:hidden;background:#fff;color:#243446}
            .catalog-recommendation-image{display:block;position:relative;aspect-ratio:1;background:#f5f7f8;margin:12px;border-radius:8px;overflow:hidden}
            .catalog-recommendation-image img{position:absolute;inset:0;width:100%;height:100%;padding:12px;object-fit:contain;transition:transform .2s}
            .catalog-recommendation-image:hover img{transform:scale(1.04)}
            .catalog-recommendation-image span{display:grid;place-items:center;height:100%;font-size:48px;opacity:.35}
            .catalog-recommendation-info{display:flex;flex:1;flex-direction:column;padding:8px 20px 20px;gap:16px}
            .catalog-recommendation-info h3{font-size:16px;line-height:1.6;margin:0;overflow-wrap:anywhere;color:inherit}
            .catalog-recommendation-price{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px;margin-top:auto;line-height:1.5}
            .catalog-recommendation-price strong{font-size:18px}.catalog-recommendation-price del{font-size:13px;color:#6a7580}
            .catalog-recommendation-info .catalog-product-link{border-top:1px solid #8899aa30;padding-top:14px;display:flex;justify-content:space-between;gap:8px;font-size:14px;font-weight:600}
            .catalog-recommendations a:focus-visible{outline:3px solid currentColor;outline-offset:3px}
            @media(max-width:900px){.catalog-recommendation-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}}
            @media(max-width:540px){.catalog-recommendations{width:calc(100% - 28px);margin:32px auto 40px}.catalog-recommendations header{align-items:flex-start;flex-direction:column;gap:18px}.catalog-recommendation-grid{gap:12px}.catalog-recommendation-info{padding:4px 12px 16px;gap:12px}.catalog-recommendation-info h3{font-size:14px}.catalog-recommendation-price strong{font-size:16px}.catalog-recommendation-info .catalog-product-link{font-size:12px}.catalog-recommendation-image{margin:8px}.catalog-recommendation-image img{padding:6px}}
            @media(max-width:359px){.catalog-recommendation-grid{grid-template-columns:1fr}}
            @media(prefers-reduced-motion:reduce){.catalog-recommendation-image img{transition:none}}
        </style>
    @endonce
    @foreach($recommendationGroups as $kind => $recommendationItems)
        @if(count($recommendationItems))
            <section class="catalog-recommendations" data-product-recommendations="{{ $kind }}" aria-labelledby="catalog-{{ $kind }}-title">
                <header>
                    <div><h2 id="catalog-{{ $kind }}-title">{{ __('catalog.'.$kind) }}</h2><p>{{ __('catalog.'.$kind.'_intro') }}</p></div>
                    <a class="catalog-browse" href="{{ route('site.catalog.search', array_filter(['locale' => app()->getLocale(), 'sort' => $kind === 'latest' ? 'newest' : null])) }}">{{ __('catalog.view_all') }} <span aria-hidden="true">→</span></a>
                </header>
                <div class="catalog-recommendation-grid">
                    @foreach(array_slice($recommendationItems, 0, 4) as $recommendation)
                        <article class="catalog-recommendation-card">
                            <a class="catalog-recommendation-image" href="{{ $recommendation['url'] }}" aria-label="{{ $recommendation['title'] }}">
                                @if(!empty($recommendation['image']))<img src="{{ $recommendation['image'] }}" alt="{{ $recommendation['title'] }}" loading="lazy">@else<span aria-hidden="true">◇</span>@endif
                            </a>
                            <div class="catalog-recommendation-info">
                                <h3><a href="{{ $recommendation['url'] }}">{{ $recommendation['title'] }}</a></h3>
                                <div class="catalog-recommendation-price"><strong>{{ (float) ($recommendation['price'] ?? 0) > 0 ? number_format($recommendation['price'], 0, ',', '.').'đ' : __('catalog.contact_price') }}</strong>@if((float) ($recommendation['old_price'] ?? 0) > (float) ($recommendation['price'] ?? 0))<del>{{ number_format($recommendation['old_price'], 0, ',', '.') }}đ</del>@endif</div>
                                <a class="catalog-product-link" href="{{ $recommendation['url'] }}">{{ __('catalog.view_product') }} <span aria-hidden="true">→</span></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
@endif
