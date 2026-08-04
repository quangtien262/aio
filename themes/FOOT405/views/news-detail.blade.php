@extends('theme-foot405::layout')
@section('title', data_get($post ?? null, 'title', 'Bài viết'))
@section('content')
<main><section class="f405-content"><article class="f405-container f405-prose"><h1>{{ data_get($post ?? null, 'title', 'Bài viết') }}</h1>@if(data_get($post ?? null, 'image'))<img src="{{ data_get($post, 'image') }}" alt="{{ data_get($post, 'title') }}">@endif{!! data_get($postModel ?? $post ?? null, 'body', data_get($post ?? null, 'content')) !!}</article></section></main>
@endsection
