@extends('theme-ec906::layout')
@section('title', data_get($project ?? null, 'title', data_get($project ?? null, 'name', 'Bộ sưu tập')))
@section('content')<main><section class="ec96-content"><article class="ec96-container ec96-prose"><h1>{{ data_get($project ?? null, 'title', data_get($project ?? null, 'name')) }}</h1>{!! data_get($project ?? null, 'body', data_get($project ?? null, 'description')) !!}</article></section></main>@endsection
