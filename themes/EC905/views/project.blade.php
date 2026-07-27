@extends('theme-ec905::layout')
@section('title', data_get($project ?? null, 'title', data_get($project ?? null, 'name', 'Dự án')))
@section('content')<main><section class="ec95-content"><article class="ec95-container ec95-prose"><h1>{{ data_get($project ?? null, 'title', data_get($project ?? null, 'name')) }}</h1>{!! data_get($project ?? null, 'body', data_get($project ?? null, 'description')) !!}</article></section></main>@endsection
