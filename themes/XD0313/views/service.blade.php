@php
    $shell = $themeShellData ?? [];
    $branding = (array) data_get($shell, 'branding', data_get($siteProfile ?? [], 'branding', []));
    $hotline = $branding['support_hotline'] ?? '';
    $supportEmail = $branding['support_email'] ?? '';
    $supportAddress = $branding['support_location'] ?? '';
    $normalizeNavItem = function (array $item) use (&$normalizeNavItem): array {
        return [
            'label' => $item['label'] ?? $item['title'] ?? '',
            'href' => \App\Support\FrontendRouteUrl::localized($item['url'] ?? $item['href'] ?? '#'),
            'target' => $item['target'] ?? '_self',
            'children' => collect($item['children'] ?? [])->filter(fn ($child) => is_array($child))->map($normalizeNavItem)->all(),
        ];
    };
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map($normalizeNavItem)->values();
    if (! $navItems->contains(fn ($item) => rtrim($item['href'], '/') === rtrim(route('site.home'), '/'))) {
        $navItems->prepend(['label' => __('XD0313.service.home'), 'href' => route('site.home'), 'target' => '_self', 'children' => []]);
    }
    $productNav = collect(data_get($menus ?? [], 'product-navigation', []))->filter(fn ($item) => is_array($item))->map($normalizeNavItem)->all();
    if ($productNav !== []) {
        $productIndex = $navItems->search(fn ($item) => in_array(mb_strtolower($item['label']), ['sản phẩm', 'san pham', 'products', 'product'], true));
        if ($productIndex === false) {
            $navItems->splice(1, 0, [['label' => __('XD0313.service.products'), 'href' => route('site.catalog.search'), 'target' => '_self', 'children' => $productNav]]);
        } else {
            $navItems = $navItems->map(fn ($item, $index) => $index === $productIndex && empty($item['children']) ? array_replace($item, ['children' => $productNav]) : $item);
        }
    }
    $canEditLanding = false;
    $footerNewsletterSource = 'theme-footer-XD0313-cms';
    $featuredImage = $entry->featuredImage;
    $gallery = $entry->images->filter(fn ($image) => $image->id !== $featuredImage?->id);
@endphp

@extends('theme-xd0313::layout')

@push('head')
    @include('theme-xd0313::partials.service-styles')
@endpush

