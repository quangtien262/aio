@extends('theme-ec913::layout')
@section('title', data_get($project ?? null, 'title', 'Dự án'))
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>{{ data_get($project ?? null, 'title', 'Dự án') }}</h1></div></section><section class="ec13-content"><div class="ec13-container ec13-prose">{!! data_get($project ?? null, 'body', data_get($project ?? null, 'content')) !!}</div></section></main>
@endsection
