<nav class="xd323-project-breadcrumb" aria-label="{{ __('xd0323-project.breadcrumb') }}">
    <a href="{{ route('site.home') }}">{{ __('xd0323-project.home') }}</a><span aria-hidden="true">/</span>
    <a href="{{ route('site.projects.index') }}">{{ __('xd0323-project.projects') }}</a><span aria-hidden="true">/</span>
    <span>{{ $entry->title }}</span>
</nav>
<article class="xd323-project-article">
    <header @class(['xd323-project-hero', 'xd323-project-hero--with-image' => filled($entry->featuredImage?->image_url)])>
        <div class="xd323-project-intro">
            <span class="xd323-project-kicker">{{ __('xd0323-project.projects') }}</span>
            <h1>{{ $entry->title }}</h1>
            @if(filled($entry->summary))<p>{{ $entry->summary }}</p>@endif
            @if(filled($entry->category?->name))<span class="xd323-project-category">{{ $entry->category->name }}</span>@endif
        </div>
        @if(filled($entry->featuredImage?->image_url))
            <img class="xd323-project-cover" src="{{ $entry->featuredImage->image_url }}" alt="{{ $entry->featuredImage->alt_text ?: $entry->title }}">
        @endif
    </header>
    @if(filled($entry->content))
        <div class="xd323-project-body">
            <h2 class="xd323-project-body-title">{{ __('xd0323-project.overview') }}</h2>
            <div class="xd-rich-content">{!! $entry->content !!}</div>
        </div>
    @endif
    @if($entry->images->count() > 1)
        <div class="xd323-project-gallery">
            @foreach($entry->images as $image)
                <figure><img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}" loading="lazy">
                    @if(filled($image->caption))<figcaption>{{ $image->caption }}</figcaption>@endif
                </figure>
            @endforeach
        </div>
    @endif
</article>
