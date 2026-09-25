@php
    $article = $entry ?? $post;
    $articleTheme = (string) data_get($activeTheme ?? [], 'key', 'corporate-starter');
    $readingMinutes = max(1, (int) ceil(count(preg_split('/\s+/u', strip_tags($article->body ?? ''), -1, PREG_SPLIT_NO_EMPTY)) / 220));
@endphp
@include('themes.common.news-detail-styles')
<main class="tna-article" data-article-theme="{{ $articleTheme }}"><div class="tna-article-wrap">
    <nav class="tna-article-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ __('news-detail.home') }}</a><span>/</span><a href="{{ route('site.blog.index') }}">{{ __('news-detail.news') }}</a></nav>
    <header class="tna-article-heading">
        <span class="tna-article-kicker">{{ __('news-detail.news') }}</span>
        <h1>{{ $article->title }}</h1>
        <div class="tna-article-meta">@if($article->publish_at)<time datetime="{{ $article->publish_at->toAtomString() }}">{{ $article->publish_at->format('d/m/Y') }}</time><span>·</span>@endif<span>{{ $readingMinutes }} {{ __('news-detail.minutes') }}</span></div>
        @if($article->excerpt)<p>{{ strip_tags($article->excerpt) }}</p>@endif
    </header>
    <div class="tna-article-layout"><div>
        <article class="tna-article-paper">
            @if(data_get($article, 'featuredMedia.file_url'))<figure class="tna-article-cover"><img src="{{ $article->featuredMedia->file_url }}" alt="{{ $article->featuredMedia->alt_text ?: $article->title }}"></figure>@endif
            <details class="tna-article-toc" data-tna-toc hidden open><summary>{{ __('news-detail.toc') }}</summary><ol></ol></details>
            <div class="tna-article-body" data-tna-article-body>{!! $article->body ?: $article->excerpt !!}</div>
            @if(!empty($postTags))<nav class="tna-article-tags">@foreach($postTags as $tag)<a href="{{ $tag['url'] }}" rel="tag">#{{ $tag['name'] }}</a>@endforeach</nav>@endif
            <div class="tna-article-share"><button type="button" data-tna-copy data-copied="{{ __('news-detail.copied') }}" data-failed="{{ __('news-detail.failed') }}">{{ __('news-detail.share') }} ↗</button><span role="status" data-tna-copy-status></span><input hidden readonly data-tna-share-url aria-label="URL" value="{{ ($canonicalUrl ?? null) ?: request()->url() }}"></div>
        </article>
        @if(collect($relatedPosts ?? [])->isNotEmpty())<section class="tna-article-related"><h2>{{ __('news-detail.related') }}</h2><div class="tna-article-related-grid">@foreach($relatedPosts as $item)<a class="tna-article-card" href="{{ route('site.blog.show', ['slug' => $item->slug]) }}">@if(data_get($item, 'featuredMedia.file_url'))<img loading="lazy" src="{{ $item->featuredMedia->file_url }}" alt="{{ $item->title }}">@endif<div><h3>{{ $item->title }}</h3><span>{{ __('news-detail.read') }} →</span></div></a>@endforeach</div></section>@endif
    </div><aside class="tna-article-sidebar">
        @if(collect($latestPosts ?? [])->isNotEmpty())<section class="tna-article-latest"><h2>{{ __('news-detail.latest') }}</h2>@foreach($latestPosts as $item)<a class="tna-article-latest-item" href="{{ route('site.blog.show', ['slug' => $item->slug]) }}">@if(data_get($item, 'featuredMedia.file_url'))<img loading="lazy" src="{{ $item->featuredMedia->file_url }}" alt="">@endif<div><h3>{{ $item->title }}</h3>@if($item->publish_at)<time datetime="{{ $item->publish_at->toAtomString() }}">{{ $item->publish_at->format('d/m/Y') }}</time>@endif</div></a>@endforeach<a class="tna-article-all" href="{{ route('site.blog.index') }}">{{ __('news-detail.all') }} →</a></section>@endif
        <section class="tna-article-promo"><h2>{{ __('news-detail.browse') }}</h2><p>{{ __('news-detail.browse_text') }}</p><a href="{{ route('site.catalog.search') }}">{{ __('news-detail.browse') }} →</a></section>
    </aside></div>
</div></main>


<script>
(() => {
    const body = document.querySelector('[data-tna-article-body]');
    const toc = document.querySelector('[data-tna-toc]');
    body?.querySelectorAll('h2,h3').forEach((heading, index) => {
        if (!heading.textContent.trim()) return;
        if (!heading.id) { let id = 'tna-heading-' + index; while (document.getElementById(id)) id += '-section'; heading.id = id; }
        const item = document.createElement('li'); const link = document.createElement('a');
        link.href = '#' + encodeURIComponent(heading.id); link.textContent = heading.textContent;
        item.append(link); toc.querySelector('ol').append(item); toc.hidden = false;
    });
    document.querySelector('[data-tna-copy]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget; const field = document.querySelector('[data-tna-share-url]'); const status = document.querySelector('[data-tna-copy-status]');
        try { await navigator.clipboard.writeText(field.value); status.textContent = button.dataset.copied; }
        catch { field.hidden = false; field.focus(); field.select(); status.textContent = button.dataset.failed; }
    });
})();
</script>

