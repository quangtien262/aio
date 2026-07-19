@php($detail = $entry ?? $service ?? null)
@extends('theme-nt502::layout')
@section('title', data_get($detail, 'title', 'Dịch vụ'))
@section('content')
    @include('theme-nt502::partials.content-shell', [
        'title' => data_get($detail, 'title'),
        'summary' => data_get($detail, 'summary', data_get($detail, 'excerpt')),
        'cover' => data_get($detail, 'featuredImage.image_url', data_get($detail, 'cover_image_url')),
        'body' => data_get($detail, 'body', data_get($detail, 'content')),
    ])
@endsection
