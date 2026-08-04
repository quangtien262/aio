@extends('theme-foot404::layout')
@section('title', data_get($page ?? null, 'title', 'Thông tin'))
@section('content')
<main><section class="f404-inner-hero"><div class="f404-container"><h1>{{ data_get($page ?? null, 'title', 'Thông tin') }}</h1></div></section><section class="f404-content"><div class="f404-container f404-prose">{!! data_get($pageModel ?? $page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
