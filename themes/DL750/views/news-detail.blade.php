@extends('theme-dl750::layout')
@section('content')
@php
    $articleTitle = data_get($entry ?? null, 'title', $pageTitle ?? '');
    $articleExcerpt = trim(strip_tags((string) data_get($entry ?? null, 'excerpt', '')));
    $articleBody = (string) (data_get($entry ?? null, 'body') ?: data_get($entry ?? null, 'content', ''));
    $articleImage = data_get($entry ?? null, 'featuredMedia.file_url') ?: data_get($entry ?? null, 'image_url');
    $articleAlt = data_get($entry ?? null, 'featuredMedia.alt_text') ?: $articleTitle;
    $articleDate = data_get($entry ?? null, 'publish_at');
    $articleCategory = data_get($entry ?? null, 'category.name');
    $articleMinutes = max(1, (int) ceil(count(preg_split('/\s+/u', trim(strip_tags($articleBody)), -1, PREG_SPLIT_NO_EMPTY) ?: []) / 220));
@endphp
@include('theme-dl750::partials.article-styles')
<main class="dl-inner dl-article-page">
    <div class="dl-wrap">
        <nav class="dl-article-breadcrumb" aria-label="@themeT('article.breadcrumb', 'Điều hướng')">
            <a href="{{ route('site.home') }}">@themeT('home', 'Trang chủ')</a><span aria-hidden="true">/</span>
            <a href="{{ route('site.blog.index') }}">@themeT('news', 'Tin tức')</a><span aria-hidden="true">/</span>
            <span aria-current="page">{{ $articleTitle }}</span>
        </nav>
        <article>
            <header class="dl-article-heading">
                <p class="dl-article-kicker">@themeT('article.journal', 'Góc chia sẻ hành trình')</p>
                @if($articleCategory)<span class="dl-article-category">{{ $articleCategory }}</span>@endif
                <h1>{{ $articleTitle }}</h1>
                @if($articleExcerpt)<p class="dl-article-intro">{{ $articleExcerpt }}</p>@endif
                <div class="dl-article-meta">
                    @if($articleDate)<time datetime="{{ $articleDate->toDateString() }}"><i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ $articleDate->format('d/m/Y') }}</time>@endif
                    @if(trim(strip_tags($articleBody)) !== '')<span><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $articleMinutes }} @themeT('article.minutes', 'phút đọc · ước tính')</span>@endif
                    <a href="#dl-article-content">@themeT('article.start', 'Đọc bài viết') <span aria-hidden="true">&darr;</span></a>
                </div>
            </header>
            @if($articleImage)<figure class="dl-article-cover"><img src="{{ $articleImage }}" alt="{{ $articleAlt }}" width="1440" height="800" fetchpriority="high"></figure>@endif
            <div class="dl-article-layout">
                <div class="dl-article-main">
                    <div id="dl-article-content" class="dl-prose dl-article-content">
                        @if(trim($articleBody) !== ''){!! $articleBody !!}@else<p>@themeT('article.empty', 'Nội dung bài viết đang được cập nhật. Mời bạn khám phá thêm các bài viết khác.')</p>@endif
                    </div>
                    @if(!empty($postTags))
                        <nav class="dl-article-tags" aria-label="@themeT('article.tags', 'Chủ đề bài viết')">
                            @foreach($postTags as $tag)<a href="{{ data_get($tag, 'url') }}">#{{ data_get($tag, 'name') }}</a>@endforeach
                        </nav>
                    @endif
                    <footer class="dl-article-end">
                        <span><i class="fa-solid fa-mountain-sun" aria-hidden="true"></i>@themeT('article.end', 'Thêm cảm hứng cho hành trình của bạn')</span>
                        <a href="{{ route('site.blog.index') }}">@themeT('article.back', 'Trở về tin tức') <span aria-hidden="true">&rarr;</span></a>
                    </footer>
                </div>
                <aside class="dl-article-sidebar">
                    <nav class="dl-article-toc" data-dl-article-toc hidden aria-label="@themeT('article.toc', 'Trong bài viết này')">
                        <p class="dl-article-kicker">@themeT('article.guide', 'Dẫn lối nội dung')</p>
                        <h2>@themeT('article.toc', 'Trong bài viết này')</h2>
                        <ol></ol>
                    </nav>
                    <div class="dl-article-advice">
                        <i class="fa-solid fa-compass" aria-hidden="true"></i>
                        <p class="dl-article-kicker">@themeT('article.next', 'Từ cảm hứng đến trải nghiệm')</p>
                        <h2>@themeT('article.advice_title', 'Bạn đang lên kế hoạch cho chuyến đi?')</h2>
                        <p>@themeT('article.advice_text', 'Chia sẻ điểm đến và nhu cầu để cùng tìm dịch vụ, trang bị phù hợp.')</p>
                        <a class="dl-primary" href="{{ route('site.contact') }}">@themeT('article.consult', 'Trao đổi cùng chúng tôi') <span aria-hidden="true">&rarr;</span></a>
                    </div>
                </aside>
            </div>
        </article>
        @if(!empty($dl750RelatedPosts))
            <section class="dl-article-related" aria-labelledby="dl-article-related-title">
                <div class="dl-article-related-heading"><div><p class="dl-article-kicker">@themeT('article.more', 'Tiếp nối hành trình')</p><h2 id="dl-article-related-title">@themeT('article.related', 'Có thể bạn quan tâm')</h2></div><a href="{{ route('site.blog.index') }}">@themeT('article.all', 'Tất cả bài viết') &rarr;</a></div>
                <div class="dl-article-related-grid">
                    @foreach($dl750RelatedPosts as $related)
                        <article class="dl-article-card">
                            <a class="dl-article-card-image" href="{{ $related['url'] }}" aria-label="{{ $related['title'] }}">
                                @if($related['image'])<img src="{{ $related['image'] }}" alt="{{ $related['image_alt'] }}" width="600" height="400" loading="lazy">@else<i class="fa-solid fa-mountain-sun" aria-hidden="true"></i>@endif
                            </a>
                            <div><h3><a href="{{ $related['url'] }}">{{ $related['title'] }}</a></h3>@if($related['excerpt'])<p>{{ strip_tags($related['excerpt']) }}</p>@endif<a class="dl-article-read" href="{{ $related['url'] }}">@themeT('article.read', 'Đọc tiếp') <span aria-hidden="true">&rarr;</span></a></div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</main>
@endsection
@push('scripts')
<script>
(() => {
    const content = document.getElementById('dl-article-content');
    const toc = document.querySelector('[data-dl-article-toc]');
    if (!content || !toc) return;
    const headings = [...content.querySelectorAll('h2,h3')].filter(heading => heading.textContent.trim());
    if (headings.length < 2) return;
    const list = toc.querySelector('ol');
    headings.forEach((heading, index) => {
        if (!heading.id) {
            let id = 'dl-article-section-' + (index + 1);
            while (document.getElementById(id)) id += '-heading';
            heading.id = id;
        }
        const item = document.createElement('li');
        if (heading.tagName === 'H3') item.classList.add('dl-article-toc-sub');
        const link = document.createElement('a');
        link.href = '#' + encodeURIComponent(heading.id);
        link.textContent = heading.textContent.trim();
        link.addEventListener('click', () => {
            list.querySelectorAll('a').forEach(anchor => anchor.removeAttribute('aria-current'));
            link.setAttribute('aria-current', 'location');
        });
        item.append(link);
        list.append(item);
    });
    toc.hidden = false;
})();
</script>
@endpush
