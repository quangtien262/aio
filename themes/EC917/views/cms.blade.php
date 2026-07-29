@extends('theme-ec917::layout')
@section('title', data_get($page ?? null, 'title', 'Thông tin'))
@section('content')
<main><section class="ec17-inner-hero"><div class="ec17-container"><h1>{{ data_get($page ?? null, 'title', 'Thông tin') }}</h1></div></section><section class="ec17-content"><div class="ec17-container ec17-prose">{!! data_get($pageModel ?? $page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
