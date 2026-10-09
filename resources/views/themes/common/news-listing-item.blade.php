@php
    $newsUrl = route('site.blog.show', ['slug' => $post->slug]);
    $newsImage = data_get($post, 'featuredMedia.url') ?: data_get($post, 'featuredMedia.file_url') ?: data_get($post, 'image_url');
    $newsSummary = \Illuminate\Support\Str::limit(strip_tags((string) ($post->excerpt ?: $post->body)), $newsItemLayout === 'lead' ? 230 : 150);
@endphp
<article class="tnl-card tnl-card--{{ $newsItemLayout }}" data-news-item="{{ $newsItemLayout }}">
    <a class="tnl-image" href="{{ $newsUrl }}" aria-label="{{ $post->title }}">
        @if($newsImage)
            <img src="{{ $newsImage }}" alt="{{ $post->title }}" @if($newsItemLayout !== 'lead') loading="lazy" @endif>
        @else
            <span aria-hidden="true">&#9776;</span>
        @endif
    </a>
    <div class="tnl-body">
        <div class="tnl-meta">
            @if(filled(data_get($post, 'category.name')))<span>{{ $post->category->name }}</span>@endif
            @if($post->publish_at)<time datetime="{{ $post->publish_at->toDateString() }}">{{ $post->publish_at->format('d/m/Y') }}</time>@endif
        </div>
        <h2><a href="{{ $newsUrl }}">{{ $post->title }}</a></h2>
        @if($newsSummary && $newsTheme !== 'NEWS88')<p>{{ $newsSummary }}</p>@endif
        <a class="tnl-more" href="{{ $newsUrl }}">{{ $newsText('read_more') }} <span aria-hidden="true">→</span></a>
    </div>
</article>
