@extends('theme-ec917::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="ec17-content"><article class="ec17-container ec17-prose"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1>{!! data_get($service ?? null, 'content', data_get($service ?? null, 'description')) !!}</article></section></main>
@endsection
