@extends('theme-ec915::layout')
@section('title', data_get($post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec15-inner-hero"><div class="ec15-container"><h1>{{ data_get($post ?? null, 'title', 'Tin tức') }}</h1></div></section><section class="ec15-content"><article class="ec15-container ec15-prose">{!! data_get($post ?? null, 'body') !!}</article></section></main>
@endsection
