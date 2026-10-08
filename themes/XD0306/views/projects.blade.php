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
@extends('theme-xd0306::layout')

@section('content')
@include('theme-xd0306::partials.projects-page-styles')
<main class="xd6-projects-page">
    <div class="xd6-projects-container">
        <nav class="xd6-projects-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ __('storefront.menu.home') }}</a><span>/</span><span aria-current="page">{{ $pageTitle ?? __('xd0306-projects.title') }}</span></nav>
        <header class="xd6-projects-heading">
            <p class="xd6-projects-kicker">{{ __('xd0306-projects.kicker') }}</p>
            <h1>{{ $pageTitle ?? __('xd0306-projects.title') }}</h1>
            <p>{{ $currentProjectCategory->description ?? __('xd0306-projects.intro') }}</p>
        </header>
        <section class="xd6-projects-grid" aria-label="{{ __('xd0306-projects.title') }}">
            @forelse($listingItems as $project)
                @php($projectUrl = route('site.projects.show', ['slug' => $project->slug]))
                <article class="xd6-project-card">
                    @if($project->featuredImage?->image_url)
                        <a class="xd6-project-image" href="{{ $projectUrl }}" aria-label="{{ $project->title }}"><img src="{{ $project->featuredImage->image_url }}" alt="{{ $project->featuredImage->alt_text ?: $project->title }}" loading="lazy"></a>
                    @endif
                    <div class="xd6-project-copy">
                        <h2><a href="{{ $projectUrl }}"><span>{{ $project->title }}</span><span class="xd6-project-arrow" aria-hidden="true">↗</span></a></h2>
                        @if(filled($project->summary))<p>{{ \Illuminate\Support\Str::limit(strip_tags($project->summary), 180) }}</p>@endif
                    </div>
                </article>
            @empty
                <p class="xd6-projects-empty">{{ __('xd0306-projects.empty') }}</p>
            @endforelse
        </section>
        @if(method_exists($listingItems, 'links'))<div class="xd6-projects-pagination">{{ $listingItems->links() }}</div>@endif
    </div>
</main>
@endsection
