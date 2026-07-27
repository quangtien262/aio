@extends('theme-ec904::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec94-inner-hero"><div class="ec94-container"><p>TIN TỨC POCOMALL</p><h1>{{ data_get($entry ?? $post ?? null, 'title') }}</h1></div></section><section class="ec94-content"><article class="ec94-container ec94-prose">{!! data_get($entry ?? $post ?? null, 'body', data_get($entry ?? $post ?? null, 'excerpt')) !!}</article></section></main>
@endsection
