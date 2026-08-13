@php($entry = $product ?? null)
@extends('theme-news88::layout')
@section('title', data_get($entry, 'name', $pageTitle ?? 'Nội dung'))
@section('content')
<main class="n88-article"><article><h1>{{ data_get($entry, 'name') }}</h1>@if(data_get($entry, 'image_url'))<img class="n88-article-cover" src="{{ data_get($entry, 'image_url') }}" alt="{{ data_get($entry, 'name') }}">@endif<div class="n88-article-body">{!! data_get($entry, 'detail_content', e(data_get($entry, 'short_description'))) !!}</div></article></main>
@endsection
