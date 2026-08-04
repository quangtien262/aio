@php($detail = $entry ?? $page ?? null)
@extends('theme-nt504::layout')
@section('title', data_get($detail, 'title', 'NT504'))
@section('content')
    @include('theme-nt504::partials.content-shell', [
        'title' => data_get($detail, 'title'),
        'summary' => data_get($detail, 'excerpt'),
        'cover' => data_get($detail, 'featuredMedia.image_url', data_get($detail, 'cover_image_url')),
        'body' => data_get($detail, 'body'),
    ])
@endsection
