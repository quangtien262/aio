@php
    $newsTheme = (string) data_get($activeTheme ?? [], 'key', 'corporate-starter');
    $newsText = fn ($key) => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText($newsTheme, app()->getLocale(), 'news_listing.'.$key, __('news-listing.'.$key));
    $newsItems = $listingItems ?? collect();
    $newsCount = is_object($newsItems) && method_exists($newsItems, 'total') ? $newsItems->total() : count($newsItems);
    $newsSearching = filled(data_get($postFilters ?? [], 'q'));
@endphp
@include('themes.common.news-listing-styles')
<main class="theme-news-listing" data-news-theme="{{ $newsTheme }}">
    <div class="tnl-container">
        <nav class="tnl-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $newsText('home') }}</a><span aria-hidden="true">/</span><a href="{{ route('site.blog.index') }}">{{ $newsText('news') }}</a></nav>
        <header class="tnl-heading">
            <div><span class="tnl-kicker">{{ $newsText('news') }}</span><h1>{{ $topic->name ?? ($pageTitle ?? $newsText('news')) }}</h1><p>{{ filled($topic->description ?? $pageDescription ?? '') ? strip_tags($topic->description ?? $pageDescription) : $newsText('intro') }}</p>@if($newsSearching)<p class="tnl-query">{{ $newsText('search_results') }}: “{{ $postFilters['q'] }}”</p>@endif</div>
            <div class="tnl-count"><strong>{{ $newsCount }}</strong><span>{{ $newsText('articles') }}</span></div>
        </header>
        @if(filled($topic->image_url ?? null))<img class="tnl-topic-image" src="{{ $topic->image_url }}" alt="{{ $topic->name }}">@endif
        <section class="tnl-grid" aria-label="{{ $newsText('news') }}">
            @forelse($newsItems as $post)
                @php
                    $newsUrl = route('site.blog.show', ['slug' => $post->slug]);
                    $newsImage = data_get($post, 'featuredMedia.url') ?: data_get($post, 'featuredMedia.file_url') ?: data_get($post, 'image_url');
                    $newsSummary = \Illuminate\Support\Str::limit(strip_tags((string) ($post->excerpt ?: $post->body)), 170);
                @endphp
                <article class="tnl-card">
                    <a class="tnl-image" href="{{ $newsUrl }}" aria-label="{{ $post->title }}">@if($newsImage)<img src="{{ $newsImage }}" alt="{{ $post->title }}" loading="lazy">@else<span aria-hidden="true">&#9776;</span>@endif</a>
                    <div class="tnl-body">
                        @if($post->publish_at)<time datetime="{{ $post->publish_at->toDateString() }}">{{ $post->publish_at->format('d/m/Y') }}</time>@endif
                        <h2><a href="{{ $newsUrl }}">{{ $post->title }}</a></h2>
                        @if($newsSummary && $newsTheme !== 'NEWS88')<p>{{ $newsSummary }}</p>@endif
                        <a class="tnl-more" href="{{ $newsUrl }}">{{ $newsText('read_more') }} <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            @empty
                <div class="tnl-empty"><h2>{{ $newsText($newsSearching ? 'no_results' : 'empty') }}</h2><p>{{ $newsText('empty_hint') }}</p><a href="{{ route('site.blog.index') }}">{{ $newsText('all') }} →</a></div>
            @endforelse
        </section>
        @if(is_object($newsItems) && method_exists($newsItems, 'hasPages') && $newsItems->hasPages())
            <nav class="tnl-pagination" aria-label="{{ $newsText('pagination') }}">@if($newsItems->previousPageUrl())<a href="{{ $newsItems->previousPageUrl() }}">← {{ $newsText('previous') }}</a>@endif<span>{{ $newsItems->currentPage() }} / {{ $newsItems->lastPage() }}</span>@if($newsItems->nextPageUrl())<a href="{{ $newsItems->nextPageUrl() }}">{{ $newsText('next') }} →</a>@endif</nav>
        @endif
    </div>
</main>
