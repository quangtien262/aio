@extends('theme-foot404::layout')
@section('title', data_get($project ?? null, 'title', 'Bộ sưu tập'))
@section('content')
<main><section class="f404-content"><article class="f404-container f404-prose"><h1>{{ data_get($project ?? null, 'title', 'Bộ sưu tập') }}</h1>{!! data_get($project ?? null, 'content', data_get($project ?? null, 'description')) !!}</article></section></main>
@endsection
