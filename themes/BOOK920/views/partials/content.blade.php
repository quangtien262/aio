@php
    $contentTitle = data_get($entry ?? null, 'title', $pageTitle ?? '');
    $cover = data_get($entry ?? null, 'featuredMedia.file_url') ?: data_get($entry ?? null, 'featuredImage.image_url') ?: data_get($entry ?? null, 'image_url');
    $body = data_get($entry ?? null, 'body') ?: data_get($entry ?? null, 'content');
    $intro = data_get($entry ?? null, 'excerpt') ?: data_get($entry ?? null, 'summary');
@endphp
<main class="book20-inner">
    <div class="book20-container">
        @include('theme-book920::partials.breadcrumb', ['current' => $contentTitle])
        <header class="book20-editorial-head"><p class="book20-kicker">@themeT('inner.story', 'Cùng mở những trang mới')</p><h1>{{ $contentTitle }}</h1>@if($intro)<p>{{ strip_tags($intro) }}</p>@endif
            @if(data_get($entry ?? null, 'publish_at'))<time datetime="{{ $entry->publish_at->toDateString() }}">{{ $entry->publish_at->format('d/m/Y') }}</time>@endif
        </header>
        @if($cover)<figure class="book20-cover"><img src="{{ $cover }}" alt="{{ $contentTitle }}" width="1200" height="600" fetchpriority="high"></figure>@endif
        <div class="book20-editorial-layout">
            <article class="book20-prose">@if($body){!! $body !!}@else<p>@themeT('inner.updating', 'Nội dung đang được cập nhật. Vui lòng liên hệ để được hỗ trợ.')</p>@endif
                @if(!empty($postTags))<div class="book20-tags">@foreach($postTags as $tag)<a class="book20-pill" href="{{ data_get($tag, 'url') }}">#{{ data_get($tag, 'name') }}</a>@endforeach</div>@endif
            </article>
            <aside class="book20-note"><i class="fa-solid fa-book-open" aria-hidden="true"></i><h2>@themeT('inner.keep_reading', 'Tiếp nối cảm hứng đọc')</h2><p>@themeT('inner.find_text', 'Khám phá thêm những chủ đề và đầu sách phù hợp với sở thích đọc của bạn.')</p><a class="book20-button" href="{{ route('site.catalog.search') }}">@themeT('inner.explore', 'Khám phá tủ sách') &rarr;</a><a class="book20-text-link" href="{{ route('site.contact') }}">@themeT('BOOK920.contact', 'Liên hệ') &rarr;</a></aside>
        </div>
        @if(in_array($contentType ?? '', ['service', 'project']) && count(data_get($entry ?? null, 'images', [])) > 1)<div class="book20-media-grid">@foreach(data_get($entry, 'images', []) as $photo)@if(data_get($photo, 'image_url'))<a href="{{ $photo->image_url }}" target="_blank" rel="noopener"><img src="{{ $photo->image_url }}" alt="{{ $photo->alt_text ?: $contentTitle }}" loading="lazy"></a>@endif @endforeach</div>@endif
        <div class="book20-end-links"><a href="{{ route('site.blog.index') }}">@themeT('BOOK920.news', 'Tin tức') &rarr;</a><a href="{{ route('site.services.index') }}">@themeT('inner.services', 'Dịch vụ') &rarr;</a></div>
    </div>
</main>
