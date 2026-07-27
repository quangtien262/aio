@extends('theme-ec908::layout')
@section('title', data_get($entry ?? $post ?? null, 'title', 'Tin tức'))
@section('content')
<main><section class="ec98-inner-hero"><div class="ec98-container"><p>TIN TỨC EGO FITNESS</p><h1>{{ data_get($entry ?? $post ?? null, 'title') }}</h1></div></section><section class="ec98-content"><article class="ec98-container ec98-prose">{!! data_get($entry ?? $post ?? null, 'body', data_get($entry ?? $post ?? null, 'excerpt')) !!}</article></section></main>
@endsection

