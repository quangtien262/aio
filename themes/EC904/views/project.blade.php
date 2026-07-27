@extends('theme-ec904::layout')
@section('title', data_get($project ?? null, 'title', data_get($project ?? null, 'name', 'Bộ sưu tập')))
@section('content')<main><section class="ec94-content"><article class="ec94-container ec94-prose"><h1>{{ data_get($project ?? null, 'title', data_get($project ?? null, 'name')) }}</h1>{!! data_get($project ?? null, 'body', data_get($project ?? null, 'description')) !!}</article></section></main>@endsection
