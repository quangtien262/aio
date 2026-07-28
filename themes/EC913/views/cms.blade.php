@extends('theme-ec913::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
<main><section class="ec13-inner-hero"><div class="ec13-container"><h1>{{ data_get($page ?? null, 'title', 'Nội dung') }}</h1></div></section><section class="ec13-content"><div class="ec13-container ec13-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
