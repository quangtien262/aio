@extends('theme-foot405::layout')
@section('title', data_get($project ?? null, 'title', 'Bộ sưu tập'))
@section('content')
<main><section class="f405-content"><article class="f405-container f405-prose"><h1>{{ data_get($project ?? null, 'title', 'Bộ sưu tập') }}</h1>{!! data_get($project ?? null, 'content', data_get($project ?? null, 'description')) !!}</article></section></main>
@endsection
