@extends('theme-ec906::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
@php
    $article = $entry ?? $post;
    $readingMinutes = max(1, (int) ceil(count(preg_split('/\s+/u', strip_tags($article->body ?? ''), -1, PREG_SPLIT_NO_EMPTY)) / 220));
@endphp
@include('theme-ec906::partials.article-styles')
<main class="ec96-article"><div class="ec96-article-wrap">
    <nav class="ec96-article-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">@themeT('article.home', 'Trang chủ')</a><span>/</span><a href="{{ route('site.blog.index') }}">@themeT('article.news', 'Tin tức')</a></nav>
    <header class="ec96-article-heading">
        <span class="ec96-article-kicker">@themeT('article.news', 'Tin tức')</span>
        <h1>{{ $article->title }}</h1>
        <div class="ec96-article-meta">@if($article->publish_at)<time datetime="{{ $article->publish_at->toAtomString() }}">{{ $article->publish_at->format('d/m/Y') }}</time><span>·</span>@endif<span>{{ $readingMinutes }} @themeT('article.minutes', 'phút đọc')</span></div>
        @if($article->excerpt)<p>{{ $article->excerpt }}</p>@endif
    </header>
    <div class="ec96-article-layout"><div>
        <article class="ec96-article-paper">
            @if(data_get($article, 'featuredMedia.file_url'))<figure class="ec96-article-cover"><img src="{{ $article->featuredMedia->file_url }}" alt="{{ $article->featuredMedia->alt_text ?: $article->title }}"></figure>@endif
            <details class="ec96-article-toc" data-ec96-toc hidden open><summary>@themeT('article.toc', 'Trong bài viết này')</summary><ol></ol></details>
            <div class="ec96-article-body" data-ec96-article-body>{!! $article->body ?: $article->excerpt !!}</div>
            @if(!empty($postTags))<nav class="ec96-article-tags">@foreach($postTags as $tag)<a href="{{ $tag['url'] }}" rel="tag">#{{ $tag['name'] }}</a>@endforeach</nav>@endif
            <div class="ec96-article-share"><button type="button" data-ec96-copy data-copied="@themeT('article.copied', 'Đã sao chép liên kết')" data-failed="@themeT('article.failed', 'Hãy sao chép đường dẫn bên dưới')">@themeT('article.share', 'Sao chép liên kết') ↗</button><span role="status" data-ec96-copy-status></span><input hidden readonly data-ec96-share-url aria-label="URL" value="{{ ($canonicalUrl ?? null) ?: request()->url() }}"></div>
        </article>
        @if(collect($relatedPosts ?? [])->isNotEmpty())<section class="ec96-article-related"><h2>@themeT('article.related', 'Có thể bạn quan tâm')</h2><div class="ec96-article-related-grid">@foreach($relatedPosts as $item)<a class="ec96-article-card" href="{{ route('site.blog.show', ['slug' => $item->slug]) }}">@if(data_get($item, 'featuredMedia.file_url'))<img loading="lazy" src="{{ $item->featuredMedia->file_url }}" alt="{{ $item->title }}">@endif<div><h3>{{ $item->title }}</h3><span>@themeT('article.read', 'Đọc bài viết') →</span></div></a>@endforeach</div></section>@endif
    </div><aside class="ec96-article-sidebar">
        @if(collect($latestPosts ?? [])->isNotEmpty())<section class="ec96-article-latest"><h2>@themeT('article.latest', 'Tin mới nhất')</h2>@foreach($latestPosts as $item)<a class="ec96-article-latest-item" href="{{ route('site.blog.show', ['slug' => $item->slug]) }}">@if(data_get($item, 'featuredMedia.file_url'))<img loading="lazy" src="{{ $item->featuredMedia->file_url }}" alt="">@endif<div><h3>{{ $item->title }}</h3>@if($item->publish_at)<time datetime="{{ $item->publish_at->toAtomString() }}">{{ $item->publish_at->format('d/m/Y') }}</time>@endif</div></a>@endforeach<a class="ec96-article-all" href="{{ route('site.blog.index') }}">@themeT('article.all', 'Xem tất cả tin') →</a></section>@endif
        <section class="ec96-article-promo"><h2>@themeT('article.browse', 'Khám phá sản phẩm gia đình')</h2><p>@themeT('article.browse_text', 'Tìm những sản phẩm phù hợp với nhu cầu chăm sóc tổ ấm của bạn.')</p><a href="{{ route('site.catalog.search') }}">@themeT('article.browse', 'Khám phá sản phẩm gia đình') →</a></section>
    </aside></div>
</div></main>
@endsection
@push('scripts')
<script>
(() => {
    const body = document.querySelector('[data-ec96-article-body]');
    const toc = document.querySelector('[data-ec96-toc]');
    body?.querySelectorAll('h2,h3').forEach((heading, index) => {
        if (!heading.textContent.trim()) return;
        if (!heading.id) { let id = 'ec96-heading-' + index; while (document.getElementById(id)) id += '-section'; heading.id = id; }
        const item = document.createElement('li'); const link = document.createElement('a');
        link.href = '#' + encodeURIComponent(heading.id); link.textContent = heading.textContent;
        item.append(link); toc.querySelector('ol').append(item); toc.hidden = false;
    });
    document.querySelector('[data-ec96-copy]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget; const field = document.querySelector('[data-ec96-share-url]'); const status = document.querySelector('[data-ec96-copy-status]');
        try { await navigator.clipboard.writeText(field.value); status.textContent = button.dataset.copied; }
        catch { field.hidden = false; field.focus(); field.select(); status.textContent = button.dataset.failed; }
    });
})();
</script>
@endpush
