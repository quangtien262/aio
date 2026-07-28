@extends('theme-ec915::layout')
@section('title', data_get($project ?? null, 'title', 'Dự án'))
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>{{ data_get($project ?? null, 'title', 'Dự án') }}</h1></div></section><section class="ec15-content"><div class="ec15-container ec15-prose">{!! data_get($project ?? null, 'body', data_get($project ?? null, 'content')) !!}</div></section></main>
@endsection
