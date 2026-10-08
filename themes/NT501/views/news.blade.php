@php
    $canEditLanding = false;
    $newsText = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('NT501', app()->getLocale(), 'nt501.news.'.$key);
    $newsCommon = fn (string $key): string => app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('NT501', app()->getLocale(), 'news_listing.'.$key, __('news-listing.'.$key));
    $newsItems = $listingItems ?? collect();
    $articles = method_exists($newsItems, 'getCollection') ? $newsItems->getCollection() : collect($newsItems);
    $search = (string) data_get($postFilters ?? [], 'q', '');
    $categorySlug = (string) data_get($postFilters ?? [], 'category', '');
    $categories = collect($postCategories ?? []);
    $currentCategory = $categories->firstWhere('slug', $categorySlug);
    $isNewsIndex = request()->routeIs('site.blog.index') && $categorySlug === '';
    $title = $topic->name ?? ($currentCategory?->name ?? ($pageTitle ?? $newsCommon('news')));
    $intro = $topic->description ?? ($currentCategory?->description ?: $newsText('intro'));
    $showFront = $search === '' && (! method_exists($newsItems, 'currentPage') || $newsItems->currentPage() === 1);
    $lead = $showFront ? $articles->first() : null;
    $briefs = $lead ? $articles->slice(1, 2) : collect();
    $remaining = $lead ? $articles->slice(3) : $articles;
@endphp

@extends('theme-nt501::layout')

@section('title', $pageTitle ?? $title)
@section('meta_description', $pageDescription ?? $intro)

@push('head')
    @include('theme-nt501::partials.news-styles')
@endpush

@section('content')
    <main class="theme-news-listing" data-news-theme="NT501">
        <div class="nt-news-container">
            <nav class="nt-news-breadcrumb" aria-label="@themeT('nt501.projects.breadcrumb')">
                <a href="{{ route('site.home') }}">{{ $newsCommon('home') }}</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('site.blog.index') }}" @if ($isNewsIndex) aria-current="page" @endif>{{ $newsCommon('news') }}</a>
                @if ($currentCategory || isset($topic))
                    <span aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span>
                @endif
            </nav>

            <header class="nt-news-masthead">
                <div>
                    <p class="nt-news-kicker">{{ $newsText('journal') }}</p>
                    <h1>{{ $title }}</h1>
                    <p class="nt-news-intro">{{ strip_tags((string) $intro) }}</p>
                </div>
                @if (isset($postFilters))
                    <form class="nt-news-search" method="get" action="{{ route('site.blog.index') }}" role="search">
                        @if ($categorySlug !== '')<input type="hidden" name="category" value="{{ $categorySlug }}">@endif
                        <label class="nt-news-sr-only" for="nt-news-query">{{ $newsText('search_label') }}</label>
                        <input type="search" id="nt-news-query" name="q" value="{{ $search }}" placeholder="{{ $newsText('search_placeholder') }}">
                        <button type="submit" aria-label="{{ $newsText('search_label') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg></button>
                    </form>
                @endif
            </header>

            <nav class="nt-news-sections" aria-label="{{ $newsText('categories_label') }}">
                <a href="{{ route('site.blog.index', $search !== '' ? ['q' => $search] : []) }}" @if ($isNewsIndex) aria-current="page" @endif>{{ $newsText('all_categories') }}</a>
                @foreach ($categories as $category)
                    <a href="{{ route('site.blog.category', array_merge(['slug' => $category->slug], $search !== '' ? ['q' => $search] : [])) }}" @if ($categorySlug === $category->slug) aria-current="page" @endif>{{ $category->name }}</a>
                @endforeach
            </nav>

            @if ($search !== '')
                <p class="nt-news-query">{{ $newsCommon('search_results') }}: <strong>“{{ $search }}”</strong> <a href="{{ $categorySlug !== '' ? route('site.blog.category', ['slug' => $categorySlug]) : route('site.blog.index') }}">{{ $newsText('clear_search') }} <span aria-hidden="true">×</span></a></p>
            @endif

            @if (filled($topic->image_url ?? null))
                <img class="nt-news-topic-image" src="{{ $topic->image_url }}" alt="{{ $topic->name }}">
            @endif

            @if ($lead)
                <div class="nt-news-front {{ $briefs->isEmpty() ? 'nt-news-front--single' : '' }}">
                    <section class="nt-news-lead" aria-label="{{ $newsText('spotlight') }}">
                        <p class="nt-news-section-label">{{ $newsText('spotlight') }}</p>
                        @include('theme-nt501::partials.news-story', ['post' => $lead, 'variant' => 'lead'])
                    </section>
                    @if ($briefs->isNotEmpty())
                        <section class="nt-news-briefs" aria-labelledby="nt-news-latest">
                            <h2 id="nt-news-latest" class="nt-news-section-label">{{ $newsText('latest') }}</h2>
                            @foreach ($briefs as $post)
                                @include('theme-nt501::partials.news-story', ['post' => $post, 'variant' => 'brief'])
                            @endforeach
                        </section>
                    @endif
                </div>
            @endif

            @if ($remaining->isNotEmpty())
                <section class="nt-news-feed" aria-label="{{ $newsCommon('news') }}">
                    <div class="nt-news-feed-heading"><h2>{{ $newsText($lead ? 'more_stories' : 'latest') }}</h2><span>{{ $remaining->count() }} {{ $newsCommon('articles') }}</span></div>
                    @foreach ($remaining as $post)
                        @include('theme-nt501::partials.news-story', ['post' => $post, 'variant' => 'row'])
                    @endforeach
                </section>
            @elseif ($articles->isEmpty())
                <section class="nt-news-empty">
                    <h2>{{ $newsCommon($search !== '' ? 'no_results' : 'empty') }}</h2>
                    <p>{{ $newsCommon('empty_hint') }}</p>
                    <a href="{{ route('site.blog.index') }}">{{ $newsCommon('all') }} <span aria-hidden="true">→</span></a>
                </section>
            @endif

            @if (method_exists($newsItems, 'hasPages') && $newsItems->hasPages())
                <nav class="nt-news-pagination" aria-label="{{ $newsCommon('pagination') }}">
                    @if ($newsItems->previousPageUrl())<a href="{{ $newsItems->previousPageUrl() }}" rel="prev">← {{ $newsCommon('previous') }}</a>@endif
                    <span>{{ $newsItems->currentPage() }} / {{ $newsItems->lastPage() }}</span>
                    @if ($newsItems->nextPageUrl())<a href="{{ $newsItems->nextPageUrl() }}" rel="next">{{ $newsCommon('next') }} →</a>@endif
                </nav>
            @endif
        </div>
    </main>
@endsection
