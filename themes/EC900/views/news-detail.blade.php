@extends('theme-ec900::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
@include('theme-ec900::partials.content-shell', ['title' => data_get($post ?? null, 'title'), 'summary' => data_get($post ?? null, 'excerpt'), 'cover' => data_get($post ?? null, 'cover_image_url'), 'body' => data_get($post ?? null, 'body')])
@endsection
