@extends('theme-foot404::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="f404-content"><article class="f404-container f404-prose"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1>{!! data_get($service ?? null, 'content', data_get($service ?? null, 'description')) !!}</article></section></main>
@endsection
