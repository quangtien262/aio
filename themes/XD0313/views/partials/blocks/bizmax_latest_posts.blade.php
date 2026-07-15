@php
    $posts = collect($block['dynamic_items'] ?? data_get($content, 'items', []))->filter()->values();
@endphp

<section id="{{ $anchorId ?? '' }}" class="rx13-section rx13-posts-section">
    @include('theme-xd0313::partials.inline-block-edit', ['block' => $block, 'blockIndex' => $blockIndex ?? 0])
    <div class="rx13-container">
        <div class="rx13-section-heading">
            @if(filled($subtitle))<p class="rx13-eyebrow">{{ $subtitle }}</p>@endif
            <h2>{{ $title }}</h2>
        </div>
        <div class="rx13-post-grid">
            @foreach($posts as $post)
                @php $image = data_get($post, 'image_url', data_get($post, 'featured_image_url', data_get($post, 'image'))); @endphp
                <article class="rx13-post-card">
                    @if(filled($image))<img src="{{ $image }}" alt="{{ data_get($post, 'title') }}">@endif
                    <div>
                        <div class="rx13-post-card__meta"><span>{{ data_get($post, 'published_at', data_get($post, 'date', '')) }}</span><span>{{ data_get($post, 'views', '') }}</span></div>
                        <h3>{{ data_get($post, 'title') }}</h3>
                        <p>{{ data_get($post, 'excerpt', data_get($post, 'description')) }}</p>
                        <a href="{{ data_get($post, 'url', '#') }}">Xem chi tiet <span aria-hidden="true">&rarr;</span></a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
