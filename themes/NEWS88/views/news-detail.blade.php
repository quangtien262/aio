@php
    $entry = $post ?? $entry ?? null;
    $title = data_get($entry, 'title', 'Tin tức');
    $cover = data_get($entry, 'featuredMedia.file_url');
    $body = data_get($entry, 'body') ?: '<p>'.e(data_get($entry, 'excerpt', __('NEWS88.no_content'))).'</p>';
@endphp
@extends('theme-news88::layout')
@section('title', $pageTitle ?? $title)
@section('content')
<main class="n88-article"><article><small>{{ data_get($entry, 'category.name') }} @if(data_get($entry, 'publish_at')) · {{ data_get($entry, 'publish_at')->format('d/m/Y') }} @endif</small><h1>{{ $title }}</h1>@if(filled(data_get($entry, 'excerpt')))<p>{{ data_get($entry, 'excerpt') }}</p>@endif @if($cover)<img class="n88-article-cover" src="{{ $cover }}" alt="{{ data_get($entry, 'featuredMedia.alt_text', $title) }}">@endif<div class="n88-article-body">{!! $body !!}</div></article></main>
@endsection
