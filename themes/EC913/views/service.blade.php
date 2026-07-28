@extends('theme-ec913::layout')
@section('title', data_get($service ?? null, 'title', 'Dịch vụ'))
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>{{ data_get($service ?? null, 'title', 'Dịch vụ') }}</h1></div></section><section class="ec13-content"><div class="ec13-container ec13-prose">{!! data_get($service ?? null, 'body', data_get($service ?? null, 'content')) !!}</div></section></main>
@endsection
