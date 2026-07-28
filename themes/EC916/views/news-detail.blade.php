@extends('theme-ec916::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec16-inner-hero"><div class="ec16-container"><h1>{{ data_get($post ?? null, 'title', 'Tin tức') }}</h1></div></section><section class="ec16-content"><article class="ec16-container ec16-prose">{!! data_get($post ?? null, 'body') !!}</article></section></main>
@endsection
