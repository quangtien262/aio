@extends('theme-ec912::layout')
@section('title', data_get($project ?? null, 'title', 'Dự án'))
@section('content')
<main><section class="ec12-inner-hero"><div class="ec12-container"><h1>{{ data_get($project ?? null, 'title', 'Dự án') }}</h1></div></section><section class="ec12-content"><div class="ec12-container ec12-prose">{!! data_get($project ?? null, 'body', data_get($project ?? null, 'content')) !!}</div></section></main>
@endsection
