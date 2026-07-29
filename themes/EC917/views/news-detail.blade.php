@extends('theme-ec917::layout')
@section('title', data_get($post ?? null, 'title', 'Bài viết'))
@section('content')
<main><section class="ec17-content"><article class="ec17-container ec17-prose"><h1>{{ data_get($post ?? null, 'title', 'Bài viết') }}</h1>@if(data_get($post ?? null, 'image'))<img src="{{ data_get($post, 'image') }}" alt="{{ data_get($post, 'title') }}" style="width:100%;max-height:620px;object-fit:cover">@endif{!! data_get($postModel ?? $post ?? null, 'body', data_get($post ?? null, 'content')) !!}</article></section></main>
@endsection
