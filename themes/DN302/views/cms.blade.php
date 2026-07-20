@extends('theme-dn302::layout')
@section('title', data_get($page ?? null, 'title', 'Janelas'))
@section('content')
    @include('theme-dn302::partials.content-shell', ['title' => data_get($page ?? null, 'title'), 'summary' => data_get($page ?? null, 'excerpt'), 'cover' => data_get($page ?? null, 'cover_image_url'), 'body' => data_get($page ?? null, 'body')])
@endsection
