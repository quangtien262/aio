@php
    $catalogTheme = (string) data_get($activeTheme ?? [], 'key', 'XD0320');
    $t = fn ($key) => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText($catalogTheme, app()->getLocale(), 'catalog_listing.'.$key, __('catalog-listing.'.$key));
    $isSearch = $catalogMode === 'search';
    $items = collect($products ?? []);
    $activeFilters = $isSearch ? ($searchFilters ?? []) : ($filters ?? []);
    $selectedCategory = $isSearch ? (string) ($activeFilters['category'] ?? '') : (string) $category->slug;
    $selectedSort = (string) ($activeFilters['sort'] ?? 'default');
    $selectedSort = $selectedSort === 'newest' ? 'default' : $selectedSort;
    $query = $isSearch ? (string) ($searchQuery ?? '') : '';
    $params = request()->only(['q', 'category', 'sort', 'min_price', 'max_price']);
    $filterUrl = fn ($changes) => request()->url().(($values = array_filter(array_merge($params, $changes), fn ($value) => $value !== '' && $value !== null)) ? '?'.http_build_query($values) : '');
    $money = fn ($value) => (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : $t('price');
    $title = $isSearch ? $t('search') : $category->name;
    $count = $resultCount ?? $items->count();
@endphp
@include('themes.common.catalog-listing-styles')
<main class="xd-catalog-page" data-catalog-theme="{{ $catalogTheme }}">
    <section class="xdc-hero">
        <div class="xdc-container">
            <nav class="xdc-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $t('home') }}</a><span aria-hidden="true">/</span><a href="{{ route('site.catalog.search') }}">{{ $t('products') }}</a>@unless($isSearch)<span aria-hidden="true">/</span><span>{{ $title }}</span>@endunless</nav>
            <span class="xdc-eyebrow">{{ $t('products') }}</span>
            <h1>{{ $title }}</h1>
            <p>{{ !$isSearch && filled($category->description) ? strip_tags($category->description) : $t('intro') }}</p>
        </div>
    </section>
    <div class="xdc-container xdc-layout">
        <aside>
            <details class="xdc-filters" open>
                <summary>{{ $t('filter') }}</summary>
                <form action="{{ route('site.catalog.search') }}" method="GET" class="xdc-search">
                    <label for="xdc-keyword">{{ $t('submit') }}</label>
                    <div><input id="xdc-keyword" type="search" name="q" value="{{ $query }}" placeholder="{{ $t('keyword') }}"><button type="submit" aria-label="{{ $t('submit') }}">→</button></div>
                    @if($selectedCategory !== '')<input type="hidden" name="category" value="{{ $selectedCategory }}">@endif
                    <input type="hidden" name="sort" value="{{ $selectedSort }}">
                    @foreach(['min_price', 'max_price'] as $priceKey)
                        @if(request()->filled($priceKey))<input type="hidden" name="{{ $priceKey }}" value="{{ request($priceKey) }}">@endif
                    @endforeach
                </form>
                <h2>{{ $t('categories') }}</h2>
                <nav class="xdc-categories" aria-label="{{ $t('categories') }}">
                    <a href="{{ $isSearch ? $filterUrl(['category' => '']) : route('site.catalog.search') }}" @if($selectedCategory === '') aria-current="true" @endif>{{ $t('all') }}</a>
                    @if($isSearch)
                        @foreach(($searchCategories ?? []) as $entry)
                            <a href="{{ $filterUrl(['category' => $entry->slug]) }}" @if($selectedCategory === $entry->slug) aria-current="true" @endif>{{ $entry->name }}</a>
                        @endforeach
                    @else
                        @include('themes.common.catalog-categories', ['nodes' => $catalogTreeCategories ?? $sidebarCategories ?? []])
                    @endif
                </nav>
            </details>
            <div class="xdc-help"><span aria-hidden="true">↗</span><h2>{{ $t('help') }}</h2><p>{{ $t('help_text') }}</p><a href="{{ route('site.contact') }}">{{ $t('contact') }} →</a></div>
        </aside>
        <section class="xdc-results" aria-label="{{ $t('products') }}">
            <div class="xdc-results-heading"><div><span class="xdc-eyebrow">{{ $t('showing') }}</span><h2>{{ $count }} {{ $t('results') }}</h2></div>@if($query !== '')<span class="xdc-query">“{{ $query }}”</span>@endif</div>
            <nav class="xdc-sort" aria-label="{{ $t('sort') }}"><span>{{ $t('sort') }}</span>@foreach(['default', 'price_asc', 'price_desc', 'bestseller'] as $sort)<a href="{{ $filterUrl(['sort' => $sort]) }}" @if($selectedSort === $sort) aria-current="true" @endif>{{ $t($sort) }}</a>@endforeach</nav>
            @if(count($params))<a class="xdc-reset" href="{{ request()->url() }}">{{ $t('clear') }} ×</a>@endif
            @if($items->isNotEmpty())
                <div class="xdc-grid">
                    @foreach($items as $product)
                        <a class="xdc-card" href="{{ $product['url'] }}">
                            <div class="xdc-image">@if(!empty($product['image']))<img src="{{ $product['image'] }}" alt="{{ $product['title'] }}" loading="lazy">@else<span>{{ $t('no_image') }}</span>@endif @if(!empty($product['discount']))<span class="xdc-discount">−{{ $product['discount'] }}%</span>@endif</div>
                            <div class="xdc-card-body"><span class="xdc-tag">{{ $product['tag'] ?? $t('products') }}</span><h3>{{ $product['title'] }}</h3><div class="xdc-price"><strong>{{ $money($product['price'] ?? 0) }}</strong>@if(!empty($product['old_price']) && $product['old_price'] > ($product['price'] ?? 0))<del>{{ $money($product['old_price']) }}</del>@endif</div><span class="xdc-card-action">{{ $t('details') }} <span aria-hidden="true">↗</span></span></div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="xdc-empty"><span aria-hidden="true">⌕</span><h2>{{ $t('empty') }}</h2><p>{{ $t('empty_text') }}</p><a href="{{ route('site.catalog.search') }}">{{ $t('all') }} →</a></div>
            @endif
            @if(isset($pagination) && $pagination->hasPages())
                <nav class="xdc-pagination" aria-label="{{ $t('page') }}">@if($pagination->previousPageUrl())<a href="{{ $pagination->previousPageUrl() }}">← {{ $t('previous') }}</a>@endif<span>{{ $t('page') }} {{ $pagination->currentPage() }} / {{ $pagination->lastPage() }}</span>@if($pagination->nextPageUrl())<a href="{{ $pagination->nextPageUrl() }}">{{ $t('next') }} →</a>@endif</nav>
            @endif
        </section>
    </div>
</main>
