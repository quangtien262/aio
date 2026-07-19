@php($detail = $entry ?? $post ?? null)
@extends('theme-nt502::layout')
@section('title', data_get($detail, 'title', 'Tin tức'))
@section('content')
    @include('theme-nt502::partials.content-shell', [
        'title' => data_get($detail, 'title'),
        'summary' => data_get($detail, 'excerpt'),
        'cover' => data_get($detail, 'featuredMedia.image_url', data_get($detail, 'cover_image_url')),
        'body' => data_get($detail, 'body'),
    ])
@endsection
