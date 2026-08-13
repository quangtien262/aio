@php($entry = $service ?? null)
@extends('theme-news88::layout')
@section('title', data_get($entry, 'title', $pageTitle ?? 'Dịch vụ'))
@section('content')
<main class="n88-article"><article><h1>{{ data_get($entry, 'title') }}</h1>@if(data_get($entry, 'image_url'))<img class="n88-article-cover" src="{{ data_get($entry, 'image_url') }}" alt="{{ data_get($entry, 'title') }}">@endif<div class="n88-article-body">{!! data_get($entry, 'content', e(data_get($entry, 'summary'))) !!}</div></article></main>
@endsection
