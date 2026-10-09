@php
    $newsTheme = (string) data_get($activeTheme ?? [], 'key', 'corporate-starter');
    $newsText = fn ($key) => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText($newsTheme, app()->getLocale(), 'news_listing.'.$key, __('news-listing.'.$key));
    $newsItems = $listingItems ?? collect();
    $newsCount = is_object($newsItems) && method_exists($newsItems, 'total') ? $newsItems->total() : count($newsItems);
    $newsSearching = filled(data_get($postFilters ?? [], 'q'));
    $newsPageItems = is_object($newsItems) && method_exists($newsItems, 'getCollection') ? $newsItems->getCollection() : collect($newsItems);
    $newsLeadItems = $newsPageItems->take(3);
    $newsFeedItems = $newsPageItems->skip(3);
@endphp
@include('themes.common.news-listing-styles')
@if($newsTheme === 'BZ501')
    @include('theme-bz501::partials.news-listing-styles')
@endif
<main class="theme-news-listing" data-news-theme="{{ $newsTheme }}">
    <div class="tnl-container">
        <nav class="tnl-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $newsText('home') }}</a><span aria-hidden="true">/</span><a href="{{ route('site.blog.index') }}">{{ $newsText('news') }}</a></nav>
        <header class="tnl-heading">
            <div><span class="tnl-kicker">{{ $newsText('news') }}</span><h1>{{ $topic->name ?? ($pageTitle ?? $newsText('news')) }}</h1><p>{{ filled($topic->description ?? $pageDescription ?? '') ? strip_tags($topic->description ?? $pageDescription) : $newsText('intro') }}</p>@if($newsSearching)<p class="tnl-query">{{ $newsText('search_results') }}: “{{ $postFilters['q'] }}”</p>@endif</div>
            <div class="tnl-count"><strong>{{ $newsCount }}</strong><span>{{ $newsText('articles') }}</span></div>
        </header>
        @if(filled($topic->image_url ?? null))<img class="tnl-topic-image" src="{{ $topic->image_url }}" alt="{{ $topic->name }}">@endif
        @if($newsPageItems->isNotEmpty())
            <section class="tnl-lead-grid {{ $newsLeadItems->count() === 1 ? 'tnl-lead-grid--single' : '' }}" aria-label="{{ $newsText('news') }}">
                @foreach($newsLeadItems as $post)
                    @include('themes.common.news-listing-item', ['newsItemLayout' => $loop->first ? 'lead' : 'secondary'])
                @endforeach
            </section>
            @if($newsFeedItems->isNotEmpty())
                <section class="tnl-feed" aria-labelledby="tnl-feed-heading">
                    <h2 class="tnl-section-heading" id="tnl-feed-heading">{{ $newsText('more_articles') }}</h2>
                    <div class="tnl-feed-grid">
                        @foreach($newsFeedItems as $post)
                            @include('themes.common.news-listing-item', ['newsItemLayout' => 'list'])
                        @endforeach
                    </div>
                </section>
            @endif
        @else
            <div class="tnl-empty"><h2>{{ $newsText($newsSearching ? 'no_results' : 'empty') }}</h2><p>{{ $newsText('empty_hint') }}</p><a href="{{ route('site.blog.index') }}">{{ $newsText('all') }} →</a></div>
        @endif
        @if(is_object($newsItems) && method_exists($newsItems, 'hasPages') && $newsItems->hasPages())
            <nav class="tnl-pagination" aria-label="{{ $newsText('pagination') }}">@if($newsItems->previousPageUrl())<a href="{{ $newsItems->previousPageUrl() }}">← {{ $newsText('previous') }}</a>@endif<span>{{ $newsItems->currentPage() }} / {{ $newsItems->lastPage() }}</span>@if($newsItems->nextPageUrl())<a href="{{ $newsItems->nextPageUrl() }}">{{ $newsText('next') }} →</a>@endif</nav>
        @endif
    </div>
</main>
