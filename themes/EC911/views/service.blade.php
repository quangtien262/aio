@extends('theme-ec911::layout')
@section('title', data_get($service ?? null, 'title', data_get($service ?? null, 'name', 'Dịch vụ')))
@section('content')<main><section class="ec97-content"><article class="ec97-container ec97-prose"><h1>{{ data_get($service ?? null, 'title', data_get($service ?? null, 'name')) }}</h1>{!! data_get($service ?? null, 'body', data_get($service ?? null, 'description')) !!}</article></section></main>@endsection

