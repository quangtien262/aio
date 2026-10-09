@php
    $canEditLanding = false;
    $shell = $themeShellData ?? $themeHomeData ?? [];
    $navItems = collect(data_get($shell, 'top_menu', data_get($menus ?? [], 'primary-navigation', data_get($menus ?? [], 'primary', []))))
        ->filter(fn ($item) => is_array($item) && filled($item['label'] ?? $item['title'] ?? null))
        ->map(fn ($item) => [
            'label' => $item['label'] ?? $item['title'],
            'href' => \App\Support\FrontendRouteUrl::localized($item['url'] ?? $item['href'] ?? '#'),
        ])->values();
    if (! $navItems->contains(fn ($item) => rtrim($item['href'], '/') === rtrim(route('site.home'), '/'))) {
        $navItems->prepend(['label' => __('storefront.menu.home'), 'href' => route('site.home')]);
    }
@endphp
@extends('theme-xd0305::layout')

@section('content')
@include('theme-xd0305::partials.projects-page-styles')
<main class="xd305-projects-page">
    <div class="xd305-projects-container">
        <nav class="xd305-projects-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ __('storefront.menu.home') }}</a><span>/</span><span aria-current="page">{{ $pageTitle ?? __('xd0305-projects.title') }}</span></nav>
        <header class="xd305-projects-heading">
            <p class="xd305-projects-kicker">{{ __('xd0305-projects.kicker') }}</p>
            <h1>{{ $pageTitle ?? __('xd0305-projects.title') }}</h1>
            <p>{{ $currentProjectCategory->description ?? __('xd0305-projects.intro') }}</p>
        </header>
        <section class="xd305-projects-grid" aria-label="{{ __('xd0305-projects.title') }}">
            @forelse($listingItems as $project)
                @php($projectUrl = route('site.projects.show', ['slug' => $project->slug]))
                <article class="xd305-project-card">
                    @if($project->featuredImage?->image_url)
                        <a class="xd305-project-image" href="{{ $projectUrl }}" aria-label="{{ $project->title }}"><img src="{{ $project->featuredImage->image_url }}" alt="{{ $project->featuredImage->alt_text ?: $project->title }}" loading="lazy"></a>
                    @endif
                    <div class="xd305-project-copy">
                        <h2><a href="{{ $projectUrl }}"><span>{{ $project->title }}</span><span class="xd305-project-arrow" aria-hidden="true">↗</span></a></h2>
                        @if(filled($project->summary))<p>{{ \Illuminate\Support\Str::limit(strip_tags($project->summary), 180) }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="xd305-projects-empty">{{ __('xd0305-projects.empty') }}</p>
            @endforelse
        </section>
        @if(method_exists($listingItems, 'links'))<div class="xd305-projects-pagination">{{ $listingItems->links() }}</div>@endif
    </div>
</main>
@endsection
