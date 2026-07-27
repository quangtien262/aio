@extends('theme-ec904::layout')
@section('title', data_get($service ?? null, 'title', data_get($service ?? null, 'name', 'Dịch vụ')))
@section('content')<main><section class="ec94-content"><article class="ec94-container ec94-prose"><h1>{{ data_get($service ?? null, 'title', data_get($service ?? null, 'name')) }}</h1>{!! data_get($service ?? null, 'body', data_get($service ?? null, 'description')) !!}</article></section></main>@endsection
