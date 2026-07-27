@extends('theme-ec908::layout')
@section('title', data_get($service ?? null, 'title', data_get($service ?? null, 'name', 'Dịch vụ')))
@section('content')<main><section class="ec98-content"><article class="ec98-container ec98-prose"><h1>{{ data_get($service ?? null, 'title', data_get($service ?? null, 'name')) }}</h1>{!! data_get($service ?? null, 'body', data_get($service ?? null, 'description')) !!}</article></section></main>@endsection

