@extends('theme-shop606::layout')
@section('title', $entry->title)
@section('content')
@include('theme-shop606::partials.service-styles')
<main class="s606-service-page"><div class="s606-service-wrap">
    <nav class="s606-service-breadcrumb"><a href="{{ route('site.home') }}">{{ __('news-detail.home') }}</a><span>/</span><a href="{{ route('site.projects.index') }}">{{ app()->getLocale() === 'vi' ? 'Dự án' : 'Projects' }}</a></nav>
    <article class="s606-service-detail">
        <header class="s606-service-heading"><h1>{{ $entry->title }}</h1>@if($entry->excerpt)<p>{{ $entry->excerpt }}</p>@endif</header>
        @if($entry->featuredImage?->image_url)<img class="s606-service-cover" src="{{ $entry->featuredImage->image_url }}" alt="{{ $entry->featuredImage->alt_text ?: $entry->title }}">@endif
        <div class="s606-service-prose">{!! $entry->body ?: $entry->content !!}</div>
    </article>
</div></main>
@endsection
