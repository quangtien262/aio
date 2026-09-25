@extends('theme-foot403::layout')
@php
    $cmsContent = $entry ?? $cmsEntry ?? null;
    $cmsTitle = data_get($cmsContent, 'title') ?: data_get($cmsContent, 'name', $pageTitle ?? '');
    $cmsBody = data_get($cmsContent, 'body') ?: data_get($cmsContent, 'content') ?: data_get($cmsContent, 'description', '');
@endphp
@section('title', $cmsTitle)
@section('content')
    @if(($contentType ?? '') === 'post' && $cmsContent instanceof \App\Models\CmsPost)
        @include('themes.common.news-detail', ['entry' => $cmsContent])
    @else
        <section class="dr-section dr-cms"><div class="dr-container"><h1>{{ $cmsTitle }}</h1><div>{!! $cmsBody !!}</div></div></section>
    @endif
@endsection
