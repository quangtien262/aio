@extends('theme-bds701::layout')
@section('title', 'Tin tức bất động sản')
@section('content')
<section class="bds-inner-hero"><div class="bds-container"><p>DELTA PLATINUM</p><h1>Tin tức bất động sản</h1></div></section>
<main class="bds-section"><div class="bds-container bds-news-grid">@foreach(($posts ?? $contentEntries ?? collect()) as $item)<article class="bds-news-card"><img src="{{ data_get($item, 'featuredImage.image_url', data_get($item, 'featuredMedia.url')) }}" alt=""><div><h3><a href="{{ route('site.blog.show', ['locale' => app()->getLocale(), 'slug' => data_get($item, 'slug')]) }}">{{ data_get($item, 'title') }}</a></h3><p>{{ \Illuminate\Support\Str::limit(strip_tags((string)data_get($item, 'excerpt')), 120) }}</p></div></article>@endforeach</div></main>
@endsection
