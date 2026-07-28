@extends('theme-ec913::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>{{ data_get($post ?? null, 'title', 'Tin tức') }}</h1></div></section><section class="ec13-content"><article class="ec13-container ec13-prose">{!! data_get($post ?? null, 'body') !!}</article></section></main>
@endsection
