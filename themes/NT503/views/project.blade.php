@php($detail = $entry ?? $project ?? null)
@extends('theme-nt503::layout')
@section('title', data_get($detail, 'title', 'Dự án'))
@section('content')
    @include('theme-nt503::partials.content-shell', [
        'title' => data_get($detail, 'title'),
        'summary' => data_get($detail, 'summary'),
        'cover' => data_get($detail, 'featuredImage.image_url', data_get($detail, 'cover_image_url')),
        'body' => data_get($detail, 'content', data_get($detail, 'body')),
    ])
@endsection
