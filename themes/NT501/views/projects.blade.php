@php
    $canEditLanding = false;
    $search = (string) data_get($projectFilters ?? [], 'q', '');
    $category = (string) data_get($projectFilters ?? [], 'category', '');
    $projectItems = $listingItems ?? collect();
@endphp

@extends('theme-nt501::layout')

@section('title')
    @if ($category !== ''){{ $pageTitle }}@else @themeT('nt501.projects.title') @endif
@endsection

@section('meta_description')
    @if ($category !== ''){{ $pageDescription }}@else @themeT('nt501.projects.intro') @endif
@endsection

@push('head')
    @include('theme-nt501::partials.project-styles')
@endpush

@section('content')
    <main class="nt-project-index">
        <div class="nt-project-container">
            <nav class="nt-project-breadcrumb" aria-label="@themeT('nt501.projects.breadcrumb')">
                <a href="{{ route('site.home') }}">@themeT('nt501.nav.home')</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">@themeT('nt501.projects.title')</span>
            </nav>

            <header class="nt-project-heading">
                <div>
                    <p class="nt-project-eyebrow">@themeT('nt501.projects.eyebrow')</p>
                    <h1>@if ($category !== ''){{ $pageTitle }}@else @themeT('nt501.projects.title') @endif</h1>
                    <p class="nt-project-intro">@if ($category !== ''){{ $pageDescription }}@else @themeT('nt501.projects.intro') @endif</p>
                </div>
                <form class="nt-project-search" action="{{ route('site.projects.index') }}" method="get" role="search">
                    @if ($category !== '')
                        <input type="hidden" name="category" value="{{ $category }}">
                    @endif
                    <label class="nt-project-sr-only" for="nt-project-query">@themeT('nt501.projects.search_label')</label>
                    <input id="nt-project-query" type="search" name="q" value="{{ $search }}" placeholder="@themeT('nt501.projects.search_placeholder')">
                    <button type="submit" aria-label="@themeT('nt501.projects.search_label')">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                    </button>
                </form>
            </header>

            <div class="nt-project-toolbar">
                <span>{{ $projectItems->count() }} @themeT('nt501.projects.shown')</span>
                @if ($search !== '' || $category !== '')
                    <a href="{{ route('site.projects.index') }}">@themeT('nt501.projects.clear_filters') <span aria-hidden="true">×</span></a>
                @endif
            </div>

            <section class="nt-project-grid" aria-label="@themeT('nt501.projects.list_label')">
                @forelse ($projectItems as $project)
                    <article class="nt-project-item">
                        <a class="nt-project-image" href="{{ route('site.projects.show', ['slug' => $project->slug]) }}" aria-label="{{ $project->title }}">
                            @if ($project->featuredImage?->image_url)
                                <img src="{{ $project->featuredImage->image_url }}" alt="{{ $project->title }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async">
                            @else
                                <span class="nt-project-placeholder" aria-hidden="true">{{ $project->title }}</span>
                            @endif
                        </a>
                        <div class="nt-project-body">
                            <h2><a href="{{ route('site.projects.show', ['slug' => $project->slug]) }}">{{ $project->title }}</a></h2>
                            @if (filled($project->summary))
                                <p>{{ $project->summary }}</p>
                            @endif
                            <a class="nt-project-link" href="{{ route('site.projects.show', ['slug' => $project->slug]) }}" aria-label="@themeT('nt501.projects.view') — {{ $project->title }}">
                                @themeT('nt501.projects.view')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 12h16m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>
                    </article>
                @empty
                    <div class="nt-project-empty">
                        <h2>@themeT('nt501.projects.empty_title')</h2>
                        <p>@themeT('nt501.projects.empty_description')</p>
                        @if ($search !== '' || $category !== '')
                            <a class="nt-project-link" href="{{ route('site.projects.index') }}">@themeT('nt501.projects.clear_filters') <span aria-hidden="true">→</span></a>
                        @endif
                    </div>
                @endforelse
            </section>

            @if (method_exists($projectItems, 'hasPages') && $projectItems->hasPages())
                <div class="nt-project-pagination">
                    {{ $projectItems->links('pagination::simple-bootstrap-4') }}
                </div>
            @endif
        </div>
    </main>
@endsection
