@extends('theme-ec914::layout')
@section('title', data_get($project ?? null, 'title', 'Dự án'))
@section('content')
<main><section class="ec14-inner-hero"><div class="ec14-container"><h1>{{ data_get($project ?? null, 'title', 'Dự án') }}</h1></div></section><section class="ec14-content"><div class="ec14-container ec14-prose">{!! data_get($project ?? null, 'body', data_get($project ?? null, 'content')) !!}</div></section></main>
@endsection
