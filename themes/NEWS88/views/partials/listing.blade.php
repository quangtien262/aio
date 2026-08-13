@php
    $source = $listingItems ?? $entries ?? $posts ?? $products ?? $services ?? $projects ?? [];
    $entries = is_object($source) && method_exists($source, 'getCollection') ? $source->getCollection() : collect($source);
    $heading = $pageTitle ?? $title ?? __('NEWS88.latest');
    $fallback = '/theme-demo/news88/hero-mekong.png';
@endphp
<main class="n88-inner"><div class="n88-container">
    <header class="n88-inner-head"><h1>{{ $heading }}</h1>@if(filled($pageDescription ?? null))<p>{{ $pageDescription }}</p>@endif</header>
    <div class="n88-list">
        @forelse($entries as $item)
            @php
                $slug = data_get($item, 'slug');
                $url = data_get($item, 'url') ?: match($contentType ?? null) {
                    'posts' => $slug ? route('site.blog.show', ['slug' => $slug]) : '#',
                    'services' => $slug ? route('site.services.show', ['slug' => $slug]) : '#',
                    'projects' => $slug ? route('site.projects.show', ['slug' => $slug]) : '#',
                    default => $slug && route()->has('site.catalog.product') ? route('site.catalog.product', ['slug' => $slug]) : '#',
                };
                $image = data_get($item, 'image') ?: data_get($item, 'image_url') ?: data_get($item, 'featuredMedia.file_url') ?: data_get($item, 'featuredImage.image_url') ?: $fallback;
            @endphp
            <article><a href="{{ $url }}"><img src="{{ $image }}" alt="{{ data_get($item, 'title', data_get($item, 'name')) }}"></a><div><h2><a href="{{ $url }}">{{ data_get($item, 'title', data_get($item, 'name')) }}</a></h2><p>{{ data_get($item, 'excerpt', data_get($item, 'summary', data_get($item, 'description'))) }}</p><a href="{{ $url }}">@themeT('NEWS88.read_more', 'Đọc tiếp') →</a></div></article>
        @empty<p>@themeT('NEWS88.no_content', 'Nội dung đang được cập nhật.')</p>@endforelse
    </div>
</div></main>
