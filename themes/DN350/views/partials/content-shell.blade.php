@php
    $contentTitle = $title ?? $pageTitle ?? 'Nội dung';
    $contentSummary = $summary ?? $pageDescription ?? null;
    $companyName = data_get($themeShellData ?? [], 'branding.company_name', data_get($siteProfile ?? null, 'site_name', ''));
    $relatedPostItems = collect($relatedPosts ?? [])->take(10)->values();
@endphp
<main>
    <section class="dn-inner-hero"><div class="dn-container" data-dn-reveal="up">
        @if (filled($companyName))<p class="dn-eyebrow">{{ $companyName }}</p>@endif
        <h1>{{ $contentTitle }}</h1>
        @if (filled($contentSummary))<p class="dn-inner-hero__description">{{ $contentSummary }}</p>@endif
    </div></section>
    <section class="dn-section"><article class="dn-container dn-content-card" data-dn-reveal="up">
        @if(!empty($cover ?? null))<img class="dn-content-cover" src="{{ $cover }}" alt="{{ $contentTitle }}">@endif
        <div class="dn-richtext">{!! $body ?? '' !!}</div>
    </article></section>
    @if($relatedPostItems->isNotEmpty())
        <section class="dn-section dn-related-posts" aria-labelledby="dn-related-posts-title">
            <div class="dn-container">
                <header class="dn-heading center" data-dn-reveal="up">
                    <p class="dn-eyebrow">Bài viết mới nhất</p>
                    <h2 class="dn-title" id="dn-related-posts-title">Tin liên quan</h2>
                </header>
                <div class="dn-related-posts__grid">
                    @foreach($relatedPostItems as $index => $relatedPost)
                        @php
                            $relatedImage = data_get($relatedPost, 'featuredMedia.file_url') ?: '/theme-demo/dn350/gallery-kitchen.webp';
                            $relatedUrl = route('site.blog.show', ['slug' => data_get($relatedPost, 'slug')]);
                        @endphp
                        <article class="dn-related-post" data-related-post-card style="--dn-delay:{{ ($index % 4) * 70 }}ms" data-dn-reveal="up">
                            <a class="dn-related-post__image" href="{{ $relatedUrl }}"><img src="{{ $relatedImage }}" alt="{{ data_get($relatedPost, 'title') }}"></a>
                            <div class="dn-related-post__body">
                                @if(data_get($relatedPost, 'publish_at'))<time datetime="{{ data_get($relatedPost, 'publish_at')->toDateString() }}">{{ data_get($relatedPost, 'publish_at')->format('d/m/Y') }}</time>@endif
                                <h3><a href="{{ $relatedUrl }}">{{ data_get($relatedPost, 'title') }}</a></h3>
                                @if(filled(data_get($relatedPost, 'excerpt')))<p>{{ \Illuminate\Support\Str::limit(strip_tags((string) data_get($relatedPost, 'excerpt')), 115) }}</p>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</main>
