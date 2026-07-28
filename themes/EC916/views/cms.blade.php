@extends('theme-ec916::layout')
@section('title', data_get($page ?? null, 'title', 'Nội dung'))
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>{{ data_get($page ?? null, 'title', 'Nội dung') }}</h1></div></section><section class="ec16-content"><div class="ec16-container ec16-prose">{!! data_get($page ?? null, 'body', data_get($page ?? null, 'content')) !!}</div></section></main>
@endsection
