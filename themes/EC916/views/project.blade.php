@extends('theme-ec916::layout')
@section('title', data_get($project ?? null, 'title', 'Dự án'))
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>{{ data_get($project ?? null, 'title', 'Dự án') }}</h1></div></section><section class="ec16-content"><div class="ec16-container ec16-prose">{!! data_get($project ?? null, 'body', data_get($project ?? null, 'content')) !!}</div></section></main>
@endsection