@section('content')
<main class="rx13-service-page">
    <div class="rx13-container">
        <nav class="rx13-service-breadcrumb" aria-label="{{ __('XD0313.service.breadcrumb') }}">
            <a href="{{ route('site.home') }}">{{ __('XD0313.service.home') }}</a><span aria-hidden="true">/</span>
            <a href="{{ route('site.services.index') }}">{{ __('XD0313.service.services') }}</a><span aria-hidden="true">/</span>
            <span aria-current="page">{{ $entry->title }}</span>
        </nav>
        <div class="rx13-service-grid">
            <article class="rx13-service-article" aria-labelledby="rx13-service-title">
                <header class="rx13-service-heading">
                    <p class="rx13-service-eyebrow">{{ __('XD0313.service.services') }}</p>
                    <h1 id="rx13-service-title">{{ $entry->title }}</h1>
                    @if(filled($entry->excerpt))<p class="rx13-service-summary">{{ $entry->excerpt }}</p>@endif
                    <a class="rx13-service-cta" href="#service-contact">{{ __('XD0313.service.consult') }} <span aria-hidden="true">↗</span></a>
                </header>
                @if(filled($featuredImage?->image_url))
                    <figure class="rx13-service-cover"><img src="{{ $featuredImage->image_url }}" alt="{{ $featuredImage->alt_text ?: $entry->title }}" fetchpriority="high"></figure>
                @endif
                <div class="rx13-service-content">
                    <div class="rx13-service-rich">
                        @if(filled($entry->body)){!! $entry->body !!}@else<p>{{ __('XD0313.service.updating') }}</p>@endif
                    </div>
                    @if($gallery->isNotEmpty())
                        <div class="rx13-service-gallery" aria-label="{{ __('XD0313.service.gallery') }}">
                            @foreach($gallery as $image)
                                <figure><img src="{{ $image->image_url }}" alt="{{ $image->alt_text ?: $entry->title }}" loading="lazy">@if(filled($image->caption))<figcaption>{{ $image->caption }}</figcaption>@endif</figure>
                            @endforeach
                        </div>
                    @endif
                    <div class="rx13-service-next">
                        <div><h2>{{ __('XD0313.service.next_title') }}</h2><p>{{ __('XD0313.service.next_description') }}</p></div>
                        <a href="#service-contact">{{ __('XD0313.service.consult') }} <span aria-hidden="true">→</span></a>
                    </div>
                </div>
            </article>

            <aside class="rx13-service-sidebar" aria-label="{{ __('XD0313.service.sidebar') }}">
                @if(($latestServices ?? collect())->isNotEmpty())
                    <section class="rx13-service-panel" aria-labelledby="rx13-service-list-title">
                        <div class="rx13-service-panel-heading"><h2 id="rx13-service-list-title">{{ __('XD0313.service.service_list') }}</h2><span>{{ $latestServices->count() }}</span></div>
                        <ul class="rx13-service-list">
                            @foreach($latestServices as $service)
                                <li><a href="{{ \App\Support\FrontendRouteUrl::service($service->slug, app()->getLocale()) }}" @if($service->id === $entry->id) aria-current="page" @endif><span>{{ $service->title }}</span><span aria-hidden="true">↗</span></a></li>
                            @endforeach
                        </ul>
                        <a class="rx13-service-view-all" href="{{ route('site.services.index') }}">{{ __('XD0313.service.all_services') }} <span aria-hidden="true">→</span></a>
                    </section>
                @endif

                <section class="rx13-service-help">
                    <span class="rx13-service-help-mark" aria-hidden="true">↗</span>
                    <h2>{{ __('XD0313.service.help_title') }}</h2>
                    <p>{{ __('XD0313.service.help_description') }}</p>
                    @if(filled($hotline ?? null))<a class="rx13-service-phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $hotline) }}">{{ $hotline }}</a>@endif
                    <a class="rx13-service-cta" href="#service-contact">{{ __('XD0313.service.send') }} <span aria-hidden="true">→</span></a>
                </section>

                @if(($latestPosts ?? collect())->isNotEmpty())
                    <section class="rx13-service-panel" aria-labelledby="rx13-service-news-title">
                        <div class="rx13-service-panel-heading"><h2 id="rx13-service-news-title">{{ __('XD0313.service.latest_news') }}</h2></div>
                        <ul class="rx13-service-news">
                            @foreach($latestPosts as $post)
                                <li><a href="{{ \App\Support\FrontendRouteUrl::post($post->slug, app()->getLocale()) }}">
                                    @if(filled($post->featuredMedia?->file_url))<img src="{{ $post->featuredMedia->file_url }}" alt="" loading="lazy">@endif
                                    <div><h3>{{ $post->title }}</h3>@if($post->publish_at)<time datetime="{{ $post->publish_at->toDateString() }}">{{ $post->publish_at->format('d/m/Y') }}</time>@endif</div>
                                </a></li>
                            @endforeach
                        </ul>
                        <a class="rx13-service-view-all" href="{{ route('site.blog.index') }}">{{ __('XD0313.service.all_news') }} <span aria-hidden="true">→</span></a>
                    </section>
                @endif
            </aside>
        </div>
    </div>
    @include('theme-xd0313::partials.blocks.landing_contact', [
        'block' => ['id' => 'service-'.$entry->id, 'block_type' => 'landing_contact'],
        'anchor' => 'service-contact', 'editButton' => '', 'data' => [], 'content' => [],
        'contactSubject' => $entry->title,
    ])
</main>
@include('themes.common.detail-recommendations', ['recommendationLayout' => 'sections', 'recommendationKinds' => ['products']])
@endsection

@push('scripts')
<script>
    document.querySelector('[data-rx13-contact-feedback]')?.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'center'});
</script>
@endpush
