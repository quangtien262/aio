@extends('theme-ec917::layout')
@section('title', data_get($project ?? null, 'title', 'Bộ sưu tập'))
@section('content')
<main><section class="ec17-content"><article class="ec17-container ec17-prose"><h1>{{ data_get($project ?? null, 'title', 'Bộ sưu tập') }}</h1>{!! data_get($project ?? null, 'content', data_get($project ?? null, 'description')) !!}</article></section></main>
@endsection
