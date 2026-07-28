@extends('theme-ec915::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>{{ data_get($page ?? null, 'title', 'Nội dung') }}</h1></div></section><section class="ec15-content"><div class="ec15-container ec15-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
