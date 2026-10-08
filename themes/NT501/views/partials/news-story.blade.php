@php
    $storyUrl = route('site.blog.show', ['slug' => $post->slug]);
    $storyImage = data_get($post, 'featuredMedia.url') ?: data_get($post, 'featuredMedia.file_url') ?: data_get($post, 'image_url');
    $storySummary = \Illuminate\Support\Str::limit(trim(strip_tags((string) ($post->excerpt ?: $post->body))), $variant === 'lead' ? 220 : 160);
@endphp
<article class="nt-news-story nt-news-story--{{ $variant }}">
    <div class="nt-news-story-copy">
        <div class="nt-news-story-meta">
            @if ($post->category?->name && $post->category?->slug)
                <a href="{{ route('site.blog.category', ['slug' => $post->category->slug]) }}">{{ $post->category->name }}</a>
            @endif
            @if ($post->publish_at)
                <time datetime="{{ $post->publish_at->toIso8601String() }}">{{ $post->publish_at->format('d/m/Y') }}</time>
            @endif
        </div>
        @if ($variant === 'brief')
            <h3 class="nt-news-story-title"><a href="{{ $storyUrl }}">{{ $post->title }}</a></h3>
        @else
            <h2 class="nt-news-story-title"><a href="{{ $storyUrl }}">{{ $post->title }}</a></h2>
        @endif
        @if ($storySummary)<p class="nt-news-story-summary">{{ $storySummary }}</p>@endif
    </div>
    <a class="nt-news-story-image" href="{{ $storyUrl }}" aria-label="{{ $post->title }}">
        @if ($storyImage)
            <img src="{{ $storyImage }}" alt="{{ data_get($post, 'featuredMedia.alt_text') ?: $post->title }}" loading="{{ $variant === 'lead' ? 'eager' : 'lazy' }}" decoding="async">
        @else
            <span class="nt-news-image-placeholder" aria-hidden="true"><svg width="44" height="44" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1"><rect x="8" y="6" width="32" height="36" rx="1"/><path d="M15 14h18M15 20h18M15 26h8M15 32h8M28 26h5M28 32h5"/></svg></span>
        @endif
    </a>
    @if ($variant === 'lead')
        <a class="nt-news-read" href="{{ $storyUrl }}">{{ $newsCommon('read_more') }} <span aria-hidden="true">↗</span></a>
    @endif
</article>
