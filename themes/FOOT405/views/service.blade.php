@extends('theme-foot405::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="f405-content"><article class="f405-container f405-prose"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1>{!! data_get($service ?? null, 'content', data_get($service ?? null, 'description')) !!}</article></section></main>
@endsection
