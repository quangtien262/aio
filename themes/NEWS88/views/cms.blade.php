@php
    $entry = $page ?? $entry ?? null;
    $title = data_get($entry, 'title', $pageTitle ?? 'NEWS88');
@endphp
@extends('theme-news88::layout')
@section('title', $title)
@section('content')
<main class="n88-article"><article><h1>{{ $title }}</h1>@if(filled(data_get($entry, 'excerpt')))<p>{{ data_get($entry, 'excerpt') }}</p>@endif<div class="n88-article-body">{!! data_get($entry, 'body', '<p>'.e(__('NEWS88.no_content')).'</p>') !!}</div></article></main>
@endsection
